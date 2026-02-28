<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('providers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            $table->string('approval_status')->default('pending')->index(); // pending|approved|rejected
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->string('phone_public', 20)->nullable()->index();
            $table->text('bio')->nullable();

            // location & realtime status
            $table->decimal('current_lat', 10, 7)->nullable()->index();
            $table->decimal('current_lng', 10, 7)->nullable()->index();
            $table->timestamp('last_location_at')->nullable();

            $table->string('online_status')->default('offline')->index();
            // online|offline|busy|blocked_debt
            $table->string('offline_reason')->nullable();

            // debt logic
            $table->decimal('debt_balance', 12, 2)->default(0)->index();

            // quick stats
            $table->unsignedInteger('total_orders')->default(0);
            $table->decimal('rating_avg', 3, 2)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('providers');
    }
};
