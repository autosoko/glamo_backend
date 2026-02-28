<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CategoriesSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        // IMPORTANT: services references categories, so delete services first if you want clean reset
        DB::table('services')->delete();
        DB::statement('ALTER TABLE services AUTO_INCREMENT = 1');

        DB::table('categories')->delete();
        DB::statement('ALTER TABLE categories AUTO_INCREMENT = 1');

        DB::table('categories')->insert([
            ['name' => 'Misuko',  'slug' => 'misuko',  'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Makeup',  'slug' => 'makeup',  'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Kubana',  'slug' => 'kubana',  'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Massage', 'slug' => 'massage', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }
}
