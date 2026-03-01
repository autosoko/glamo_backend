<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Provider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProviderAvailabilityControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_provider_cannot_go_online_when_account_is_not_approved(): void
    {
        [$providerUser, $provider] = $this->createProvider([
            'approval_status' => 'pending',
            'online_status' => 'offline',
            'debt_balance' => 0,
        ]);

        Sanctum::actingAs($providerUser);

        $response = $this->postJson('/api/v1/provider/availability', [
            'online_status' => 'online',
        ]);

        $response->assertStatus(422);

        $this->assertSame('offline', $provider->fresh()->online_status);
    }

    public function test_provider_cannot_go_online_when_active_order_exists(): void
    {
        [$providerUser, $provider] = $this->createProvider([
            'approval_status' => 'approved',
            'online_status' => 'offline',
            'debt_balance' => 0,
        ]);

        $client = $this->createClient();
        $serviceId = $this->createService();

        $this->createOrder($client, $provider, $serviceId, [
            'status' => 'pending',
        ]);

        Sanctum::actingAs($providerUser);

        $response = $this->postJson('/api/v1/provider/availability', [
            'online_status' => 'online',
        ]);

        $response->assertStatus(422);

        $this->assertSame('offline', $provider->fresh()->online_status);
    }

    public function test_provider_cannot_go_online_when_debt_reaches_threshold(): void
    {
        [$providerUser, $provider] = $this->createProvider([
            'approval_status' => 'approved',
            'online_status' => 'offline',
            'debt_balance' => 10000,
        ]);

        Sanctum::actingAs($providerUser);

        $response = $this->postJson('/api/v1/provider/availability', [
            'online_status' => 'online',
        ]);

        $response->assertStatus(422);

        $provider->refresh();

        $this->assertSame('offline', $provider->online_status);
    }

    public function test_client_cancel_returns_provider_online_when_eligible(): void
    {
        [$providerUser, $provider] = $this->createProvider([
            'approval_status' => 'approved',
            'online_status' => 'offline',
            'offline_reason' => 'Ana oda mpya inayosubiri uthibitisho wa mwisho.',
            'debt_balance' => 0,
        ]);

        $client = $this->createClient();
        $serviceId = $this->createService();
        $order = $this->createOrder($client, $provider, $serviceId, [
            'status' => 'accepted',
            'commission_amount' => 1000,
        ]);

        Sanctum::actingAs($client);

        $response = $this->postJson("/api/v1/client/orders/{$order->id}/cancel", [
            'reason' => 'Nimebadilisha ratiba ya leo',
        ]);

        $response->assertOk();

        $provider->refresh();

        $this->assertSame('online', $provider->online_status);
        $this->assertNull($provider->offline_reason);
        $this->assertSame('cancelled', $order->fresh()->status);
    }

    public function test_provider_reject_returns_provider_online_when_eligible(): void
    {
        [$providerUser, $provider] = $this->createProvider([
            'approval_status' => 'approved',
            'online_status' => 'offline',
            'offline_reason' => 'Ana oda mpya inayosubiri uthibitisho wa mwisho.',
            'debt_balance' => 0,
        ]);

        $client = $this->createClient();
        $serviceId = $this->createService();
        $order = $this->createOrder($client, $provider, $serviceId, [
            'status' => 'accepted',
            'commission_amount' => 1000,
        ]);

        Sanctum::actingAs($providerUser);

        $response = $this->postJson("/api/v1/provider/orders/{$order->id}/reject", [
            'reason' => 'Nimepata dharura ya ghafla',
        ]);

        $response->assertOk();

        $provider->refresh();

        $this->assertSame('online', $provider->online_status);
        $this->assertNull($provider->offline_reason);
        $this->assertSame('cancelled', $order->fresh()->status);
    }

    public function test_provider_complete_returns_provider_online_when_eligible(): void
    {
        [$providerUser, $provider] = $this->createProvider([
            'approval_status' => 'approved',
            'online_status' => 'busy',
            'offline_reason' => null,
            'debt_balance' => 0,
        ]);

        $client = $this->createClient();
        $serviceId = $this->createService();
        $order = $this->createOrder($client, $provider, $serviceId, [
            'status' => 'accepted',
            'commission_amount' => 1000,
        ]);

        Sanctum::actingAs($providerUser);

        $response = $this->postJson("/api/v1/provider/orders/{$order->id}/complete", [
            'note' => 'Huduma imekamilika salama',
        ]);

        $response->assertOk();

        $provider->refresh();

        $this->assertSame('online', $provider->online_status);
        $this->assertNull($provider->offline_reason);
        $this->assertSame('completed', $order->fresh()->status);
    }

    private function createClient(): User
    {
        return User::query()->create([
            'name' => 'Client Test',
            'email' => 'client' . uniqid() . '@example.com',
            'phone' => '255700100' . random_int(10, 99),
            'role' => 'client',
            'password' => bcrypt('password'),
        ]);
    }

    private function createProvider(array $overrides = []): array
    {
        $user = User::query()->create([
            'name' => 'Provider Test',
            'email' => 'provider' . uniqid() . '@example.com',
            'phone' => '255700200' . random_int(10, 99),
            'role' => 'provider',
            'password' => bcrypt('password'),
        ]);

        $provider = Provider::query()->create(array_merge([
            'user_id' => (int) $user->id,
            'approval_status' => 'approved',
            'online_status' => 'offline',
            'is_active' => true,
            'phone_public' => (string) $user->phone,
            'debt_balance' => 0,
        ], $overrides));

        return [$user, $provider];
    }

    private function createService(): int
    {
        $suffix = uniqid();

        $categoryId = DB::table('categories')->insertGetId([
            'name' => 'Hair ' . $suffix,
            'slug' => 'hair-' . $suffix,
            'is_active' => true,
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::table('services')->insertGetId([
            'category_id' => $categoryId,
            'name' => 'Braids ' . $suffix,
            'slug' => 'braids-' . $suffix,
            'category' => 'misuko',
            'base_price' => 10000,
            'materials_price' => 0,
            'usage_percent' => 10,
            'duration_minutes' => 60,
            'is_active' => true,
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createOrder(User $client, Provider $provider, int $serviceId, array $overrides = []): Order
    {
        return Order::query()->create(array_merge([
            'order_no' => 'GL-AV-' . strtoupper(substr(uniqid(), -8)),
            'client_id' => (int) $client->id,
            'provider_id' => (int) $provider->id,
            'service_id' => $serviceId,
            'status' => 'accepted',
            'client_lat' => -6.8000000,
            'client_lng' => 39.2800000,
            'address_text' => 'Kariakoo',
            'price_total' => 10000,
            'commission_rate' => 0.1000,
            'commission_amount' => 1000,
            'finish_code' => '1234',
        ], $overrides));
    }
}
