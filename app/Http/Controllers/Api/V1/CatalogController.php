<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Provider;
use App\Models\Service;
use App\Services\Api\QuoteService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CatalogController extends Controller
{
    use ApiResponse;

    public function meta()
    {
        return $this->ok([
            'app_name' => (string) config('app.name', 'Glamo'),
            'website_url' => (string) config('services.glamo.website_url', 'https://getglamo.com'),
            'support_email' => (string) config('services.glamo.support_email', 'info@getglamo.com'),
            'play_store_url' => (string) config('services.glamo.play_store_url', ''),
            'app_store_url' => (string) config('services.glamo.app_store_url', ''),
            'api_base_url' => rtrim((string) config('app.url'), '/') . '/api/v1',
        ]);
    }

    public function categories()
    {
        $categories = Category::query()
            ->where('is_active', 1)
            ->withCount(['services as services_count' => function ($query) {
                $query->where('is_active', 1);
            }])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'sort_order']);

        return $this->ok([
            'categories' => $categories->map(function (Category $category): array {
                return [
                    'id' => (int) $category->id,
                    'name' => (string) $category->name,
                    'slug' => (string) $category->slug,
                    'sort_order' => (int) ($category->sort_order ?? 0),
                    'services_count' => (int) ($category->services_count ?? 0),
                ];
            })->values()->all(),
        ]);
    }

    public function services(Request $request)
    {
        $perPage = max(1, min(50, (int) $request->query('per_page', 20)));
        $search = trim((string) $request->query('search', ''));
        $categorySlug = trim((string) $request->query('category_slug', ''));
        $categoryId = (int) $request->query('category_id', 0);
        $random = !in_array(strtolower((string) $request->query('random', '1')), ['0', 'false', 'no'], true);
        $location = $request->validate([
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $lat = array_key_exists('lat', $location) ? (float) $location['lat'] : null;
        $lng = array_key_exists('lng', $location) ? (float) $location['lng'] : null;

        if (($lat === null && $lng !== null) || ($lat !== null && $lng === null)) {
            return $this->fail('Weka lat na lng zote kwa pamoja.', 422);
        }

        $query = Service::query()
            ->with([
                'category:id,name,slug',
                'media' => fn ($q) => $q->orderBy('sort_order'),
            ])
            ->where('is_active', 1)
            ->withCount(['providers as providers_total' => function ($q) {
                $q->where('provider_services.is_active', 1)
                    ->where('providers.is_active', 1)
                    ->where('providers.approval_status', 'approved');
            }]);

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', '%' . $search . '%')
                    ->orWhere('short_desc', 'like', '%' . $search . '%');
            });
        }

        if ($categoryId > 0) {
            $query->where('category_id', $categoryId);
        } elseif ($categorySlug !== '') {
            $query->whereHas('category', fn ($q) => $q->where('slug', $categorySlug));
        }

        if ($random) {
            $query->inRandomOrder();
        } else {
            $query->orderBy('sort_order')->orderBy('id');
        }

        $services = $query->paginate($perPage);
        $serviceItems = collect($services->items());
        $this->attachDisplayPricing($serviceItems, $lat, $lng);

        return $this->ok([
            'services' => $serviceItems->map(fn (Service $service) => $this->servicePayload($service))->values()->all(),
            'pricing_context' => [
                'lat' => $lat,
                'lng' => $lng,
                'has_location' => $lat !== null && $lng !== null,
            ],
            'meta' => [
                'current_page' => (int) $services->currentPage(),
                'per_page' => (int) $services->perPage(),
                'total' => (int) $services->total(),
                'last_page' => (int) $services->lastPage(),
            ],
        ]);
    }

    public function serviceShow(Request $request, Service $service)
    {
        if (!(bool) $service->is_active) {
            return $this->fail('Huduma haipo.', 404);
        }

        $location = $request->validate([
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $lat = array_key_exists('lat', $location) ? (float) $location['lat'] : null;
        $lng = array_key_exists('lng', $location) ? (float) $location['lng'] : null;

        if (($lat === null && $lng !== null) || ($lat !== null && $lng === null)) {
            return $this->fail('Weka lat na lng zote kwa pamoja.', 422);
        }

        $service->load([
            'category:id,name,slug',
            'media' => fn ($q) => $q->orderBy('sort_order'),
        ]);

        $related = Service::query()
            ->with([
                'category:id,name,slug',
                'media' => fn ($q) => $q->orderBy('sort_order'),
            ])
            ->where('is_active', 1)
            ->where('category_id', (int) $service->category_id)
            ->where('id', '!=', (int) $service->id)
            ->inRandomOrder()
            ->limit(8)
            ->get();

        $pricingServices = collect([$service])->merge($related);
        $this->attachDisplayPricing($pricingServices, $lat, $lng);

        return $this->ok([
            'service' => $this->servicePayload($service),
            'related_services' => $related->map(fn (Service $item) => $this->servicePayload($item))->values()->all(),
            'pricing_context' => [
                'lat' => $lat,
                'lng' => $lng,
                'has_location' => $lat !== null && $lng !== null,
            ],
        ]);
    }

    public function providers(Request $request, Service $service, QuoteService $quoteService)
    {
        if (!(bool) $service->is_active) {
            return $this->fail('Huduma haipo.', 404);
        }

        $data = $request->validate([
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:60'],
        ]);

        $lat = array_key_exists('lat', $data) ? (float) $data['lat'] : null;
        $lng = array_key_exists('lng', $data) ? (float) $data['lng'] : null;
        $limit = (int) ($data['limit'] ?? 24);

        if (($lat === null && $lng !== null) || ($lat !== null && $lng === null)) {
            return $this->fail('Weka lat na lng zote kwa pamoja.', 422);
        }

        $service->loadMissing('category:id,name,slug');
        $providers = $quoteService->providersForService($service, $lat, $lng, $limit);

        return $this->ok([
            'service' => $this->servicePayload($service),
            'providers' => $providers->map(function (Provider $provider): array {
                return [
                    'id' => (int) $provider->id,
                    'display_name' => (string) ($provider->display_name ?? ''),
                    'business_nickname' => $provider->business_nickname ?: null,
                    'phone_public' => $provider->phone_public ?: null,
                    'rating_avg' => $provider->rating_avg !== null ? (float) $provider->rating_avg : null,
                    'total_orders' => (int) ($provider->total_orders ?? 0),
                    'online_status' => (string) ($provider->online_status ?? ''),
                    'profile_image_url' => (string) ($provider->profile_image_url ?? ''),
                    'distance_km' => is_numeric($provider->calc_distance_km) ? round((float) $provider->calc_distance_km, 3) : null,
                    'pricing' => [
                        'service' => (float) ($provider->calc_service_price ?? 0),
                        'materials' => (float) ($provider->calc_materials_price ?? 0),
                        'usage_percent' => (float) ($provider->calc_usage_percent ?? 0),
                        'usage' => (float) ($provider->calc_usage_price ?? 0),
                        'travel' => $provider->calc_travel_price !== null ? (float) $provider->calc_travel_price : null,
                        'hair_wash_enabled' => ((float) ($provider->calc_hair_wash_price ?? 0)) > 0,
                        'hair_wash_selected' => (bool) ($provider->calc_hair_wash_selected ?? false),
                        'hair_wash_price' => (float) ($provider->calc_hair_wash_price ?? 0),
                        'hair_wash_amount' => (float) ($provider->calc_hair_wash_amount ?? 0),
                        'total' => (float) ($provider->calc_total_price ?? 0),
                    ],
                ];
            })->values()->all(),
        ]);
    }

    public function quote(Request $request, Service $service, QuoteService $quoteService)
    {
        if (!(bool) $service->is_active) {
            return $this->fail('Huduma haipo.', 404);
        }

        $data = $request->validate([
            'provider_id' => ['required', 'integer', 'exists:providers,id'],
            'service_ids' => ['nullable', 'array', 'max:10'],
            'service_ids.*' => ['integer', 'distinct', 'exists:services,id'],
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'include_hair_wash' => ['nullable', 'boolean'],
            'coupon_code' => ['nullable', 'string', 'max:40'],
        ]);

        $provider = Provider::query()->find((int) $data['provider_id']);
        if (!$provider || !$quoteService->providerIsBookable($provider)) {
            return $this->fail('Mtoa huduma hayupo tayari kwa sasa.', 422);
        }

        $serviceIds = collect($data['service_ids'] ?? [])
            ->map(fn ($v) => (int) $v)
            ->filter(fn ($v) => $v > 0)
            ->unique()
            ->values();
        if (!$serviceIds->contains((int) $service->id)) {
            $serviceIds->prepend((int) $service->id);
        }

        $includeHairWash = array_key_exists('include_hair_wash', $data)
            ? (bool) $data['include_hair_wash']
            : null;

        $quote = $quoteService->buildQuote(
            $provider,
            $serviceIds,
            (float) $data['lat'],
            (float) $data['lng'],
            $service,
            $includeHairWash
        );
        if (!(bool) ($quote['ok'] ?? false)) {
            return $this->fail((string) ($quote['error'] ?? 'Imeshindikana kukokotoa quote.'), 422);
        }

        $couponCode = strtoupper(trim((string) ($data['coupon_code'] ?? '')));
        $discount = 0.0;
        $couponId = null;
        $couponError = null;

        if ($couponCode !== '') {
            $couponResult = $quoteService->couponDiscountForSubtotal($couponCode, (float) $quote['subtotal']);
            $coupon = $couponResult['coupon'] ?? null;
            $discount = (float) ($couponResult['discount'] ?? 0);
            $couponError = $couponResult['error'] ?? null;
            $couponId = $coupon ? (int) $coupon->id : null;
        }

        $rawTotal = max(0, (float) $quote['subtotal'] - $discount);
        $roundedTotal = $quoteService->roundCashAmount($rawTotal);

        return $this->ok([
            'service' => $this->servicePayload($service),
            'provider' => [
                'id' => (int) $provider->id,
                'display_name' => (string) ($provider->display_name ?? ''),
                'profile_image_url' => (string) ($provider->profile_image_url ?? ''),
            ],
            'service_ids' => $serviceIds->all(),
            'items' => $quote['items'] ?? [],
            'price' => [
                'service' => (float) ($quote['sum_service_effective'] ?? $quote['sum_service'] ?? 0),
                'materials' => (float) ($quote['sum_materials'] ?? 0),
                'usage' => (float) ($quote['sum_usage'] ?? 0),
                'usage_percent' => (float) config('glamo_pricing.usage_percent', 5),
                'travel' => (float) ($quote['travel'] ?? 0),
                'hair_wash_enabled' => (bool) ($quote['hair_wash_enabled'] ?? false),
                'hair_wash_selected' => (bool) ($quote['hair_wash_selected'] ?? false),
                'hair_wash_price' => (float) ($quote['hair_wash_price'] ?? 0),
                'hair_wash_amount' => (float) ($quote['hair_wash'] ?? 0),
                'distance_km' => round((float) ($quote['distance_km'] ?? 0), 3),
                'subtotal' => (float) ($quote['subtotal'] ?? 0),
                'discount' => $discount,
                'raw_total' => $rawTotal,
                'rounded_total' => $roundedTotal,
            ],
            'coupon' => [
                'code' => $couponCode !== '' ? $couponCode : null,
                'coupon_id' => $couponId,
                'error' => $couponError,
            ],
        ]);
    }

    public function couponPreview(Request $request, QuoteService $quoteService)
    {
        $data = $request->validate([
            'coupon_code' => ['required', 'string', 'max:40'],
            'subtotal' => ['required', 'numeric', 'min:0'],
        ]);

        $code = strtoupper(trim((string) $data['coupon_code']));
        $subtotal = (float) $data['subtotal'];

        $result = $quoteService->couponDiscountForSubtotal($code, $subtotal);
        $coupon = $result['coupon'] ?? null;

        return $this->ok([
            'coupon_code' => $code,
            'is_valid' => (bool) $coupon,
            'discount' => (float) ($result['discount'] ?? 0),
            'error' => $result['error'] ?? null,
            'coupon' => $coupon ? [
                'id' => (int) $coupon->id,
                'type' => (string) $coupon->type,
                'value' => (float) $coupon->value,
                'min_order_amount' => $coupon->min_order_amount !== null ? (float) $coupon->min_order_amount : null,
                'starts_at' => optional($coupon->starts_at)->toIso8601String(),
                'ends_at' => optional($coupon->ends_at)->toIso8601String(),
            ] : null,
        ]);
    }

    public function appDownloadLinks()
    {
        return $this->ok([
            'glamo_client' => [
                'play_store' => (string) config('services.glamo.play_store_url', ''),
                'app_store' => (string) config('services.glamo.app_store_url', ''),
            ],
            'glamo_provider' => [
                'play_store' => (string) config('services.glamo.play_store_url', ''),
                'app_store' => (string) config('services.glamo.app_store_url', ''),
            ],
        ]);
    }

    private function attachDisplayPricing(Collection $services, ?float $lat, ?float $lng): void
    {
        if ($services->isEmpty()) {
            return;
        }

        $hasLocation = $lat !== null && $lng !== null;
        $usagePercent = (float) config('glamo_pricing.usage_percent', 5);
        $pricingConfig = $this->pricingConfig();
        $nearestByService = [];

        if ($hasLocation) {
            $radiusKm = (int) config('glamo_pricing.radius_km', 10);
            if ($radiusKm <= 0) {
                $radiusKm = 10;
            }

            $debtBlock = (float) config('glamo_pricing.provider_debt_block_threshold', 10000);
            if ($debtBlock <= 0) {
                $debtBlock = 10000;
            }

            $serviceIds = $services
                ->map(fn (Service $service): int => (int) $service->id)
                ->filter(fn (int $id): bool => $id > 0)
                ->values()
                ->all();

            if (!empty($serviceIds)) {
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
                    ->whereIn('provider_services.service_id', $serviceIds)
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

                foreach ($rows as $row) {
                    $serviceId = (int) ($row->service_id ?? 0);
                    if ($serviceId > 0 && !isset($nearestByService[$serviceId])) {
                        $nearestByService[$serviceId] = $row;
                    }
                }
            }
        }

        foreach ($services as $service) {
            if (!$service instanceof Service) {
                continue;
            }

            $servicePrice = (float) ($service->base_price ?? 0);
            $materials = (float) ($service->materials_price ?? 0);
            $distanceKm = null;
            $travel = 0.0;

            if ($hasLocation) {
                $row = $nearestByService[(int) $service->id] ?? null;
                if ($row) {
                    $override = (float) ($row->price_override ?? 0);
                    if ($override > 0) {
                        $servicePrice = $override;
                    }

                    $distance = data_get($row, 'distance_km');
                    $distanceKm = is_numeric($distance) ? (float) $distance : null;
                    if ($distanceKm !== null) {
                        $travel = $this->calcTravelFee($distanceKm, $pricingConfig);
                    }
                }
            }

            $usage = $this->calcUsageFee($servicePrice, $usagePercent);
            $total = $servicePrice + $materials + $usage + ($hasLocation ? $travel : 0.0);

            $service->setAttribute('display_price_service', $servicePrice);
            $service->setAttribute('display_price_materials', $materials);
            $service->setAttribute('display_price_usage_percent', $usagePercent);
            $service->setAttribute('display_price_usage', $usage);
            $service->setAttribute('display_price_travel', $hasLocation ? $travel : 0.0);
            $service->setAttribute('display_price_total', $total);
            $service->setAttribute('display_price_distance_km', $distanceKm);
            $service->setAttribute('display_price_has_location', $hasLocation);
        }
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

    private function servicePayload(Service $service): array
    {
        $baseServicePrice = (float) ($service->base_price ?? 0);
        $materialsPrice = (float) ($service->materials_price ?? 0);

        $displayService = data_get($service, 'display_price_service');
        $displayMaterials = data_get($service, 'display_price_materials');
        $displayUsagePercent = data_get($service, 'display_price_usage_percent');
        $displayUsage = data_get($service, 'display_price_usage');
        $displayTravel = data_get($service, 'display_price_travel');
        $displayTotal = data_get($service, 'display_price_total');
        $displayDistance = data_get($service, 'display_price_distance_km');

        $pricingService = is_numeric($displayService) ? (float) $displayService : $baseServicePrice;
        $pricingMaterials = is_numeric($displayMaterials) ? (float) $displayMaterials : $materialsPrice;
        $pricingUsagePercent = is_numeric($displayUsagePercent) ? (float) $displayUsagePercent : (float) config('glamo_pricing.usage_percent', 5);
        $pricingUsage = is_numeric($displayUsage) ? (float) $displayUsage : $this->calcUsageFee($pricingService, $pricingUsagePercent);
        $pricingTravel = is_numeric($displayTravel) ? (float) $displayTravel : 0.0;
        $pricingTotal = is_numeric($displayTotal) ? (float) $displayTotal : ($pricingService + $pricingMaterials + $pricingUsage + $pricingTravel);
        $distanceKm = is_numeric($displayDistance) ? round((float) $displayDistance, 3) : null;
        $hasLocation = (bool) data_get($service, 'display_price_has_location', false);

        return [
            'id' => (int) $service->id,
            'name' => (string) $service->name,
            'slug' => (string) $service->slug,
            'short_desc' => $service->short_desc ?: null,
            'base_price' => $baseServicePrice,
            'materials_price' => $materialsPrice,
            'display_price' => $pricingTotal,
            'price' => [
                'service' => $pricingService,
                'materials' => $pricingMaterials,
                'usage_percent' => $pricingUsagePercent,
                'usage' => $pricingUsage,
                'travel' => $pricingTravel,
                'distance_km' => $distanceKm,
                'total' => $pricingTotal,
                'has_location' => $hasLocation,
            ],
            'duration_minutes' => (int) ($service->duration_minutes ?? 0),
            'image_url' => (string) ($service->primary_image_url ?? $service->image_url ?? ''),
            'gallery' => array_values($service->gallery_image_urls ?? []),
            'category' => [
                'id' => (int) data_get($service, 'category.id', 0),
                'name' => (string) data_get($service, 'category.name', ''),
                'slug' => (string) data_get($service, 'category.slug', ''),
            ],
            'hair_wash' => [
                'enabled' => (bool) ($service->hair_wash_enabled ?? false),
                'default_selected' => (bool) ($service->hair_wash_default_selected ?? false),
                'price' => (float) ($service->hair_wash_price ?? 0),
            ],
            'providers_total' => isset($service->providers_total) ? (int) $service->providers_total : null,
            'is_active' => (bool) ($service->is_active ?? false),
        ];
    }
}
