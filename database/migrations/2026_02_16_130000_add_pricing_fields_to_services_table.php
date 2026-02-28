<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $needsMaterials = !Schema::hasColumn('services', 'materials_price');
        $needsUsage = !Schema::hasColumn('services', 'usage_percent');

        if (! $needsMaterials && ! $needsUsage) {
            return;
        }

        Schema::table('services', function (Blueprint $table) use ($needsMaterials, $needsUsage) {
            if ($needsMaterials) {
                $table->decimal('materials_price', 12, 2)
                    ->default(0)
                    ->after('base_price');
            }

            if ($needsUsage) {
                $table->decimal('usage_percent', 5, 2)
                    ->default(10)
                    ->after($needsMaterials ? 'materials_price' : 'base_price');
            }
        });
    }

    public function down(): void
    {
        $hasMaterials = Schema::hasColumn('services', 'materials_price');
        $hasUsage = Schema::hasColumn('services', 'usage_percent');

        if (! $hasMaterials && ! $hasUsage) {
            return;
        }

        Schema::table('services', function (Blueprint $table) use ($hasMaterials, $hasUsage) {
            if ($hasUsage) {
                $table->dropColumn('usage_percent');
            }
            if ($hasMaterials) {
                $table->dropColumn('materials_price');
            }
        });
    }
};

