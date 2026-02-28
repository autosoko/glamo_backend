<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProvidersSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        // Clean providers (SAFE - no truncate)
        DB::table('providers')->delete();
        DB::statement('ALTER TABLE providers AUTO_INCREMENT = 1');

        // Chukua users 50 wa mwisho (wale tulioseed)
        $users = DB::table('users')
            ->orderByDesc('id')
            ->limit(50)
            ->get(['id', 'name', 'phone']);

        if ($users->isNotEmpty()) {
            DB::table('users')
                ->whereIn('id', $users->pluck('id')->all())
                ->update([
                    'role' => 'provider',
                    'updated_at' => $now,
                ]);
        }

        // Arusha clusters (base coordinates)
        $areas = [
            ['name' => 'Sakina',       'lat' => -3.3638, 'lng' => 36.6450],
            ['name' => 'Njiro',        'lat' => -3.3867, 'lng' => 36.7086],
            ['name' => 'Uzunguni',     'lat' => -3.3720, 'lng' => 36.6880],
            ['name' => 'Sombetini',    'lat' => -3.3520, 'lng' => 36.7010],
            ['name' => 'Kijenge',      'lat' => -3.3740, 'lng' => 36.6970],
            ['name' => 'Themi',        'lat' => -3.3700, 'lng' => 36.7120],
            ['name' => 'Kimandolu',    'lat' => -3.3745, 'lng' => 36.7240],
            ['name' => 'Engutoto',     'lat' => -3.3220, 'lng' => 36.6680],
            ['name' => 'Burka',        'lat' => -3.3450, 'lng' => 36.6150],
            ['name' => 'Kisongo',      'lat' => -3.4000, 'lng' => 36.5900],
            ['name' => 'Tengeru',      'lat' => -3.3710, 'lng' => 36.8430],
            ['name' => 'Clock Tower',  'lat' => -3.3668, 'lng' => 36.6822],
        ];

        $rows = [];

        foreach ($users as $u) {
            $a = $areas[array_rand($areas)];

            // jitter kidogo ili wasiwe point moja
            $lat = $a['lat'] + (mt_rand(-25, 25) / 10000); // +/-0.0025
            $lng = $a['lng'] + (mt_rand(-25, 25) / 10000);

            $rows[] = [
                'is_active' => 1,
                'user_id' => $u->id,
                'approval_status' => 'approved',
                'approved_at' => $now,
                'rejection_reason' => null,
                'phone_public' => $u->phone ?? null,
                'bio' => 'Mtoa huduma wa Glamo (Arusha) — '.$a['name'].'.',
                'current_lat' => $lat,
                'current_lng' => $lng,
                'last_location_at' => $now,
                'online_status' => (rand(0, 1) ? 'online' : 'offline'),
                'offline_reason' => null,
                'debt_balance' => 0,
                'total_orders' => rand(0, 120),
                'rating_avg' => rand(38, 50) / 10, // 3.8 - 5.0
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('providers')->insert($rows);
    }
}
