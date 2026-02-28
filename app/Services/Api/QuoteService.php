<?php

namespace App\Services\Api;

use App\Models\Coupon;
use App\Models\Provider;
use App\Models\Service;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class QuoteService
{
    public function providerIsBookable(Provider $provider): bool
    {
        $debtBlock = (float) config('glamo_pricing.provider_debt_block_threshold', 10000);
        if ($debtBlock <= 0) {
            $debtBlock = 10000;
        }

        return (bool) ($provider->is_active)
            && (string) $provider->approval_status === 'approved'
            && (string) $provider->online_status === 'online'
            && (float) ($provider->debt_balance ?? 0) <= $debtBlock;
    }

    public function providersForService(Service $service, ?float $lat, ?float $lng, int $limit = 24): Collection
    {
        $radiusKm = (int) config('glamo_pricing.radius_km', 10);
        if ($radiusKm <= 0) {
            $radiusKm = 10;
        }

        $debtBlock = (float) config('glamo_pricing.provider_debt_block_threshold', 10000);
        if ($debtBlock <= 0) {
            $debtBlock = 10000;
        }

        $hairWashDefault = $this->resolveHairWashOption($service, null);
        $hairWashAmount = (float) ($hairWashDefault['amount'] ?? 0);

        $query = $service->providers()
            ->wherePivot('is_active', 1)
            ->where('providers.is_active', true)
            ->where('providers.approval_status', 'approved')
            ->where('providers.online_status', 'online')
            ->where('providers.debt_balance', '<=', $debtBlock)
            ->with([
                'user:id,name,phone',
                'portfolio:id,provider_id,type,file_path',
            ])
            ->limit(max(1, $limit));

        if ($lat !== null && $lng !== null) {
            $query
                ->whereNotNull('providers.current_lat')
                ->whereNotNull('providers.current_lng')
                ->select('providers.*')
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
                ->having('distance_km', '<=', $radiusKm)
                ->orderBy('distance_km');
        } else {
            $query
                ->orderByDesc('providers.rating_avg')
                ->orderByDesc('providers.total_orders')
                ->orderBy('providers.id');
        }

        $pricingConfig = $this->pricingConfig();
        $usagePercent = (float) config('glamo_pricing.usage_percent', 5);

        return $query->get()->map(function (Provider $provider) use ($service, $pricingConfig, $usagePercent, $hairWashAmount, $hairWashDefault) {
            $servicePrice = (float) ($provider->pivot?->price_override ?? 0);
            if ($servicePrice <= 0) {
                $servicePrice = (float) ($service->base_price ?? 0);
            }

            $materials = (float) ($service->materials_price ?? 0);
            $usage = $this->calcUsageFee($servicePrice, $usagePercent);

            $distanceKm = data_get($provider, 'distance_km');
            $distanceKm = is_numeric($distanceKm) ? (float) $distanceKm : null;
            $travel = $distanceKm !== null ? $this->calcTravelFee($distanceKm, $pricingConfig) : null;

            $provider->setAttribute('calc_service_price', $servicePrice);
            $provider->setAttribute('calc_materials_price', $materials);
            $provider->setAttribute('calc_usage_percent', $usagePercent);
            $provider->setAttribute('calc_usage_price', $usage);
            $provider->setAttribute('calc_hair_wash_price', (float) ($hairWashDefault['price'] ?? 0));
            $provider->setAttribute('calc_hair_wash_selected', (bool) ($hairWashDefault['selected'] ?? false));
            $provider->setAttribute('calc_hair_wash_amount', $hairWashAmount);
            $provider->setAttribute('calc_distance_km', $distanceKm);
            $provider->setAttribute('calc_travel_price', $travel);
            $provider->setAttribute('calc_total_price', $servicePrice + $materials + $usage + (float) ($travel ?? 0) + $hairWashAmount);

            return $provider;
        });
    }

    public function buildQuote(
        Provider $provider,
        Collection $serviceIds,
        float $clientLat,
        float $clientLng,
        ?Service $primaryService = null,
        ?bool $includeHairWash = null
    ): array {
        $selectedServices = $provider->services()
            ->whereIn('services.id', $serviceIds->all())
            ->where('services.is_active', true)
            ->wherePivot('is_active', 1)
            ->get([
                'services.id',
                'services.category_id',
                'services.name',
                'services.slug',
                'services.base_price',
                'services.materials_price',
                'services.hair_wash_enabled',
                'services.hair_wash_price',
                'services.hair_wash_default_selected',
            ]);

        if ($selectedServices->count() !== $serviceIds->count()) {
            return [
                'ok' => false,
                'error' => 'Baadhi ya huduma ulizochagua hazipatikani kwa mtoa huduma huyu.',
            ];
        }

        $pLat = data_get($provider, 'current_lat');
        $pLng = data_get($provider, 'current_lng');
        if (!is_numeric($pLat) || !is_numeric($pLng)) {
            return [
                'ok' => false,
                'error' => 'Location ya mtoa huduma haijulikani kwa sasa.',
            ];
        }

        $distanceKm = $this->haversineKm($clientLat, $clientLng, (float) $pLat, (float) $pLng);
        $pricingConfig = $this->pricingConfig();
        $usagePercent = (float) config('glamo_pricing.usage_percent', 5);

        $sumService = 0.0;
        $sumMaterials = 0.0;
        $sumUsage = 0.0;
        $items = [];

        foreach ($selectedServices as $svc) {
            $svcPrice = (float) ($svc->pivot?->price_override ?? 0);
            if ($svcPrice <= 0) {
                $svcPrice = (float) ($svc->base_price ?? 0);
            }

            $svcMaterials = (float) ($svc->materials_price ?? 0);
            $svcUsage = $this->calcUsageFee($svcPrice, $usagePercent);
            $lineTotal = $svcPrice + $svcMaterials + $svcUsage;

            $sumService += $svcPrice;
            $sumMaterials += $svcMaterials;
            $sumUsage += $svcUsage;

            $items[] = [
                'service_id' => (int) $svc->id,
                'service_name' => (string) ($svc->name ?? ''),
                'price_service' => $svcPrice,
                'price_materials' => $svcMaterials,
                'price_usage' => $svcUsage,
                'usage_percent' => $usagePercent,
                'line_total' => $lineTotal,
            ];
        }

        $primaryServiceId = (int) $serviceIds->first();
        if (!$primaryService instanceof Service) {
            $primaryService = $selectedServices->firstWhere('id', $primaryServiceId) ?: $selectedServices->first();
        }

        $hairWash = $this->resolveHairWashOption($primaryService, $includeHairWash);
        $hairWashAmount = (float) ($hairWash['amount'] ?? 0);
        if ($hairWashAmount > 0) {
            $items[] = [
                'service_id' => 0,
                'service_name' => 'Kuosha nywele',
                'price_service' => $hairWashAmount,
                'price_materials' => 0,
                'price_usage' => 0,
                'usage_percent' => 0,
                'line_total' => $hairWashAmount,
                'is_hair_wash' => true,
            ];
        }

        $sumServiceEffective = $sumService + $hairWashAmount;
        $travel = $this->calcTravelFee($distanceKm, $pricingConfig);
        $subtotal = $sumServiceEffective + $sumMaterials + $sumUsage + $travel;

        return [
            'ok' => true,
            'items' => $items,
            'sum_service' => $sumService,
            'sum_service_effective' => $sumServiceEffective,
            'sum_materials' => $sumMaterials,
            'sum_usage' => $sumUsage,
            'hair_wash' => $hairWashAmount,
            'hair_wash_enabled' => (bool) ($hairWash['enabled'] ?? false),
            'hair_wash_selected' => (bool) ($hairWash['selected'] ?? false),
            'hair_wash_price' => (float) ($hairWash['price'] ?? 0),
            'distance_km' => $distanceKm,
            'travel' => $travel,
            'subtotal' => $subtotal,
        ];
    }

    public function couponDiscountForSubtotal(string $code, float $subtotal): array
    {
        $now = now();
        $coupon = Coupon::query()
            ->whereRaw('LOWER(code) = ?', [strtolower($code)])
            ->first();

        if (!$coupon || !$coupon->is_active) {
            return ['coupon' => null, 'discount' => 0, 'error' => 'Coupon si sahihi.'];
        }

        if ($coupon->starts_at && $now->lt($coupon->starts_at)) {
            return ['coupon' => null, 'discount' => 0, 'error' => 'Coupon bado haijaanza.'];
        }

        if ($coupon->ends_at && $now->gt($coupon->ends_at)) {
            return ['coupon' => null, 'discount' => 0, 'error' => 'Coupon muda wake umeisha.'];
        }

        if ($coupon->max_uses !== null && (int) $coupon->used_count >= (int) $coupon->max_uses) {
            return ['coupon' => null, 'discount' => 0, 'error' => 'Coupon imeisha.'];
        }

        if ($coupon->min_order_amount !== null && $subtotal < (float) $coupon->min_order_amount) {
            return ['coupon' => null, 'discount' => 0, 'error' => 'Kiasi hiki hakijafikia kiwango cha coupon.'];
        }

        $type = (string) $coupon->type;
        $value = (float) $coupon->value;

        $discount = 0.0;
        if ($type === 'percent') {
            $discount = round($subtotal * ($value / 100), 2);
        } elseif ($type === 'fixed') {
            $discount = round(min($value, $subtotal), 2);
        } else {
            return ['coupon' => null, 'discount' => 0, 'error' => 'Coupon type si sahihi.'];
        }

        $discount = max(0, min($discount, $subtotal));

        return ['coupon' => $coupon, 'discount' => $discount, 'error' => null];
    }

    public function roundCashAmount(float $amount): float
    {
        $amount = max(0, $amount);
        $roundTo = (int) config('glamo_pricing.checkout_cash_round_to', 1000);
        if ($roundTo <= 1) {
            return (float) round($amount);
        }

        return (float) (ceil($amount / $roundTo) * $roundTo);
    }

    private function resolveHairWashOption(?Service $service, ?bool $requestedSelected): array
    {
        if (!$service instanceof Service) {
            return [
                'enabled' => false,
                'selected' => false,
                'default_selected' => false,
                'price' => 0.0,
                'amount' => 0.0,
            ];
        }

        $enabled = $this->serviceSupportsHairWash($service);
        $price = $enabled ? $this->serviceHairWashPrice($service) : 0.0;
        $defaultSelected = $enabled && $this->serviceHairWashDefaultSelected($service);
        $selected = $enabled
            ? ($requestedSelected === null ? $defaultSelected : (bool) $requestedSelected)
            : false;

        if ($price <= 0) {
            $selected = false;
        }

        return [
            'enabled' => $enabled,
            'selected' => $selected,
            'default_selected' => $defaultSelected,
            'price' => $price,
            'amount' => $selected ? $price : 0.0,
        ];
    }

    private function serviceSupportsHairWash(Service $service): bool
    {
        if ($this->hairWashColumnsAvailable()) {
            return (bool) ($service->hair_wash_enabled ?? false);
        }

        return in_array($this->serviceCategorySlug($service), ['misuko', 'kubana'], true);
    }

    private function serviceHairWashPrice(Service $service): float
    {
        $fallback = (float) config('glamo_pricing.hair_wash_default_price', 3000);
        if ($fallback <= 0) {
            $fallback = 3000;
        }

        if (!$this->hairWashColumnsAvailable()) {
            return $fallback;
        }

        $price = (float) ($service->hair_wash_price ?? 0);
        return $price > 0 ? $price : $fallback;
    }

    private function serviceHairWashDefaultSelected(Service $service): bool
    {
        if ($this->hairWashColumnsAvailable()) {
            return (bool) ($service->hair_wash_default_selected ?? false);
        }

        return true;
    }

    private function hairWashColumnsAvailable(): bool
    {
        static $available = null;

        if ($available !== null) {
            return $available;
        }

        $available = Schema::hasColumn('services', 'hair_wash_enabled')
            && Schema::hasColumn('services', 'hair_wash_price')
            && Schema::hasColumn('services', 'hair_wash_default_selected');

        return $available;
    }

    private function serviceCategorySlug(Service $service): string
    {
        $slug = trim(strtolower((string) data_get($service, 'category.slug')));
        if ($slug !== '') {
            return $slug;
        }

        $categoryId = (int) ($service->category_id ?? 0);
        if ($categoryId <= 0) {
            return '';
        }

        static $slugCache = [];
        if (!array_key_exists($categoryId, $slugCache)) {
            $slugCache[$categoryId] = trim(strtolower((string) DB::table('categories')->where('id', $categoryId)->value('slug')));
        }

        return (string) ($slugCache[$categoryId] ?? '');
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

    private function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2)
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
            * sin($dLng / 2) * sin($dLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
