<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Provider;
use App\Models\ProviderLedger;
use App\Models\Review;
use App\Models\Service;
use App\Models\User;
use App\Services\Api\QuoteService;
use App\Services\OrderNotifier;
use App\Services\OrderPaymentService;
use App\Services\OrderService;
use App\Services\SnippePay;
use App\Support\BookingWindow;
use App\Support\Phone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ClientController extends Controller
{
    use ApiResponse;

    public function orders(Request $request)
    {
        $user = $this->clientUser($request);

        $perPage = max(1, min(50, (int) $request->query('per_page', 20)));
        $status = trim((string) $request->query('status', ''));

        $query = Order::query()
            ->with(['service.category', 'provider.user'])
            ->where('client_id', (int) $user->id)
            ->latest('id');

        if ($status !== '') {
            $query->where('status', $status);
        }

        $orders = $query->paginate($perPage);

        return $this->ok([
            'orders' => collect($orders->items())->map(fn (Order $order) => $this->orderPayload($order))->values()->all(),
            'meta' => [
                'current_page' => (int) $orders->currentPage(),
                'per_page' => (int) $orders->perPage(),
                'total' => (int) $orders->total(),
                'last_page' => (int) $orders->lastPage(),
            ],
        ]);
    }

    public function activeOrder(Request $request)
    {
        $user = $this->clientUser($request);

        $order = $this->activeOrderForClient((int) $user->id);
        if (!$order) {
            return $this->ok(['order' => null], 'Hakuna oda active kwa sasa.');
        }

        $order->load(['service.category', 'provider.user', 'items.service.category', 'review']);

        return $this->ok([
            'order' => $this->orderPayload($order, true),
        ]);
    }

    public function show(Request $request, Order $order)
    {
        $user = $this->clientUser($request);
        if ((int) $order->client_id !== (int) $user->id) {
            return $this->fail('Huruhusiwi kuona oda hii.', 403);
        }

        $order->load(['service.category', 'provider.user', 'items.service.category', 'review']);

        return $this->ok([
            'order' => $this->orderPayload($order, true),
        ]);
    }

    public function createOrder(
        Request $request,
        QuoteService $quoteService,
        OrderService $orderService,
        OrderNotifier $orderNotifier,
        OrderPaymentService $orderPaymentService
    ) {
        $user = $this->clientUser($request);

        if ($this->activeOrderForClient((int) $user->id)) {
            return $this->fail('Una oda ambayo bado haijakamilika.', 422);
        }

        if (!BookingWindow::isOpenNow()) {
            return $this->fail(BookingWindow::closedMessage(), 422, [
                'booking_window' => [BookingWindow::closedMessage()],
            ]);
        }

        $data = $request->validate([
            'provider_id' => ['required', 'integer', 'exists:providers,id'],
            'primary_service_id' => ['required', 'integer', 'exists:services,id'],
            'service_ids' => ['nullable', 'array', 'max:10'],
            'service_ids.*' => ['integer', 'distinct', 'exists:services,id'],
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'address_text' => ['required', 'string', 'max:255'],
            'include_hair_wash' => ['nullable', 'boolean'],
            'coupon_code' => ['nullable', 'string', 'max:40'],
            'payment_method' => ['nullable', Rule::in(['cash', 'prepay'])],
            'payment_channel' => [
                Rule::requiredIf(fn (): bool => (string) $request->input('payment_method', 'cash') === 'prepay'),
                'nullable',
                Rule::in(['mobile', 'card']),
            ],
            'phone_number' => [
                Rule::requiredIf(fn (): bool => (string) $request->input('payment_method', 'cash') === 'prepay'
                    && (string) $request->input('payment_channel') === 'mobile'),
                'nullable',
                'string',
                'max:30',
            ],
        ], [
            'address_text.required' => 'Andika mtaa au maelezo mafupi ya location yako.',
            'payment_channel.required' => 'Chagua channel ya malipo ya online.',
            'phone_number.required' => 'Weka namba ya simu kwa mobile payment.',
        ]);

        $provider = Provider::query()->find((int) $data['provider_id']);
        if (!$provider || !$quoteService->providerIsBookable($provider)) {
            return $this->fail('Mtoa huduma hayupo tayari kwa sasa.', 422);
        }

        $primaryService = Service::query()
            ->where('is_active', 1)
            ->find((int) $data['primary_service_id']);
        if (!$primaryService) {
            return $this->fail('Huduma uliyochagua haipo kwa sasa.', 404);
        }

        $serviceIds = collect($data['service_ids'] ?? [])
            ->map(fn ($v) => (int) $v)
            ->filter(fn ($v) => $v > 0)
            ->unique()
            ->values();
        if (!$serviceIds->contains((int) $primaryService->id)) {
            $serviceIds->prepend((int) $primaryService->id);
        }

        $includeHairWash = array_key_exists('include_hair_wash', $data)
            ? (bool) $data['include_hair_wash']
            : null;

        $quote = $quoteService->buildQuote(
            $provider,
            $serviceIds,
            (float) $data['lat'],
            (float) $data['lng'],
            $primaryService,
            $includeHairWash
        );

        if (!(bool) ($quote['ok'] ?? false)) {
            return $this->fail((string) ($quote['error'] ?? 'Imeshindikana kukokotoa oda.'), 422);
        }

        $couponCode = strtoupper(trim((string) ($data['coupon_code'] ?? '')));
        $discount = 0.0;
        $couponId = null;

        if ($couponCode !== '') {
            $couponResult = $quoteService->couponDiscountForSubtotal($couponCode, (float) $quote['subtotal']);
            $coupon = $couponResult['coupon'] ?? null;
            $discount = (float) ($couponResult['discount'] ?? 0);
            if (!$coupon) {
                return $this->fail((string) ($couponResult['error'] ?? 'Coupon si sahihi.'), 422);
            }
            $couponId = (int) $coupon->id;
        }

        $rawTotal = max(0, (float) $quote['subtotal'] - $discount);
        $roundedTotal = $quoteService->roundCashAmount($rawTotal);
        $addressText = trim((string) ($data['address_text'] ?? ''));
        $paymentMethod = strtolower(trim((string) ($data['payment_method'] ?? 'cash')));
        $paymentChannel = $paymentMethod === 'prepay'
            ? strtolower(trim((string) ($data['payment_channel'] ?? '')))
            : null;
        $finalTotal = $paymentMethod === 'prepay'
            ? (float) round($rawTotal, 2)
            : (float) $roundedTotal;

        try {
            $order = DB::transaction(function () use (
                $orderService,
                $provider,
                $user,
                $primaryService,
                $data,
                $addressText,
                $quote,
                $discount,
                $couponId,
                $couponCode,
                $finalTotal,
                $paymentMethod,
                $paymentChannel
            ) {
                $lockedProvider = Provider::whereKey((int) $provider->id)->lockForUpdate()->first();
                if (!$lockedProvider) {
                    throw ValidationException::withMessages(['provider' => 'Mtoa huduma hayupo kwa sasa.']);
                }

                if (!$this->providerIsBookable($lockedProvider)) {
                    throw ValidationException::withMessages(['provider' => 'Mtoa huduma hayupo tayari kwa sasa.']);
                }

                if ($this->providerHasActiveOrders((int) $lockedProvider->id)) {
                    throw ValidationException::withMessages(['provider' => 'Mtoa huduma ana oda nyingine inayoendelea.']);
                }

                if ($couponId) {
                    $lockedCoupon = Coupon::whereKey($couponId)->lockForUpdate()->first();
                    if (!$lockedCoupon || !$lockedCoupon->is_active) {
                        throw ValidationException::withMessages(['coupon_code' => 'Coupon haipatikani.']);
                    }

                    if ($lockedCoupon->max_uses !== null && (int) $lockedCoupon->used_count >= (int) $lockedCoupon->max_uses) {
                        throw ValidationException::withMessages(['coupon_code' => 'Coupon imeisha.']);
                    }

                    $lockedCoupon->increment('used_count');
                }

                return $orderService->createOrder([
                    'client_id' => (int) $user->id,
                    'provider_id' => (int) $lockedProvider->id,
                    'service_id' => (int) $primaryService->id,
                    'client_lat' => (float) $data['lat'],
                    'client_lng' => (float) $data['lng'],
                    'address_text' => $addressText !== '' ? $addressText : null,
                    'price_subtotal' => (float) ($quote['subtotal'] ?? 0),
                    'discount_amount' => (float) $discount,
                    'coupon_id' => $couponId,
                    'coupon_code' => $couponId ? $couponCode : null,
                    'payment_method' => $paymentMethod,
                    'payment_channel' => $paymentChannel,
                    'payment_status' => $paymentMethod === 'prepay' ? 'pending' : 'cash_after',
                    'price_total' => (float) $finalTotal,
                    'price_service' => (float) ($quote['sum_service_effective'] ?? $quote['sum_service'] ?? 0),
                    'price_materials' => (float) ($quote['sum_materials'] ?? 0),
                    'price_travel' => (float) ($quote['travel'] ?? 0),
                    'price_usage' => (float) ($quote['sum_usage'] ?? 0),
                    'travel_distance_km' => (float) ($quote['distance_km'] ?? 0),
                    'items' => $quote['items'] ?? [],
                ]);
            });
        } catch (ValidationException $e) {
            return $this->fail('Imeshindikana kuunda oda.', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->fail('Imeshindikana kuunda oda kwa sasa. Jaribu tena.', 500, [
                'error' => [trim((string) $e->getMessage())],
            ]);
        }

        $paymentAction = null;
        if ($paymentMethod === 'prepay') {
            try {
                $paymentAction = $orderPaymentService->startClientPayment(
                    $order,
                    $user,
                    (string) $paymentChannel,
                    (string) ($data['phone_number'] ?? '')
                );
            } catch (\Throwable $e) {
                DB::transaction(function () use ($order): void {
                    $locked = Order::query()->whereKey((int) $order->id)->lockForUpdate()->first();
                    if (!$locked) {
                        return;
                    }
                    if (in_array((string) ($locked->payment_status ?? ''), ['held', 'released', 'refunded'], true)) {
                        return;
                    }
                    $locked->update(['payment_status' => 'failed']);
                });

                $paymentAction = [
                    'error' => trim((string) $e->getMessage()) !== '' ? trim((string) $e->getMessage()) : 'Imeshindikana kuanzisha malipo.',
                ];
            }
        } else {
            try {
                $orderNotifier->notifyCreated($order);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $order->load(['service.category', 'provider.user', 'items.service.category']);

        return $this->ok([
            'order' => $this->orderPayload($order, true),
            'payment_action' => $paymentAction,
        ], $paymentMethod === 'prepay'
            ? 'Oda imeundwa. Kamilisha malipo ili iendelee.'
            : 'Oda imepokelewa.', 201);
    }

    public function setPaymentMode(Request $request, Order $order)
    {
        $user = $this->clientUser($request);
        if ((int) $order->client_id !== (int) $user->id) {
            return $this->fail('Huruhusiwi kufanya tendo hili.', 403);
        }

        $data = $request->validate([
            'payment_method' => ['required', Rule::in(['cash', 'prepay'])],
            'payment_channel' => [
                Rule::requiredIf(fn (): bool => (string) $request->input('payment_method') === 'prepay'),
                'nullable',
                Rule::in(['mobile', 'card']),
            ],
        ]);

        if (in_array((string) $order->status, ['completed', 'cancelled'], true)) {
            return $this->fail('Huwezi kubadili malipo kwa oda iliyofungwa.', 422);
        }

        if (in_array((string) ($order->payment_status ?? ''), ['held', 'released', 'refunded'], true)) {
            return $this->fail('Malipo tayari yamekamilika kwenye oda hii.', 422);
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

        return $this->ok([], $paymentMethod === 'prepay'
            ? 'Malipo yamewekwa online. Endesha payment/start kukamilisha.'
            : 'Malipo yamewekwa kuwa cash.');
    }

    public function startPayment(Request $request, Order $order, OrderPaymentService $orderPaymentService)
    {
        $user = $this->clientUser($request);
        if ((int) $order->client_id !== (int) $user->id) {
            return $this->fail('Huruhusiwi kufanya tendo hili.', 403);
        }

        $data = $request->validate([
            'payment_channel' => ['nullable', Rule::in(['mobile', 'card'])],
            'phone_number' => ['nullable', 'string', 'max:30'],
        ]);

        $channel = strtolower(trim((string) ($data['payment_channel'] ?? $order->payment_channel ?? '')));
        if ($channel === '') {
            return $this->fail('Chagua payment channel kwanza.', 422);
        }

        if ((string) ($order->payment_method ?? '') !== 'prepay') {
            return $this->fail('Oda hii haijawekwa kwa online payment.', 422);
        }

        try {
            $payment = $orderPaymentService->startClientPayment(
                $order,
                $user,
                $channel,
                (string) ($data['phone_number'] ?? '')
            );
        } catch (\Throwable $e) {
            return $this->fail(trim((string) $e->getMessage()) ?: 'Imeshindikana kuanzisha malipo.', 422);
        }

        $fresh = Order::query()->with(['service.category', 'provider.user', 'items.service.category'])->find((int) $order->id);

        return $this->ok([
            'order' => $fresh ? $this->orderPayload($fresh, true) : null,
            'payment' => $payment,
        ], 'Ombi la malipo limetumwa.');
    }

    public function refreshPayment(Request $request, Order $order, OrderPaymentService $orderPaymentService)
    {
        $user = $this->clientUser($request);
        if ((int) $order->client_id !== (int) $user->id) {
            return $this->fail('Huruhusiwi kufanya tendo hili.', 403);
        }

        try {
            $payment = $orderPaymentService->refreshClientPayment($order, $user);
        } catch (\Throwable $e) {
            return $this->fail(trim((string) $e->getMessage()) ?: 'Imeshindikana ku-refresh malipo.', 422);
        }

        $fresh = Order::query()->with(['service.category', 'provider.user', 'items.service.category'])->find((int) $order->id);

        return $this->ok([
            'order' => $fresh ? $this->orderPayload($fresh, true) : null,
            'payment' => $payment,
        ], 'Payment status ime-refresh.');
    }

    public function cancelOrder(Request $request, Order $order, SnippePay $snippePay)
    {
        $user = $this->clientUser($request);
        if ((int) $order->client_id !== (int) $user->id) {
            return $this->fail('Huruhusiwi kufanya tendo hili.', 403);
        }

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'reason.required' => 'Weka sababu ya kusitisha oda.',
            'reason.min' => 'Sababu iwe na angalau herufi 5.',
        ]);
        $cancelReason = trim((string) ($data['reason'] ?? ''));

        if (in_array((string) $order->status, ['completed', 'cancelled'], true)) {
            return $this->fail('Oda hii tayari imekamilika au imeghairiwa.', 422);
        }

        if (!in_array((string) $order->status, ['pending', 'accepted', 'on_the_way'], true)) {
            return $this->fail('Kwa sasa huwezi ku-cancel oda hii.', 422);
        }

        $refundNeeded = false;

        DB::transaction(function () use ($order, &$refundNeeded, $cancelReason) {
            $locked = Order::whereKey((int) $order->id)->lockForUpdate()->firstOrFail();
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

            if (in_array($prevStatus, ['pending', 'accepted', 'on_the_way'], true)) {
                $provider = Provider::whereKey((int) $locked->provider_id)->lockForUpdate()->first();
                if (!$provider) {
                    return;
                }

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
                        'provider_id' => (int) $provider->id,
                        'type' => 'commission_credit',
                        'order_id' => (int) $locked->id,
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
        });

        if ($refundNeeded && Schema::hasColumn('orders', 'refund_reference')) {
            $fresh = Order::whereKey((int) $order->id)->first();
            $alreadyHasRefund = $fresh && trim((string) ($fresh->refund_reference ?? '')) !== '';

            if ($fresh && !$alreadyHasRefund) {
                $msisdn = Phone::normalizeTzMsisdn((string) ($user->phone ?? ''));
                if ($msisdn === null) {
                    DB::transaction(function () use ($fresh) {
                        $locked = Order::whereKey((int) $fresh->id)->lockForUpdate()->first();
                        if ($locked) {
                            $locked->update(['payment_status' => 'refund_failed']);
                        }
                    });

                    return $this->fail('Imeshindikana kuanzisha refund: namba ya simu haipo/sio sahihi.', 422);
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
                            $locked = Order::whereKey((int) $fresh->id)->lockForUpdate()->first();
                            if ($locked) {
                                $locked->update(['refund_reference' => $ref]);
                            }
                        });
                    } catch (\Throwable $e) {
                        DB::transaction(function () use ($fresh) {
                            $locked = Order::whereKey((int) $fresh->id)->lockForUpdate()->first();
                            if ($locked) {
                                $locked->update(['payment_status' => 'refund_failed']);
                            }
                        });

                        return $this->fail('Imeshindikana kuanzisha refund. Jaribu tena au wasiliana na support.', 500);
                    }
                }
            }
        }

        $cancelled = Order::query()->with(['service.category', 'provider.user'])->find((int) $order->id);

        return $this->ok([
            'order' => $cancelled ? $this->orderPayload($cancelled) : null,
        ], 'Oda imeghairiwa.');
    }

    public function confirmArrival(Request $request, Order $order)
    {
        $user = $this->clientUser($request);
        if ((int) $order->client_id !== (int) $user->id) {
            return $this->fail('Huruhusiwi kufanya tendo hili.', 403);
        }

        if (!Schema::hasColumn('orders', 'client_arrival_confirmed_at') || !Schema::hasColumn('orders', 'provider_arrived_at')) {
            return $this->fail('Feature ya arrival confirmation haipo kwenye database yako.', 422);
        }

        $result = DB::transaction(function () use ($order) {
            $locked = Order::whereKey((int) $order->id)->lockForUpdate()->firstOrFail();

            if (in_array((string) $locked->status, ['completed', 'cancelled'], true)) {
                return ['error' => 'Oda hii tayari imekamilika au imeghairiwa.'];
            }

            if ($locked->provider_arrived_at === null) {
                return ['error' => 'Bado mtoa huduma hajaonyesha kuwa amefika.'];
            }

            if ($locked->client_arrival_confirmed_at !== null) {
                return ['message' => 'Arrival tayari imethibitishwa.'];
            }

            $updates = ['client_arrival_confirmed_at' => now()];
            if ((string) $locked->status === 'accepted') {
                $updates['status'] = 'on_the_way';
            }

            $locked->update($updates);
            return ['message' => 'Ume-thibitisha kuwa mtoa huduma amefika.'];
        });

        if (!empty($result['error'])) {
            return $this->fail((string) $result['error'], 422);
        }

        $fresh = Order::query()->with(['service.category', 'provider.user'])->find((int) $order->id);

        return $this->ok([
            'order' => $fresh ? $this->orderPayload($fresh) : null,
        ], (string) ($result['message'] ?? 'Imefanikiwa.'));
    }

    public function review(Request $request, Order $order)
    {
        $user = $this->clientUser($request);
        if ((int) $order->client_id !== (int) $user->id) {
            return $this->fail('Huruhusiwi kufanya tendo hili.', 403);
        }

        if (!Schema::hasTable('reviews')) {
            return $this->fail('Reviews table haipo. Fanya migrate.', 422);
        }

        if ((string) $order->status !== 'completed') {
            return $this->fail('Unaweza kuweka review baada ya oda kukamilika.', 422);
        }

        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $exists = Review::query()->where('order_id', (int) $order->id)->exists();
        if ($exists) {
            $review = Review::query()->where('order_id', (int) $order->id)->first();
            return $this->ok([
                'review' => $review,
            ], 'Review yako tayari imeshatumwa.');
        }

        $review = DB::transaction(function () use ($order, $user, $data) {
            $locked = Order::whereKey((int) $order->id)->lockForUpdate()->firstOrFail();
            if ((string) $locked->status !== 'completed') {
                throw ValidationException::withMessages([
                    'order' => 'Order not completed.',
                ]);
            }

            $already = Review::query()->where('order_id', (int) $locked->id)->lockForUpdate()->first();
            if ($already) {
                return $already;
            }

            $created = Review::create([
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

            return $created;
        });

        return $this->ok([
            'review' => $review,
        ], 'Asante! Review imetumwa.');
    }

    public function updateOrderServices(Request $request, Order $order, QuoteService $quoteService)
    {
        $user = $this->clientUser($request);
        if ((int) $order->client_id !== (int) $user->id) {
            return $this->fail('Huruhusiwi kufanya tendo hili.', 403);
        }

        $createdAt = $order->created_at;
        if (!$createdAt || now()->gt($createdAt->copy()->addMinutes(2))) {
            return $this->fail('Muda wa kubadilisha huduma umeisha (dakika 2).', 422);
        }

        if ((string) $order->status !== 'pending') {
            return $this->fail('Huwezi kubadilisha huduma baada ya oda kukubaliwa.', 422);
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
            return $this->fail('Chagua angalau huduma 1.', 422);
        }

        $provider = Provider::query()->find((int) $order->provider_id);
        if (!$provider) {
            return $this->fail('Mtoa huduma hayupo kwa sasa.', 422);
        }

        $primaryService = null;
        if ((int) ($order->service_id ?? 0) > 0) {
            $primaryService = Service::with('category')->find((int) $order->service_id);
        }

        $includeHairWash = array_key_exists('include_hair_wash', $data)
            ? (bool) $data['include_hair_wash']
            : null;

        $quote = $quoteService->buildQuote(
            $provider,
            $serviceIds,
            (float) $order->client_lat,
            (float) $order->client_lng,
            $primaryService,
            $includeHairWash
        );
        if (!(bool) ($quote['ok'] ?? false)) {
            return $this->fail((string) ($quote['error'] ?? 'Imeshindikana kuendelea.'), 422);
        }

        $subtotal = (float) ($quote['subtotal'] ?? 0);
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
        $total = $quoteService->roundCashAmount($rawTotal);

        $commissionPercent = (float) config('glamo_pricing.commission_percent', 10);
        if ($commissionPercent < 0) {
            $commissionPercent = 0;
        }
        $commissionRate = round($commissionPercent / 100, 4);
        $commissionAmount = round($total * $commissionRate, 2);
        $payoutAmount = round(max(0, $total - $commissionAmount), 2);

        try {
            DB::transaction(function () use ($order, $serviceIds, $quote, $subtotal, $discount, $total, $commissionRate, $commissionAmount, $payoutAmount) {
                $locked = Order::whereKey((int) $order->id)->lockForUpdate()->firstOrFail();
                if ((string) $locked->status !== 'pending') {
                    throw new \RuntimeException('Order cannot be edited now.');
                }

                $primaryServiceId = (int) $serviceIds->first();
                $update = [
                    'service_id' => $primaryServiceId,
                    'price_subtotal' => $subtotal,
                    'discount_amount' => $discount,
                    'price_total' => $total,
                    'price_service' => (float) ($quote['sum_service_effective'] ?? $quote['sum_service'] ?? 0),
                    'price_materials' => (float) ($quote['sum_materials'] ?? 0),
                    'price_travel' => (float) ($quote['travel'] ?? 0),
                    'price_usage' => (float) ($quote['sum_usage'] ?? 0),
                    'travel_distance_km' => (float) ($quote['distance_km'] ?? 0),
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
            return $this->fail('Imeshindikana kubadilisha huduma. Jaribu tena.', 500);
        }

        $fresh = Order::query()->with(['service.category', 'provider.user', 'items.service.category'])->find((int) $order->id);

        return $this->ok([
            'order' => $fresh ? $this->orderPayload($fresh, true) : null,
        ], 'Huduma zimebadilishwa.');
    }

    private function clientUser(Request $request): User
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthorized.');
        abort_unless((string) ($user->role ?? '') === 'client', 403, 'Forbidden.');
        return $user;
    }

    private function activeOrderForClient(int $clientId): ?Order
    {
        return Order::query()
            ->where('client_id', $clientId)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->orderByDesc('id')
            ->first();
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

    private function providerHasActiveOrders(int $providerId, ?int $excludeOrderId = null): bool
    {
        $query = Order::query()
            ->where('provider_id', $providerId)
            ->whereNotIn('status', ['completed', 'cancelled', 'suspended']);

        if ($excludeOrderId !== null && $excludeOrderId > 0) {
            $query->where('id', '!=', $excludeOrderId);
        }

        return $query->exists();
    }

    private function orderPayload(Order $order, bool $withItems = false): array
    {
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
            'client_location' => [
                'lat' => is_numeric($order->client_lat) ? (float) $order->client_lat : null,
                'lng' => is_numeric($order->client_lng) ? (float) $order->client_lng : null,
            ],
            'service' => [
                'id' => (int) data_get($order, 'service.id', $order->service_id),
                'name' => (string) data_get($order, 'service.name', ''),
                'slug' => (string) data_get($order, 'service.slug', ''),
                'category' => [
                    'id' => (int) data_get($order, 'service.category.id', 0),
                    'name' => (string) data_get($order, 'service.category.name', ''),
                    'slug' => (string) data_get($order, 'service.category.slug', ''),
                ],
            ],
            'provider' => [
                'id' => (int) data_get($order, 'provider.id', 0),
                'display_name' => (string) data_get($order, 'provider.display_name', ''),
                'phone' => (string) (data_get($order, 'provider.phone_public') ?: data_get($order, 'provider.user.phone') ?: ''),
                'online_status' => (string) data_get($order, 'provider.online_status', ''),
                'profile_image_url' => (string) data_get($order, 'provider.profile_image_url', ''),
                'lat' => is_numeric(data_get($order, 'provider.current_lat')) ? (float) data_get($order, 'provider.current_lat') : null,
                'lng' => is_numeric(data_get($order, 'provider.current_lng')) ? (float) data_get($order, 'provider.current_lng') : null,
                'last_location_at' => optional(data_get($order, 'provider.last_location_at'))->toIso8601String(),
            ],
            'payment' => [
                'method' => (string) ($order->payment_method ?? ''),
                'channel' => (string) ($order->payment_channel ?? ''),
                'status' => (string) ($order->payment_status ?? ''),
                'provider' => (string) ($order->payment_provider ?? ''),
                'reference' => (string) ($order->payment_reference ?? ''),
                'refund_reference' => (string) ($order->refund_reference ?? ''),
                'refund_reason' => (string) ($order->refund_reason ?? ''),
            ],
            'cancellation_reason' => (string) ($order->cancellation_reason ?? ''),
            'price' => [
                'subtotal' => $order->price_subtotal !== null ? (float) $order->price_subtotal : null,
                'discount' => $order->discount_amount !== null ? (float) $order->discount_amount : null,
                'service' => $order->price_service !== null ? (float) $order->price_service : null,
                'materials' => $order->price_materials !== null ? (float) $order->price_materials : null,
                'travel' => $order->price_travel !== null ? (float) $order->price_travel : null,
                'usage' => $order->price_usage !== null ? (float) $order->price_usage : null,
                'distance_km' => $order->travel_distance_km !== null ? (float) $order->travel_distance_km : null,
                'total' => (float) ($order->price_total ?? 0),
                'commission_rate' => (float) ($order->commission_rate ?? 0),
                'commission_amount' => (float) ($order->commission_amount ?? 0),
                'payout_amount' => $order->payout_amount !== null ? (float) $order->payout_amount : null,
                'coupon_id' => $order->coupon_id ? (int) $order->coupon_id : null,
                'coupon_code' => $order->coupon_code ?: null,
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
                    'category_slug' => (string) data_get($item, 'service.category.slug', ''),
                    'price_service' => (float) ($item->price_service ?? 0),
                    'price_materials' => (float) ($item->price_materials ?? 0),
                    'price_usage' => (float) ($item->price_usage ?? 0),
                    'usage_percent' => (float) ($item->usage_percent ?? 0),
                    'line_total' => (float) ($item->line_total ?? 0),
                ];
            })->values()->all();
        }

        return $payload;
    }
}
