<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('services', 'duration_minutes')) {
            return;
        }

        Schema::table('services', function (Blueprint $table) {
            $table->unsignedSmallInteger('duration_minutes')
                ->default(60)
                ->after('base_price');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('services', 'duration_minutes')) {
            return;
        }

        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('duration_minutes');
        });
    }
};

