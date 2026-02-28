<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('services')) {
            return;
        }

        $needsEnabled = ! Schema::hasColumn('services', 'hair_wash_enabled');
        $needsPrice = ! Schema::hasColumn('services', 'hair_wash_price');
        $needsDefault = ! Schema::hasColumn('services', 'hair_wash_default_selected');

        if ($needsEnabled || $needsPrice || $needsDefault) {
            Schema::table('services', function (Blueprint $table) use ($needsEnabled, $needsPrice, $needsDefault): void {
                if ($needsEnabled) {
                    $table->boolean('hair_wash_enabled')
                        ->default(false)
                        ->after('materials_price');
                }

                if ($needsPrice) {
                    $table->decimal('hair_wash_price', 12, 2)
                        ->default(0)
                        ->after($needsEnabled ? 'hair_wash_enabled' : 'materials_price');
                }

                if ($needsDefault) {
                    $table->boolean('hair_wash_default_selected')
                        ->default(false)
                        ->after($needsPrice ? 'hair_wash_price' : 'materials_price');
                }
            });
        }

        if (! Schema::hasTable('categories')) {
            return;
        }

        $targetCategoryIds = DB::table('categories')
            ->whereIn('slug', ['misuko', 'kubana'])
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->values()
            ->all();

        if (empty($targetCategoryIds)) {
            return;
        }

        $defaultHairWashPrice = (float) config('glamo_pricing.hair_wash_default_price', 3000);
        if ($defaultHairWashPrice <= 0) {
            $defaultHairWashPrice = 3000;
        }

        DB::table('services')
            ->whereIn('category_id', $targetCategoryIds)
            ->update([
                'hair_wash_enabled' => 1,
                'hair_wash_default_selected' => 1,
            ]);

        DB::table('services')
            ->whereIn('category_id', $targetCategoryIds)
            ->where(function ($q): void {
                $q->whereNull('hair_wash_price')->orWhere('hair_wash_price', '<=', 0);
            })
            ->update([
                'hair_wash_price' => $defaultHairWashPrice,
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('services')) {
            return;
        }

        $hasEnabled = Schema::hasColumn('services', 'hair_wash_enabled');
        $hasPrice = Schema::hasColumn('services', 'hair_wash_price');
        $hasDefault = Schema::hasColumn('services', 'hair_wash_default_selected');

        if (! $hasEnabled && ! $hasPrice && ! $hasDefault) {
            return;
        }

        Schema::table('services', function (Blueprint $table) use ($hasEnabled, $hasPrice, $hasDefault): void {
            if ($hasDefault) {
                $table->dropColumn('hair_wash_default_selected');
            }

            if ($hasPrice) {
                $table->dropColumn('hair_wash_price');
            }

            if ($hasEnabled) {
                $table->dropColumn('hair_wash_enabled');
            }
        });
    }
};

