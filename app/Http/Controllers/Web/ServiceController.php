<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Provider;
use App\Models\ProviderLedger;
use App\Models\Review;
use App\Models\Service;
use App\Services\OrderNotifier;
use App\Services\OrderPaymentService;
use App\Services\OrderService;
use App\Services\SnippePay;
use App\Support\BookingWindow;
use App\Support\CheckoutPayment;
use App\Support\Phone;
use App\Support\PublicFileUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ServiceController extends Controller
{
    public function show(Request $request, Category $category, Service $service)
    {
        $user = $request->user();
        if ($user && (string) ($user->role ?? '') === 'client') {
            $active = $this->activeOrderForClient((int) $user->id);
            if ($active) {
                return redirect()->route('orders.show', ['order' => $active->id]);
            }
        }

        $service->load([
            'category',
            'media' => fn ($q) => $q->orderBy('sort_order'),
        ]);

        $lat = session('geo_lat');
        $lng = session('geo_lng');
        $hasLocation = $lat !== null && $lng !== null;

        $pricingConfig = $this->pricingConfig();

        $debtBlock = (float) config('glamo_pricing.provider_debt_block_threshold', 10000);
        if ($debtBlock <= 0) {
            $debtBlock = 10000;
        }

        $servicePrice = (float) ($service->base_price ?? 0);
        $materials = (float) ($service->materials_price ?? 0);
        $usagePercent = (float) config('glamo_pricing.usage_percent', 5);
        $usage = $this->calcUsageFee($servicePrice, $usagePercent);
        $hairWashDefault = $this->resolveHairWashOption($service, null);
        $hairWashAmount = (float) ($hairWashDefault['amount'] ?? 0);

        $baseBreakdown = [
            'service' => $servicePrice,
            'materials' => $materials,
            'travel' => null,
            'usage' => $usage,
            'hair_wash' => $hairWashAmount,
            'total' => $servicePrice + $materials + $usage + $hairWashAmount,
            'usage_percent' => $usagePercent,
        ];

        $providers = $this->providersForService($service, $hasLocation ? (float) $lat : null, $hasLocation ? (float) $lng : null);

        // Related services (same category)
        $related = Service::with([
                'category',
                'media' => fn ($q) => $q->orderBy('sort_order'),
            ])
            ->where('is_active', true)
            ->where('category_id', $service->category_id)
            ->where('id', '!=', $service->id)
            ->orderBy('sort_order')
            ->limit(8)
            ->get();

        // Ensure every service card shows a final (calculated) price.
        $nearestByService = [];
        if ($hasLocation && $related->isNotEmpty()) {
            $radiusKm = (int) config('glamo_pricing.radius_km', 10);
            if ($radiusKm <= 0) {
                $radiusKm = 10;
            }

            $rows = DB::table('provider_services')
                ->join('providers', 'providers.id', '=', 'provider_services.provider_id')
                ->select([
                    'provider_services.service_id',
                    'provider_services.price_override',
                ])
                ->selectRaw("
                    (6371 * acos(
                        cos(radians(?)) * cos(radians(providers.current_lat)) *
                        cos(radians(providers.current_lng) - radians(?)) +
                        sin(radians(?)) * sin(radians(providers.current_lat))
                    )) AS distance_km
                ", [$lat, $lng, $lat])
                ->whereIn('provider_services.service_id', $related->pluck('id')->all())
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

        foreach ($related as $s) {
            $sPrice = (float) ($s->base_price ?? 0);
            $sMaterials = (float) ($s->materials_price ?? 0);
            $sUsagePercent = (float) config('glamo_pricing.usage_percent', 5);
            $sUsage = $this->calcUsageFee($sPrice, $sUsagePercent);

            $travel = 0.0;
            if ($hasLocation) {
                $row = $nearestByService[(int) $s->id] ?? null;
                if ($row) {
                    $override = (float) ($row->price_override ?? 0);
                    if ($override > 0) {
                        $sPrice = $override;
                        $sUsage = $this->calcUsageFee($sPrice, $sUsagePercent);
                    }

                    $distanceKm = data_get($row, 'distance_km');
                    $distanceKm = is_numeric($distanceKm) ? (float) $distanceKm : null;
                    if ($distanceKm !== null) {
                        $travel = $this->calcTravelFee($distanceKm, $pricingConfig);
                    }
                }
            }

            $s->setAttribute('display_price', $sPrice + $sMaterials + $sUsage + ($hasLocation ? $travel : 0.0));
        }

        $svcMetaById = [];
        $serviceIdsForPreview = [];
        $categoryIdsForPreview = [];

        foreach ($providers as $provider) {
            foreach (($provider->services ?? collect()) as $svc) {
                $sid = (int) ($svc->id ?? 0);
                if ($sid <= 0) {
                    continue;
                }

                $serviceIdsForPreview[] = $sid;

                $cid = (int) ($svc->category_id ?? 0);
                if ($cid > 0) {
                    $categoryIdsForPreview[] = $cid;
                }

                if (!isset($svcMetaById[$sid])) {
                    $svcMetaById[$sid] = [
                        'id' => $sid,
                        'name' => (string) ($svc->name ?? ''),
                        'slug' => (string) ($svc->slug ?? ''),
                        'category_id' => $cid,
                        'image_url' => (string) ($svc->image_url ?? ''),
                    ];
                }
            }
        }

        $serviceIdsForPreview = array_values(array_unique($serviceIdsForPreview));
        $categoryIdsForPreview = array_values(array_unique($categoryIdsForPreview));

        $categorySlugById = [];
        if (!empty($categoryIdsForPreview)) {
            $categorySlugById = Category::whereIn('id', $categoryIdsForPreview)
                ->pluck('slug', 'id')
                ->map(fn ($s) => strtolower((string) $s))
                ->all();
        }

        $mediaByServiceId = [];
        if (!empty($serviceIdsForPreview)) {
            $rows = DB::table('service_media')
                ->whereIn('service_id', $serviceIdsForPreview)
                ->whereNotNull('file_path')
                ->where('file_path', 'not like', 'livewire-file:%')
                ->orderBy('service_id')
                ->orderBy('sort_order')
                ->get(['service_id', 'file_path']);

            foreach ($rows as $r) {
                $sid = (int) ($r->service_id ?? 0);
                if ($sid <= 0) {
                    continue;
                }

                $fp = trim(str_replace('\\', '/', (string) ($r->file_path ?? '')));
                if ($fp === '' || str_starts_with($fp, 'livewire-file:')) {
                    continue;
                }

                $mediaByServiceId[$sid] = $mediaByServiceId[$sid] ?? [];
                if (count($mediaByServiceId[$sid]) >= 6) {
                    continue;
                }

                $url = (str_starts_with($fp, 'http://') || str_starts_with($fp, 'https://'))
                    ? $fp
                    : ((string) PublicFileUrl::url($fp, asset('images/placeholder.svg')));

                $mediaByServiceId[$sid][] = $url;
            }
        }

        $placeholderImg = asset('images/placeholder.svg');
        $servicePreviewMap = [];

        foreach ($svcMetaById as $sid => $meta) {
            $imgs = $mediaByServiceId[$sid] ?? [];
            if (empty($imgs)) {
                $imgUrl = trim((string) ($meta['image_url'] ?? ''));
                $imgs = $imgUrl !== '' ? [$imgUrl] : [$placeholderImg];
            }

            $servicePreviewMap[$sid] = [
                'id' => (int) $sid,
                'name' => (string) ($meta['name'] ?? ''),
                'slug' => (string) ($meta['slug'] ?? ''),
                'category_slug' => (string) ($categorySlugById[(int) ($meta['category_id'] ?? 0)] ?? ''),
                'images' => array_values(array_slice($imgs, 0, 6)),
            ];
        }

        $providerServicesMap = [];
        foreach ($providers as $provider) {
            $options = [];

            foreach (($provider->services ?? collect()) as $svc) {
                $svcPrice = (float) ($svc->pivot?->price_override ?? 0);
                if ($svcPrice <= 0) {
                    $svcPrice = (float) ($svc->base_price ?? 0);
                }

                $svcMaterials = (float) ($svc->materials_price ?? 0);
                $svcUsage = $this->calcUsageFee($svcPrice, $usagePercent);

                $options[] = [
                    'id' => (int) $svc->id,
                    'name' => (string) $svc->name,
                    'slug' => (string) ($svc->slug ?? ''),
                    'service' => $svcPrice,
                    'materials' => $svcMaterials,
                    'usage' => $svcUsage,
                    'total' => $svcPrice + $svcMaterials + $svcUsage,
                ];
            }

            $providerServicesMap[(int) $provider->id] = $options;
        }

        return view('public.service-show', [
            'service' => $service,
            'providers' => $providers,
            'related' => $related,
            'lat' => $lat,
            'lng' => $lng,
            'hasLocation' => $hasLocation,
            'pricingConfig' => $pricingConfig,
            'baseBreakdown' => $baseBreakdown,
            'hairWash' => $hairWashDefault,
            'providerServicesMap' => $providerServicesMap,
            'servicePreviewMap' => $servicePreviewMap,
        ]);
    }

    public function startCheckout(Request $request, Category $category, Service $service, Provider $provider)
    {
        $user = $request->user();
        abort_unless($user, 403);
        abort_unless((string) ($user->role ?? '') === 'client', 403);

        if (!BookingWindow::isOpenNow()) {
            return back()->withErrors([
                'provider' => BookingWindow::closedMessage(),
            ]);
        }

        $active = $this->activeOrderForClient((int) $user->id);
        if ($active) {
            return redirect()->route('orders.show', ['order' => $active->id]);
        }

        $lat = session('geo_lat');
        $lng = session('geo_lng');
        if ($lat === null || $lng === null) {
            return back()->withErrors([
                'provider' => 'Washa location kwanza ili tukokotoe gharama ya usafiri.',
            ]);
        }

        if (!$this->providerIsBookable($provider)) {
            return back()->withErrors([
                'provider' => 'Mtoa huduma huyu hayupo tayari kwa sasa. Chagua mwingine.',
            ]);
        }

        $data = $request->validate([
            'service_ids' => ['nullable', 'array', 'max:10'],
            'service_ids.*' => ['integer', 'distinct', 'exists:services,id'],
            'include_hair_wash' => ['nullable', 'boolean'],
        ]);

        $serviceIds = collect($data['service_ids'] ?? [])
            ->map(fn ($v) => (int) $v)
            ->filter(fn ($v) => $v > 0)
            ->unique()
            ->values();

        if (!$serviceIds->contains((int) $service->id)) {
            $serviceIds->prepend((int) $service->id);
        }

        $requestedHairWash = array_key_exists('include_hair_wash', $data)
            ? (bool) $data['include_hair_wash']
            : null;
        $hairWashOption = $this->resolveHairWashOption($service, $requestedHairWash);
        $includeHairWash = (bool) ($hairWashOption['enabled'] && $hairWashOption['selected']);

        $quote = $this->buildQuote($provider, $serviceIds, (float) $lat, (float) $lng, $service, $includeHairWash);
        if (!$quote['ok']) {
            return back()->withErrors([
                'provider' => (string) ($quote['error'] ?? 'Imeshindikana kuendelea.'),
            ]);
        }

        session([
            'checkout' => [
                'service_id' => (int) $service->id,
                'provider_id' => (int) $provider->id,
                'service_ids' => $serviceIds->all(),
                'include_hair_wash' => $includeHairWash,
                'coupon_code' => null,
                'coupon_id' => null,
                'discount_amount' => 0,
                'address_text' => trim((string) session('geo_label', '')) ?: null,
            ],
        ]);

        return redirect()->route('services.checkout', [
            'category' => $category->slug,
            'service' => $service->slug,
        ]);
    }

    public function checkout(Request $request, Category $category, Service $service)
    {
        $user = $request->user();
        abort_unless($user, 403);
        abort_unless((string) ($user->role ?? '') === 'client', 403);

        $service->load([
            'category',
            'media' => fn ($q) => $q->orderBy('sort_order'),
        ]);

        $active = $this->activeOrderForClient((int) $user->id);
        if ($active) {
            return redirect()->route('orders.show', ['order' => $active->id]);
        }

        $ctx = session('checkout');
        if (!is_array($ctx) || (int) ($ctx['service_id'] ?? 0) !== (int) $service->id) {
            $ctx = $this->restoreCheckoutFromResumeQuery($request, $service) ?? $ctx;
        }

        if (!is_array($ctx) || (int) ($ctx['service_id'] ?? 0) !== (int) $service->id) {
            return redirect()->route('services.show', [
                'category' => $category->slug,
                'service' => $service->slug,
            ])->with('error', 'Anza booking kwanza ndipo uende checkout.');
        }

        $providerId = (int) ($ctx['provider_id'] ?? 0);
        $provider = Provider::with(['user', 'portfolio', 'services' => function ($q) {
                $q->where('services.is_active', true)
                    ->wherePivot('is_active', 1)
                    ->orderBy('services.sort_order')
                    ->orderBy('services.name')
                    ->select([
                        'services.id',
                        'services.name',
                        'services.slug',
                        'services.base_price',
                        'services.materials_price',
                    ]);
            }])
            ->find($providerId);

        if (!$provider || !$this->providerIsBookable($provider)) {
            return redirect()->route('services.show', [
                'category' => $category->slug,
                'service' => $service->slug,
            ])->with('error', 'Mtoa huduma hayupo tayari kwa sasa. Chagua mwingine.');
        }

        $lat = session('geo_lat');
        $lng = session('geo_lng');
        if ($lat === null || $lng === null) {
            return redirect()->route('services.show', [
                'category' => $category->slug,
                'service' => $service->slug,
            ])->with('error', 'Washa location kwanza ili uendelee.');
        }

        $serviceIds = collect($ctx['service_ids'] ?? [])
            ->map(fn ($v) => (int) $v)
            ->filter(fn ($v) => $v > 0)
            ->unique()
            ->values();

        if (!$serviceIds->contains((int) $service->id)) {
            $serviceIds->prepend((int) $service->id);
        }

        $includeHairWash = array_key_exists('include_hair_wash', $ctx)
            ? (bool) $ctx['include_hair_wash']
            : null;

        $quote = $this->buildQuote($provider, $serviceIds, (float) $lat, (float) $lng, $service, $includeHairWash);
        if (!$quote['ok']) {
            return redirect()->route('services.show', [
                'category' => $category->slug,
                'service' => $service->slug,
            ])->with('error', (string) ($quote['error'] ?? 'Imeshindikana kuendelea.'));
        }

        $ctxHairWash = (bool) ($quote['hair_wash_selected'] ?? false);
        if ((bool) ($ctx['include_hair_wash'] ?? false) !== $ctxHairWash) {
            $ctx['include_hair_wash'] = $ctxHairWash;
            session(['checkout' => $ctx]);
        }

        $couponCode = trim((string) ($ctx['coupon_code'] ?? ''));
        $coupon = null;
        $discount = 0.0;
        $couponErr = null;

        if ($couponCode !== '') {
            $couponResult = $this->couponDiscountForSubtotal($couponCode, (float) $quote['subtotal']);
            $coupon = $couponResult['coupon'] ?? null;
            $discount = (float) ($couponResult['discount'] ?? 0);
            $couponErr = $couponResult['error'] ?? null;

            // If invalid now, clear it from session.
            if (!$coupon) {
                $ctx['coupon_code'] = null;
                $ctx['coupon_id'] = null;
                $ctx['discount_amount'] = 0;
                session(['checkout' => $ctx]);
            } else {
                $ctx['coupon_id'] = (int) $coupon->id;
                $ctx['discount_amount'] = $discount;
                session(['checkout' => $ctx]);
            }
        }

        $rawTotal = max(0, (float) $quote['subtotal'] - $discount);
        $total = $this->roundCashAmount($rawTotal);
        $cashAdjustment = max(0, $total - $rawTotal);

        return view('public.checkout', [
            'service' => $service,
            'category' => $category,
            'provider' => $provider,
            'serviceIds' => $serviceIds,
            'quote' => $quote,
            'subtotal' => (float) $quote['subtotal'],
            'discount' => $discount,
            'rawTotal' => $rawTotal,
            'total' => $total,
            'cashAdjustment' => $cashAdjustment,
            'couponCode' => $couponCode,
            'couponErr' => $couponErr,
            'addressTextDefault' => trim((string) ($ctx['address_text'] ?? session('geo_label', ''))),
            'paymentMethodDefault' => (string) ($ctx['payment_method'] ?? 'cash'),
            'paymentChannelDefault' => (string) ($ctx['payment_channel'] ?? ''),
            'paymentPhoneDefault' => (string) ($ctx['payment_phone'] ?? (($request->user()->phone ?? '') ?: '')),
        ]);
    }

    public function applyCheckoutCoupon(Request $request, Category $category, Service $service)
    {
        $user = $request->user();
        abort_unless($user, 403);
        abort_unless((string) ($user->role ?? '') === 'client', 403);

        $active = $this->activeOrderForClient((int) $user->id);
        if ($active) {
            return redirect()->route('orders.show', ['order' => $active->id]);
        }

        $ctx = session('checkout');
        if (!is_array($ctx) || (int) ($ctx['service_id'] ?? 0) !== (int) $service->id) {
            return redirect()->route('services.show', [
                'category' => $category->slug,
                'service' => $service->slug,
            ])->with('error', 'Anza booking kwanza ndipo uende checkout.');
        }

        $data = $request->validate([
            'coupon_code' => ['nullable', 'string', 'max:40'],
        ]);

        $code = strtoupper(trim((string) ($data['coupon_code'] ?? '')));
        if ($code === '') {
            $ctx['coupon_code'] = null;
            $ctx['coupon_id'] = null;
            $ctx['discount_amount'] = 0;
            session(['checkout' => $ctx]);

            return redirect()->route('services.checkout', [
                'category' => $category->slug,
                'service' => $service->slug,
            ])->with('success', 'Coupon imeondolewa.');
        }

        $lat = session('geo_lat');
        $lng = session('geo_lng');
        if ($lat === null || $lng === null) {
            return redirect()->route('services.show', [
                'category' => $category->slug,
                'service' => $service->slug,
            ])->with('error', 'Washa location kwanza ili uendelee.');
        }

        $provider = Provider::find((int) ($ctx['provider_id'] ?? 0));
        if (!$provider || !$this->providerIsBookable($provider)) {
            return redirect()->route('services.show', [
                'category' => $category->slug,
                'service' => $service->slug,
            ])->with('error', 'Mtoa huduma hayupo tayari kwa sasa. Chagua mwingine.');
        }

        $serviceIds = collect($ctx['service_ids'] ?? [])
            ->map(fn ($v) => (int) $v)
            ->filter(fn ($v) => $v > 0)
            ->unique()
            ->values();
        if (!$serviceIds->contains((int) $service->id)) {
            $serviceIds->prepend((int) $service->id);
        }

        $includeHairWash = array_key_exists('include_hair_wash', $ctx)
            ? (bool) $ctx['include_hair_wash']
            : null;

        $quote = $this->buildQuote($provider, $serviceIds, (float) $lat, (float) $lng, $service, $includeHairWash);
        if (!$quote['ok']) {
            return redirect()->route('services.checkout', [
                'category' => $category->slug,
                'service' => $service->slug,
            ])->with('error', (string) ($quote['error'] ?? 'Imeshindikana kuendelea.'));
        }

        $couponResult = $this->couponDiscountForSubtotal($code, (float) $quote['subtotal']);
        $coupon = $couponResult['coupon'] ?? null;
        $discount = (float) ($couponResult['discount'] ?? 0);
        $err = $couponResult['error'] ?? null;

        if (!$coupon) {
            return redirect()->route('services.checkout', [
                'category' => $category->slug,
                'service' => $service->slug,
            ])->with('error', $err ?: 'Coupon si sahihi.');
        }

        $ctx['coupon_code'] = $code;
        $ctx['coupon_id'] = (int) $coupon->id;
        $ctx['discount_amount'] = $discount;
        session(['checkout' => $ctx]);

        return redirect()->route('services.checkout', [
            'category' => $category->slug,
            'service' => $service->slug,
        ])->with('success', 'Coupon imekubaliwa.');
    }

    public function checkoutConfirmFallback(Request $request, Category $category, Service $service)
    {
        return redirect()->route('services.checkout', [
            'category' => $category->slug,
            'service' => $service->slug,
        ])->with('error', 'Tafadhali thibitisha oda kupitia kitufe cha "Weka oda sasa".');
    }

    public function confirmCheckout(
        Request $request,
        Category $category,
        Service $service,
        OrderService $orderService,
        OrderNotifier $orderNotifier
    )
    {
        $user = $request->user();
        abort_unless($user, 403);
        abort_unless((string) ($user->role ?? '') === 'client', 403);

        if (!BookingWindow::isOpenNow()) {
            return redirect()->route('services.checkout', [
                'category' => $category->slug,
                'service' => $service->slug,
            ])->with('error', BookingWindow::closedMessage());
        }

        $active = $this->activeOrderForClient((int) $user->id);
        if ($active) {
            return redirect()->route('orders.show', ['order' => $active->id]);
        }

        $ctx = session('checkout');
        if (!is_array($ctx) || (int) ($ctx['service_id'] ?? 0) !== (int) $service->id) {
            return redirect()->route('services.show', [
                'category' => $category->slug,
                'service' => $service->slug,
            ])->with('error', 'Anza booking kwanza ndipo uende checkout.');
        }

        $data = $request->validate([
            'address_text' => ['required', 'string', 'max:255'],
            'payment_method' => ['required', Rule::in(['cash', 'prepay'])],
        ], [
            'address_text.required' => 'Andika mtaa au maelezo mafupi ya location yako.',
            'address_text.max' => 'Maelezo ya location yamezidi urefu unaoruhusiwa.',
            'payment_method.required' => 'Chagua njia ya malipo.',
        ]);
        $addressText = trim((string) ($data['address_text'] ?? ''));
        $paymentMethod = (string) ($data['payment_method'] ?? 'cash');

        $ctx['address_text'] = $addressText !== '' ? $addressText : null;
        $ctx['payment_method'] = $paymentMethod;
        $ctx['payment_channel'] = null;
        $ctx['payment_phone'] = null;
        session(['checkout' => $ctx]);

        $lat = session('geo_lat');
        $lng = session('geo_lng');
        if ($lat === null || $lng === null) {
            return redirect()->route('services.show', [
                'category' => $category->slug,
                'service' => $service->slug,
            ])->with('error', 'Washa location kwanza ili uendelee.');
        }

        $provider = Provider::find((int) ($ctx['provider_id'] ?? 0));
        if (!$provider || !$this->providerIsBookable($provider)) {
            return redirect()->route('services.show', [
                'category' => $category->slug,
                'service' => $service->slug,
            ])->with('error', 'Mtoa huduma hayupo tayari kwa sasa. Chagua mwingine.');
        }

        $serviceIds = collect($ctx['service_ids'] ?? [])
            ->map(fn ($v) => (int) $v)
            ->filter(fn ($v) => $v > 0)
            ->unique()
            ->values();
        if (!$serviceIds->contains((int) $service->id)) {
            $serviceIds->prepend((int) $service->id);
        }

        $includeHairWash = array_key_exists('include_hair_wash', $ctx)
            ? (bool) $ctx['include_hair_wash']
            : null;

        $quote = $this->buildQuote($provider, $serviceIds, (float) $lat, (float) $lng, $service, $includeHairWash);
        if (!$quote['ok']) {
            return redirect()->route('services.checkout', [
                'category' => $category->slug,
                'service' => $service->slug,
            ])->with('error', (string) ($quote['error'] ?? 'Imeshindikana kuendelea.'));
        }

        $couponCode = strtoupper(trim((string) ($ctx['coupon_code'] ?? '')));

        $discount = 0.0;
        $couponId = null;

        if ($couponCode !== '') {
            // validate again right before order creation
            $couponResult = $this->couponDiscountForSubtotal($couponCode, (float) $quote['subtotal']);
            $coupon = $couponResult['coupon'] ?? null;
            $discount = (float) ($couponResult['discount'] ?? 0);

            if (!$coupon) {
                return redirect()->route('services.checkout', [
                    'category' => $category->slug,
                    'service' => $service->slug,
                ])->with('error', $couponResult['error'] ?? 'Coupon si sahihi.');
            }

            $couponId = (int) $coupon->id;
        }

        $rawTotal = max(0, (float) $quote['subtotal'] - (float) $discount);
        $roundedTotal = $this->roundCashAmount($rawTotal);

        if ($paymentMethod === 'prepay') {
            return redirect()->route('services.pay', [
                'category' => $category->slug,
                'service' => $service->slug,
            ])->with('success', 'Endelea kwenye ukurasa wa malipo uchague mobile au card.');
        }

        try {
            $order = DB::transaction(function () use (
                $orderService,
                $user,
                $provider,
                $service,
                $lat,
                $lng,
                $addressText,
                $quote,
                $discount,
                $couponId,
                $couponCode,
                $roundedTotal
            ) {
                $lockedProvider = Provider::whereKey((int) $provider->id)->lockForUpdate()->first();
                if (!$lockedProvider || !$this->providerIsBookable($lockedProvider)) {
                    throw new \RuntimeException('Mtoa huduma hayupo tayari kwa sasa. Chagua mwingine.');
                }

                if ($this->providerHasActiveOrders((int) $lockedProvider->id)) {
                    throw new \RuntimeException('Mtoa huduma ana oda nyingine inayoendelea. Tafadhali chagua mwingine.');
                }

                if ($couponId) {
                    $locked = Coupon::whereKey($couponId)->lockForUpdate()->first();
                    if (!$locked || !$locked->is_active) {
                        throw new \RuntimeException('Coupon haipatikani.');
                    }

                    if ($locked->max_uses !== null && (int) $locked->used_count >= (int) $locked->max_uses) {
                        throw new \RuntimeException('Coupon imeisha.');
                    }

                    $locked->increment('used_count');
                }

                $order = $orderService->createOrder([
                    'client_id' => $user->id,
                    'provider_id' => $lockedProvider->id,
                    'service_id' => $service->id,
                    'client_lat' => (float) $lat,
                    'client_lng' => (float) $lng,
                    'address_text' => $addressText !== '' ? $addressText : null,
                    'price_subtotal' => (float) $quote['subtotal'],
                    'discount_amount' => (float) $discount,
                    'coupon_id' => $couponId,
                    'coupon_code' => $couponId ? $couponCode : null,
                    'payment_method' => 'cash',
                    'payment_channel' => null,
                    'payment_status' => 'cash_after',
                    'price_total' => (float) $roundedTotal,
                    'price_service' => (float) ($quote['sum_service_effective'] ?? $quote['sum_service']),
                    'price_materials' => (float) $quote['sum_materials'],
                    'price_travel' => (float) $quote['travel'],
                    'price_usage' => (float) $quote['sum_usage'],
                    'travel_distance_km' => (float) $quote['distance_km'],
                    'items' => $quote['items'] ?? [],
                ]);

                return $order;
            });
        } catch (\Throwable $e) {
            $msg = $e instanceof \RuntimeException
                ? $e->getMessage()
                : 'Imeshindikana ku-confirm booking. Jaribu tena.';

            return redirect()->route('services.checkout', [
                'category' => $category->slug,
                'service' => $service->slug,
            ])->with('error', $msg);
        }

        // clear checkout session
        $request->session()->forget('checkout');
        $this->notifyOrderCreated($orderNotifier, $order);

        return redirect()->route('orders.show', ['order' => $order->id])
            ->with('success', 'Oda imepokelewa. Endelea na malipo ya cash baada ya huduma.');
    }

    public function pay(Request $request, Category $category, Service $service)
    {
        $user = $request->user();
        abort_unless($user, 403);
        abort_unless((string) ($user->role ?? '') === 'client', 403);

        if (!BookingWindow::isOpenNow()) {
            return redirect()->route('services.checkout', [
                'category' => $category->slug,
                'service' => $service->slug,
            ])->with('error', BookingWindow::closedMessage());
        }

        $active = $this->activeOrderForClient((int) $user->id);
        if ($active) {
            return redirect()->route('orders.show', ['order' => $active->id]);
        }

        $ctx = session('checkout');
        if (!is_array($ctx) || (int) ($ctx['service_id'] ?? 0) !== (int) $service->id) {
            return redirect()->route('services.show', [
                'category' => $category->slug,
                'service' => $service->slug,
            ])->with('error', 'Anza booking kwanza ndipo uende checkout.');
        }

        if ((string) ($ctx['payment_method'] ?? 'cash') !== 'prepay') {
            return redirect()->route('services.checkout', [
                'category' => $category->slug,
                'service' => $service->slug,
            ])->with('error', 'Chagua "Lipa sasa online" kwanza kwenye checkout.');
        }

        $lat = session('geo_lat');
        $lng = session('geo_lng');
        if ($lat === null || $lng === null) {
            return redirect()->route('services.show', [
                'category' => $category->slug,
                'service' => $service->slug,
            ])->with('error', 'Washa location kwanza ili uendelee.');
        }

        $provider = Provider::find((int) ($ctx['provider_id'] ?? 0));
        if (!$provider || !$this->providerIsBookable($provider)) {
            return redirect()->route('services.show', [
                'category' => $category->slug,
                'service' => $service->slug,
            ])->with('error', 'Mtoa huduma hayupo tayari kwa sasa. Chagua mwingine.');
        }

        $serviceIds = collect($ctx['service_ids'] ?? [])
            ->map(fn ($v) => (int) $v)
            ->filter(fn ($v) => $v > 0)
            ->unique()
            ->values();
        if (!$serviceIds->contains((int) $service->id)) {
            $serviceIds->prepend((int) $service->id);
        }

        $includeHairWash = array_key_exists('include_hair_wash', $ctx)
            ? (bool) $ctx['include_hair_wash']
            : null;

        $quote = $this->buildQuote($provider, $serviceIds, (float) $lat, (float) $lng, $service, $includeHairWash);
        if (!($quote['ok'] ?? false)) {
            return redirect()->route('services.checkout', [
                'category' => $category->slug,
                'service' => $service->slug,
            ])->with('error', (string) ($quote['error'] ?? 'Imeshindikana kuendelea.'));
        }

        $couponCode = strtoupper(trim((string) ($ctx['coupon_code'] ?? '')));
        $discount = 0.0;

        if ($couponCode !== '') {
            $couponResult = $this->couponDiscountForSubtotal($couponCode, (float) $quote['subtotal']);
            $coupon = $couponResult['coupon'] ?? null;
            $discount = (float) ($couponResult['discount'] ?? 0);

            if (!$coupon) {
                return redirect()->route('services.checkout', [
                    'category' => $category->slug,
                    'service' => $service->slug,
                ])->with('error', (string) ($couponResult['error'] ?? 'Coupon si sahihi.'));
            }
        }

        $rawTotal = max(0, (float) $quote['subtotal'] - $discount);
        $total = (float) round($rawTotal, 2);

        return view('public.pay', [
            'service' => $service,
            'category' => $category,
            'provider' => $provider,
            'quote' => $quote,
            'couponCode' => $couponCode,
            'discount' => $discount,
            'total' => $total,
            'paymentChannel' => (string) ($ctx['payment_channel'] ?? ''),
        ]);
    }

    public function confirmPay(Request $request, Category $category, Service $service, SnippePay $snippePay)
    {
        $user = $request->user();
        abort_unless($user, 403);
        abort_unless((string) ($user->role ?? '') === 'client', 403);

        if (!BookingWindow::isOpenNow()) {
            return redirect()->route('services.checkout', [
                'category' => $category->slug,
                'service' => $service->slug,
            ])->with('error', BookingWindow::closedMessage());
        }

        $active = $this->activeOrderForClient((int) $user->id);
        if ($active) {
            return redirect()->route('orders.show', ['order' => $active->id]);
        }

        $ctx = session('checkout');
        if (!is_array($ctx) || (int) ($ctx['service_id'] ?? 0) !== (int) $service->id) {
            return redirect()->route('services.show', [
                'category' => $category->slug,
                'service' => $service->slug,
            ])->with('error', 'Session ya checkout imeisha. Anza tena.');
        }

        if ((string) ($ctx['payment_method'] ?? 'cash') !== 'prepay') {
            return redirect()->route('services.checkout', [
                'category' => $category->slug,
                'service' => $service->slug,
            ])->with('error', 'Chagua "Lipa sasa online" kwanza kwenye checkout.');
        }

        $data = $request->validate([
            'payment_channel' => ['required', Rule::in(['mobile', 'card'])],
            'phone_number' => [
                Rule::requiredIf(fn (): bool => (string) $request->input('payment_channel') === 'mobile'),
                'nullable',
                'string',
                'max:30',
            ],
            'card_name' => [
                Rule::requiredIf(fn (): bool => (string) $request->input('payment_channel') === 'card'),
                'nullable',
                'string',
                'max:120',
            ],
            'card_number' => [
                Rule::requiredIf(fn (): bool => (string) $request->input('payment_channel') === 'card'),
                'nullable',
                'regex:/^[0-9\\s]{12,24}$/',
            ],
            'card_expiry' => [
                Rule::requiredIf(fn (): bool => (string) $request->input('payment_channel') === 'card'),
                'nullable',
                'regex:/^(0[1-9]|1[0-2])\\/\\d{2}$/',
            ],
            'card_cvv' => [
                Rule::requiredIf(fn (): bool => (string) $request->input('payment_channel') === 'card'),
                'nullable',
                'regex:/^\\d{3,4}$/',
            ],
        ], [
            'payment_channel.required' => 'Chagua channel ya malipo.',
            'phone_number.required' => 'Weka namba ya simu ya kulipia.',
            'card_name.required' => 'Weka jina la mwenye kadi.',
            'card_number.required' => 'Weka namba ya kadi.',
            'card_expiry.required' => 'Weka tarehe ya ku-expire kadi (MM/YY).',
            'card_cvv.required' => 'Weka CVV ya kadi.',
            'card_number.regex' => 'Namba ya kadi si sahihi.',
            'card_expiry.regex' => 'Expire date tumia muundo wa MM/YY (mfano 09/27).',
            'card_cvv.regex' => 'CVV si sahihi.',
        ]);

        $channel = strtolower(trim((string) ($data['payment_channel'] ?? '')));
        $phoneNumber = '';
        if ($channel === 'mobile') {
            $phoneNumber = (string) Phone::normalizeTzMsisdn((string) ($data['phone_number'] ?? ''));
            if ($phoneNumber === '') {
                return redirect()->route('services.pay', [
                    'category' => $category->slug,
                    'service' => $service->slug,
                ])->with('error', 'Namba ya simu ya kulipia si sahihi.');
            }
        }

        $lat = session('geo_lat');
        $lng = session('geo_lng');
        if ($lat === null || $lng === null) {
            return redirect()->route('services.show', [
                'category' => $category->slug,
                'service' => $service->slug,
            ])->with('error', 'Washa location kwanza ili uendelee.');
        }

        $provider = Provider::find((int) ($ctx['provider_id'] ?? 0));
        if (!$provider || !$this->providerIsBookable($provider)) {
            return redirect()->route('services.show', [
                'category' => $category->slug,
                'service' => $service->slug,
            ])->with('error', 'Mtoa huduma hayupo tayari kwa sasa. Chagua mwingine.');
        }

        $serviceIds = collect($ctx['service_ids'] ?? [])
            ->map(fn ($v) => (int) $v)
            ->filter(fn ($v) => $v > 0)
            ->unique()
            ->values();
        if (!$serviceIds->contains((int) $service->id)) {
            $serviceIds->prepend((int) $service->id);
        }

        $includeHairWash = array_key_exists('include_hair_wash', $ctx)
            ? (bool) $ctx['include_hair_wash']
            : null;

        $quote = $this->buildQuote($provider, $serviceIds, (float) $lat, (float) $lng, $service, $includeHairWash);
        if (!($quote['ok'] ?? false)) {
            return redirect()->route('services.checkout', [
                'category' => $category->slug,
                'service' => $service->slug,
            ])->with('error', (string) ($quote['error'] ?? 'Imeshindikana kuendelea.'));
        }

        $couponCode = strtoupper(trim((string) ($ctx['coupon_code'] ?? '')));
        $couponId = null;
        $discount = 0.0;

        if ($couponCode !== '') {
            $couponResult = $this->couponDiscountForSubtotal($couponCode, (float) $quote['subtotal']);
            $coupon = $couponResult['coupon'] ?? null;
            $discount = (float) ($couponResult['discount'] ?? 0);

            if (!$coupon) {
                return redirect()->route('services.checkout', [
                    'category' => $category->slug,
                    'service' => $service->slug,
                ])->with('error', (string) ($couponResult['error'] ?? 'Coupon si sahihi.'));
            }

            $couponId = (int) $coupon->id;
        }

        $rawTotal = max(0, (float) $quote['subtotal'] - (float) $discount);
        $total = (float) round($rawTotal, 2);
        $amount = (int) round($total);
        if ($amount <= 0) {
            return redirect()->route('services.checkout', [
                'category' => $category->slug,
                'service' => $service->slug,
            ])->with('error', 'Kiasi cha kulipa si sahihi.');
        }

        $token = (string) Str::uuid();
        $externalReference = CheckoutPayment::externalReference($token);

        $name = trim((string) ($user->name ?? 'Client'));
        if ($name === '') {
            $name = 'Client';
        }
        [$firstName, $lastName] = $this->splitPersonName($name);
        $email = trim((string) ($user->email ?? ''));
        if ($email === '') {
            $email = 'client' . (int) $user->id . '@getglamo.com';
        }

        try {
            if ($channel === 'mobile') {
                $snippePayload = [
                    'payment_type' => 'mobile',
                    'details' => [
                        'amount' => $amount,
                        'currency' => 'TZS',
                    ],
                    'phone_number' => $phoneNumber,
                    'customer' => [
                        'firstname' => $firstName,
                        'lastname' => $lastName,
                        'email' => $email,
                    ],
                    'external_reference' => $externalReference,
                    'metadata' => [
                        'checkout_token' => $token,
                        'client_id' => (string) (int) $user->id,
                        'provider_id' => (string) (int) $provider->id,
                        'service_id' => (string) (int) $service->id,
                        'channel' => 'mobile',
                    ],
                    'webhook_url' => $snippePay->webhookUrl(),
                ];

                $snippeResponse = $snippePay->createPayment($snippePayload, 'checkout-mobile-' . $token . '-' . time());
            } else {
                $snippePayload = [
                    'amount' => $amount,
                    'currency' => 'TZS',
                    'external_reference' => $externalReference,
                    'success_redirect_url' => route('snippe.done', [
                        'checkout_token' => $token,
                        'category' => $category->slug,
                        'service' => $service->slug,
                    ]),
                    'cancel_redirect_url' => route('snippe.cancel', [
                        'checkout_token' => $token,
                        'category' => $category->slug,
                        'service' => $service->slug,
                    ]),
                    'allowed_methods' => ['card'],
                    'metadata' => [
                        'checkout_token' => $token,
                        'client_id' => (string) (int) $user->id,
                        'provider_id' => (string) (int) $provider->id,
                        'service_id' => (string) (int) $service->id,
                        'channel' => 'card',
                        'card_name' => trim((string) ($data['card_name'] ?? '')),
                    ],
                ];

                $snippeResponse = $snippePay->createPaymentSession($snippePayload, 'checkout-card-' . $token . '-' . time());
            }
        } catch (\Throwable $e) {
            return redirect()->route('services.pay', [
                'category' => $category->slug,
                'service' => $service->slug,
            ])->with('error', trim((string) $e->getMessage()) ?: 'Imeshindikana kuanzisha malipo.');
        }

        $reference = $this->extractSnippeReference($snippeResponse);
        if ($reference === '') {
            return redirect()->route('services.pay', [
                'category' => $category->slug,
                'service' => $service->slug,
            ])->with('error', 'Gateway haijarudisha payment reference. Jaribu tena.');
        }

        $paymentUrl = $this->extractSnippePaymentUrl($snippeResponse);
        $gatewayStatus = $this->extractSnippeStatus($snippeResponse);

        $pendingPayload = [
            'client_id' => (int) $user->id,
            'provider_id' => (int) $provider->id,
            'service_id' => (int) $service->id,
            'service_ids' => $serviceIds->values()->all(),
            'include_hair_wash' => (bool) ($quote['hair_wash_selected'] ?? false),
            'coupon_id' => $couponId,
            'coupon_code' => $couponCode !== '' ? $couponCode : null,
            'discount_amount' => (float) $discount,
            'address_text' => trim((string) ($ctx['address_text'] ?? '')) ?: null,
            'client_lat' => (float) $lat,
            'client_lng' => (float) $lng,
            'price_total' => (float) $total,
            'price_subtotal' => (float) ($quote['subtotal'] ?? 0),
            'price_service' => (float) ($quote['sum_service_effective'] ?? $quote['sum_service'] ?? 0),
            'price_materials' => (float) ($quote['sum_materials'] ?? 0),
            'price_travel' => (float) ($quote['travel'] ?? 0),
            'price_usage' => (float) ($quote['sum_usage'] ?? 0),
            'travel_distance_km' => (float) ($quote['distance_km'] ?? 0),
            'items' => (array) ($quote['items'] ?? []),
            'payment_method' => 'prepay',
            'payment_channel' => $channel,
            'payment_provider' => 'snippe',
            'payment_reference' => $reference,
            'payment_gateway_status' => $gatewayStatus !== '' ? $gatewayStatus : null,
            'created_at' => now()->toIso8601String(),
        ];

        Cache::put(CheckoutPayment::pendingKey($token), $pendingPayload, now()->addHours(2));
        Cache::put(CheckoutPayment::referenceKey($reference), $token, now()->addHours(2));

        $ctx['payment_channel'] = $channel;
        $ctx['payment_phone'] = $channel === 'mobile' ? $phoneNumber : null;
        $ctx['checkout_token'] = $token;
        session(['checkout' => $ctx]);

        if ($paymentUrl !== '') {
            return redirect()->away($paymentUrl);
        }

        return redirect()->route('services.pay', [
            'category' => $category->slug,
            'service' => $service->slug,
        ])->with('success', 'Ombi la malipo limetumwa. Ukikamilisha malipo, oda itaundwa moja kwa moja.');
    }

    public function orderConfirm(Request $request, Order $order)
    {
        $user = $request->user();
        abort_unless($user, 403);

        // Only the client can view this simple confirmation page.
        abort_unless((int) $order->client_id === (int) $user->id, 403);

        $order->load(['service.category', 'provider.user']);
        $orderItems = collect();
        if (Schema::hasTable('order_items')) {
            $order->load(['items.service.category']);
            $orderItems = $order->items;
        }

        $review = null;
        if (Schema::hasTable('reviews')) {
            $order->load(['review']);
            $review = $order->review;
        }

        $providerServices = collect();
        $selectedServiceIds = [];

        if ($order->provider) {
            $providerServices = $order->provider->services()
                ->where('services.is_active', true)
                ->wherePivot('is_active', 1)
                ->orderBy('services.sort_order')
                ->orderBy('services.name')
                ->get([
                    'services.id',
                    'services.name',
                    'services.slug',
                    'services.base_price',
                    'services.materials_price',
                ]);
        }

        if ($orderItems->isNotEmpty()) {
            $selectedServiceIds = $orderItems
                ->map(function ($it) {
                    $sid = (int) (data_get($it, 'service.id') ?? data_get($it, 'service_id') ?? 0);
                    return $sid > 0 ? $sid : null;
                })
                ->filter()
                ->values()
                ->all();
        } else {
            $sid = (int) ($order->service_id ?? 0);
            if ($sid > 0) {
                $selectedServiceIds = [$sid];
            }
        }

        return view('public.order-confirm', [
            'order' => $order,
            'orderItems' => $orderItems,
            'providerServices' => $providerServices,
            'selectedServiceIds' => $selectedServiceIds,
            'review' => $review,
        ]);
    }

    public function orderTracking(Request $request, Order $order)
    {
        $user = $request->user();
        abort_unless($user, 403);
        abort_unless((int) $order->client_id === (int) $user->id, 403);

        $order->load(['provider.user']);

        return response()->json([
            'ok' => true,
            'order' => [
                'id' => (int) $order->id,
                'status' => (string) ($order->status ?? ''),
                'payment_method' => (string) ($order->payment_method ?? ''),
                'payment_status' => (string) ($order->payment_status ?? ''),
                'updated_at' => optional($order->updated_at)?->toIso8601String(),
            ],
            'provider' => [
                'id' => (int) data_get($order, 'provider.id'),
                'name' => (string) (data_get($order, 'provider.display_name') ?? 'Mtoa huduma'),
                'online_status' => (string) (data_get($order, 'provider.online_status') ?? ''),
                'lat' => is_numeric(data_get($order, 'provider.current_lat')) ? (float) data_get($order, 'provider.current_lat') : null,
                'lng' => is_numeric(data_get($order, 'provider.current_lng')) ? (float) data_get($order, 'provider.current_lng') : null,
                'last_location_at' => optional(data_get($order, 'provider.last_location_at'))?->toIso8601String(),
            ],
        ]);
    }

    public function setOrderPaymentMode(Request $request, Order $order)
    {
        $user = $request->user();
        abort_unless($user, 403);
        abort_unless((int) $order->client_id === (int) $user->id, 403);

        $data = $request->validate([
            'payment_method' => ['required', Rule::in(['cash', 'prepay'])],
            'payment_channel' => [
                Rule::requiredIf(fn (): bool => (string) $request->input('payment_method') === 'prepay'),
                'nullable',
                Rule::in(['mobile', 'card']),
            ],
        ], [
            'payment_method.required' => 'Chagua njia ya malipo.',
            'payment_channel.required' => 'Chagua channel ya malipo ya online.',
        ]);

        if (in_array((string) $order->status, ['completed', 'cancelled'], true)) {
            return redirect()->route('orders.show', ['order' => $order->id])
                ->with('error', 'Huwezi kubadili malipo kwa oda iliyofungwa.');
        }

        if (in_array((string) ($order->payment_status ?? ''), ['held', 'released', 'refunded'], true)) {
            return redirect()->route('orders.show', ['order' => $order->id])
                ->with('error', 'Malipo tayari yamekamilika kwenye oda hii.');
        }

        $paymentMethod = (string) ($data['payment_method'] ?? 'cash');
        $paymentChannel = $paymentMethod === 'prepay'
            ? strtolower(trim((string) ($data['payment_channel'] ?? '')))
            : null;

        DB::transaction(function () use ($order, $paymentMethod, $paymentChannel) {
            $locked = Order::whereKey((int) $order->id)->lockForUpdate()->firstOrFail();

            if (in_array((string) $locked->status, ['completed', 'cancelled'], true)) {
                return;
            }

            $settledStatuses = ['held', 'released', 'refunded'];
            if (in_array((string) ($locked->payment_status ?? ''), $settledStatuses, true)) {
                return;
            }

            $updates = [
                'payment_method' => $paymentMethod,
                'payment_channel' => $paymentChannel,
                'payment_status' => $paymentMethod === 'prepay' ? 'pending' : 'cash_after',
            ];

            if (Schema::hasColumn('orders', 'payment_provider')) {
                $updates['payment_provider'] = $paymentMethod === 'prepay' ? 'snippe' : null;
            }
            if (Schema::hasColumn('orders', 'payment_reference')) {
                $updates['payment_reference'] = null;
            }

            $locked->update($updates);
        });

        return redirect()->route('orders.show', ['order' => $order->id])
            ->with(
                'success',
                $paymentMethod === 'prepay'
                    ? 'Malipo yamewekwa online. Bonyeza "Anzisha malipo" kukamilisha.'
                    : 'Malipo yamewekwa kuwa cash.'
            );
    }

    public function startOrderPayment(Request $request, Order $order, OrderPaymentService $orderPaymentService)
    {
        $user = $request->user();
        abort_unless($user, 403);
        abort_unless((int) $order->client_id === (int) $user->id, 403);

        $data = $request->validate([
            'payment_channel' => ['nullable', Rule::in(['mobile', 'card'])],
            'phone_number' => ['nullable', 'string', 'max:30'],
        ]);

        $channel = strtolower(trim((string) ($data['payment_channel'] ?? $order->payment_channel ?? '')));
        if ($channel === '') {
            return redirect()->route('orders.show', ['order' => $order->id])
                ->with('error', 'Chagua channel ya malipo kwanza.');
        }

        if ((string) ($order->payment_method ?? '') !== 'prepay') {
            return redirect()->route('orders.show', ['order' => $order->id])
                ->with('error', 'Oda hii haijawekwa kwa online payment.');
        }

        try {
            $payment = $orderPaymentService->startClientPayment(
                $order,
                $user,
                $channel,
                (string) ($data['phone_number'] ?? '')
            );
        } catch (\Throwable $e) {
            return redirect()->route('orders.show', ['order' => $order->id])
                ->with('error', trim((string) $e->getMessage()) ?: 'Imeshindikana kuanzisha malipo.');
        }

        $paymentUrl = (string) ($payment['payment_url'] ?? '');
        if ($paymentUrl !== '') {
            return redirect()->away($paymentUrl);
        }

        return redirect()->route('orders.show', ['order' => $order->id])
            ->with('success', 'Ombi la malipo limetumwa. Angalia simu yako uthibitishe.');
    }

    public function refreshPayment(Request $request, Order $order, OrderPaymentService $orderPaymentService)
    {
        $user = $request->user();
        abort_unless($user, 403);
        abort_unless((int) $order->client_id === (int) $user->id, 403);

        try {
            $result = $orderPaymentService->refreshClientPayment($order, $user);
        } catch (\Throwable $e) {
            return redirect()->route('orders.show', ['order' => $order->id])
                ->with('error', trim((string) $e->getMessage()) ?: 'Imeshindikana ku-refresh malipo.');
        }

        $status = (string) ($result['order_payment_status'] ?? '');
        $message = match ($status) {
            'held' => 'Malipo yamethibitishwa kikamilifu. Oda ipo tayari kuendelea.',
            'failed' => 'Malipo hayajafanikiwa. Unaweza kuanzisha tena malipo.',
            default => 'Malipo bado yapo pending. Endelea kuthibitisha kwenye channel uliyochagua.',
        };

        return redirect()->route('orders.show', ['order' => $order->id])->with('success', $message);
    }

    public function cancelOrder(Request $request, Order $order, SnippePay $snippePay)
    {
        $user = $request->user();
        abort_unless($user, 403);
        abort_unless((int) $order->client_id === (int) $user->id, 403);

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'reason.required' => 'Weka sababu ya kusitisha oda.',
            'reason.min' => 'Sababu iwe na angalau herufi 5.',
        ]);
        $cancelReason = trim((string) ($data['reason'] ?? ''));

        if (in_array((string) $order->status, ['completed', 'cancelled'], true)) {
            return redirect()->route('orders.show', ['order' => $order->id])->with('error', 'Oda hii tayari imekamilika au imeghairiwa.');
        }

        $allowed = ['pending', 'accepted', 'on_the_way'];
        if (!in_array((string) $order->status, $allowed, true)) {
            return redirect()->route('orders.show', ['order' => $order->id])->with('error', 'Kwa sasa huwezi ku-cancel oda hii.');
        }

        $refundNeeded = false;

        DB::transaction(function () use ($order, &$refundNeeded, $cancelReason) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if (in_array((string) $locked->status, ['completed', 'cancelled'], true)) {
                return;
            }

            $prevStatus = (string) $locked->status;

            $updates = ['status' => 'cancelled'];

            if (Schema::hasColumn('orders', 'payment_status') && (string) ($locked->payment_method ?? '') === 'prepay') {
                $currentPayStatus = (string) ($locked->payment_status ?? '');
                if ($currentPayStatus === 'held') {
                    $updates['payment_status'] = 'refund_pending';
                    $refundNeeded = true;
                    if (Schema::hasColumn('orders', 'refund_reason')) {
                        $updates['refund_reason'] = $cancelReason;
                    }
                } else {
                    $updates['payment_status'] = 'cancelled';
                }
            }

            if (Schema::hasColumn('orders', 'cancellation_reason')) {
                $updates['cancellation_reason'] = $cancelReason;
            }
            if (Schema::hasColumn('orders', 'completion_note')) {
                $updates['completion_note'] = 'Client cancelled: ' . $cancelReason;
            }

            $locked->update($updates);

            // Restore provider visibility when this cancellation releases the provider.
            if (in_array($prevStatus, ['pending', 'accepted', 'on_the_way'], true)) {
                $provider = Provider::whereKey((int) $locked->provider_id)->lockForUpdate()->first();
                if ($provider) {
                    $method = (string) ($locked->payment_method ?? '');
                    $isCash = $method === '' || $method === 'cash';

                    $debtBlock = (float) config('glamo_pricing.provider_debt_block_threshold', 10000);
                    if ($debtBlock <= 0) {
                        $debtBlock = 10000;
                    }

                    if ($isCash && in_array($prevStatus, ['accepted', 'on_the_way'], true)) {
                        $commission = (float) ($locked->commission_amount ?? 0);
                        $newBalance = max(0, (float) ($provider->debt_balance ?? 0) - $commission);

                        ProviderLedger::create([
                            'provider_id' => $provider->id,
                            'type' => 'commission_credit',
                            'order_id' => $locked->id,
                            'amount' => $commission,
                            'balance_after' => $newBalance,
                            'note' => 'Commission reversal for cancelled order ' . (string) ($locked->order_no ?? ''),
                        ]);

                        $provider->update([
                            'debt_balance' => $newBalance,
                        ]);
                    }

                    if ($this->providerHasActiveOrders((int) $provider->id, (int) $locked->id)) {
                        $provider->update([
                            'online_status' => 'offline',
                            'offline_reason' => 'Ana oda nyingine inayoendelea.',
                        ]);
                    } else {
                        $debt = max(0, (float) ($provider->debt_balance ?? 0));
                        $isDebtBlocked = $debt > $debtBlock
                            || ((string) ($provider->online_status ?? '') === 'blocked_debt' && $debt >= $debtBlock);

                        $provider->update([
                            'online_status' => $isDebtBlocked ? 'blocked_debt' : 'offline',
                            'offline_reason' => $isDebtBlocked
                                ? ('Debt over ' . number_format($debtBlock, 0) . '. Please pay.')
                                : null,
                        ]);
                    }
                }
            }
        });

        if ($refundNeeded && Schema::hasColumn('orders', 'refund_reference')) {
            $fresh = Order::whereKey($order->id)->first();

            $alreadyHasRefund = $fresh && trim((string) ($fresh->refund_reference ?? '')) !== '';
            if ($fresh && !$alreadyHasRefund) {
                $msisdn = Phone::normalizeTzMsisdn((string) ($user->phone ?? ''));
                if ($msisdn === null) {
                    DB::transaction(function () use ($fresh) {
                        $locked = Order::whereKey($fresh->id)->lockForUpdate()->first();
                        if (!$locked) {
                            return;
                        }
                        $locked->update([
                            'payment_status' => 'refund_failed',
                        ]);
                    });

                    return redirect()->route('orders.show', ['order' => $order->id])
                        ->with('error', 'Imeshindikana kuanzisha refund: namba ya simu haipo/sio sahihi.');
                }

                $amount = (int) round((float) ($fresh->price_total ?? 0));
                if ($amount > 0) {
                    $payload = [
                        'amount' => $amount,
                        'channel' => 'mobile',
                        'recipient_phone' => $msisdn,
                        'recipient_name' => (string) ($user->name ?? 'Client'),
                        'narration' => 'Refund order ' . (string) ($fresh->order_no ?? '') . ' - ' . substr($cancelReason, 0, 60),
                        'webhook_url' => $snippePay->webhookUrl(),
                        'metadata' => [
                            'order_id' => (string) (int) $fresh->id,
                            'order_no' => (string) ($fresh->order_no ?? ''),
                            'refund_reason' => $cancelReason,
                        ],
                    ];

                    try {
                        $snippeRes = $snippePay->createPayout($payload, 'refund-' . (int) $fresh->id);
                        $ref = trim((string) (
                            data_get($snippeRes, 'data.reference')
                            ?: data_get($snippeRes, 'data.payment_reference')
                            ?: data_get($snippeRes, 'reference')
                        ));

                        if ($ref === '') {
                            throw new \RuntimeException('Refund reference missing.');
                        }

                        DB::transaction(function () use ($fresh, $ref) {
                            $locked = Order::whereKey($fresh->id)->lockForUpdate()->first();
                            if (!$locked) {
                                return;
                            }
                            $locked->update([
                                'refund_reference' => $ref,
                            ]);
                        });
                    } catch (\Throwable $e) {
                        DB::transaction(function () use ($fresh, $e) {
                            $locked = Order::whereKey($fresh->id)->lockForUpdate()->first();
                            if (!$locked) {
                                return;
                            }
                            $locked->update([
                                'payment_status' => 'refund_failed',
                            ]);
                        });

                        return redirect()->route('orders.show', ['order' => $order->id])
                            ->with('error', 'Imeshindikana kuanzisha refund. Jaribu tena au wasiliana na support.');
                    }
                }
            }
        }

        return redirect()->route('orders.show', ['order' => $order->id])->with('success', 'Oda imeghairiwa.');
    }

    public function confirmArrival(Request $request, Order $order)
    {
        $user = $request->user();
        abort_unless($user, 403);
        abort_unless((int) $order->client_id === (int) $user->id, 403);

        if (!Schema::hasColumn('orders', 'client_arrival_confirmed_at') || !Schema::hasColumn('orders', 'provider_arrived_at')) {
            return redirect()->route('orders.show', ['order' => $order->id])->with('error', 'Feature ya arrival confirmation haipo kwenye database yako. Fanya migrate.');
        }

        $message = null;
        $error = null;

        DB::transaction(function () use ($order, &$message, &$error) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if (in_array((string) $locked->status, ['completed', 'cancelled'], true)) {
                $error = 'Oda hii tayari imekamilika au imeghairiwa.';
                return;
            }

            if ($locked->provider_arrived_at === null) {
                $error = 'Bado mtoa huduma hajaonyesha kuwa amefika.';
                return;
            }

            if ($locked->client_arrival_confirmed_at !== null) {
                $message = 'Arrival tayari imethibitishwa.';
                return;
            }

            $updates = [
                'client_arrival_confirmed_at' => now(),
            ];

            // Keep a single active client-facing state until completion.
            if ((string) $locked->status === 'accepted') {
                $updates['status'] = 'on_the_way';
            }

            $locked->update($updates);
            $message = 'Ume-thibitisha kuwa mtoa huduma amefika.';
        });

        if ($error) {
            return redirect()->route('orders.show', ['order' => $order->id])->with('error', $error);
        }

        return redirect()->route('orders.show', ['order' => $order->id])->with('success', $message ?: 'Imefanikiwa.');
    }

    public function storeReview(Request $request, Order $order)
    {
        $user = $request->user();
        abort_unless($user, 403);
        abort_unless((int) $order->client_id === (int) $user->id, 403);

        if (!Schema::hasTable('reviews')) {
            return redirect()->route('orders.show', ['order' => $order->id])->with('error', 'Reviews table haipo. Fanya migrate.');
        }

        if ((string) $order->status !== 'completed') {
            return redirect()->route('orders.show', ['order' => $order->id])->with('error', 'Unaweza kuweka review baada ya oda kukamilika.');
        }

        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $exists = Review::query()->where('order_id', (int) $order->id)->exists();
        if ($exists) {
            return redirect()->route('orders.show', ['order' => $order->id])->with('success', 'Review yako tayari imeshatumwa.');
        }

        DB::transaction(function () use ($order, $user, $data) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ((string) $locked->status !== 'completed') {
                throw new \RuntimeException('Order not completed.');
            }

            $already = Review::query()->where('order_id', (int) $locked->id)->lockForUpdate()->first();
            if ($already) {
                return;
            }

            Review::create([
                'order_id' => (int) $locked->id,
                'provider_id' => (int) $locked->provider_id,
                'client_id' => (int) $user->id,
                'rating' => (int) $data['rating'],
                'comment' => $data['comment'] ?? null,
            ]);

            $avg = Review::query()->where('provider_id', (int) $locked->provider_id)->avg('rating');
            Provider::whereKey((int) $locked->provider_id)->update([
                'rating_avg' => $avg !== null ? round((float) $avg, 2) : null,
            ]);
        });

        return redirect()->route('orders.show', ['order' => $order->id])->with('success', 'Asante! Review imetumwa.');
    }

    public function updateOrderServices(Request $request, Order $order)
    {
        $user = $request->user();
        abort_unless($user, 403);
        abort_unless((int) $order->client_id === (int) $user->id, 403);

        $createdAt = $order->created_at;
        if (!$createdAt || now()->gt($createdAt->copy()->addMinutes(2))) {
            return redirect()->route('orders.show', ['order' => $order->id])
                ->with('error', 'Muda wa kubadilisha huduma umeisha (dakika 2).');
        }

        if ((string) $order->status !== 'pending') {
            return redirect()->route('orders.show', ['order' => $order->id])->with('error', 'Huwezi kubadilisha huduma baada ya oda kukubaliwa.');
        }

        $data = $request->validate([
            'service_ids' => ['required', 'array', 'min:1', 'max:10'],
            'service_ids.*' => ['integer', 'distinct', 'exists:services,id'],
            'include_hair_wash' => ['nullable', 'boolean'],
        ]);

        $serviceIds = collect($data['service_ids'] ?? [])
            ->map(fn ($v) => (int) $v)
            ->filter(fn ($v) => $v > 0)
            ->unique()
            ->values();

        if ($serviceIds->isEmpty()) {
            return redirect()->route('orders.show', ['order' => $order->id])->with('error', 'Chagua angalau huduma 1.');
        }

        $provider = Provider::find((int) $order->provider_id);
        if (!$provider) {
            return redirect()->route('orders.show', ['order' => $order->id])->with('error', 'Mtoa huduma hayupo kwa sasa.');
        }

        $primaryService = null;
        if ($order->relationLoaded('service') && $order->service) {
            $primaryService = $order->service;
        } elseif ((int) ($order->service_id ?? 0) > 0) {
            $primaryService = Service::with('category')->find((int) $order->service_id);
        }

        $includeHairWash = array_key_exists('include_hair_wash', $data)
            ? (bool) $data['include_hair_wash']
            : null;

        $quote = $this->buildQuote(
            $provider,
            $serviceIds,
            (float) $order->client_lat,
            (float) $order->client_lng,
            $primaryService,
            $includeHairWash
        );
        if (!$quote['ok']) {
            return redirect()->route('orders.show', ['order' => $order->id])->with('error', (string) ($quote['error'] ?? 'Imeshindikana kuendelea.'));
        }

        $subtotal = (float) $quote['subtotal'];
        $discount = 0.0;

        $couponId = (int) (data_get($order, 'coupon_id') ?? 0);
        if ($couponId > 0) {
            $coupon = Coupon::find($couponId);
            if ($coupon) {
                $type = (string) $coupon->type;
                $value = (float) $coupon->value;
                if ($type === 'percent') {
                    $discount = round($subtotal * ($value / 100), 2);
                } elseif ($type === 'fixed') {
                    $discount = round(min($value, $subtotal), 2);
                }
            }
        }

        $discount = max(0, min($discount, $subtotal));
        $rawTotal = max(0, $subtotal - $discount);
        $total = $this->roundCashAmount($rawTotal);

        $commissionPercent = (float) config('glamo_pricing.commission_percent', 10);
        if ($commissionPercent < 0) {
            $commissionPercent = 0;
        }
        $commissionRate = round($commissionPercent / 100, 4);
        $commissionAmount = round($total * $commissionRate, 2);
        $payoutAmount = round(max(0, $total - $commissionAmount), 2);

        try {
            DB::transaction(function () use ($order, $serviceIds, $quote, $subtotal, $discount, $total, $commissionRate, $commissionAmount, $payoutAmount) {
                $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
                if ((string) $locked->status !== 'pending') {
                    throw new \RuntimeException('Order cannot be edited now.');
                }

            $primaryServiceId = (int) $serviceIds->first();

            $update = [
                'service_id' => $primaryServiceId,
                'price_subtotal' => $subtotal,
                'discount_amount' => $discount,
                'price_total' => $total,
                'price_service' => (float) ($quote['sum_service_effective'] ?? $quote['sum_service']),
                'price_materials' => (float) $quote['sum_materials'],
                'price_travel' => (float) $quote['travel'],
                'price_usage' => (float) $quote['sum_usage'],
                'travel_distance_km' => (float) $quote['distance_km'],
                'commission_rate' => $commissionRate,
                'commission_amount' => $commissionAmount,
                'payout_amount' => $payoutAmount,
            ];

            foreach (['price_subtotal', 'discount_amount'] as $col) {
                if (!Schema::hasColumn('orders', $col)) {
                    unset($update[$col]);
                }
            }

            if (!Schema::hasColumn('orders', 'payout_amount')) {
                unset($update['payout_amount']);
            }

            $locked->update($update);

            if (Schema::hasTable('order_items')) {
                DB::table('order_items')->where('order_id', (int) $locked->id)->delete();

                $now = now();
                $rows = [];
                foreach (($quote['items'] ?? []) as $it) {
                    $sid = (int) ($it['service_id'] ?? 0);
                    if ($sid <= 0) {
                        continue;
                    }

                    $rows[] = [
                        'order_id' => (int) $locked->id,
                        'service_id' => $sid,
                        'price_service' => (float) ($it['price_service'] ?? 0),
                        'price_materials' => (float) ($it['price_materials'] ?? 0),
                        'price_usage' => (float) ($it['price_usage'] ?? 0),
                        'usage_percent' => (float) ($it['usage_percent'] ?? config('glamo_pricing.usage_percent', 5)),
                        'line_total' => (float) ($it['line_total'] ?? 0),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if (!empty($rows)) {
                    DB::table('order_items')->insert($rows);
                }
            }
            });
        } catch (\Throwable $e) {
            return redirect()->route('orders.show', ['order' => $order->id])->with('error', 'Imeshindikana kubadilisha huduma. Jaribu tena.');
        }

        return redirect()->route('orders.show', ['order' => $order->id])->with('success', 'Huduma zimebadilishwa.');
    }

    private function activeOrderForClient(int $clientId): ?Order
    {
        return Order::query()
            ->where('client_id', $clientId)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->orderByDesc('id')
            ->first();
    }

    private function providerHasActiveOrders(int $providerId, ?int $excludeOrderId = null): bool
    {
        if ($providerId <= 0) {
            return false;
        }

        $query = Order::query()
            ->where('provider_id', $providerId)
            ->whereNotIn('status', ['completed', 'cancelled', 'suspended']);

        if ($excludeOrderId !== null && $excludeOrderId > 0) {
            $query->where('id', '!=', $excludeOrderId);
        }

        return $query->exists();
    }

    private function notifyOrderCreated(OrderNotifier $orderNotifier, Order $order): void
    {
        try {
            $orderNotifier->notifyCreated($order);
        } catch (\Throwable $e) {
            Log::warning('Order created notifier failed', [
                'order_id' => (int) $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function splitPersonName(string $name): array
    {
        $name = trim(preg_replace('/\s+/', ' ', $name) ?? '');
        if ($name === '') {
            return ['Client', 'Glamo'];
        }

        $parts = preg_split('/\s+/', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $first = (string) ($parts[0] ?? 'Client');
        $last = (string) ($parts[count($parts) - 1] ?? 'Glamo');
        if ($last === '') {
            $last = 'Glamo';
        }

        return [$first, $last];
    }

    private function extractSnippeReference(array $response): string
    {
        return trim((string) (
            data_get($response, 'data.session_reference')
            ?: data_get($response, 'data.reference')
            ?: data_get($response, 'data.payment_reference')
            ?: data_get($response, 'session_reference')
            ?: data_get($response, 'reference')
            ?: data_get($response, 'payment_reference')
        ));
    }

    private function extractSnippePaymentUrl(array $response): string
    {
        return trim((string) (
            data_get($response, 'data.payment_url')
            ?: data_get($response, 'data.checkout_url')
            ?: data_get($response, 'data.url')
            ?: data_get($response, 'payment_url')
            ?: data_get($response, 'checkout_url')
            ?: data_get($response, 'url')
        ));
    }

    private function extractSnippeStatus(array $response): string
    {
        return strtolower(trim((string) (
            data_get($response, 'data.status')
            ?: data_get($response, 'data.payment_status')
            ?: data_get($response, 'status')
            ?: data_get($response, 'payment_status')
        )));
    }

    private function providerIsBookable(Provider $provider): bool
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

    private function restoreCheckoutFromResumeQuery(Request $request, Service $service): ?array
    {
        if (!$request->boolean('resume')) {
            return null;
        }

        $providerId = (int) $request->query('provider', 0);
        if ($providerId <= 0) {
            return null;
        }

        $provider = Provider::query()->find($providerId);
        if (!$provider || !$this->providerIsBookable($provider)) {
            return null;
        }

        $lat = session('geo_lat');
        $lng = session('geo_lng');
        if ($lat === null || $lng === null) {
            return null;
        }

        $serviceIds = $this->resumeServiceIdsFromRequest($request, (int) $service->id);

        $requestedHairWash = $request->has('include_hair_wash')
            ? $request->boolean('include_hair_wash')
            : null;
        $hairWash = $this->resolveHairWashOption($service, $requestedHairWash);
        $includeHairWash = (bool) ($hairWash['enabled'] && $hairWash['selected']);

        $quote = $this->buildQuote($provider, $serviceIds, (float) $lat, (float) $lng, $service, $includeHairWash);
        if (!($quote['ok'] ?? false)) {
            return null;
        }

        $checkout = [
            'service_id' => (int) $service->id,
            'provider_id' => (int) $provider->id,
            'service_ids' => $serviceIds->all(),
            'include_hair_wash' => (bool) ($quote['hair_wash_selected'] ?? $includeHairWash),
            'coupon_code' => null,
            'coupon_id' => null,
            'discount_amount' => 0,
            'address_text' => trim((string) session('geo_label', '')) ?: null,
        ];

        session(['checkout' => $checkout]);

        return $checkout;
    }

    private function resumeServiceIdsFromRequest(Request $request, int $primaryServiceId): \Illuminate\Support\Collection
    {
        $raw = $request->query('service_ids');

        if (is_array($raw)) {
            $items = collect($raw);
        } else {
            $rawString = trim((string) ($raw ?? ''));
            $items = $rawString !== ''
                ? collect(preg_split('/[\s,]+/', $rawString))
                : collect();
        }

        $serviceIds = $items
            ->map(fn ($v) => (int) $v)
            ->filter(fn ($v) => $v > 0)
            ->unique()
            ->values()
            ->take(10);

        if (!$serviceIds->contains($primaryServiceId)) {
            $serviceIds->prepend($primaryServiceId);
        }

        return $serviceIds->values()->take(10);
    }

    private function buildQuote(
        Provider $provider,
        \Illuminate\Support\Collection $serviceIds,
        float $clientLat,
        float $clientLng,
        ?Service $primaryService = null,
        ?bool $includeHairWash = null
    ): array
    {
        // Pull only services that this provider can actually do (active pivot + active service).
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
                'error' => 'Location ya mtoa huduma haijulikani kwa sasa. Jaribu mwingine.',
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
        if (! $primaryService instanceof Service) {
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

    private function resolveHairWashOption(?Service $service, ?bool $requestedSelected): array
    {
        if (! $service instanceof Service) {
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

        if (! $this->hairWashColumnsAvailable()) {
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
        if (! array_key_exists($categoryId, $slugCache)) {
            $slugCache[$categoryId] = trim(strtolower((string) DB::table('categories')->where('id', $categoryId)->value('slug')));
        }

        return (string) ($slugCache[$categoryId] ?? '');
    }

    private function couponDiscountForSubtotal(string $code, float $subtotal): array
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
            return ['coupon' => null, 'discount' => 0, 'error' => 'Kiasi hiki hakijafikia kiwango cha coupon.' ];
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

    private function roundCashAmount(float $amount): float
    {
        $amount = max(0, $amount);
        $roundTo = (int) config('glamo_pricing.checkout_cash_round_to', 1000);
        if ($roundTo <= 1) {
            return (float) round($amount);
        }

        return (float) (ceil($amount / $roundTo) * $roundTo);
    }

    private function providersForService(Service $service, ?float $lat, ?float $lng)
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
                'user',
                'portfolio',
                'services' => function ($q) {
                    $q->where('services.is_active', true)
                        ->wherePivot('is_active', 1)
                        ->orderBy('services.sort_order')
                        ->orderBy('services.name')
                        ->select([
                            'services.id',
                            'services.category_id',
                            'services.name',
                            'services.slug',
                            'services.image_url',
                            'services.base_price',
                            'services.materials_price',
                        ]);
                },
            ])
            ->limit(24);

        if ($lat !== null && $lng !== null) {
            $query
                ->whereNotNull('providers.current_lat')
                ->whereNotNull('providers.current_lng')
                ->select('providers.*')
                ->selectRaw("
                    (6371 * acos(
                        cos(radians(?)) * cos(radians(providers.current_lat)) *
                        cos(radians(providers.current_lng) - radians(?)) +
                        sin(radians(?)) * sin(radians(providers.current_lat))
                    )) AS distance_km
                ", [$lat, $lng, $lat])
                ->having('distance_km', '<=', $radiusKm)
                ->orderBy('distance_km');
        } else {
            $query->orderByDesc('providers.rating_avg')
                ->orderByDesc('providers.total_orders')
                ->orderBy('providers.id');
        }

        return $query->get()->map(function (Provider $provider) use ($service, $lat, $lng, $hairWashAmount, $hairWashDefault) {
            $pricingConfig = $this->pricingConfig();

            $servicePrice = (float) ($provider->pivot?->price_override ?? 0);
            if ($servicePrice <= 0) {
                $servicePrice = (float) ($service->base_price ?? 0);
            }

            $materials = (float) ($service->materials_price ?? 0);
            $usagePercent = (float) config('glamo_pricing.usage_percent', 5);
            $usage = $this->calcUsageFee($servicePrice, $usagePercent);

            $distanceKm = data_get($provider, 'distance_km');
            $distanceKm = is_numeric($distanceKm) ? (float) $distanceKm : null;

            $travel = null;
            if ($distanceKm !== null) {
                $travel = $this->calcTravelFee($distanceKm, $pricingConfig);
            }

            $total = $servicePrice + $materials + $usage + (float) ($travel ?? 0) + $hairWashAmount;

            $provider->setAttribute('calc_service_price', $servicePrice);
            $provider->setAttribute('calc_materials_price', $materials);
            $provider->setAttribute('calc_usage_percent', $usagePercent);
            $provider->setAttribute('calc_usage_price', $usage);
            $provider->setAttribute('calc_hair_wash_price', (float) ($hairWashDefault['price'] ?? 0));
            $provider->setAttribute('calc_hair_wash_selected', (bool) ($hairWashDefault['selected'] ?? false));
            $provider->setAttribute('calc_hair_wash_amount', $hairWashAmount);
            $provider->setAttribute('calc_distance_km', $distanceKm);
            $provider->setAttribute('calc_travel_price', $travel);
            $provider->setAttribute('calc_total_price', $total);

            return $provider;
        });
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
        $earthRadius = 6371; // km

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2)
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
            * sin($dLng / 2) * sin($dLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
