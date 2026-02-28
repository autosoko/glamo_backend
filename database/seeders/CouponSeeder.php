<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CouponSeeder extends Seeder
{
    public function run(): void
    {
        if (!Schema::hasTable('coupons')) {
            return;
        }

        if (DB::table('coupons')->limit(1)->exists()) {
            return;
        }

        $now = now();

        DB::table('coupons')->insert([
            [
                'code' => 'GLAMO5',
                'type' => 'percent',
                'value' => 5,
                'max_uses' => null,
                'used_count' => 0,
                'min_order_amount' => 0,
                'starts_at' => null,
                'ends_at' => null,
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'WELCOME2000',
                'type' => 'fixed',
                'value' => 2000,
                'max_uses' => 500,
                'used_count' => 0,
                'min_order_amount' => 15000,
                'starts_at' => null,
                'ends_at' => null,
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }
}

