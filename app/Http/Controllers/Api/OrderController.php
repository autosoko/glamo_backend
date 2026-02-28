<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Provider;
use App\Models\ProviderLedger;
use App\Models\Review;
use App\Models\Service;
use App\Services\OrderNotifier;
use App\Services\OrderService;
use App\Support\BookingWindow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class OrderController extends Controller
{
    public function create(Request $request, OrderService $orderService, OrderNotifier $orderNotifier)
    {
        $user = $request->user();
        abort_unless($user->role === 'client', 403);

        if (!BookingWindow::isOpenNow()) {
            return response()->json([
                'message' => BookingWindow::closedMessage(),
            ], 422);
        }

        $data = $request->validate([
            'provider_id' => ['required','exists:providers,id'],
            'service_id' => ['required','exists:services,id'],
            'client_lat' => ['required','numeric','between:-90,90'],
            'client_lng' => ['required','numeric','between:-180,180'],
            'address_text' => ['nullable','string','max:255'],
        ]);

        $provider = Provider::findOrFail($data['provider_id']);
        $debtBlock = (float) config('glamo_pricing.provider_debt_block_threshold', 10000);
        if ($debtBlock <= 0) {
            $debtBlock = 10000;
        }

        if ($provider->approval_status !== 'approved' || $provider->online_status !== 'online' || (float) $provider->debt_balance > $debtBlock) {
            return response()->json(['message' => 'Provider not available.'], 422);
        }

        $service = Service::findOrFail($data['service_id']);

        // provider price override if exists
        $pivotRow = $provider->services()->where('services.id', $service->id)->first();
        $price = $pivotRow?->pivot?->price_override ?? $service->base_price;

        $order = $orderService->createOrder([
            'client_id' => $user->id,
            'provider_id' => $provider->id,
            'service_id' => $service->id,
            'client_lat' => $data['client_lat'],
            'client_lng' => $data['client_lng'],
            'address_text' => $data['address_text'] ?? null,
            'price_total' => $price,
        ]);

        try {
            $orderNotifier->notifyCreated($order);
        } catch (\Throwable $e) {
            Log::warning('API order notify failed', [
                'order_id' => (int) $order->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Optional: notifications/events (weka baadae kama umefika hatua hiyo)
        // $order->load(['service','client','provider.user']);
        // $order->provider->user->notify(new \App\Notifications\ProviderNewOrder($order));
        // event(new \App\Events\OrderCreated($order));

        return response()->json(['order' => $order], 201);
    }

    public function accept(Request $request, Order $order, OrderService $orderService)
    {
        $user = $request->user();
        abort_unless($user->role === 'provider', 403);

        $provider = $user->provider;
        abort_unless($provider, 422);

        $order = $orderService->acceptOrder($order, $provider);

        // Optional notify client/event
        // $order->load(['service','client','provider.user']);
        // $order->client->notify(new \App\Notifications\ClientOrderAccepted($order));
        // event(new \App\Events\OrderAccepted($order));

        return response()->json([
            'message' => 'Order accepted.',
            'order' => $order,
        ]);
    }

    public function reject(Request $request, Order $order)
    {
        $user = $request->user();
        abort_unless((string) ($user->role ?? '') === 'provider', 403);

        $provider = $user->provider;
        abort_unless($provider, 422);

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $order = DB::transaction(function () use ($order, $provider, $data) {
            $lockedOrder = Order::whereKey((int) $order->id)->lockForUpdate()->firstOrFail();
            $lockedProvider = Provider::whereKey((int) $provider->id)->lockForUpdate()->firstOrFail();

            if ((int) $lockedOrder->provider_id !== (int) $lockedProvider->id) {
                abort(403, 'Not your order.');
            }

            if (in_array((string) $lockedOrder->status, ['completed', 'cancelled'], true)) {
                abort(422, 'Order cannot be rejected now.');
            }

            $previousStatus = (string) $lockedOrder->status;

            $updates = [
                'status' => 'cancelled',
            ];

            if (Schema::hasColumn('orders', 'payment_status') && (string) ($lockedOrder->payment_method ?? '') === 'prepay') {
                $updates['payment_status'] = (string) ($lockedOrder->payment_status ?? '') === 'held'
                    ? 'refund_pending'
                    : 'cancelled';
            }

            $reason = trim((string) ($data['reason'] ?? ''));
            if ($reason !== '' && Schema::hasColumn('orders', 'completion_note')) {
                $updates['completion_note'] = 'Provider rejected: ' . $reason;
            }

            $lockedOrder->update($updates);

            $method = (string) ($lockedOrder->payment_method ?? '');
            $isCash = $method === '' || $method === 'cash';

            if ($isCash && in_array($previousStatus, ['accepted', 'on_the_way'], true)) {
                $commission = (float) ($lockedOrder->commission_amount ?? 0);
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

            $hasOtherActive = Order::query()
                ->where('provider_id', (int) $lockedProvider->id)
                ->where('id', '!=', (int) $lockedOrder->id)
                ->whereNotIn('status', ['completed', 'cancelled', 'suspended'])
                ->exists();

            $debtBlock = (float) config('glamo_pricing.provider_debt_block_threshold', 10000);
            if ($debtBlock <= 0) {
                $debtBlock = 10000;
            }

            if ($hasOtherActive) {
                $lockedProvider->update([
                    'online_status' => 'offline',
                    'offline_reason' => 'Ana oda nyingine inayoendelea.',
                ]);
            } else {
                $debt = max(0, (float) ($lockedProvider->debt_balance ?? 0));
                $isDebtBlocked = $debt > $debtBlock
                    || ((string) ($lockedProvider->online_status ?? '') === 'blocked_debt' && $debt >= $debtBlock);

                $lockedProvider->update([
                    'online_status' => $isDebtBlocked ? 'blocked_debt' : 'offline',
                    'offline_reason' => $isDebtBlocked
                        ? ('Debt over ' . number_format($debtBlock, 0) . '. Please pay.')
                        : null,
                ]);
            }

            return $lockedOrder->fresh();
        });

        return response()->json([
            'message' => 'Order rejected.',
            'order' => $order,
        ]);
    }

    public function markOnTheWay(Request $request, Order $order)
    {
        $user = $request->user();
        abort_unless((string) ($user->role ?? '') === 'provider', 403);

        $provider = $user->provider;
        abort_unless($provider, 422);

        $order = DB::transaction(function () use ($order, $provider) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

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
            return $locked->fresh();
        });

        return response()->json([
            'message' => 'ok',
            'order' => $order,
        ]);
    }

    public function markArrived(Request $request, Order $order)
    {
        $user = $request->user();
        abort_unless((string) ($user->role ?? '') === 'provider', 403);

        $provider = $user->provider;
        abort_unless($provider, 422);

        $order = DB::transaction(function () use ($order, $provider) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

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

            return $locked->fresh();
        });

        return response()->json([
            'message' => 'ok',
            'order' => $order,
        ]);
    }

    public function clientConfirmArrival(Request $request, Order $order)
    {
        $user = $request->user();
        abort_unless((string) ($user->role ?? '') === 'client', 403);
        abort_unless((int) $order->client_id === (int) $user->id, 403);

        $order = DB::transaction(function () use ($order) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if (!Schema::hasColumn('orders', 'client_arrival_confirmed_at') || !Schema::hasColumn('orders', 'provider_arrived_at')) {
                abort(422, 'Arrival tracking not enabled.');
            }

            if (in_array((string) $locked->status, ['completed', 'cancelled'], true)) {
                abort(422, 'Order is finished.');
            }

            if ($locked->provider_arrived_at === null) {
                abort(422, 'Provider has not marked arrived.');
            }

            if ($locked->client_arrival_confirmed_at !== null) {
                return $locked->fresh();
            }

            $updates = [
                'client_arrival_confirmed_at' => now(),
            ];
            if ((string) $locked->status === 'accepted') {
                $updates['status'] = 'on_the_way';
            }

            $locked->update($updates);

            return $locked->fresh();
        });

        return response()->json([
            'message' => 'ok',
            'order' => $order,
        ]);
    }

    public function review(Request $request, Order $order)
    {
        $user = $request->user();
        abort_unless((string) ($user->role ?? '') === 'client', 403);
        abort_unless((int) $order->client_id === (int) $user->id, 403);

        abort_unless(Schema::hasTable('reviews'), 422);

        if ((string) $order->status !== 'completed') {
            return response()->json(['message' => 'Order not completed.'], 422);
        }

        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $review = DB::transaction(function () use ($order, $user, $data) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ((string) $locked->status !== 'completed') {
                abort(422, 'Order not completed.');
            }

            $existing = Review::where('order_id', (int) $locked->id)->lockForUpdate()->first();
            if ($existing) {
                return $existing;
            }

            $review = Review::create([
                'order_id' => (int) $locked->id,
                'provider_id' => (int) $locked->provider_id,
                'client_id' => (int) $user->id,
                'rating' => (int) $data['rating'],
                'comment' => $data['comment'] ?? null,
            ]);

            $avg = Review::where('provider_id', (int) $locked->provider_id)->avg('rating');
            Provider::whereKey((int) $locked->provider_id)->update([
                'rating_avg' => $avg !== null ? round((float) $avg, 2) : null,
            ]);

            return $review;
        });

        return response()->json([
            'message' => 'ok',
            'review' => $review,
        ]);
    }

    public function complete(Request $request, Order $order, OrderService $orderService)
    {
        $user = $request->user();
        abort_unless($user->role === 'provider', 403);

        $data = $request->validate([
            'note' => ['nullable','string','max:500'],
        ]);

        $provider = $user->provider;
        abort_unless($provider, 422);

        $order = $orderService->completeOrder($order, $provider, $data['note'] ?? null);

        // Optional notify client
        // $order->load(['client']);
        // $order->client->notify(new \App\Notifications\ClientOrderCompleted($order));

        return response()->json([
            'message' => 'Order completed.',
            'order' => $order,
        ]);
    }

    public function clientIndex(Request $request)
    {
        $user = $request->user();
        abort_unless($user->role === 'client', 403);

        $orders = Order::with(['service','provider.user'])
            ->where('client_id', $user->id)
            ->latest()
            ->paginate(20);

        return response()->json($orders);
    }

    public function providerIndex(Request $request)
    {
        $user = $request->user();
        abort_unless($user->role === 'provider', 403);

        $provider = $user->provider;
        abort_unless($provider, 422);

        $orders = Order::with(['service','client'])
            ->where('provider_id', $provider->id)
            ->latest()
            ->paginate(20);

        return response()->json($orders);
    }

    public function track(Request $request, Order $order)
    {
        $user = $request->user();

        $isClient = (int) $order->client_id === (int) $user->id;
        $isProviderUser = $order->provider && (int) $order->provider->user_id === (int) $user->id;
        abort_unless($isClient || $isProviderUser, 403);

        $order->load(['service','client','provider.user']);

        return response()->json([
            'order' => [
                'id' => $order->id,
                'order_no' => $order->order_no,
                'status' => $order->status,
                'price_total' => (float) $order->price_total,
                'service' => $order->service?->name,
                'client' => [
                    'phone' => $order->client?->phone,
                    'lat' => (float) $order->client_lat,
                    'lng' => (float) $order->client_lng,
                ],
                'provider' => [
                    'phone' => $order->provider?->phone_public ?? $order->provider?->user?->phone,
                    'lat' => (float) ($order->provider?->current_lat ?? 0),
                    'lng' => (float) ($order->provider?->current_lng ?? 0),
                    'online_status' => $order->provider?->online_status,
                ],
                'accepted_at' => $order->accepted_at,
                'completed_at' => $order->completed_at,
            ],
        ]);
    }

    public function pushLocation(Request $request, Order $order)
    {
        $data = $request->validate([
            'lat' => ['required','numeric','between:-90,90'],
            'lng' => ['required','numeric','between:-180,180'],
        ]);

        $user = $request->user();

        $isClient = (int) $order->client_id === (int) $user->id;
        $isProviderUser = $order->provider && (int) $order->provider->user_id === (int) $user->id;
        abort_unless($isClient || $isProviderUser, 403);

        if ($isClient) {
            $order->update(['client_lat' => $data['lat'], 'client_lng' => $data['lng']]);
        } else {
            $order->provider->update([
                'current_lat' => $data['lat'],
                'current_lng' => $data['lng'],
                'last_location_at' => now(),
            ]);
        }

        // Optional broadcast
        // event(new \App\Events\LocationUpdated($order->id, $isClient ? 'client' : 'provider', (float)$data['lat'], (float)$data['lng'], time()));

        return response()->json(['message' => 'ok']);
    }
}
