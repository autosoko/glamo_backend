<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProviderServiceSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        if (!Schema::hasTable('provider_services')) {
            return;
        }

        // If pivot already has data, don't overwrite existing mapping.
        if (DB::table('provider_services')->limit(1)->exists()) {
            return;
        }

        $providerIds = DB::table('providers')
            ->where('is_active', 1)
            ->pluck('id')
            ->all();

        $services = DB::table('services')
            ->where('is_active', 1)
            ->get(['id', 'base_price']);

        if (empty($providerIds) || $services->isEmpty()) {
            return;
        }

        $serviceIds = $services->pluck('id')->all();
        $basePriceById = $services->pluck('base_price', 'id')->map(fn ($v) => (float) $v)->all();

        $rows = [];
        $assignedCounts = array_fill_keys($serviceIds, 0);

        foreach ($providerIds as $providerId) {
            $n = rand(6, 12);
            $picked = collect($serviceIds)->shuffle()->take($n)->values()->all();

            foreach ($picked as $serviceId) {
                $base = (float) ($basePriceById[$serviceId] ?? 0);

                $override = null;
                if ($base > 0 && rand(1, 100) <= 35) {
                    // +/- up to 15%
                    $delta = $base * (rand(-15, 15) / 100);
                    $override = max(0, round($base + $delta, 2));
                }

                $rows[] = [
                    'provider_id' => $providerId,
                    'service_id' => $serviceId,
                    'price_override' => $override ?: null,
                    'is_active' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                $assignedCounts[$serviceId] = (int) ($assignedCounts[$serviceId] ?? 0) + 1;
            }
        }

        // Ensure every service has at least one provider (so homepage counts aren't all zero).
        foreach ($assignedCounts as $serviceId => $count) {
            if ($count > 0) {
                continue;
            }

            $providerId = $providerIds[array_rand($providerIds)];
            $base = (float) ($basePriceById[$serviceId] ?? 0);

            $rows[] = [
                'provider_id' => $providerId,
                'service_id' => $serviceId,
                'price_override' => $base > 0 ? round($base * (1 + (rand(-10, 10) / 100)), 2) : null,
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 1000) as $chunk) {
            DB::table('provider_services')->insert($chunk);
        }

        // Legacy pivot (if it exists) - helps any old pages that still rely on provider_service
        if (Schema::hasTable('provider_service')) {
            DB::table('provider_service')->delete();
            try {
                DB::statement('ALTER TABLE provider_service AUTO_INCREMENT = 1');
            } catch (\Throwable $e) {
                // ignore for non-mysql drivers
            }

            $legacyRows = array_map(function (array $r) {
                return [
                    'provider_id' => $r['provider_id'],
                    'service_id' => $r['service_id'],
                    'created_at' => $r['created_at'],
                    'updated_at' => $r['updated_at'],
                ];
            }, $rows);

            foreach (array_chunk($legacyRows, 1000) as $chunk) {
                DB::table('provider_service')->insert($chunk);
            }
        }

        return;

        // SAFE CLEAN: delete providers tu (hatu-truncate kwa FK safety)
        DB::table('providers')->delete();
        DB::statement('ALTER TABLE providers AUTO_INCREMENT = 1');

        // Chukua users 50 wa mwisho (wale tulioweka sasa hivi)
        $users = DB::table('users')->orderByDesc('id')->limit(50)->get(['id','name','phone']);

        // Arusha clusters (base coords)
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

        foreach ($users as $idx => $u) {
            $a = $areas[array_rand($areas)];

            // jitter ndogo (~0–2km) ili wawe tofauti
            $lat = $a['lat'] + (mt_rand(-25, 25) / 10000); // +/-0.0025
            $lng = $a['lng'] + (mt_rand(-25, 25) / 10000);

            $rows[] = [
                'is_active' => 1,
                'user_id' => $u->id,
                'approval_status' => 'approved',          // pending/approved/rejected
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
