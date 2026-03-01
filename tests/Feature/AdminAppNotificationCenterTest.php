<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAppNotificationCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_app_notification_center_page(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin Test',
            'email' => 'admin-' . uniqid() . '@example.com',
            'phone' => '255700400' . random_int(10, 99),
            'role' => 'admin',
            'password' => bcrypt('password'),
        ]);

        $response = $this->actingAs($admin)->get('/admin/app-notification-center');

        $response->assertOk();
        $response->assertSee('Tuma notification kwenye app');
    }
}
