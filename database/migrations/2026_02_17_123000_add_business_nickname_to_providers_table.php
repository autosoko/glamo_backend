<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('providers') || Schema::hasColumn('providers', 'business_nickname')) {
            return;
        }

        Schema::table('providers', function (Blueprint $table) {
            $table->string('business_nickname', 120)->nullable()->after('phone_public');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('providers') || !Schema::hasColumn('providers', 'business_nickname')) {
            return;
        }

        Schema::table('providers', function (Blueprint $table) {
            $table->dropColumn('business_nickname');
        });
    }
};
