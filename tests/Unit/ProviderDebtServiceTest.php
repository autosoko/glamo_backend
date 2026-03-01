<?php

namespace Tests\Unit;

use App\Models\Provider;
use App\Models\ProviderPayment;
use App\Models\User;
use App\Services\ProviderDebtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProviderDebtServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_mark_payment_as_paid_restores_provider_online_when_debt_drops_below_threshold(): void
    {
        $provider = $this->createProvider([
            'online_status' => 'blocked_debt',
            'offline_reason' => 'Deni limefika TZS 10,000 au zaidi. Lipa deni liwe chini ya hapo.',
            'debt_balance' => 15000,
        ]);

        $payment = ProviderPayment::query()->create([
            'provider_id' => (int) $provider->id,
            'amount' => 6000,
            'reference' => 'PAY-LOWER-THRESHOLD',
            'status' => 'pending',
        ]);

        app(ProviderDebtService::class)->markPaymentAsPaid($payment);

        $provider->refresh();

        $this->assertSame(9000.0, (float) $provider->debt_balance);
        $this->assertSame('online', $provider->online_status);
        $this->assertNull($provider->offline_reason);
    }

    public function test_mark_payment_as_paid_keeps_provider_blocked_when_debt_remains_at_threshold(): void
    {
        $provider = $this->createProvider([
            'online_status' => 'blocked_debt',
            'offline_reason' => 'Deni limefika TZS 10,000 au zaidi. Lipa deni liwe chini ya hapo.',
            'debt_balance' => 15000,
        ]);

        $payment = ProviderPayment::query()->create([
            'provider_id' => (int) $provider->id,
            'amount' => 5000,
            'reference' => 'PAY-AT-THRESHOLD',
            'status' => 'pending',
        ]);

        app(ProviderDebtService::class)->markPaymentAsPaid($payment);

        $provider->refresh();

        $this->assertSame(10000.0, (float) $provider->debt_balance);
        $this->assertSame('blocked_debt', $provider->online_status);
    }

    private function createProvider(array $overrides = []): Provider
    {
        $user = User::query()->create([
            'name' => 'Provider Debt Test',
            'email' => 'provider-debt-' . uniqid() . '@example.com',
            'phone' => '255700300' . random_int(10, 99),
            'role' => 'provider',
            'password' => bcrypt('password'),
        ]);

        return Provider::query()->create(array_merge([
            'user_id' => (int) $user->id,
            'approval_status' => 'approved',
            'online_status' => 'offline',
            'is_active' => true,
            'phone_public' => (string) $user->phone,
            'debt_balance' => 0,
        ], $overrides));
    }
}
