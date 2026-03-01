<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\AppNotificationService;
use App\Services\PushNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\TestCase;

class AppNotificationServiceTest extends TestCase
{
    use MockeryPHPUnitIntegration;
    use RefreshDatabase;

    public function test_send_to_users_splits_push_by_role_specific_app_variant(): void
    {
        $client = User::query()->create([
            'name' => 'Client Split',
            'email' => 'client-split-' . uniqid() . '@example.com',
            'phone' => '255700600' . random_int(10, 99),
            'role' => 'client',
            'password' => bcrypt('password'),
        ]);

        $provider = User::query()->create([
            'name' => 'Provider Split',
            'email' => 'provider-split-' . uniqid() . '@example.com',
            'phone' => '255700700' . random_int(10, 99),
            'role' => 'provider',
            'password' => bcrypt('password'),
        ]);

        $push = Mockery::mock(PushNotificationService::class);
        $push->shouldReceive('sendToUsers')
            ->once()
            ->with(
                Mockery::on(fn ($ids) => collect($ids)->values()->all() === [(int) $client->id]),
                'Habari',
                'Ujumbe',
                Mockery::type('array'),
                ['app_variants' => ['glamo_client']]
            )
            ->andReturn([
                'configured' => true,
                'provider' => 'fcm_legacy',
                'users' => 1,
                'tokens' => 1,
                'attempted' => 1,
                'sent' => 1,
                'failed' => 0,
                'invalidated' => 0,
            ]);
        $push->shouldReceive('sendToUsers')
            ->once()
            ->with(
                Mockery::on(fn ($ids) => collect($ids)->values()->all() === [(int) $provider->id]),
                'Habari',
                'Ujumbe',
                Mockery::type('array'),
                ['app_variants' => ['glamo_provider']]
            )
            ->andReturn([
                'configured' => true,
                'provider' => 'fcm_legacy',
                'users' => 1,
                'tokens' => 1,
                'attempted' => 1,
                'sent' => 1,
                'failed' => 0,
                'invalidated' => 0,
            ]);

        $service = new AppNotificationService($push);

        $result = $service->sendToUsers(
            [(int) $client->id, (int) $provider->id],
            'admin_manual',
            'Habari',
            'Ujumbe',
            ['source' => 'test'],
            false
        );

        $this->assertSame(2, (int) $result['users']);
        $this->assertSame(2, (int) data_get($result, 'push.sent'));
    }
}
