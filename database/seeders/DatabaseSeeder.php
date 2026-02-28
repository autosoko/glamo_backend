<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CategoriesSeeder::class,
            ServicesSeeder::class,
            CouponSeeder::class,
            UsersSeeder::class,
            AdminUserSeeder::class,
            ProvidersSeeder::class,
            ProviderServiceSeeder::class,
        ]);
    }
}
