<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            $table->string('order_no')->unique();

            $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('provider_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();

            $table->string('status')->default('pending')->index();
            // pending|accepted|on_the_way|in_progress|completed|cancelled

            // location
            $table->decimal('client_lat', 10, 7);
            $table->decimal('client_lng', 10, 7);
            $table->string('address_text')->nullable();

            $table->timestamp('scheduled_at')->nullable();

            // money
            $table->decimal('price_total', 12, 2);
            $table->decimal('commission_rate', 5, 4)->default(0.1500);
            $table->decimal('commission_amount', 12, 2);

            // timestamps
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->text('completion_note')->nullable();

            // optional security: client confirms finish
            $table->string('finish_code', 10)->nullable();

            $table->timestamps();

            $table->index(['provider_id','status']);
            $table->index(['client_id','status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
