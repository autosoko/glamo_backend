<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $needsService = !Schema::hasColumn('orders', 'price_service');
        $needsMaterials = !Schema::hasColumn('orders', 'price_materials');
        $needsTravel = !Schema::hasColumn('orders', 'price_travel');
        $needsUsage = !Schema::hasColumn('orders', 'price_usage');
        $needsDistance = !Schema::hasColumn('orders', 'travel_distance_km');

        if (! $needsService && ! $needsMaterials && ! $needsTravel && ! $needsUsage && ! $needsDistance) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) use ($needsService, $needsMaterials, $needsTravel, $needsUsage, $needsDistance) {
            if ($needsService) {
                $table->decimal('price_service', 12, 2)
                    ->nullable()
                    ->after('service_id');
            }
            if ($needsMaterials) {
                $table->decimal('price_materials', 12, 2)
                    ->nullable()
                    ->after($needsService ? 'price_service' : 'service_id');
            }
            if ($needsTravel) {
                $table->decimal('price_travel', 12, 2)
                    ->nullable()
                    ->after($needsMaterials ? 'price_materials' : ($needsService ? 'price_service' : 'service_id'));
            }
            if ($needsUsage) {
                $table->decimal('price_usage', 12, 2)
                    ->nullable()
                    ->after($needsTravel ? 'price_travel' : ($needsMaterials ? 'price_materials' : ($needsService ? 'price_service' : 'service_id')));
            }
            if ($needsDistance) {
                $table->decimal('travel_distance_km', 8, 3)
                    ->nullable()
                    ->after($needsUsage ? 'price_usage' : ($needsTravel ? 'price_travel' : ($needsMaterials ? 'price_materials' : ($needsService ? 'price_service' : 'service_id'))));
            }
        });
    }

    public function down(): void
    {
        $hasService = Schema::hasColumn('orders', 'price_service');
        $hasMaterials = Schema::hasColumn('orders', 'price_materials');
        $hasTravel = Schema::hasColumn('orders', 'price_travel');
        $hasUsage = Schema::hasColumn('orders', 'price_usage');
        $hasDistance = Schema::hasColumn('orders', 'travel_distance_km');

        if (! $hasService && ! $hasMaterials && ! $hasTravel && ! $hasUsage && ! $hasDistance) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) use ($hasService, $hasMaterials, $hasTravel, $hasUsage, $hasDistance) {
            if ($hasDistance) {
                $table->dropColumn('travel_distance_km');
            }
            if ($hasUsage) {
                $table->dropColumn('price_usage');
            }
            if ($hasTravel) {
                $table->dropColumn('price_travel');
            }
            if ($hasMaterials) {
                $table->dropColumn('price_materials');
            }
            if ($hasService) {
                $table->dropColumn('price_service');
            }
        });
    }
};
