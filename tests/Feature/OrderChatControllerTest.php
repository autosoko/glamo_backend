<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Provider;
use App\Models\User;
use App\Services\AppNotificationService;
use App\Services\OrderRealtimeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\TestCase;

class OrderChatControllerTest extends TestCase
{
    use MockeryPHPUnitIntegration;
    use RefreshDatabase;

    public function test_store_still_succeeds_when_realtime_and_push_fail(): void
    {
        $client = User::query()->create([
            'name' => 'Client Test',
            'email' => 'client@example.com',
            'phone' => '255700000001',
            'role' => 'client',
            'password' => bcrypt('password'),
        ]);

        $providerUser = User::query()->create([
            'name' => 'Provider Test',
            'email' => 'provider@example.com',
            'phone' => '255700000002',
            'role' => 'provider',
            'password' => bcrypt('password'),
        ]);

        $provider = Provider::query()->create([
            'user_id' => (int) $providerUser->id,
            'approval_status' => 'approved',
            'online_status' => 'busy',
            'is_active' => true,
            'phone_public' => '255700000002',
        ]);

        $categoryId = DB::table('categories')->insertGetId([
            'name' => 'Hair',
            'slug' => 'hair',
            'is_active' => true,
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $serviceId = DB::table('services')->insertGetId([
            'category_id' => $categoryId,
            'name' => 'Braids',
            'slug' => 'braids',
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

        $order = Order::query()->create([
            'order_no' => 'GL-CHAT-001',
            'client_id' => (int) $client->id,
            'provider_id' => (int) $provider->id,
            'service_id' => (int) $serviceId,
            'status' => 'accepted',
            'client_lat' => -6.8000000,
            'client_lng' => 39.2800000,
            'address_text' => 'Kariakoo',
            'price_total' => 10000,
            'commission_rate' => 0.1000,
            'commission_amount' => 1000,
            'finish_code' => '1234',
        ]);

        $realtime = Mockery::mock(OrderRealtimeService::class);
        $realtime->shouldReceive('dispatchMessageSent')
            ->once()
            ->andThrow(new \RuntimeException('Broadcast unavailable'));
        $this->app->instance(OrderRealtimeService::class, $realtime);

        $notifications = Mockery::mock(AppNotificationService::class);
        $notifications->shouldReceive('sendToUsers')
            ->once()
            ->andThrow(new \RuntimeException('Push unavailable'));
        $this->app->instance(AppNotificationService::class, $notifications);

        Sanctum::actingAs($client);

        $response = $this->postJson("/api/v1/orders/{$order->id}/messages", [
            'body' => 'Habari ya jioni',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.message.body', 'Habari ya jioni');

        $this->assertDatabaseHas('messages', [
            'sender_id' => (int) $client->id,
            'body' => 'Habari ya jioni',
        ]);
    }
}
