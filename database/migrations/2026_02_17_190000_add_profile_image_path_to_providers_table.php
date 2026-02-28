<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('providers') || Schema::hasColumn('providers', 'profile_image_path')) {
            return;
        }

        Schema::table('providers', function (Blueprint $table): void {
            $table->string('profile_image_path')->nullable()->after('business_nickname');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('providers') || ! Schema::hasColumn('providers', 'profile_image_path')) {
            return;
        }

        Schema::table('providers', function (Blueprint $table): void {
            $table->dropColumn('profile_image_path');
        });
    }
};

