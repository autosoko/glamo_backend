<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('users') || Schema::hasColumn('users', 'profile_image_path')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('profile_image_path')->nullable()->after('last_location_at');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('users') || !Schema::hasColumn('users', 'profile_image_path')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('profile_image_path');
        });
    }
};
