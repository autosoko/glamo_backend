<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Provider;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HomeController extends Controller
{
    public function setLocation(Request $request)
    {
        $data = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $lat = (float) $data['lat'];
        $lng = (float) $data['lng'];

        session([
            'geo_lat' => $lat,
            'geo_lng' => $lng,
        ]);

        $user = $request->user();

        if ($user) {
            $user->update([
                'last_lat' => $lat,
                'last_lng' => $lng,
                'last_location_at' => now(),
            ]);

            $provider = $user->provider()->first();
            if ($provider) {
                $provider->update([
                    'current_lat' => $lat,
                    'current_lng' => $lng,
                    'last_location_at' => now(),
                ]);
            }
        }

        return response()->json([
            'ok' => true,
            'lat' => $lat,
            'lng' => $lng,
        ]);
    }

    public function index(Request $request)
    {
        $user = $request->user();
        if ($user) {
            $user->loadMissing('provider');

            if ($user->isApprovedActiveProvider()) {
                if ((string) ($user->role ?? '') !== 'provider') {
                    $user->forceFill(['role' => 'provider'])->save();
                }

                return redirect()->route('provider.dashboard');
            }

            if ((string) ($user->role ?? '') === 'provider') {
                return redirect()->route('provider.dashboard');
            }
        }

        if ($user && (string) ($user->role ?? '') === 'client') {
            $active = Order::query()
                ->where('client_id', (int) $user->id)
                ->whereNotIn('status', ['completed', 'cancelled'])
                ->orderByDesc('id')
                ->first();

            if ($active) {
                return redirect()->route('orders.show', ['order' => $active->id]);
            }
        }

        return view('public.landing', $this->buildHomePayload());
    }

    public function services(Request $request)
    {
        $user = $request->user();
        if ($user) {
            $user->loadMissing('provider');

            if ($user->isApprovedActiveProvider()) {
                if ((string) ($user->role ?? '') !== 'provider') {
                    $user->forceFill(['role' => 'provider'])->save();
                }

                return redirect()->route('provider.dashboard');
            }

            if ((string) ($user->role ?? '') === 'provider') {
                return redirect()->route('provider.dashboard');
            }
        }

        if ($user && (string) ($user->role ?? '') === 'client') {
            $active = Order::query()
                ->where('client_id', (int) $user->id)
                ->whereNotIn('status', ['completed', 'cancelled'])
                ->orderByDesc('id')
                ->first();

            if ($active) {
                return redirect()->route('orders.show', ['order' => $active->id]);
            }
        }

        return view('public.services-index', $this->buildHomePayload());
    }

    private function buildHomePayload(): array
    {
        $lat = session('geo_lat');
        $lng = session('geo_lng');
        $hasLocation = $lat !== null && $lng !== null;
        $radiusKm = $this->nearbyRadiusKm();

        $debtBlock = (float) config('glamo_pricing.provider_debt_block_threshold', 10000);
        if ($debtBlock <= 0) {
            $debtBlock = 10000;
        }

        $services = Service::with([
                'category',
                'media' => fn ($q) => $q->orderBy('sort_order'),
            ])
            ->where('is_active', true)
            ->inRandomOrder()
            ->get();

        $activeServiceIds = $services->pluck('id')->all();

        $totalProvidersByService = [];
        if (!empty($activeServiceIds)) {
            $rows = DB::table('provider_services')
                ->join('providers', 'providers.id', '=', 'provider_services.provider_id')
                ->select('provider_services.service_id', DB::raw('COUNT(DISTINCT provider_services.provider_id) as total'))
                ->whereIn('provider_services.service_id', $activeServiceIds)
                ->where('provider_services.is_active', 1)
                ->where('providers.approval_status', 'approved')
                ->where('providers.is_active', 1)
                ->groupBy('provider_services.service_id')
                ->get();

            foreach ($rows as $r) {
                $totalProvidersByService[(int) $r->service_id] = (int) $r->total;
            }
        }

        $completedOrdersByService = [];
        if (!empty($activeServiceIds)) {
            if (Schema::hasTable('order_items')) {
                $rows = DB::table('order_items')
                    ->join('orders', 'orders.id', '=', 'order_items.order_id')
                    ->select('order_items.service_id', DB::raw('COUNT(DISTINCT order_items.order_id) as total'))
                    ->whereIn('order_items.service_id', $activeServiceIds)
                    ->where('orders.status', 'completed')
                    ->groupBy('order_items.service_id')
                    ->get();

                foreach ($rows as $r) {
                    $completedOrdersByService[(int) $r->service_id] = (int) $r->total;
                }

                // Backward compatibility: count old completed orders that had no order_items rows.
                $legacyRows = DB::table('orders')
                    ->leftJoin('order_items', 'order_items.order_id', '=', 'orders.id')
                    ->select('orders.service_id', DB::raw('COUNT(DISTINCT orders.id) as total'))
                    ->whereIn('orders.service_id', $activeServiceIds)
                    ->where('orders.status', 'completed')
                    ->whereNull('order_items.id')
                    ->groupBy('orders.service_id')
                    ->get();

                foreach ($legacyRows as $r) {
                    $sid = (int) $r->service_id;
                    $completedOrdersByService[$sid] = (int) ($completedOrdersByService[$sid] ?? 0) + (int) $r->total;
                }
            } else {
                $rows = DB::table('orders')
                    ->select('service_id', DB::raw('COUNT(*) as total'))
                    ->whereIn('service_id', $activeServiceIds)
                    ->where('status', 'completed')
                    ->groupBy('service_id')
                    ->get();

                foreach ($rows as $r) {
                    $completedOrdersByService[(int) $r->service_id] = (int) $r->total;
                }
            }
        }

        $countsByService = [];
        $nearbyProviderIds = [];

        if ($hasLocation) {
            $nearbyProviderIds = Provider::query()
                ->where('is_active', true)
                ->where('approval_status', 'approved')
                ->where('online_status', 'online')
                ->where('debt_balance', '<=', $debtBlock)
                ->whereNotNull('current_lat')
                ->whereNotNull('current_lng')
                ->selectRaw(
                    "
                    id,
                    (6371 * acos(
                        cos(radians(?)) * cos(radians(current_lat)) *
                        cos(radians(current_lng) - radians(?)) +
                        sin(radians(?)) * sin(radians(current_lat))
                    )) AS distance_km
                ",
                    [$lat, $lng, $lat]
                )
                ->having('distance_km', '<=', $radiusKm)
                ->pluck('id')
                ->all();

            if (!empty($nearbyProviderIds)) {
                $rows = DB::table('provider_services')
                    ->select('service_id', DB::raw('COUNT(DISTINCT provider_id) as total'))
                    ->whereIn('provider_id', $nearbyProviderIds)
                    ->where('is_active', 1)
                    ->groupBy('service_id')
                    ->get();

                foreach ($rows as $r) {
                    $countsByService[(int) $r->service_id] = (int) $r->total;
                }
            }
        }

        $pricingConfig = $this->pricingConfig();
        $nearestByService = [];

        if ($hasLocation) {
            $rows = DB::table('provider_services')
                ->join('providers', 'providers.id', '=', 'provider_services.provider_id')
                ->select([
                    'provider_services.service_id',
                    'provider_services.price_override',
                ])
                ->selectRaw(
                    "
                    (6371 * acos(
                        cos(radians(?)) * cos(radians(providers.current_lat)) *
                        cos(radians(providers.current_lng) - radians(?)) +
                        sin(radians(?)) * sin(radians(providers.current_lat))
                    )) AS distance_km
                ",
                    [$lat, $lng, $lat]
                )
                ->whereIn('provider_services.service_id', $activeServiceIds)
                ->where('provider_services.is_active', 1)
                ->where('providers.is_active', 1)
                ->where('providers.approval_status', 'approved')
                ->where('providers.online_status', 'online')
                ->where('providers.debt_balance', '<=', $debtBlock)
                ->whereNotNull('providers.current_lat')
                ->whereNotNull('providers.current_lng')
                ->having('distance_km', '<=', $radiusKm)
                ->orderBy('provider_services.service_id')
                ->orderBy('distance_km')
                ->get();

            foreach ($rows as $r) {
                $sid = (int) $r->service_id;
                if (!isset($nearestByService[$sid])) {
                    $nearestByService[$sid] = $r;
                }
            }
        }

        foreach ($services as $service) {
            $servicePrice = (float) ($service->base_price ?? 0);
            $materials = (float) ($service->materials_price ?? 0);
            $usagePercent = (float) config('glamo_pricing.usage_percent', 5);
            $usage = $this->calcUsageFee($servicePrice, $usagePercent);

            $travel = 0.0;

            if ($hasLocation) {
                $row = $nearestByService[(int) $service->id] ?? null;
                if ($row) {
                    $override = (float) ($row->price_override ?? 0);
                    if ($override > 0) {
                        $servicePrice = $override;
                        $usage = $this->calcUsageFee($servicePrice, $usagePercent);
                    }

                    $distanceKm = data_get($row, 'distance_km');
                    $distanceKm = is_numeric($distanceKm) ? (float) $distanceKm : null;
                    if ($distanceKm !== null) {
                        $travel = $this->calcTravelFee($distanceKm, $pricingConfig);
                    }
                }
            }

            $total = $servicePrice + $materials + $usage + ($hasLocation ? $travel : 0.0);
            $service->setAttribute('display_price', $total);
        }

        $topServices = $services->values();
        if ($topServices->count() > 4) {
            $topServices = $services->shuffle()->take(4)->values();
        }

        return [
            'services' => $services,
            'topServices' => $topServices,
            'countsByService' => $countsByService,
            'totalProvidersByService' => $totalProvidersByService,
            'completedOrdersByService' => $completedOrdersByService,
            'lat' => $lat,
            'lng' => $lng,
            'hasLocation' => $hasLocation,
            'radiusKm' => $radiusKm,
        ];
    }

    private function nearbyRadiusKm(): int
    {
        $radiusKm = (int) config('glamo_pricing.home_nearby_radius_km', 5);
        if ($radiusKm <= 0) {
            $radiusKm = 5;
        }

        return $radiusKm;
    }

    private function pricingConfig(): array
    {
        return [
            'fuel_price_per_liter' => (float) config('glamo_pricing.fuel_price_per_liter', 3200),
            'motorbike_liters_per_km' => (float) config('glamo_pricing.motorbike_liters_per_km', 0.03),
            'travel_fixed_fee' => (float) config('glamo_pricing.travel_fixed_fee', 2000),
            'round_to' => (int) config('glamo_pricing.round_to', 100),
        ];
    }

    private function calcUsageFee(float $servicePrice, float $usagePercent): float
    {
        $usagePercent = max(0, $usagePercent);
        return round(($servicePrice * $usagePercent) / 100, 2);
    }

    private function calcTravelFee(float $distanceKm, array $config): float
    {
        $distanceKm = max(0, $distanceKm);
        $fuel = $distanceKm * (float) ($config['motorbike_liters_per_km'] ?? 0.03);
        $fuelCost = $fuel * (float) ($config['fuel_price_per_liter'] ?? 3200);

        $travel = $fuelCost + (float) ($config['travel_fixed_fee'] ?? 2000);
        $roundTo = (int) ($config['round_to'] ?? 100);

        if ($roundTo > 1) {
            $travel = ceil($travel / $roundTo) * $roundTo;
        }

        return round($travel, 2);
    }
}
