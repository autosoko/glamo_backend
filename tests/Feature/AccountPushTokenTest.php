<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccountPushTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_push_token_normalizes_provider_app_variant_from_package_id(): void
    {
        $user = User::query()->create([
            'name' => 'Provider Push',
            'email' => 'provider-push-' . uniqid() . '@example.com',
            'phone' => '255700500' . random_int(10, 99),
            'role' => 'provider',
            'password' => bcrypt('password'),
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/me/push-tokens', [
            'token' => 'provider-device-token',
            'platform' => 'android',
            'app_variant' => 'com.glamopro.link',
            'device_id' => 'provider-device-1',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.app_variant', 'glamo_provider')
            ->assertJsonPath('data.app_variant_meta.package_id', 'com.glamopro.link');

        $this->assertDatabaseHas('device_push_tokens', [
            'user_id' => (int) $user->id,
            'app_variant' => 'glamo_provider',
            'device_id' => 'provider-device-1',
        ]);
    }

    public function test_meta_endpoint_exposes_broadcast_and_app_variant_configuration(): void
    {
        $response = $this->getJson('/api/v1/meta');

        $response->assertOk()
            ->assertJsonPath('data.broadcast.auth_endpoint', url('/broadcasting/auth'))
            ->assertJsonPath('data.app_variants.0.package_id', 'com.beautful.link');
    }
}
