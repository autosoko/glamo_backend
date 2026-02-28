<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 20)->unique()->after('id');
            $table->string('role')->default('client')->index()->after('phone'); // admin|provider|client
            $table->timestamp('otp_verified_at')->nullable()->after('email_verified_at');

            // optional, kama unataka user-level last known location
            $table->decimal('last_lat', 10, 7)->nullable()->after('remember_token');
            $table->decimal('last_lng', 10, 7)->nullable()->after('last_lat');
            $table->timestamp('last_location_at')->nullable()->after('last_lng');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone','role','otp_verified_at','last_lat','last_lng','last_location_at']);
        });
    }
};
