<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Order;
use App\Models\Provider;
use App\Models\ProviderLedger;
use App\Models\ProviderPayment;
use App\Models\ProviderWalletLedger;
use App\Models\User;
use App\Services\OrderService;
use App\Models\Service;
use App\Services\ProviderWalletService;
use App\Services\SnippePay;
use App\Support\BusinessNickname;
use App\Support\Phone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProviderDashboardController extends Controller
{
    public function index(Request $request)
    {
        [$user, $provider] = $this->resolveProvider($request);
        $this->refreshProviderAvailability($provider);
        $provider->refresh();

        $approvalStatus = (string) ($provider->approval_status ?? 'pending');
        $onboardingComplete = $provider->onboarding_completed_at !== null || $approvalStatus === 'approved';

        if (!$onboardingComplete) {
            return redirect()
                ->route('provider.onboarding')
                ->with('error', 'Kamilisha taarifa zako kwanza kabla ya kuendelea.');
        }

        $debtBlock = (float) config('glamo_pricing.provider_debt_block_threshold', 10000);
        if ($debtBlock <= 0) {
            $debtBlock = 10000;
        }
        $hasBlockingOrders = $this->providerHasBlockingOrders((int) $provider->id);
        $availabilityControl = $this->availabilityControlState($provider, $hasBlockingOrders, $debtBlock);

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
                ->get(['id', 'price_total', 'commission_amount', 'payout_amount']);

            foreach ($pendingOrders as $o) {
                $payout = (float) ($o->payout_amount ?? 0);
                if ($payout <= 0) {
                    $payout = max(0, (float) ($o->price_total ?? 0) - (float) ($o->commission_amount ?? 0));
                }
                $pendingEscrow += $payout;
            }
        }

        $orders = Order::with(['service', 'client'])
            ->where('provider_id', (int) $provider->id)
            ->latest()
            ->limit(25)
            ->get();

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
        $dashboardPopupOrder = $this->resolveDashboardPopupOrder((int) $provider->id);
        $activeActionOrder = $this->resolveActiveActionOrder((int) $provider->id);

        $selectedSkills = collect($provider->selected_skills ?? [])
            ->map(fn ($s) => strtolower(trim((string) $s)))
            ->filter()
            ->unique()
            ->values();

        $skillOptions = Category::query()
            ->where('is_active', 1)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('name', 'slug')
            ->mapWithKeys(fn ($name, $slug) => [strtolower(trim((string) $slug)) => (string) $name])
            ->all();

        $servicesQuery = Service::query()
            ->with([
                'category:id,name,slug',
                'media' => fn ($q) => $q->orderBy('sort_order'),
            ])
            ->where('is_active', 1)
            ->orderBy('sort_order');

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

        return view('public.provider-dashboard', [
            'provider' => $provider,
            'walletBalance' => $walletBalance,
            'pendingEscrow' => $pendingEscrow,
            'debtBlock' => $debtBlock,
            'orders' => $orders,
            'debtLedgers' => $debtLedgers,
            'walletLedgers' => $walletLedgers,
            'debtPayments' => $debtPayments,
            'totalEarn' => $totalEarn,
            'dashboardPopupOrder' => $dashboardPopupOrder,
            'activeActionOrder' => $activeActionOrder,
            'approvalStatus' => $approvalStatus,
            'selectedSkills' => $selectedSkills,
            'skillOptions' => $skillOptions,
            'allowedServices' => $allowedServices,
            'activeServiceIds' => $activeServiceIds,
            'canOperate' => $approvalStatus === 'approved',
            'availabilityControl' => $availabilityControl,
        ]);
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

        $data = $request->validate($rules, [
            'phone_public.required' => 'Weka namba ya simu ya kazi.',
            'selected_skills.array' => 'Ujuzi lazima uwe kwenye mfumo sahihi.',
        ]);

        $mainPhone = Phone::normalizeTzMsisdn((string) ($data['phone_public'] ?? ''));
        if ($mainPhone === null) {
            return redirect()->route('provider.dashboard')
                ->with('error', 'Namba ya simu ya kazi si sahihi. Tumia 07XXXXXXXX au 2557XXXXXXXX.');
        }

        $altPhoneRaw = trim((string) ($data['alt_phone'] ?? ''));
        $altPhone = null;
        if ($altPhoneRaw !== '') {
            $altPhone = Phone::normalizeTzMsisdn($altPhoneRaw);
            if ($altPhone === null) {
                return redirect()->route('provider.dashboard')
                    ->with('error', 'Namba ya simu mbadala si sahihi.');
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

            return redirect()
                ->route('provider.dashboard')
                ->withErrors(['business_nickname' => $message])
                ->withInput();
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
                    throw new \RuntimeException('Namba hii ya simu tayari inatumika kwenye akaunti nyingine.');
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
            return redirect()->route('provider.dashboard')
                ->with('error', $this->orderActionError($e, 'Imeshindikana kusasisha profile kwa sasa. Jaribu tena.'));
        }

        return redirect()->route('provider.dashboard')
            ->with('success', 'Profile yako imeboreshwa kwa mafanikio.');
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
            DB::transaction(function () use ($provider, $targetStatus, $debtBlock) {
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
            });
        } catch (ValidationException $e) {
            return redirect()
                ->route('provider.dashboard')
                ->withErrors($e->errors());
        } catch (\Throwable $e) {
            return redirect()
                ->route('provider.dashboard')
                ->with('error', 'Imeshindikana kubadili status ya online/offline. Jaribu tena.');
        }

        return redirect()
            ->route('provider.dashboard')
            ->with('success', $targetStatus === 'online'
                ? 'Umejiweka online.'
                : 'Umejiweka offline.');
    }

    public function approveOrder(Request $request, Order $order, OrderService $orderService)
    {
        [, $provider] = $this->resolveProvider($request);

        if ((int) $order->provider_id !== (int) $provider->id) {
            abort(403);
        }

        if ((string) ($provider->approval_status ?? '') !== 'approved') {
            return redirect()->route('provider.dashboard')->with('error', 'Akaunti yako bado haijapitishwa kikamilifu.');
        }

        $data = $request->validate([
            'approve_mode' => ['required', Rule::in(['now', 'later'])],
            'scheduled_for' => ['nullable', 'date', 'after:now'],
        ], [
            'approve_mode.required' => 'Chagua kama unaenda sasa au unaenda baadaye.',
            'approve_mode.in' => 'Chaguo la approval si sahihi.',
            'scheduled_for.date' => 'Tarehe/saa ya ratiba si sahihi.',
            'scheduled_for.after' => 'Ratiba ya kwenda baadaye lazima iwe muda ujao.',
        ]);

        $approveMode = (string) ($data['approve_mode'] ?? 'now');
        $goNow = $approveMode !== 'later';
        $scheduledFor = null;

        if (!$goNow) {
            $rawSchedule = trim((string) ($data['scheduled_for'] ?? ''));
            if ($rawSchedule === '') {
                return redirect()
                    ->route('provider.dashboard')
                    ->withErrors(['scheduled_for' => 'Chagua tarehe na saa ya kwenda kwa mteja.'])
                    ->withInput();
            }

            try {
                $scheduledFor = \Illuminate\Support\Carbon::parse($rawSchedule);
            } catch (\Throwable $e) {
                return redirect()
                    ->route('provider.dashboard')
                    ->withErrors(['scheduled_for' => 'Ratiba uliyochagua si sahihi.'])
                    ->withInput();
            }
        }

        try {
            $acceptedOrder = $orderService->acceptOrder($order, $provider, $goNow, $scheduledFor);

            if (!$goNow) {
                $providerFresh = Provider::query()->find((int) $provider->id);
                if ($providerFresh) {
                    $this->refreshProviderAvailability($providerFresh, (int) $acceptedOrder->id, true);
                }
            }
        } catch (\Throwable $e) {
            return redirect()->route('provider.dashboard')
                ->with('error', $this->orderActionError($e, 'Imeshindikana kukubali oda hii kwa sasa.'));
        }

        if ($goNow) {
            return redirect()
                ->route('provider.dashboard')
                ->with('success', 'Oda imekubaliwa. Status ya mteja sasa ni: Yuko njiani anakuja...');
        }

        return redirect()
            ->route('provider.dashboard')
            ->with('success', 'Oda imekubaliwa kwa ratiba. Utakwenda tarehe ' . $scheduledFor->format('d/m/Y H:i') . '.');
    }

    public function rejectOrder(Request $request, Order $order)
    {
        [, $provider] = $this->resolveProvider($request);

        if ((int) $order->provider_id !== (int) $provider->id) {
            abort(403);
        }

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
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

                $updates = [
                    'status' => 'cancelled',
                ];

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
                if ($reason !== '' && Schema::hasColumn('orders', 'completion_note')) {
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
            return redirect()->route('provider.dashboard')
                ->with('error', $this->orderActionError($e, 'Imeshindikana kughairi oda hii kwa sasa.'));
        }

        return redirect()->route('provider.dashboard')->with('success', 'Oda imekataliwa kikamilifu.');
    }

    public function completeOrder(Request $request, Order $order, OrderService $orderService)
    {
        [, $provider] = $this->resolveProvider($request);

        if ((int) $order->provider_id !== (int) $provider->id) {
            abort(403);
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
            return redirect()->route('provider.dashboard')
                ->with('error', $this->orderActionError($e, 'Imeshindikana kumaliza oda hii kwa sasa.'));
        }

        return redirect()->route('provider.dashboard')->with('success', 'Oda imewekwa kuwa imekamilika.');
    }

    public function suspendOrder(Request $request, Order $order)
    {
        [, $provider] = $this->resolveProvider($request);

        if ((int) $order->provider_id !== (int) $provider->id) {
            abort(403);
        }

        if (
            !Schema::hasColumn('orders', 'suspended_at')
            || !Schema::hasColumn('orders', 'suspended_until_at')
            || !Schema::hasColumn('orders', 'suspension_note')
            || !Schema::hasColumn('orders', 'resumed_at')
            || !Schema::hasColumn('orders', 'schedule_notified_at')
        ) {
            return redirect()->route('provider.dashboard')
                ->with('error', 'Feature ya kusitisha oda haijakamilika kwenye database. Endesha migrate.');
        }

        $data = $request->validate([
            'suspended_until_at' => ['required', 'date', 'after:now'],
            'suspension_note' => ['nullable', 'string', 'max:255'],
        ], [
            'suspended_until_at.required' => 'Chagua tarehe na saa ya kurudia oda.',
            'suspended_until_at.after' => 'Ratiba ya kurudia lazima iwe muda ujao.',
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
            return redirect()->route('provider.dashboard')
                ->with('error', $this->orderActionError($e, 'Imeshindikana kuweka ratiba ya kusitisha oda.'));
        }

        return redirect()->route('provider.dashboard')
            ->with('success', 'Oda imesitishwa hadi ' . $untilAt->format('d/m/Y H:i') . '. Umerudishwa online kupokea oda nyingine.');
    }

    public function showService(Request $request, Service $service)
    {
        [, $provider] = $this->resolveProvider($request);

        if (!$this->onboardingComplete($provider)) {
            return redirect()
                ->route('provider.onboarding')
                ->with('error', 'Kamilisha taarifa zako kwanza kabla ya kuendelea.');
        }

        if (!$this->serviceIsAllowedForProvider($provider, (int) $service->id)) {
            return redirect()
                ->route('provider.dashboard')
                ->with('error', 'Huduma hii haipo kwenye category ulizochagua.');
        }

        $service->load([
            'category:id,name,slug',
            'media' => fn ($q) => $q->orderBy('sort_order'),
        ]);

        $canOperate = (string) ($provider->approval_status ?? '') === 'approved';

        $isAdded = DB::table('provider_services')
            ->where('provider_id', (int) $provider->id)
            ->where('service_id', (int) $service->id)
            ->where('is_active', 1)
            ->exists();

        $images = $this->serviceImageUrls($service);

        return view('public.provider-service-details', [
            'provider' => $provider,
            'service' => $service,
            'images' => $images,
            'canOperate' => $canOperate,
            'isAdded' => $isAdded,
        ]);
    }

    public function addService(Request $request, Service $service)
    {
        [, $provider] = $this->resolveProvider($request);

        if (!$this->onboardingComplete($provider)) {
            return redirect()
                ->route('provider.onboarding')
                ->with('error', 'Kamilisha taarifa zako kwanza kabla ya kuendelea.');
        }

        if ((string) ($provider->approval_status ?? '') !== 'approved') {
            return redirect()
                ->route('provider.services.show', ['service' => (int) $service->id])
                ->with('error', 'Profile yako bado haijaidhinishwa. Button ya kuongeza itafunguka ukishaidhinishwa.');
        }

        if (!$this->serviceIsAllowedForProvider($provider, (int) $service->id)) {
            return redirect()
                ->route('provider.dashboard')
                ->with('error', 'Huduma hii haipo kwenye category ulizochagua.');
        }

        $now = now();

        DB::transaction(function () use ($provider, $service, $now) {
            DB::table('provider_services')->updateOrInsert(
                [
                    'provider_id' => (int) $provider->id,
                    'service_id' => (int) $service->id,
                ],
                [
                    'is_active' => 1,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );

            if (Schema::hasTable('provider_service')) {
                DB::table('provider_service')->updateOrInsert(
                    [
                        'provider_id' => (int) $provider->id,
                        'service_id' => (int) $service->id,
                    ],
                    [
                        'updated_at' => $now,
                        'created_at' => $now,
                    ]
                );
            }
        });

        return redirect()
            ->to(route('provider.dashboard') . '#huduma-zangu')
            ->with('success', 'Huduma imeongezwa. Unaweza kuongeza huduma nyingine.');
    }

    public function withdraw(Request $request, ProviderWalletService $walletService, SnippePay $snippePay)
    {
        [, $provider] = $this->resolveProvider($request);

        if ((string) ($provider->approval_status ?? '') !== 'approved') {
            return redirect()
                ->route('provider.dashboard')
                ->with('error', 'Akaunti yako bado haijapitishwa kikamilifu.');
        }

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'method' => ['nullable', 'string', 'max:50'],
            'destination' => ['nullable', 'string', 'max:120'],
        ]);

        $withdrawal = $walletService->requestWithdrawal(
            $provider,
            (float) $data['amount'],
            $data['method'] ?? null,
            $data['destination'] ?? null,
        );

        $destination = trim((string) ($data['destination'] ?? ''));
        if ($destination === '') {
            $destination = (string) (data_get($provider, 'user.phone') ?? $provider->phone_public ?? '');
        }

        $msisdn = Phone::normalizeTzMsisdn($destination);
        if ($msisdn === null) {
            $walletService->failWithdrawalAndReverse($withdrawal, 'Namba ya simu si sahihi kwa payout.');
            return redirect()->route('provider.dashboard')->with('error', 'Namba ya simu si sahihi kwa payout.');
        }

        $amount = (int) round((float) ($withdrawal->amount ?? 0));
        if ($amount <= 0) {
            $walletService->failWithdrawalAndReverse($withdrawal, 'Invalid payout amount.');
            return redirect()->route('provider.dashboard')->with('error', 'Kiasi cha payout si sahihi.');
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
            return redirect()->route('provider.dashboard')->with('error', $e->getMessage());
        }

        $reference = trim((string) (
            data_get($snippeRes, 'data.reference')
            ?: data_get($snippeRes, 'data.payment_reference')
            ?: data_get($snippeRes, 'reference')
        ));
        if ($reference === '') {
            $walletService->failWithdrawalAndReverse($withdrawal, 'Imeshindikana kuanzisha payout.');
            return redirect()->route('provider.dashboard')->with('error', 'Imeshindikana kuanzisha payout.');
        }

        DB::transaction(function () use ($withdrawal, $reference) {
            $locked = \App\Models\ProviderWithdrawal::whereKey($withdrawal->id)->lockForUpdate()->first();
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

        return redirect()->route('provider.dashboard')->with('success', 'Ombi la kutoa pesa limepokelewa. Payout inaendelea.');
    }

    public function payDebt(Request $request, SnippePay $snippePay)
    {
        [$user, $provider] = $this->resolveProvider($request);

        if (!Schema::hasColumn('providers', 'debt_balance') || !Schema::hasTable('provider_payments')) {
            return redirect()
                ->route('provider.dashboard')
                ->with('error', 'Feature ya kulipa deni online haijakamilika kwenye database.');
        }

        $data = $request->validate([
            'debt_amount' => ['required', 'numeric', 'min:100'],
            'payment_channel' => ['required', 'in:mpesa,tigopesa,airtelmoney,halopesa'],
            'phone_number' => ['required', 'string', 'max:30'],
        ], [
            'debt_amount.required' => 'Weka kiasi unachotaka kulipa.',
            'debt_amount.min' => 'Kiasi cha chini ni TZS 100.',
            'payment_channel.required' => 'Chagua mtandao wa malipo.',
            'phone_number.required' => 'Weka namba ya simu ya malipo.',
        ]);

        $requestedAmount = round((float) $data['debt_amount'], 2);
        if ($requestedAmount <= 0) {
            return redirect()->route('provider.dashboard')->with('error', 'Kiasi si sahihi.');
        }

        $msisdn = Phone::normalizeTzMsisdn((string) $data['phone_number']);
        if ($msisdn === null) {
            return redirect()->route('provider.dashboard')
                ->with('error', 'Namba ya simu si sahihi. Tumia mfano 07XXXXXXXX au 2557XXXXXXXX.');
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
            $msg = $e instanceof \RuntimeException
                ? $e->getMessage()
                : 'Imeshindikana kuandaa malipo ya deni kwa sasa. Jaribu tena.';

            return redirect()->route('provider.dashboard')->with('error', $msg);
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
            return redirect()->route('provider.dashboard')->with('error', 'Kiasi cha kulipa si sahihi.');
        }

        $externalReference = 'provider-debt-' . (int) $payment->id;
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
            'external_reference' => $externalReference,
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
            return redirect()->route('provider.dashboard')->with('error', $e->getMessage());
        }

        $paymentReference = trim((string) (
            data_get($snippeRes, 'data.reference')
            ?: data_get($snippeRes, 'data.payment_reference')
            ?: data_get($snippeRes, 'reference')
        ));
        if ($paymentReference === '') {
            ProviderPayment::whereKey((int) $payment->id)->update(['status' => 'failed']);
            return redirect()->route('provider.dashboard')->with('error', 'Imeshindikana kuanzisha malipo ya deni. Jaribu tena.');
        }

        ProviderPayment::whereKey((int) $payment->id)->update([
            'reference' => $paymentReference,
            'method' => $paymentChannel,
            'status' => 'pending',
        ]);

        return redirect()
            ->route('provider.dashboard')
            ->with('success', 'Ombi la malipo ya deni la TZS ' . number_format($payAmount, 0) . ' limetumwa. Kamilisha uthibitisho kwenye simu yako.');
    }

    public function updateServices(Request $request)
    {
        [, $provider] = $this->resolveProvider($request);

        if ((string) ($provider->approval_status ?? '') !== 'approved') {
            return redirect()->route('provider.dashboard')->with('error', 'Bado hujaruhusiwa kubadilisha huduma.');
        }

        $allowedServiceIds = $this->allowedServiceIds($provider);
        if (empty($allowedServiceIds)) {
            return redirect()->route('provider.dashboard')->with('error', 'Hakuna huduma za kuchagua kwa profile yako kwa sasa.');
        }

        $validated = $request->validate([
            'service_ids' => ['required', 'array', 'min:1'],
            'service_ids.*' => ['required', 'integer', Rule::in($allowedServiceIds)],
        ], [
            'service_ids.required' => 'Chagua angalau huduma moja.',
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

        return redirect()->route('provider.dashboard')->with('success', 'Huduma zako zimeboreshwa kwa mafanikio.');
    }

    private function calculateTotalEarn(int $providerId): float
    {
        if ($providerId <= 0) {
            return 0.0;
        }

        $rows = Order::query()
            ->where('provider_id', $providerId)
            ->whereNotIn('status', ['cancelled'])
            ->get(['price_total', 'commission_amount', 'payout_amount']);

        $total = 0.0;
        $toMoney = static function (mixed $value): float {
            if (is_numeric($value)) {
                return max(0, (float) $value);
            }

            $raw = trim((string) $value);
            if ($raw === '') {
                return 0.0;
            }

            $normalized = str_replace([',', ' '], '', $raw);
            return is_numeric($normalized) ? max(0, (float) $normalized) : 0.0;
        };

        foreach ($rows as $row) {
            $priceTotal = $toMoney($row->price_total ?? 0);
            $commissionAmount = $toMoney($row->commission_amount ?? 0);
            $payoutAmount = $toMoney($row->payout_amount ?? 0);

            if ($priceTotal <= 0 && $payoutAmount > 0 && $commissionAmount > 0) {
                $priceTotal = $payoutAmount + $commissionAmount;
            } elseif ($priceTotal <= 0 && $payoutAmount > 0) {
                $priceTotal = round($payoutAmount / 0.90, 2);
            }

            if ($priceTotal <= 0) {
                continue;
            }

            $commission = round($priceTotal * 0.10, 2);
            $total += max(0, $priceTotal - $commission);
        }

        return round($total, 2);
    }

    private function resolveDashboardPopupOrder(int $providerId): ?Order
    {
        if ($providerId <= 0) {
            return null;
        }

        $with = ['service:id,name,slug', 'client:id,name,phone'];

        if (Schema::hasColumn('orders', 'schedule_notified_at')) {
            $scheduledPopup = Order::with($with)
                ->where('provider_id', $providerId)
                ->whereIn('status', ['accepted', 'on_the_way', 'in_progress'])
                ->whereNotNull('schedule_notified_at')
                ->latest('schedule_notified_at')
                ->latest('id')
                ->first();

            if ($scheduledPopup) {
                return $scheduledPopup;
            }
        }

        return Order::with($with)
            ->where('provider_id', $providerId)
            ->where('status', 'pending')
            ->latest('id')
            ->first();
    }

    private function resolveActiveActionOrder(int $providerId): ?Order
    {
        if ($providerId <= 0) {
            return null;
        }

        return Order::with(['service:id,name,slug', 'client:id,name,phone'])
            ->where('provider_id', $providerId)
            ->whereIn('status', ['accepted', 'on_the_way', 'in_progress', 'suspended'])
            ->latest('updated_at')
            ->latest('id')
            ->first();
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
        if ($providerId <= 0) {
            return false;
        }

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

    private function orderActionError(\Throwable $e, string $fallback): string
    {
        $message = trim((string) $e->getMessage());
        if ($message === '') {
            return $fallback;
        }

        return $message;
    }

    private function nullIfEmpty(mixed $value): ?string
    {
        $text = trim((string) $value);
        return $text === '' ? null : $text;
    }

    private function resolveProvider(Request $request): array
    {
        $user = $request->user();
        abort_unless($user, 403);

        $existingProvider = $user->provider;
        if ((string) ($user->role ?? '') !== 'provider' && !$existingProvider) {
            abort(403);
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

        $providerPhone = Phone::normalizeTzMsisdn((string) ($provider->phone_public ?? ''));
        if ($providerPhone !== null && trim((string) ($user->phone ?? '')) !== $providerPhone) {
            $usedByOther = \App\Models\User::query()
                ->where('phone', $providerPhone)
                ->where('id', '!=', (int) $user->id)
                ->exists();

            if (!$usedByOther) {
                $user->forceFill(['phone' => $providerPhone])->save();
            }
        }

        return [$user, $provider];
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

    private function serviceIsAllowedForProvider(Provider $provider, int $serviceId): bool
    {
        if ($serviceId <= 0) {
            return false;
        }

        return in_array($serviceId, $this->allowedServiceIds($provider), true);
    }

    private function onboardingComplete(Provider $provider): bool
    {
        $status = (string) ($provider->approval_status ?? 'pending');
        return $provider->onboarding_completed_at !== null || $status === 'approved';
    }

    private function serviceImageUrls(Service $service): array
    {
        return $service->imageUrls(12);
    }
}
