<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Order;
use App\Models\Provider;
use App\Models\ProviderClientFeedback;
use App\Models\ProviderLedger;
use App\Models\ProviderPayment;
use App\Models\ProviderWalletLedger;
use App\Models\ProviderWithdrawal;
use App\Models\Review;
use App\Models\Service;
use App\Models\User;
use App\Services\BeemSms;
use App\Services\AppNotificationService;
use App\Services\OrderService;
use App\Services\ProviderDebtService;
use App\Services\ProviderWalletService;
use App\Services\SnippePay;
use App\Support\BusinessNickname;
use App\Support\Phone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProviderController extends Controller
{
    use ApiResponse;

    public function dashboard(Request $request)
    {
        [, $provider] = $this->resolveProvider($request);
        $this->refreshProviderAvailability($provider);
        $provider->refresh();

        $approvalStatus = (string) ($provider->approval_status ?? 'pending');
        $onboardingComplete = $provider->onboarding_completed_at !== null || $approvalStatus === 'approved';
        $debtBlock = (float) config('glamo_pricing.provider_debt_block_threshold', 10000);
        if ($debtBlock <= 0) {
            $debtBlock = 10000;
        }
        $hasBlockingOrders = $this->providerHasBlockingOrders((int) $provider->id);

        $walletBalance = Schema::hasColumn('providers', 'wallet_balance')
            ? (float) ($provider->wallet_balance ?? 0)
            : 0.0;

        $pendingEscrow = 0.0;
        if (
            Schema::hasColumn('orders', 'payment_method')
            && Schema::hasColumn('orders', 'payment_status')
            && Schema::hasColumn('orders', 'escrow_released_at')
        ) {
            $pendingOrders = Order::query()
                ->where('provider_id', (int) $provider->id)
                ->where('payment_method', 'prepay')
                ->where('payment_status', 'held')
                ->whereNull('escrow_released_at')
                ->whereNotIn('status', ['cancelled'])
                ->latest()
                ->limit(200)
                ->get(['price_total', 'commission_amount', 'payout_amount']);

            foreach ($pendingOrders as $o) {
                $payout = (float) ($o->payout_amount ?? 0);
                if ($payout <= 0) {
                    $payout = max(0, (float) ($o->price_total ?? 0) - (float) ($o->commission_amount ?? 0));
                }
                $pendingEscrow += $payout;
            }
        }

        $ordersStats = Order::query()
            ->where('provider_id', (int) $provider->id)
            ->select('status', DB::raw('COUNT(*) AS total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($v) => (int) $v)
            ->all();
        $ordersCount = array_sum($ordersStats);
        $totalOrderAmount = $this->calculateTotalOrderAmount((int) $provider->id);

        $debtLedgers = ProviderLedger::query()
            ->where('provider_id', (int) $provider->id)
            ->latest()
            ->limit(20)
            ->get();

        $walletLedgers = collect();
        if (Schema::hasTable('provider_wallet_ledgers')) {
            $walletLedgers = ProviderWalletLedger::query()
                ->where('provider_id', (int) $provider->id)
                ->latest()
                ->limit(20)
                ->get();
        }

        $debtPayments = collect();
        if (Schema::hasTable('provider_payments')) {
            $debtPayments = ProviderPayment::query()
                ->where('provider_id', (int) $provider->id)
                ->latest()
                ->limit(20)
                ->get();
        }

        $totalEarn = $this->calculateTotalEarn((int) $provider->id);
        $reviewsSummary = null;
        $recentReviews = [];
        if (Schema::hasTable('reviews')) {
            $reviewsSummary = $this->providerReviewsSummaryData((int) $provider->id);
            $recentReviews = Review::query()
                ->where('provider_id', (int) $provider->id)
                ->with([
                    'client:id,name,phone,profile_image_path',
                    'order:id,order_no,service_id,created_at,price_total',
                    'order.service:id,name,slug,image_url',
                ])
                ->latest('id')
                ->limit(5)
                ->get()
                ->map(fn (Review $review): array => $this->reviewPayload($review))
                ->values()
                ->all();
        }

        return $this->ok([
            'provider' => $this->providerPayload($provider),
            'approval_status' => $approvalStatus,
            'onboarding_complete' => $onboardingComplete,
            'can_operate' => $approvalStatus === 'approved',
            'wallet_balance' => $walletBalance,
            'pending_escrow' => round($pendingEscrow, 2),
            'debt_balance' => (float) ($provider->debt_balance ?? 0),
            'debt_block_threshold' => $debtBlock,
            'availability_control' => $this->availabilityControlState($provider, $hasBlockingOrders, $debtBlock),
            'orders_count' => (int) $ordersCount,
            'total_order_amount' => $totalOrderAmount,
            'total_earn' => $totalEarn,
            'orders_stats' => $ordersStats,
            'debt_ledgers' => $debtLedgers,
            'debt_payments' => $debtPayments->map(fn (ProviderPayment $payment) => $this->debtPaymentPayload($payment))->values()->all(),
            'wallet_ledgers' => $walletLedgers,
            'reviews_summary' => $reviewsSummary,
            'recent_reviews' => $recentReviews,
            'wallet_terms' => [
                'commission_percent' => (float) config('glamo_pricing.commission_percent', 10),
                'release_rule' => 'Pesa ya online (escrow) inaingia wallet baada ya kubonyeza "Nimemaliza kazi" na oda kuwa completed.',
                'withdraw_rule' => 'Unaweza kuwithdraw kiasi kilicho kwenye wallet_balance pekee.',
            ],
        ]);
    }

    public function reviews(Request $request)
    {
        [, $provider] = $this->resolveProvider($request);

        if (!Schema::hasTable('reviews')) {
            return $this->fail('Reviews table haipo. Fanya migrate.', 422);
        }

        $perPage = max(1, min(50, (int) $request->query('per_page', 20)));

        $reviews = Review::query()
            ->where('provider_id', (int) $provider->id)
            ->with([
                'client:id,name,phone,profile_image_path',
                'order:id,order_no,service_id,created_at,price_total',
                'order.service:id,name,slug,image_url',
            ])
            ->latest('id')
            ->paginate($perPage);

        return $this->ok([
            'summary' => $this->providerReviewsSummaryData((int) $provider->id),
            'reviews' => collect($reviews->items())
                ->map(fn (Review $review): array => $this->reviewPayload($review))
                ->values()
                ->all(),
            'meta' => [
                'current_page' => (int) $reviews->currentPage(),
                'per_page' => (int) $reviews->perPage(),
                'total' => (int) $reviews->total(),
                'last_page' => (int) $reviews->lastPage(),
            ],
        ]);
    }

    public function reviewsSummary(Request $request)
    {
        [, $provider] = $this->resolveProvider($request);

        if (!Schema::hasTable('reviews')) {
            return $this->fail('Reviews table haipo. Fanya migrate.', 422);
        }

        return $this->ok([
            'summary' => $this->providerReviewsSummaryData((int) $provider->id),
        ]);
    }

    public function clientFeedback(Request $request)
    {
        [, $provider] = $this->resolveProvider($request);

        if (!Schema::hasTable('provider_client_feedback')) {
            return $this->fail('Provider client feedback table haipo. Fanya migrate.', 422);
        }

        $perPage = max(1, min(50, (int) $request->query('per_page', 20)));

        $feedback = ProviderClientFeedback::query()
            ->where('provider_id', (int) $provider->id)
            ->with([
                'client:id,name,phone,profile_image_path',
                'order:id,order_no,service_id,created_at,price_total',
                'order.service:id,name,slug,image_url',
            ])
            ->latest('id')
            ->paginate($perPage);

        return $this->ok([
            'feedback' => collect($feedback->items())
                ->map(fn (ProviderClientFeedback $item): array => $this->providerClientFeedbackPayload($item))
                ->values()
                ->all(),
            'meta' => [
                'current_page' => (int) $feedback->currentPage(),
                'per_page' => (int) $feedback->perPage(),
                'total' => (int) $feedback->total(),
                'last_page' => (int) $feedback->lastPage(),
            ],
        ]);
    }

    public function submitClientFeedback(Request $request, Order $order)
    {
        [, $provider] = $this->resolveProvider($request);

        if ((int) $order->provider_id !== (int) $provider->id) {
            return $this->fail('Huruhusiwi kutoa feedback kwa oda hii.', 403);
        }

        if (!Schema::hasTable('provider_client_feedback')) {
            return $this->fail('Provider client feedback table haipo. Fanya migrate.', 422);
        }

        if ((string) ($order->status ?? '') !== 'completed') {
            return $this->fail('Feedback kwa mteja inaweza kutumwa baada ya order kukamilika.', 422);
        }

        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $exists = ProviderClientFeedback::query()
            ->where('order_id', (int) $order->id)
            ->first();
        if ($exists) {
            return $this->ok([
                'feedback' => $this->providerClientFeedbackPayload($exists->loadMissing([
                    'client:id,name,phone,profile_image_path',
                    'order:id,order_no,service_id,created_at,price_total',
                    'order.service:id,name,slug,image_url',
                ])),
            ], 'Feedback yako tayari imeshatumwa.');
        }

        $feedback = DB::transaction(function () use ($order, $provider, $data) {
            $lockedOrder = Order::query()->whereKey((int) $order->id)->lockForUpdate()->firstOrFail();
            if ((int) $lockedOrder->provider_id !== (int) $provider->id) {
                abort(403, 'Not your order.');
            }

            if ((string) ($lockedOrder->status ?? '') !== 'completed') {
                abort(422, 'Order not completed.');
            }

            $already = ProviderClientFeedback::query()
                ->where('order_id', (int) $lockedOrder->id)
                ->lockForUpdate()
                ->first();
            if ($already) {
                return $already;
            }

            return ProviderClientFeedback::query()->create([
                'order_id' => (int) $lockedOrder->id,
                'provider_id' => (int) $provider->id,
                'client_id' => (int) $lockedOrder->client_id,
                'rating' => (int) $data['rating'],
                'comment' => $data['comment'] ?? null,
            ]);
        });

        $feedback->loadMissing([
            'client:id,name,phone,profile_image_path',
            'order:id,order_no,service_id,created_at,price_total',
            'order.service:id,name,slug,image_url',
        ]);

        return $this->ok([
            'feedback' => $this->providerClientFeedbackPayload($feedback),
        ], 'Feedback ya mteja imetumwa.');
    }

    public function nearbyCustomers(Request $request)
    {
        [$user, $provider] = $this->resolveProvider($request);

        $data = $request->validate([
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'radius_km' => ['nullable', 'numeric', 'min:0.1', 'max:100'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $radiusKm = (float) ($data['radius_km'] ?? 5);
        $limit = (int) ($data['limit'] ?? 100);

        $latFromRequest = $data['lat'] ?? null;
        $lngFromRequest = $data['lng'] ?? null;

        $centerLat = null;
        $centerLng = null;
        $centerSource = 'provider_current';

        if (is_numeric($latFromRequest) && is_numeric($lngFromRequest)) {
            $centerLat = (float) $latFromRequest;
            $centerLng = (float) $lngFromRequest;
            $centerSource = 'request';
        } elseif (is_numeric($provider->current_lat) && is_numeric($provider->current_lng)) {
            $centerLat = (float) $provider->current_lat;
            $centerLng = (float) $provider->current_lng;
        } elseif (is_numeric($user->last_lat) && is_numeric($user->last_lng)) {
            $centerLat = (float) $user->last_lat;
            $centerLng = (float) $user->last_lng;
            $centerSource = 'provider_user_last';
        }

        if ($centerLat === null || $centerLng === null) {
            return $this->fail('Weka location ya provider kwanza au tuma lat/lng kwenye request.', 422);
        }

        $clients = User::query()
            ->where('role', 'client')
            ->whereNotNull('users.last_lat')
            ->whereNotNull('users.last_lng')
            ->select('users.id', 'users.name', 'users.phone', 'users.last_lat', 'users.last_lng', 'users.last_location_at')
            ->selectRaw(
                '(6371 * ACOS(COS(RADIANS(?)) * COS(RADIANS(users.last_lat)) * COS(RADIANS(users.last_lng) - RADIANS(?)) + SIN(RADIANS(?)) * SIN(RADIANS(users.last_lat)))) AS distance_km',
                [$centerLat, $centerLng, $centerLat]
            )
            ->withCount('clientOrders')
            ->having('distance_km', '<=', $radiusKm)
            ->orderBy('distance_km')
            ->orderByDesc('users.last_location_at')
            ->limit($limit)
            ->get();

        return $this->ok([
            'center' => [
                'lat' => $centerLat,
                'lng' => $centerLng,
                'source' => $centerSource,
            ],
            'radius_km' => round($radiusKm, 3),
            'customers_count' => (int) $clients->count(),
            'customers' => $clients->map(function (User $client): array {
                return [
                    'id' => (int) $client->id,
                    'name' => (string) ($client->name ?? ''),
                    'phone' => (string) ($client->phone ?? ''),
                    'profile_image_url' => (string) ($client->profile_image_url ?? ''),
                    'lat' => is_numeric($client->last_lat) ? (float) $client->last_lat : null,
                    'lng' => is_numeric($client->last_lng) ? (float) $client->last_lng : null,
                    'last_location_at' => optional($client->last_location_at)->toIso8601String(),
                    'distance_km' => is_numeric(data_get($client, 'distance_km'))
                        ? round((float) data_get($client, 'distance_km'), 3)
                        : null,
                    'orders_count' => (int) ($client->client_orders_count ?? 0),
                ];
            })->values()->all(),
        ]);
    }

    public function updateLocation(Request $request)
    {
        [, $provider] = $this->resolveProvider($request);

        $data = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $provider->update([
            'current_lat' => (float) $data['lat'],
            'current_lng' => (float) $data['lng'],
            'last_location_at' => now(),
        ]);

        return $this->ok([
            'provider' => $this->providerPayload($provider->fresh()),
        ], 'Location imehifadhiwa.');
    }

    public function updateAvailability(Request $request)
    {
        [, $provider] = $this->resolveProvider($request);

        $data = $request->validate([
            'online_status' => ['required', Rule::in(['online', 'offline'])],
        ]);

        $targetStatus = (string) ($data['online_status'] ?? 'offline');
        $debtBlock = (float) config('glamo_pricing.provider_debt_block_threshold', 10000);
        if ($debtBlock <= 0) {
            $debtBlock = 10000;
        }

        try {
            $provider = DB::transaction(function () use ($provider, $targetStatus, $debtBlock) {
                $lockedProvider = Provider::query()->whereKey((int) $provider->id)->lockForUpdate()->firstOrFail();

                $this->refreshProviderAvailability($lockedProvider);
                $lockedProvider->refresh();

                $debtBalance = max(0, (float) ($lockedProvider->debt_balance ?? 0));
                if ($debtBalance > 0) {
                    throw ValidationException::withMessages([
                        'online_status' => ['Huwezi kubadili status ukiwa na deni. Lipa deni kwanza.'],
                    ]);
                }

                if ($targetStatus === 'online') {
                    if ((string) ($lockedProvider->approval_status ?? '') !== 'approved') {
                        throw ValidationException::withMessages([
                            'online_status' => ['Akaunti yako bado haijapitishwa kikamilifu.'],
                        ]);
                    }

                    if ($this->providerHasBlockingOrders((int) $lockedProvider->id)) {
                        throw ValidationException::withMessages([
                            'online_status' => ['Una oda ambayo bado haijacomplete. Kamilisha oda kwanza.'],
                        ]);
                    }

                    $isDebtBlocked = $debtBalance > $debtBlock
                        || ((string) ($lockedProvider->online_status ?? '') === 'blocked_debt' && $debtBalance >= $debtBlock);

                    if ($isDebtBlocked) {
                        throw ValidationException::withMessages([
                            'online_status' => ['Deni limefikia kikomo cha kufungiwa online.'],
                        ]);
                    }

                    $lockedProvider->update([
                        'online_status' => 'online',
                        'offline_reason' => null,
                    ]);
                } else {
                    $lockedProvider->update([
                        'online_status' => 'offline',
                        'offline_reason' => 'Imewekwa offline na mtoa huduma.',
                    ]);
                }

                return $lockedProvider->fresh();
            });
        } catch (ValidationException $e) {
            return $this->fail('Imeshindikana kubadili status ya online/offline.', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->fail('Imeshindikana kubadili status ya online/offline.', 422, [
                'online_status' => [trim((string) $e->getMessage()) ?: 'Jaribu tena.'],
            ]);
        }

        $hasBlockingOrders = $this->providerHasBlockingOrders((int) $provider->id);

        return $this->ok([
            'provider' => $this->providerPayload($provider),
            'availability_control' => $this->availabilityControlState($provider, $hasBlockingOrders, $debtBlock),
        ], $targetStatus === 'online'
            ? 'Umejiweka online.'
            : 'Umejiweka offline.');
    }

    public function orders(Request $request)
    {
        [, $provider] = $this->resolveProvider($request);

        $perPage = max(1, min(50, (int) $request->query('per_page', 20)));
        $status = trim((string) $request->query('status', ''));

        $query = Order::query()
            ->with(['service.category', 'client'])
            ->where('provider_id', (int) $provider->id)
            ->latest('id');

        if ($status !== '') {
            $query->where('status', $status);
        }

        $orders = $query->paginate($perPage);

        return $this->ok([
            'orders' => collect($orders->items())->map(fn (Order $order) => $this->providerOrderPayload($order, false, $provider))->values()->all(),
            'meta' => [
                'current_page' => (int) $orders->currentPage(),
                'per_page' => (int) $orders->perPage(),
                'total' => (int) $orders->total(),
                'last_page' => (int) $orders->lastPage(),
            ],
        ]);
    }

    public function showOrder(Request $request, Order $order)
    {
        [, $provider] = $this->resolveProvider($request);
        if ((int) $order->provider_id !== (int) $provider->id) {
            return $this->fail('Huruhusiwi kuona oda hii.', 403);
        }

        $order->load(['service.category', 'client', 'items.service.category', 'review']);

        return $this->ok([
            'order' => $this->providerOrderPayload($order, true, $provider),
        ]);
    }

    public function acceptOrder(Request $request, Order $order, OrderService $orderService)
    {
        [, $provider] = $this->resolveProvider($request);
        if ((int) $order->provider_id !== (int) $provider->id) {
            return $this->fail('Huruhusiwi kufanya tendo hili.', 403);
        }

        if ((string) ($provider->approval_status ?? '') !== 'approved') {
            return $this->fail('Akaunti yako bado haijapitishwa kikamilifu.', 422);
        }

        $data = $request->validate([
            'approve_mode' => ['required', Rule::in(['now', 'later'])],
            'scheduled_for' => ['nullable', 'date', 'after:now'],
        ]);

        $approveMode = (string) ($data['approve_mode'] ?? 'now');
        $goNow = $approveMode !== 'later';
        $scheduledFor = $goNow ? null : ($data['scheduled_for'] ?? null);

        try {
            $acceptedOrder = $orderService->acceptOrder($order, $provider, $goNow, $scheduledFor);
            if (!$goNow) {
                $providerFresh = Provider::query()->find((int) $provider->id);
                if ($providerFresh) {
                    $this->refreshProviderAvailability($providerFresh, (int) $acceptedOrder->id, true);
                }
            }
        } catch (\Throwable $e) {
            return $this->fail($this->orderActionError($e, 'Imeshindikana kukubali oda hii kwa sasa.'), 422);
        }

        $fresh = Order::query()->with(['service.category', 'client'])->find((int) $order->id);
        if ($fresh) {
            $this->sendClientOrderAcceptedSms($fresh, $goNow);
            $orderNo = (string) ($fresh->order_no ?? '#' . $fresh->id);
            $this->sendClientOrderStatusNotification(
                $fresh,
                $goNow ? 'order_accepted' : 'order_scheduled',
                $goNow ? 'Oda yako imekubaliwa' : 'Oda yako imepangiwa ratiba',
                $goNow
                    ? 'Mtoa huduma yuko njiani kwenye oda ' . $orderNo . '.'
                    : 'Mtoa huduma amekubali oda ' . $orderNo . ' na amepanga kuanza muda uliopangwa.',
                [
                    'status' => (string) $fresh->status,
                ]
            );
        }

        return $this->ok([
            'order' => $fresh ? $this->providerOrderPayload($fresh, false, $provider) : null,
        ], $goNow ? 'Oda imekubaliwa.' : 'Oda imekubaliwa kwa ratiba.');
    }

    public function rejectOrder(Request $request, Order $order)
    {
        [, $provider] = $this->resolveProvider($request);
        if ((int) $order->provider_id !== (int) $provider->id) {
            return $this->fail('Huruhusiwi kufanya tendo hili.', 403);
        }

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:255'],
        ]);

        try {
            DB::transaction(function () use ($order, $provider, $data) {
                $lockedOrder = Order::whereKey((int) $order->id)->lockForUpdate()->firstOrFail();
                $lockedProvider = Provider::whereKey((int) $provider->id)->lockForUpdate()->firstOrFail();

                if ((int) $lockedOrder->provider_id !== (int) $lockedProvider->id) {
                    abort(403, 'Not your order.');
                }

                if (in_array((string) $lockedOrder->status, ['completed', 'cancelled'], true)) {
                    abort(422, 'Oda hii tayari imefungwa.');
                }

                $previousStatus = (string) $lockedOrder->status;
                $updates = ['status' => 'cancelled'];

                if (Schema::hasColumn('orders', 'payment_status') && (string) ($lockedOrder->payment_method ?? '') === 'prepay') {
                    $updates['payment_status'] = (string) ($lockedOrder->payment_status ?? '') === 'held'
                        ? 'refund_pending'
                        : 'cancelled';
                }

                if (Schema::hasColumn('orders', 'suspended_at')) {
                    $updates['suspended_at'] = null;
                }
                if (Schema::hasColumn('orders', 'suspended_until_at')) {
                    $updates['suspended_until_at'] = null;
                }
                if (Schema::hasColumn('orders', 'suspension_note')) {
                    $updates['suspension_note'] = null;
                }
                if (Schema::hasColumn('orders', 'resumed_at')) {
                    $updates['resumed_at'] = null;
                }
                if (Schema::hasColumn('orders', 'schedule_notified_at')) {
                    $updates['schedule_notified_at'] = null;
                }

                $reason = trim((string) ($data['reason'] ?? ''));
                if (Schema::hasColumn('orders', 'completion_note')) {
                    $updates['completion_note'] = 'Provider rejected: ' . $reason;
                }

                $lockedOrder->update($updates);

                $method = (string) ($lockedOrder->payment_method ?? '');
                $isCash = $method === '' || $method === 'cash';

                if ($isCash && in_array($previousStatus, ['accepted', 'on_the_way', 'in_progress', 'suspended'], true)) {
                    $commission = (float) ($lockedOrder->commission_amount ?? 0);
                    if ($commission > 0) {
                        $newDebt = max(0, (float) ($lockedProvider->debt_balance ?? 0) - $commission);

                        ProviderLedger::create([
                            'provider_id' => (int) $lockedProvider->id,
                            'type' => 'commission_credit',
                            'order_id' => (int) $lockedOrder->id,
                            'amount' => $commission,
                            'balance_after' => $newDebt,
                            'note' => 'Commission reversal for rejected order ' . (string) ($lockedOrder->order_no ?? ''),
                        ]);

                        $lockedProvider->update([
                            'debt_balance' => $newDebt,
                        ]);
                    }
                }

                $this->refreshProviderAvailability($lockedProvider, (int) $lockedOrder->id, true);
            });
        } catch (\Throwable $e) {
            return $this->fail($this->orderActionError($e, 'Imeshindikana kughairi oda hii kwa sasa.'), 422);
        }

        $fresh = Order::query()->with(['service.category', 'client'])->find((int) $order->id);
        if ($fresh) {
            $this->sendClientOrderRejectedSms($fresh, (string) $data['reason']);
            $orderNo = (string) ($fresh->order_no ?? '#' . $fresh->id);
            $this->sendClientOrderStatusNotification(
                $fresh,
                'order_rejected',
                'Oda imekataliwa',
                'Oda ' . $orderNo . ' imekataliwa. Tafadhali chagua mtoa huduma mwingine.',
                [
                    'status' => (string) $fresh->status,
                    'reason' => trim((string) $data['reason']),
                ]
            );
        }

        return $this->ok([
            'order' => $fresh ? $this->providerOrderPayload($fresh, false, $provider) : null,
        ], 'Oda imekataliwa kikamilifu.');
    }

    public function markOnTheWay(Request $request, Order $order)
    {
        [, $provider] = $this->resolveProvider($request);
        if ((int) $order->provider_id !== (int) $provider->id) {
            return $this->fail('Huruhusiwi kufanya tendo hili.', 403);
        }

        $order = DB::transaction(function () use ($order, $provider) {
            $locked = Order::whereKey((int) $order->id)->lockForUpdate()->firstOrFail();

            if ((int) $locked->provider_id !== (int) $provider->id) {
                abort(403, 'Not your order.');
            }

            if (!in_array((string) $locked->status, ['accepted', 'on_the_way'], true)) {
                abort(422, 'Order cannot be updated.');
            }

            $updates = ['status' => 'on_the_way'];
            if (Schema::hasColumn('orders', 'on_the_way_at') && $locked->on_the_way_at === null) {
                $updates['on_the_way_at'] = now();
            }

            $locked->update($updates);
            return $locked->fresh(['service.category', 'client']);
        });

        $orderNo = (string) ($order->order_no ?? '#' . $order->id);
        $this->sendClientOrderStatusNotification(
            $order,
            'order_on_the_way',
            'Mtoa huduma yuko njiani',
            'Mtoa huduma yuko njiani kwenye oda ' . $orderNo . '.',
            [
                'status' => (string) $order->status,
            ]
        );

        return $this->ok([
            'order' => $this->providerOrderPayload($order, false, $provider),
        ], 'Status imewekwa: on the way.');
    }

    public function markArrived(Request $request, Order $order)
    {
        [, $provider] = $this->resolveProvider($request);
        if ((int) $order->provider_id !== (int) $provider->id) {
            return $this->fail('Huruhusiwi kufanya tendo hili.', 403);
        }

        try {
            $order = DB::transaction(function () use ($order, $provider) {
                $locked = Order::whereKey((int) $order->id)->lockForUpdate()->firstOrFail();

                if ((int) $locked->provider_id !== (int) $provider->id) {
                    abort(403, 'Not your order.');
                }

                if (!in_array((string) $locked->status, ['accepted', 'on_the_way'], true)) {
                    abort(422, 'Order cannot be updated.');
                }

                if (!Schema::hasColumn('orders', 'provider_arrived_at')) {
                    abort(422, 'Arrival tracking not enabled.');
                }

                $updates = [];
                if ((string) $locked->status === 'accepted') {
                    $updates['status'] = 'on_the_way';
                }
                if ($locked->provider_arrived_at === null) {
                    $updates['provider_arrived_at'] = now();
                }

                if (!empty($updates)) {
                    $locked->update($updates);
                }

                return $locked->fresh(['service.category', 'client']);
            });
        } catch (\Throwable $e) {
            return $this->fail($this->orderActionError($e, 'Imeshindikana kuweka status ya arrived.'), 422);
        }

        $orderNo = (string) ($order->order_no ?? '#' . $order->id);
        $this->sendClientOrderStatusNotification(
            $order,
            'order_arrived',
            'Mtoa huduma amefika',
            'Mtoa huduma amefika kwenye location ya oda ' . $orderNo . '.',
            [
                'status' => (string) $order->status,
            ]
        );

        return $this->ok([
            'order' => $this->providerOrderPayload($order, false, $provider),
        ], 'Umeonyesha kuwa umefika.');
    }

    public function completeOrder(Request $request, Order $order, OrderService $orderService)
    {
        [, $provider] = $this->resolveProvider($request);
        if ((int) $order->provider_id !== (int) $provider->id) {
            return $this->fail('Huruhusiwi kufanya tendo hili.', 403);
        }

        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $orderService->completeOrder($order, $provider, $data['note'] ?? null);

            $providerFresh = Provider::query()->find((int) $provider->id);
            if ($providerFresh) {
                $this->refreshProviderAvailability($providerFresh, null, true);
            }
        } catch (\Throwable $e) {
            return $this->fail($this->orderActionError($e, 'Imeshindikana kumaliza oda hii kwa sasa.'), 422);
        }

        $fresh = Order::query()->with(['service.category', 'client'])->find((int) $order->id);
        if ($fresh) {
            $orderNo = (string) ($fresh->order_no ?? '#' . $fresh->id);
            $this->sendClientOrderStatusNotification(
                $fresh,
                'order_completed',
                'Oda imekamilika',
                'Oda ' . $orderNo . ' imekamilika. Karibu uweke review.',
                [
                    'status' => (string) $fresh->status,
                    'target_screen' => 'order_review',
                ]
            );
        }

        return $this->ok([
            'order' => $fresh ? $this->providerOrderPayload($fresh, false, $provider) : null,
        ], 'Oda imekamilika.');
    }

    public function suspendOrder(Request $request, Order $order)
    {
        [, $provider] = $this->resolveProvider($request);
        if ((int) $order->provider_id !== (int) $provider->id) {
            return $this->fail('Huruhusiwi kufanya tendo hili.', 403);
        }

        if (
            !Schema::hasColumn('orders', 'suspended_at')
            || !Schema::hasColumn('orders', 'suspended_until_at')
            || !Schema::hasColumn('orders', 'suspension_note')
            || !Schema::hasColumn('orders', 'resumed_at')
            || !Schema::hasColumn('orders', 'schedule_notified_at')
        ) {
            return $this->fail('Feature ya kusitisha oda haijakamilika kwenye database.', 422);
        }

        $data = $request->validate([
            'suspended_until_at' => ['required', 'date', 'after:now'],
            'suspension_note' => ['nullable', 'string', 'max:255'],
        ]);

        $untilAt = \Illuminate\Support\Carbon::parse((string) $data['suspended_until_at']);
        $note = trim((string) ($data['suspension_note'] ?? ''));

        try {
            DB::transaction(function () use ($order, $provider, $untilAt, $note) {
                $lockedOrder = Order::whereKey((int) $order->id)->lockForUpdate()->firstOrFail();
                $lockedProvider = Provider::whereKey((int) $provider->id)->lockForUpdate()->firstOrFail();

                if ((int) $lockedOrder->provider_id !== (int) $lockedProvider->id) {
                    abort(403, 'Not your order.');
                }

                if (!in_array((string) $lockedOrder->status, ['accepted', 'on_the_way', 'in_progress'], true)) {
                    abort(422, 'Oda hii haiwezi kusitishwa kwa status ya sasa.');
                }

                $lockedOrder->update([
                    'status' => 'suspended',
                    'suspended_at' => now(),
                    'suspended_until_at' => $untilAt,
                    'suspension_note' => $note !== '' ? $note : null,
                    'resumed_at' => null,
                    'schedule_notified_at' => null,
                ]);

                $this->refreshProviderAvailability($lockedProvider, (int) $lockedOrder->id, true);
            });
        } catch (\Throwable $e) {
            return $this->fail($this->orderActionError($e, 'Imeshindikana kuweka ratiba ya kusitisha oda.'), 422);
        }

        $fresh = Order::query()->with(['service.category', 'client'])->find((int) $order->id);

        return $this->ok([
            'order' => $fresh ? $this->providerOrderPayload($fresh, false, $provider) : null,
            'resume_at' => $untilAt->toIso8601String(),
        ], 'Oda imesitishwa kwa ratiba.');
    }

    public function updateProfile(Request $request)
    {
        [$user, $provider] = $this->resolveProvider($request);

        $allowedSkillSlugs = Category::query()
            ->where('is_active', 1)
            ->pluck('slug')
            ->map(fn ($slug) => strtolower(trim((string) $slug)))
            ->filter()
            ->unique()
            ->values();

        $rules = [
            'business_nickname' => ['nullable', 'string', 'max:120'],
            'phone_public' => ['required', 'string', 'max:20'],
            'alt_phone' => ['nullable', 'string', 'max:20'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'region' => ['nullable', 'string', 'max:80'],
            'district' => ['nullable', 'string', 'max:80'],
            'ward' => ['nullable', 'string', 'max:80'],
            'village' => ['nullable', 'string', 'max:120'],
            'house_number' => ['nullable', 'string', 'max:80'],
            'selected_skills' => ['nullable', 'array', 'max:20'],
        ];

        if ($allowedSkillSlugs->isNotEmpty()) {
            $rules['selected_skills.*'] = ['required', 'string', Rule::in($allowedSkillSlugs->all())];
        } else {
            $rules['selected_skills.*'] = ['required', 'string', 'max:60'];
        }

        $data = $request->validate($rules);

        $mainPhone = Phone::normalizeTzMsisdn((string) ($data['phone_public'] ?? ''));
        if ($mainPhone === null) {
            return $this->fail('Namba ya simu ya kazi si sahihi. Tumia 07XXXXXXXX au 2557XXXXXXXX.', 422);
        }

        $altPhoneRaw = trim((string) ($data['alt_phone'] ?? ''));
        $altPhone = null;
        if ($altPhoneRaw !== '') {
            $altPhone = Phone::normalizeTzMsisdn($altPhoneRaw);
            if ($altPhone === null) {
                return $this->fail('Namba ya simu mbadala si sahihi.', 422);
            }
        }

        $skills = collect($data['selected_skills'] ?? [])
            ->map(fn ($slug) => strtolower(trim((string) $slug)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $nickname = BusinessNickname::normalize((string) ($data['business_nickname'] ?? ''));
        if ($nickname !== '' && BusinessNickname::isTaken($nickname, (int) $provider->id)) {
            $suggestions = BusinessNickname::suggestions($nickname, (int) $provider->id, 3);
            $message = 'Nickname hii ya biashara tayari inatumika.';
            if (! empty($suggestions)) {
                $message .= ' Jaribu: ' . implode(', ', $suggestions) . '.';
            }

            return $this->fail($message, 422, [
                'business_nickname' => [$message],
                'business_nickname_suggestions' => $suggestions,
            ]);
        }

        try {
            DB::transaction(function () use ($provider, $user, $mainPhone, $altPhone, $skills, $data, $nickname) {
                $lockedProvider = Provider::query()->whereKey((int) $provider->id)->lockForUpdate()->firstOrFail();
                $lockedUser = User::query()->whereKey((int) $user->id)->lockForUpdate()->firstOrFail();

                $usedByOther = User::query()
                    ->where('phone', $mainPhone)
                    ->where('id', '!=', (int) $lockedUser->id)
                    ->exists();

                if ($usedByOther) {
                    abort(422, 'Namba hii ya simu tayari inatumika kwenye akaunti nyingine.');
                }

                $lockedProvider->update([
                    'business_nickname' => $nickname !== '' ? $nickname : null,
                    'phone_public' => $mainPhone,
                    'alt_phone' => $altPhone,
                    'bio' => $this->nullIfEmpty($data['bio'] ?? null),
                    'region' => $this->nullIfEmpty($data['region'] ?? null),
                    'district' => $this->nullIfEmpty($data['district'] ?? null),
                    'ward' => $this->nullIfEmpty($data['ward'] ?? null),
                    'village' => $this->nullIfEmpty($data['village'] ?? null),
                    'house_number' => $this->nullIfEmpty($data['house_number'] ?? null),
                    'selected_skills' => $skills,
                ]);

                $userUpdate = ['phone' => $mainPhone];
                if ($nickname !== '') {
                    $userUpdate['name'] = $nickname;
                }
                $lockedUser->forceFill($userUpdate)->save();
            });
        } catch (\Throwable $e) {
            return $this->fail($this->orderActionError($e, 'Imeshindikana kusasisha profile kwa sasa.'), 422);
        }

        $fresh = Provider::query()->with('user')->find((int) $provider->id);

        return $this->ok([
            'provider' => $fresh ? $this->providerPayload($fresh) : null,
        ], 'Profile imeboreshwa.');
    }

    public function servicesCatalog(Request $request)
    {
        [, $provider] = $this->resolveProvider($request);

        $selectedSkills = collect($provider->selected_skills ?? [])
            ->map(fn ($s) => strtolower(trim((string) $s)))
            ->filter()
            ->unique()
            ->values();

        $servicesQuery = Service::query()
            ->with([
                'category:id,name,slug',
                'media' => fn ($q) => $q->orderBy('sort_order'),
            ])
            ->where('is_active', 1)
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($selectedSkills->isNotEmpty()) {
            $servicesQuery->where(function ($q) use ($selectedSkills) {
                foreach ($selectedSkills as $slug) {
                    $q->orWhere('category', (string) $slug);
                }
            });
        }

        $allowedServices = $servicesQuery->get([
            'id',
            'name',
            'slug',
            'short_desc',
            'image_url',
            'base_price',
            'materials_price',
            'duration_minutes',
            'category',
            'category_id',
        ]);

        $allowedServiceIds = $allowedServices->pluck('id')->map(fn ($id) => (int) $id)->all();

        $activeServiceIdsQuery = DB::table('provider_services')
            ->where('provider_id', (int) $provider->id)
            ->where('is_active', 1);

        if (!empty($allowedServiceIds)) {
            $activeServiceIdsQuery->whereIn('service_id', $allowedServiceIds);
        }

        $activeServiceIds = $activeServiceIdsQuery
            ->pluck('service_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $activeLookup = array_flip($activeServiceIds);
        $servicePayloads = $allowedServices
            ->map(fn (Service $service): array => $this->serviceCatalogPayload($service))
            ->values();
        $selectedServices = $servicePayloads
            ->filter(fn (array $item): bool => isset($activeLookup[(int) ($item['id'] ?? 0)]))
            ->values();
        $unselectedServices = $servicePayloads
            ->filter(fn (array $item): bool => !isset($activeLookup[(int) ($item['id'] ?? 0)]))
            ->values();

        return $this->ok([
            'allowed_services' => $servicePayloads->all(),
            'active_service_ids' => array_values($activeServiceIds),
            'selected_service_ids' => $selectedServices->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
            'unselected_service_ids' => $unselectedServices->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
            'selected_services' => $selectedServices->all(),
            'unselected_services' => $unselectedServices->all(),
        ]);
    }

    public function updateServices(Request $request)
    {
        [, $provider] = $this->resolveProvider($request);

        if ((string) ($provider->approval_status ?? '') !== 'approved') {
            return $this->fail('Bado hujaruhusiwa kubadilisha huduma.', 422);
        }

        $allowedServiceIds = $this->allowedServiceIds($provider);
        if (empty($allowedServiceIds)) {
            return $this->fail('Hakuna huduma za kuchagua kwa profile yako kwa sasa.', 422);
        }

        $validated = $request->validate([
            'service_ids' => ['required', 'array', 'min:1'],
            'service_ids.*' => ['required', 'integer', Rule::in($allowedServiceIds)],
        ]);

        $serviceIds = collect($validated['service_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        $now = now();

        DB::transaction(function () use ($provider, $serviceIds, $now) {
            DB::table('provider_services')
                ->where('provider_id', (int) $provider->id)
                ->whereNotIn('service_id', $serviceIds->all())
                ->update([
                    'is_active' => 0,
                    'updated_at' => $now,
                ]);

            foreach ($serviceIds as $serviceId) {
                DB::table('provider_services')->updateOrInsert(
                    [
                        'provider_id' => (int) $provider->id,
                        'service_id' => (int) $serviceId,
                    ],
                    [
                        'is_active' => 1,
                        'updated_at' => $now,
                        'created_at' => $now,
                    ]
                );
            }

            if (Schema::hasTable('provider_service')) {
                DB::table('provider_service')
                    ->where('provider_id', (int) $provider->id)
                    ->whereNotIn('service_id', $serviceIds->all())
                    ->delete();

                foreach ($serviceIds as $serviceId) {
                    DB::table('provider_service')->updateOrInsert(
                        [
                            'provider_id' => (int) $provider->id,
                            'service_id' => (int) $serviceId,
                        ],
                        [
                            'updated_at' => $now,
                            'created_at' => $now,
                        ]
                    );
                }
            }
        });

        return $this->ok([
            'active_service_ids' => $serviceIds->all(),
        ], 'Huduma zako zimeboreshwa kwa mafanikio.');
    }

    public function withdraw(Request $request, ProviderWalletService $walletService, SnippePay $snippePay)
    {
        [, $provider] = $this->resolveProvider($request);

        if ((string) ($provider->approval_status ?? '') !== 'approved') {
            return $this->fail('Akaunti yako bado haijapitishwa kikamilifu.', 422);
        }

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'method' => ['nullable', 'string', 'max:50'],
            'destination' => ['nullable', 'string', 'max:120'],
        ]);

        try {
            $withdrawal = $walletService->requestWithdrawal(
                $provider,
                (float) $data['amount'],
                $data['method'] ?? null,
                $data['destination'] ?? null,
            );
        } catch (\Throwable $e) {
            return $this->fail($this->orderActionError($e, 'Imeshindikana kuanzisha ombi la withdrawal.'), 422);
        }

        $destination = trim((string) ($data['destination'] ?? ''));
        if ($destination === '') {
            $destination = (string) (data_get($provider, 'user.phone') ?? $provider->phone_public ?? '');
        }

        $msisdn = Phone::normalizeTzMsisdn($destination);
        if ($msisdn === null) {
            $walletService->failWithdrawalAndReverse($withdrawal, 'Namba ya simu si sahihi kwa payout.');
            return $this->fail('Namba ya simu si sahihi kwa payout.', 422);
        }

        $amount = (int) round((float) ($withdrawal->amount ?? 0));
        if ($amount <= 0) {
            $walletService->failWithdrawalAndReverse($withdrawal, 'Invalid payout amount.');
            return $this->fail('Kiasi cha payout si sahihi.', 422);
        }

        $payoutPayload = [
            'amount' => $amount,
            'channel' => 'mobile',
            'recipient_phone' => $msisdn,
            'recipient_name' => (string) (data_get($provider, 'user.name') ?? 'Provider'),
            'narration' => 'Glamo withdrawal ' . (int) $withdrawal->id,
            'webhook_url' => $snippePay->webhookUrl(),
            'metadata' => [
                'withdrawal_id' => (string) (int) $withdrawal->id,
                'provider_id' => (string) (int) $provider->id,
            ],
        ];

        try {
            $snippeRes = $snippePay->createPayout($payoutPayload, 'withdrawal-' . (int) $withdrawal->id);
        } catch (\Throwable $e) {
            $walletService->failWithdrawalAndReverse($withdrawal, $e->getMessage());
            return $this->fail($e->getMessage(), 500);
        }

        $reference = trim((string) (
            data_get($snippeRes, 'data.reference')
            ?: data_get($snippeRes, 'data.payment_reference')
            ?: data_get($snippeRes, 'reference')
        ));
        if ($reference === '') {
            $walletService->failWithdrawalAndReverse($withdrawal, 'Imeshindikana kuanzisha payout.');
            return $this->fail('Imeshindikana kuanzisha payout.', 500);
        }

        DB::transaction(function () use ($withdrawal, $reference) {
            $locked = ProviderWithdrawal::whereKey((int) $withdrawal->id)->lockForUpdate()->first();
            if (!$locked) {
                return;
            }
            if (in_array((string) $locked->status, ['paid', 'failed', 'rejected'], true)) {
                return;
            }
            $locked->update([
                'status' => 'processing',
                'reference' => $reference,
            ]);
        });

        return $this->ok([
            'withdrawal_id' => (int) $withdrawal->id,
            'reference' => $reference,
            'status' => 'processing',
        ], 'Ombi la kutoa pesa limepokelewa. Payout inaendelea.');
    }

    public function payDebt(Request $request, SnippePay $snippePay)
    {
        [$user, $provider] = $this->resolveProvider($request);

        if (!Schema::hasColumn('providers', 'debt_balance') || !Schema::hasTable('provider_payments')) {
            return $this->fail('Feature ya kulipa deni online haijakamilika kwenye database.', 422);
        }

        $data = $request->validate([
            'debt_amount' => ['required', 'numeric', 'min:100'],
            'payment_channel' => ['required', 'in:mpesa,tigopesa,airtelmoney,halopesa'],
            'phone_number' => ['required', 'string', 'max:30'],
        ]);

        $requestedAmount = round((float) $data['debt_amount'], 2);
        if ($requestedAmount <= 0) {
            return $this->fail('Kiasi si sahihi.', 422);
        }

        $msisdn = Phone::normalizeTzMsisdn((string) $data['phone_number']);
        if ($msisdn === null) {
            return $this->fail('Namba ya simu si sahihi. Tumia mfano 07XXXXXXXX au 2557XXXXXXXX.', 422);
        }

        $paymentChannel = (string) $data['payment_channel'];
        $payAmount = 0.0;
        $payment = null;
        $tempReference = 'DEBTREQ-' . now()->format('YmdHis') . '-' . (int) $provider->id . '-' . random_int(1000, 9999);

        try {
            DB::transaction(function () use ($provider, $requestedAmount, $paymentChannel, $tempReference, &$payAmount, &$payment) {
                $locked = Provider::whereKey((int) $provider->id)->lockForUpdate()->firstOrFail();
                $currentDebt = max(0, (float) ($locked->debt_balance ?? 0));

                if ($currentDebt <= 0) {
                    throw new \RuntimeException('Huna deni la kulipa kwa sasa.');
                }

                $payAmount = min($requestedAmount, $currentDebt);
                if ($payAmount <= 0) {
                    throw new \RuntimeException('Imeshindikana kuandaa ombi la malipo ya deni.');
                }

                $payment = ProviderPayment::create([
                    'provider_id' => (int) $locked->id,
                    'amount' => $payAmount,
                    'method' => $paymentChannel,
                    'reference' => $tempReference,
                    'status' => 'pending',
                    'paid_at' => null,
                ]);
            });
        } catch (\Throwable $e) {
            return $this->fail($this->orderActionError($e, 'Imeshindikana kuandaa malipo ya deni kwa sasa.'), 422);
        }

        $providerName = trim((string) ($provider->business_nickname ?? ''));
        if ($providerName === '') {
            $providerName = trim((string) ($user->name ?? 'Provider'));
        }
        $nameParts = preg_split('/\s+/', $providerName, -1, PREG_SPLIT_NO_EMPTY);
        $firstName = (string) ($nameParts[0] ?? 'Provider');
        $lastName = (string) ($nameParts[count($nameParts) - 1] ?? '');
        if ($lastName === '') {
            $lastName = 'Glamo';
        }

        $email = trim((string) ($user->email ?? ''));
        if ($email === '') {
            $email = 'provider' . (int) $user->id . '@getglamo.com';
        }

        $amount = (int) round($payAmount);
        if ($amount <= 0) {
            ProviderPayment::whereKey((int) $payment->id)->update(['status' => 'failed']);
            return $this->fail('Kiasi cha kulipa si sahihi.', 422);
        }

        $snippePayload = [
            'payment_type' => 'mobile',
            'details' => [
                'amount' => $amount,
                'currency' => 'TZS',
            ],
            'phone_number' => $msisdn,
            'customer' => [
                'firstname' => $firstName,
                'lastname' => $lastName,
                'email' => $email,
            ],
            'external_reference' => 'provider-debt-' . (int) $payment->id,
            'metadata' => [
                'provider_payment_id' => (string) (int) $payment->id,
                'provider_id' => (string) (int) $provider->id,
                'purpose' => 'provider_debt_payment',
            ],
            'webhook_url' => $snippePay->webhookUrl(),
        ];

        try {
            $snippeRes = $snippePay->createPayment($snippePayload, 'provider-debt-' . (int) $payment->id);
        } catch (\Throwable $e) {
            ProviderPayment::whereKey((int) $payment->id)->update(['status' => 'failed']);
            return $this->fail($e->getMessage(), 500);
        }

        $paymentReference = trim((string) (
            data_get($snippeRes, 'data.reference')
            ?: data_get($snippeRes, 'data.payment_reference')
            ?: data_get($snippeRes, 'reference')
        ));
        if ($paymentReference === '') {
            ProviderPayment::whereKey((int) $payment->id)->update(['status' => 'failed']);
            return $this->fail('Imeshindikana kuanzisha malipo ya deni. Jaribu tena.', 500);
        }

        ProviderPayment::whereKey((int) $payment->id)->update([
            'reference' => $paymentReference,
            'method' => $paymentChannel,
            'status' => 'pending',
        ]);

        return $this->ok([
            'provider_payment_id' => (int) $payment->id,
            'reference' => $paymentReference,
            'amount' => round($payAmount, 2),
            'status' => 'pending',
        ], 'Ombi la malipo ya deni limetumwa. Kamilisha uthibitisho kwenye simu yako.');
    }

    public function debtPayments(Request $request)
    {
        [, $provider] = $this->resolveProvider($request);

        if (!Schema::hasTable('provider_payments')) {
            return $this->fail('Feature ya debt payments haijakamilika kwenye database.', 422);
        }

        $perPage = max(1, min(50, (int) $request->query('per_page', 20)));
        $status = strtolower(trim((string) $request->query('status', '')));

        $query = ProviderPayment::query()
            ->where('provider_id', (int) $provider->id)
            ->latest('id');

        if ($status !== '') {
            $query->where('status', $status);
        }

        $payments = $query->paginate($perPage);

        return $this->ok([
            'payments' => collect($payments->items())
                ->map(fn (ProviderPayment $item): array => $this->debtPaymentPayload($item))
                ->values()
                ->all(),
            'meta' => [
                'current_page' => (int) $payments->currentPage(),
                'per_page' => (int) $payments->perPage(),
                'total' => (int) $payments->total(),
                'last_page' => (int) $payments->lastPage(),
            ],
        ]);
    }

    public function showDebtPayment(Request $request, ProviderPayment $providerPayment)
    {
        [, $provider] = $this->resolveProvider($request);

        if ((int) $providerPayment->provider_id !== (int) $provider->id) {
            return $this->fail('Huruhusiwi kuona malipo haya.', 403);
        }

        return $this->ok([
            'payment' => $this->debtPaymentPayload($providerPayment),
        ]);
    }

    public function refreshDebtPayment(
        Request $request,
        ProviderPayment $providerPayment,
        SnippePay $snippePay,
        ProviderDebtService $providerDebtService
    ) {
        [, $provider] = $this->resolveProvider($request);

        if ((int) $providerPayment->provider_id !== (int) $provider->id) {
            return $this->fail('Huruhusiwi kusasisha malipo haya.', 403);
        }

        $currentStatus = strtolower(trim((string) ($providerPayment->status ?? 'pending')));
        if ($currentStatus === 'paid') {
            return $this->ok([
                'payment' => $this->debtPaymentPayload($providerPayment->fresh() ?: $providerPayment),
                'gateway' => [
                    'status' => 'paid',
                ],
            ], 'Malipo haya tayari yameshalipwa.');
        }

        $reference = trim((string) ($providerPayment->reference ?? ''));
        if ($reference === '') {
            return $this->fail('Hakuna reference ya malipo haya. Haiwezi ku-refresh status.', 422);
        }

        try {
            $gatewayResponse = $snippePay->getPayment($reference);
        } catch (\Throwable $e) {
            return $this->fail($this->orderActionError($e, 'Imeshindikana kuangalia status ya malipo.'), 500);
        }

        $gatewayStatusRaw = trim((string) (
            data_get($gatewayResponse, 'data.status')
            ?: data_get($gatewayResponse, 'status')
            ?: data_get($gatewayResponse, 'data.payment_status')
            ?: ''
        ));
        $normalizedStatus = $this->normalizeProviderPaymentStatus($gatewayStatusRaw);

        if ($normalizedStatus === 'paid') {
            try {
                $providerPayment = $providerDebtService->markPaymentAsPaid($providerPayment);
            } catch (\Throwable $e) {
                return $this->fail($this->orderActionError($e, 'Imeshindikana kusasisha malipo kuwa paid.'), 422);
            }
        } elseif ($normalizedStatus === 'failed') {
            DB::transaction(function () use ($providerPayment) {
                $locked = ProviderPayment::query()->whereKey((int) $providerPayment->id)->lockForUpdate()->first();
                if (!$locked || (string) $locked->status === 'paid') {
                    return;
                }

                $locked->update([
                    'status' => 'failed',
                ]);
            });

            $providerPayment = $providerPayment->fresh() ?: $providerPayment;
        } else {
            $providerPayment = $providerPayment->fresh() ?: $providerPayment;
        }

        return $this->ok([
            'payment' => $this->debtPaymentPayload($providerPayment),
            'gateway' => [
                'reference' => $reference,
                'status' => $normalizedStatus,
                'raw_status' => $gatewayStatusRaw !== '' ? $gatewayStatusRaw : null,
            ],
        ], 'Status ya malipo imeboreshwa.');
    }

    private function resolveProvider(Request $request): array
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthorized.');

        $existingProvider = $user->provider;
        if ((string) ($user->role ?? '') !== 'provider' && !$existingProvider) {
            abort(403, 'Forbidden.');
        }

        if ((string) ($user->role ?? '') !== 'provider') {
            $user->forceFill(['role' => 'provider'])->save();
        }

        $provider = $existingProvider ?: Provider::query()->firstOrCreate(
            ['user_id' => (int) $user->id],
            [
                'approval_status' => 'pending',
                'phone_public' => (string) ($user->phone ?? ''),
                'online_status' => 'offline',
                'is_active' => true,
            ]
        );

        $provider->loadMissing('user');

        $providerPhone = Phone::normalizeTzMsisdn((string) ($provider->phone_public ?? ''));
        if ($providerPhone !== null && trim((string) ($user->phone ?? '')) !== $providerPhone) {
            $usedByOther = User::query()
                ->where('phone', $providerPhone)
                ->where('id', '!=', (int) $user->id)
                ->exists();

            if (!$usedByOther) {
                $user->forceFill(['phone' => $providerPhone])->save();
            }
        }

        return [$user, $provider];
    }

    private function refreshProviderAvailability(
        Provider $provider,
        ?int $excludeOrderId = null,
        bool $ignoreSuspended = false,
        ?string $busyReason = null
    ): void {
        $debtBlock = (float) config('glamo_pricing.provider_debt_block_threshold', 10000);
        if ($debtBlock <= 0) {
            $debtBlock = 10000;
        }

        $debt = max(0, (float) ($provider->debt_balance ?? 0));
        $currentStatus = (string) ($provider->online_status ?? 'offline');
        $isDebtBlocked = $debt > $debtBlock || ($currentStatus === 'blocked_debt' && $debt >= $debtBlock);

        if ($this->providerHasBlockingOrders((int) $provider->id, $excludeOrderId, $ignoreSuspended)) {
            if ($isDebtBlocked) {
                $provider->update([
                    'online_status' => 'blocked_debt',
                    'offline_reason' => 'Deni limefika kikomo. Lipa deni ushuke chini ya TZS ' . number_format($debtBlock, 0) . '.',
                ]);
                return;
            }

            $provider->update([
                'online_status' => 'offline',
                'offline_reason' => $busyReason ?: 'Ana oda nyingine inayoendelea.',
            ]);
            return;
        }

        if ($isDebtBlocked) {
            $provider->update([
                'online_status' => 'blocked_debt',
                'offline_reason' => 'Deni limefika kikomo. Lipa deni ushuke chini ya TZS ' . number_format($debtBlock, 0) . '.',
            ]);
            return;
        }

        if ((string) ($provider->approval_status ?? '') !== 'approved' || !(bool) ($provider->is_active ?? true)) {
            $provider->update([
                'online_status' => 'offline',
                'offline_reason' => 'Akaunti bado haijaruhusiwa kwenda online.',
            ]);
            return;
        }

        if ($currentStatus === 'blocked_debt') {
            $provider->update([
                'online_status' => 'offline',
                'offline_reason' => 'Deni limepungua. Unaweza kujiweka online.',
            ]);
            return;
        }

        if ($currentStatus === 'busy') {
            $provider->update([
                'online_status' => 'offline',
                'offline_reason' => null,
            ]);
        }
    }

    private function providerHasBlockingOrders(int $providerId, ?int $excludeOrderId = null, bool $ignoreSuspended = false): bool
    {
        $query = Order::query()
            ->where('provider_id', $providerId)
            ->whereNotIn('status', ['completed', 'cancelled']);

        if ($ignoreSuspended) {
            $query->where('status', '!=', 'suspended');
        }

        if ($excludeOrderId !== null && $excludeOrderId > 0) {
            $query->where('id', '!=', $excludeOrderId);
        }

        return $query->exists();
    }

    private function availabilityControlState(Provider $provider, bool $hasBlockingOrders, ?float $debtBlock = null): array
    {
        $debtBlock = $debtBlock ?? (float) config('glamo_pricing.provider_debt_block_threshold', 10000);
        if ($debtBlock <= 0) {
            $debtBlock = 10000;
        }

        $debtBalance = max(0, (float) ($provider->debt_balance ?? 0));
        $onlineStatus = strtolower((string) ($provider->online_status ?? 'offline'));
        $nextAction = $onlineStatus === 'online' ? 'offline' : 'online';

        $canToggle = true;
        $reason = null;

        if ($debtBalance > 0) {
            $canToggle = false;
            $reason = 'Una deni. Lipa deni kwanza ndipo ubadilishe status.';
        } elseif ($nextAction === 'online' && $hasBlockingOrders) {
            $canToggle = false;
            $reason = 'Una oda ambayo bado haijacomplete.';
        } elseif ($nextAction === 'online' && (string) ($provider->approval_status ?? '') !== 'approved') {
            $canToggle = false;
            $reason = 'Akaunti yako bado haijapitishwa kikamilifu.';
        }

        return [
            'current_status' => $onlineStatus,
            'next_action' => $nextAction,
            'can_toggle' => $canToggle,
            'reason' => $reason,
            'debt_balance' => $debtBalance,
            'debt_block_threshold' => (float) $debtBlock,
            'has_uncompleted_order' => $hasBlockingOrders,
        ];
    }

    private function allowedServiceIds(Provider $provider): array
    {
        $selectedSkills = collect($provider->selected_skills ?? [])
            ->map(fn ($s) => strtolower(trim((string) $s)))
            ->filter()
            ->unique()
            ->values();

        $query = Service::query()->where('is_active', 1);
        if ($selectedSkills->isNotEmpty()) {
            $query->where(function ($q) use ($selectedSkills) {
                foreach ($selectedSkills as $slug) {
                    $q->orWhere('category', (string) $slug);
                }
            });
        }

        return $query->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    private function orderActionError(\Throwable $e, string $fallback): string
    {
        $message = trim((string) $e->getMessage());
        return $message === '' ? $fallback : $message;
    }

    private function nullIfEmpty(mixed $value): ?string
    {
        $text = trim((string) $value);
        return $text === '' ? null : $text;
    }

    private function calculateTotalEarn(int $providerId): float
    {
        $rows = Order::query()
            ->where('provider_id', $providerId)
            ->whereNotIn('status', ['cancelled'])
            ->get(['price_total', 'commission_amount', 'payout_amount']);

        $total = 0.0;
        foreach ($rows as $row) {
            $priceTotal = is_numeric($row->price_total) ? (float) $row->price_total : 0.0;
            if ($priceTotal <= 0 && is_numeric($row->payout_amount) && is_numeric($row->commission_amount)) {
                $priceTotal = (float) $row->payout_amount + (float) $row->commission_amount;
            }
            if ($priceTotal <= 0) {
                continue;
            }

            $commission = round($priceTotal * 0.10, 2);
            $total += max(0, $priceTotal - $commission);
        }

        return round($total, 2);
    }

    private function calculateTotalOrderAmount(int $providerId): float
    {
        if ($providerId <= 0) {
            return 0.0;
        }

        $total = Order::query()
            ->where('provider_id', $providerId)
            ->whereNotIn('status', ['cancelled'])
            ->sum('price_total');

        return round((float) $total, 2);
    }

    private function serviceCatalogPayload(Service $service): array
    {
        return [
            'id' => (int) $service->id,
            'name' => (string) $service->name,
            'slug' => (string) $service->slug,
            'short_desc' => $service->short_desc ?: null,
            'base_price' => (float) ($service->base_price ?? 0),
            'materials_price' => (float) ($service->materials_price ?? 0),
            'duration_minutes' => (int) ($service->duration_minutes ?? 0),
            'category_slug' => (string) ($service->category ?? ''),
            'category' => [
                'id' => (int) data_get($service, 'category.id', 0),
                'name' => (string) data_get($service, 'category.name', ''),
                'slug' => (string) data_get($service, 'category.slug', ''),
            ],
            'image_url' => (string) ($service->primary_image_url ?? $service->image_url ?? ''),
            'gallery' => array_values($service->gallery_image_urls ?? []),
        ];
    }

    private function debtPaymentPayload(ProviderPayment $payment): array
    {
        return [
            'id' => (int) $payment->id,
            'provider_id' => (int) ($payment->provider_id ?? 0),
            'amount' => round((float) ($payment->amount ?? 0), 2),
            'method' => $payment->method ?: null,
            'reference' => $payment->reference ?: null,
            'status' => (string) ($payment->status ?? 'pending'),
            'paid_at' => $payment->paid_at
                ? \Illuminate\Support\Carbon::parse((string) $payment->paid_at)->toIso8601String()
                : null,
            'created_at' => optional($payment->created_at)->toIso8601String(),
            'updated_at' => optional($payment->updated_at)->toIso8601String(),
        ];
    }

    private function normalizeProviderPaymentStatus(?string $status): string
    {
        $status = strtolower(trim((string) $status));
        if ($status === '') {
            return 'pending';
        }

        if (in_array($status, ['paid', 'completed', 'complete', 'success', 'successful', 'succeeded'], true)) {
            return 'paid';
        }

        if (in_array($status, ['failed', 'fail', 'cancelled', 'canceled', 'rejected', 'declined', 'expired', 'error'], true)) {
            return 'failed';
        }

        return 'pending';
    }

    private function providerReviewsSummaryData(int $providerId): array
    {
        $query = Review::query()->where('provider_id', $providerId);
        $total = (int) (clone $query)->count();
        $avg = (clone $query)->avg('rating');
        $latestReviewAt = (clone $query)->max('created_at');

        $counts = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
        $rawBreakdown = (clone $query)
            ->select('rating', DB::raw('COUNT(*) AS total'))
            ->groupBy('rating')
            ->pluck('total', 'rating')
            ->all();

        foreach ($rawBreakdown as $rating => $count) {
            $rating = (int) $rating;
            if ($rating >= 1 && $rating <= 5) {
                $counts[$rating] = (int) $count;
            }
        }

        return [
            'total_reviews' => $total,
            'rating_avg' => $avg !== null ? round((float) $avg, 2) : null,
            'latest_review_at' => $latestReviewAt
                ? \Illuminate\Support\Carbon::parse((string) $latestReviewAt)->toIso8601String()
                : null,
            'rating_breakdown' => [
                '1' => $counts[1],
                '2' => $counts[2],
                '3' => $counts[3],
                '4' => $counts[4],
                '5' => $counts[5],
            ],
        ];
    }

    private function reviewPayload(Review $review): array
    {
        return [
            'id' => (int) $review->id,
            'order_id' => (int) ($review->order_id ?? 0),
            'provider_id' => (int) ($review->provider_id ?? 0),
            'client_id' => (int) ($review->client_id ?? 0),
            'rating' => (int) ($review->rating ?? 0),
            'comment' => $review->comment ?: null,
            'created_at' => optional($review->created_at)->toIso8601String(),
            'client' => [
                'id' => (int) data_get($review, 'client.id', $review->client_id),
                'name' => (string) data_get($review, 'client.name', ''),
                'phone' => (string) data_get($review, 'client.phone', ''),
                'profile_image_url' => (string) data_get($review, 'client.profile_image_url', ''),
            ],
            'order' => [
                'id' => (int) data_get($review, 'order.id', $review->order_id),
                'order_no' => (string) data_get($review, 'order.order_no', ''),
                'created_at' => optional(data_get($review, 'order.created_at'))->toIso8601String(),
                'price_total' => is_numeric(data_get($review, 'order.price_total'))
                    ? (float) data_get($review, 'order.price_total')
                    : null,
                'service' => [
                    'id' => (int) data_get($review, 'order.service.id', 0),
                    'name' => (string) data_get($review, 'order.service.name', ''),
                    'slug' => (string) data_get($review, 'order.service.slug', ''),
                    'image_url' => (string) (data_get($review, 'order.service.primary_image_url') ?: data_get($review, 'order.service.image_url', '')),
                ],
            ],
        ];
    }

    private function providerClientFeedbackPayload(ProviderClientFeedback $feedback): array
    {
        return [
            'id' => (int) $feedback->id,
            'order_id' => (int) ($feedback->order_id ?? 0),
            'provider_id' => (int) ($feedback->provider_id ?? 0),
            'client_id' => (int) ($feedback->client_id ?? 0),
            'rating' => (int) ($feedback->rating ?? 0),
            'comment' => $feedback->comment ?: null,
            'created_at' => optional($feedback->created_at)->toIso8601String(),
            'client' => [
                'id' => (int) data_get($feedback, 'client.id', $feedback->client_id),
                'name' => (string) data_get($feedback, 'client.name', ''),
                'phone' => (string) data_get($feedback, 'client.phone', ''),
                'profile_image_url' => (string) data_get($feedback, 'client.profile_image_url', ''),
            ],
            'order' => [
                'id' => (int) data_get($feedback, 'order.id', $feedback->order_id),
                'order_no' => (string) data_get($feedback, 'order.order_no', ''),
                'created_at' => optional(data_get($feedback, 'order.created_at'))->toIso8601String(),
                'price_total' => is_numeric(data_get($feedback, 'order.price_total'))
                    ? (float) data_get($feedback, 'order.price_total')
                    : null,
                'service' => [
                    'id' => (int) data_get($feedback, 'order.service.id', 0),
                    'name' => (string) data_get($feedback, 'order.service.name', ''),
                    'slug' => (string) data_get($feedback, 'order.service.slug', ''),
                    'image_url' => (string) (data_get($feedback, 'order.service.primary_image_url') ?: data_get($feedback, 'order.service.image_url', '')),
                ],
            ],
        ];
    }

    private function sendClientOrderAcceptedSms(Order $order, bool $goNow): void
    {
        $order->loadMissing(['client:id,name,phone', 'service:id,name']);
        $clientPhone = trim((string) data_get($order, 'client.phone', ''));
        if ($clientPhone === '') {
            return;
        }

        $orderNo = (string) ($order->order_no ?? '#' . $order->id);
        $serviceName = trim((string) data_get($order, 'service.name', ''));
        $amount = number_format((float) ($order->price_total ?? 0), 0);

        $message = $goNow
            ? 'Glamo: Oda ' . $orderNo . ' imekubaliwa. Mtoa huduma yupo njiani'
            : 'Glamo: Oda ' . $orderNo . ' imekubaliwa kwa ratiba';

        if (!$goNow && $order->suspended_until_at) {
            $message .= ' (' . \Illuminate\Support\Carbon::parse((string) $order->suspended_until_at)->format('d/m H:i') . ')';
        }

        if ($serviceName !== '') {
            $message .= '. Huduma: ' . $serviceName;
        }
        $message .= '. Jumla TZS ' . $amount . '.';

        app(BeemSms::class)->sendMessage(
            $clientPhone,
            $this->limitSmsText($message),
            (int) data_get($order, 'client.id', $order->client_id)
        );
    }

    private function sendClientOrderStatusNotification(
        Order $order,
        string $type,
        string $title,
        string $message,
        array $meta = []
    ): void {
        $clientId = (int) ($order->client_id ?? 0);
        if ($clientId <= 0) {
            return;
        }

        $payload = array_merge([
            'order_id' => (string) (int) $order->id,
            'order_no' => (string) ($order->order_no ?? '#' . $order->id),
            'target_screen' => 'order_details',
        ], $meta);

        try {
            app(AppNotificationService::class)->sendToUsers(
                [$clientId],
                trim($type),
                trim($title),
                trim($message),
                $payload,
                true
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function sendClientOrderRejectedSms(Order $order, string $reason): void
    {
        $order->loadMissing(['client:id,name,phone']);
        $clientPhone = trim((string) data_get($order, 'client.phone', ''));
        if ($clientPhone === '') {
            return;
        }

        $orderNo = (string) ($order->order_no ?? '#' . $order->id);
        $reason = trim($reason);
        if ($reason !== '') {
            $reason = preg_replace('/\s+/', ' ', $reason) ?? $reason;
            $reason = substr($reason, 0, 80);
        }

        $message = 'Glamo: Oda ' . $orderNo . ' imekataliwa.';
        if ($reason !== '') {
            $message .= ' Sababu: ' . $reason . '.';
        }
        $message .= ' Tafadhali chagua mtoa huduma mwingine.';

        app(BeemSms::class)->sendMessage(
            $clientPhone,
            $this->limitSmsText($message),
            (int) data_get($order, 'client.id', $order->client_id)
        );
    }

    private function limitSmsText(string $message, int $maxLength = 150): string
    {
        $text = trim((string) preg_replace('/\s+/', ' ', $message));
        if ($text === '') {
            return '';
        }

        if (strlen($text) <= $maxLength) {
            return $text;
        }

        return rtrim(substr($text, 0, max(1, $maxLength - 3))) . '...';
    }

    private function providerPayload(Provider $provider): array
    {
        return [
            'id' => (int) $provider->id,
            'user_id' => (int) $provider->user_id,
            'display_name' => (string) ($provider->display_name ?? ''),
            'business_nickname' => $provider->business_nickname ?: null,
            'profile_image_url' => (string) ($provider->profile_image_url ?? ''),
            'phone_public' => $provider->phone_public ?: null,
            'approval_status' => (string) ($provider->approval_status ?? 'pending'),
            'online_status' => (string) ($provider->online_status ?? 'offline'),
            'offline_reason' => $provider->offline_reason ?: null,
            'is_active' => (bool) ($provider->is_active ?? false),
            'debt_balance' => (float) ($provider->debt_balance ?? 0),
            'wallet_balance' => (float) ($provider->wallet_balance ?? 0),
            'rating_avg' => $provider->rating_avg !== null ? (float) $provider->rating_avg : null,
            'total_orders' => (int) ($provider->total_orders ?? 0),
            'current_lat' => is_numeric($provider->current_lat) ? (float) $provider->current_lat : null,
            'current_lng' => is_numeric($provider->current_lng) ? (float) $provider->current_lng : null,
            'last_location_at' => optional($provider->last_location_at)->toIso8601String(),
            'selected_skills' => array_values((array) ($provider->selected_skills ?? [])),
            'onboarding_submitted_at' => optional($provider->onboarding_submitted_at)->toIso8601String(),
            'onboarding_completed_at' => optional($provider->onboarding_completed_at)->toIso8601String(),
        ];
    }

    private function providerOrderPayload(Order $order, bool $withItems = false, ?Provider $viewerProvider = null): array
    {
        $clientLat = is_numeric($order->client_lat) ? (float) $order->client_lat : null;
        $clientLng = is_numeric($order->client_lng) ? (float) $order->client_lng : null;

        $providerLat = is_numeric($viewerProvider?->current_lat) ? (float) $viewerProvider->current_lat : null;
        $providerLng = is_numeric($viewerProvider?->current_lng) ? (float) $viewerProvider->current_lng : null;
        $providerLastLocationAt = optional($viewerProvider?->last_location_at)->toIso8601String();

        if ($providerLat === null && $order->relationLoaded('provider') && is_numeric(data_get($order, 'provider.current_lat'))) {
            $providerLat = (float) data_get($order, 'provider.current_lat');
        }
        if ($providerLng === null && $order->relationLoaded('provider') && is_numeric(data_get($order, 'provider.current_lng'))) {
            $providerLng = (float) data_get($order, 'provider.current_lng');
        }
        if ($providerLastLocationAt === null && $order->relationLoaded('provider')) {
            $providerLastLocationAt = optional(data_get($order, 'provider.last_location_at'))->toIso8601String();
        }

        $distanceKm = is_numeric($order->travel_distance_km) ? (float) $order->travel_distance_km : null;
        if ($distanceKm === null && $clientLat !== null && $clientLng !== null && $providerLat !== null && $providerLng !== null) {
            $distanceKm = $this->haversineKm($providerLat, $providerLng, $clientLat, $clientLng);
        }

        $primaryServiceId = (int) data_get($order, 'service.id', $order->service_id);
        $primaryServiceName = (string) data_get($order, 'service.name', '');
        $primaryServiceSlug = (string) data_get($order, 'service.slug', '');
        $primaryServiceImageUrl = (string) (data_get($order, 'service.primary_image_url') ?: data_get($order, 'service.image_url', ''));

        $bookedServices = [[
            'id' => $primaryServiceId,
            'name' => $primaryServiceName,
            'slug' => $primaryServiceSlug,
            'image_url' => $primaryServiceImageUrl,
            'line_total' => (float) ($order->price_total ?? 0),
        ]];

        $providerPhone = trim((string) (
            $viewerProvider?->phone_public
            ?: data_get($viewerProvider, 'user.phone')
            ?: data_get($order, 'provider.phone_public')
            ?: data_get($order, 'provider.user.phone')
            ?: ''
        ));

        $payload = [
            'id' => (int) $order->id,
            'order_no' => (string) ($order->order_no ?? ''),
            'status' => (string) ($order->status ?? ''),
            'address_text' => $order->address_text ?: null,
            'scheduled_at' => optional($order->scheduled_at)->toIso8601String(),
            'accepted_at' => optional($order->accepted_at)->toIso8601String(),
            'on_the_way_at' => optional($order->on_the_way_at)->toIso8601String(),
            'provider_arrived_at' => optional($order->provider_arrived_at)->toIso8601String(),
            'client_arrival_confirmed_at' => optional($order->client_arrival_confirmed_at)->toIso8601String(),
            'completed_at' => optional($order->completed_at)->toIso8601String(),
            'created_at' => optional($order->created_at)->toIso8601String(),
            'updated_at' => optional($order->updated_at)->toIso8601String(),
            'order_received_at' => optional($order->created_at)->toIso8601String(),
            'map' => [
                'client_lat' => $clientLat,
                'client_lng' => $clientLng,
                'provider_lat' => $providerLat,
                'provider_lng' => $providerLng,
                'distance_km' => $distanceKm !== null ? round($distanceKm, 3) : null,
            ],
            'client' => [
                'id' => (int) data_get($order, 'client.id', $order->client_id),
                'name' => (string) data_get($order, 'client.name', ''),
                'phone' => (string) data_get($order, 'client.phone', ''),
                'profile_image_url' => (string) data_get($order, 'client.profile_image_url', ''),
                'lat' => $clientLat,
                'lng' => $clientLng,
                'distance_km' => $distanceKm !== null ? round($distanceKm, 3) : null,
                'location_text' => $order->address_text ?: null,
            ],
            'provider' => [
                'id' => (int) ($viewerProvider?->id ?: data_get($order, 'provider.id', $order->provider_id)),
                'display_name' => (string) ($viewerProvider?->display_name ?: data_get($order, 'provider.display_name', '')),
                'phone' => $providerPhone,
                'profile_image_url' => (string) ($viewerProvider?->profile_image_url ?: data_get($order, 'provider.profile_image_url', '')),
                'lat' => $providerLat,
                'lng' => $providerLng,
                'last_location_at' => $providerLastLocationAt,
            ],
            'service' => [
                'id' => $primaryServiceId,
                'name' => $primaryServiceName,
                'slug' => $primaryServiceSlug,
                'image_url' => $primaryServiceImageUrl,
                'category' => [
                    'id' => (int) data_get($order, 'service.category.id', 0),
                    'name' => (string) data_get($order, 'service.category.name', ''),
                    'slug' => (string) data_get($order, 'service.category.slug', ''),
                ],
            ],
            'booked_services' => $bookedServices,
            'payment' => [
                'method' => (string) ($order->payment_method ?? ''),
                'channel' => (string) ($order->payment_channel ?? ''),
                'status' => (string) ($order->payment_status ?? ''),
                'reference' => (string) ($order->payment_reference ?? ''),
                'refund_reference' => (string) ($order->refund_reference ?? ''),
                'refund_reason' => (string) ($order->refund_reason ?? ''),
            ],
            'cancellation_reason' => (string) ($order->cancellation_reason ?? ''),
            'price' => [
                'total' => (float) ($order->price_total ?? 0),
                'service' => $order->price_service !== null ? (float) $order->price_service : null,
                'materials' => $order->price_materials !== null ? (float) $order->price_materials : null,
                'travel' => $order->price_travel !== null ? (float) $order->price_travel : null,
                'usage' => $order->price_usage !== null ? (float) $order->price_usage : null,
                'distance_km' => $distanceKm !== null ? round($distanceKm, 3) : null,
                'commission_amount' => (float) ($order->commission_amount ?? 0),
                'payout_amount' => $order->payout_amount !== null ? (float) $order->payout_amount : null,
            ],
            'review' => $order->relationLoaded('review') && $order->review ? [
                'id' => (int) $order->review->id,
                'rating' => (int) $order->review->rating,
                'comment' => $order->review->comment,
                'created_at' => optional($order->review->created_at)->toIso8601String(),
            ] : null,
        ];

        if ($withItems && Schema::hasTable('order_items')) {
            $items = $order->relationLoaded('items')
                ? $order->items
                : $order->items()->with('service.category')->get();

            $payload['items'] = $items->map(function ($item): array {
                return [
                    'id' => (int) $item->id,
                    'service_id' => (int) ($item->service_id ?? 0),
                    'service_name' => (string) data_get($item, 'service.name', ''),
                    'service_slug' => (string) data_get($item, 'service.slug', ''),
                    'service_image_url' => (string) (data_get($item, 'service.primary_image_url') ?: data_get($item, 'service.image_url', '')),
                    'price_service' => (float) ($item->price_service ?? 0),
                    'price_materials' => (float) ($item->price_materials ?? 0),
                    'price_usage' => (float) ($item->price_usage ?? 0),
                    'usage_percent' => (float) ($item->usage_percent ?? 0),
                    'line_total' => (float) ($item->line_total ?? 0),
                ];
            })->values()->all();

            if (!empty($payload['items'])) {
                $payload['booked_services'] = array_values(array_map(function (array $item): array {
                    return [
                        'id' => (int) ($item['service_id'] ?? 0),
                        'name' => (string) ($item['service_name'] ?? ''),
                        'slug' => (string) ($item['service_slug'] ?? ''),
                        'image_url' => (string) ($item['service_image_url'] ?? ''),
                        'line_total' => (float) ($item['line_total'] ?? 0),
                    ];
                }, $payload['items']));
            }
        }

        return $payload;
    }

    private function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadiusKm = 6371.0;

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2)
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
            * sin($dLng / 2) * sin($dLng / 2);
        $c = 2 * atan2(sqrt($a), sqrt(max(0.0, 1 - $a)));

        return round($earthRadiusKm * $c, 3);
    }
}
