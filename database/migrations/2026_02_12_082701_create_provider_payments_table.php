<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('provider_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained()->cascadeOnDelete();

            $table->decimal('amount', 12, 2);
            $table->string('method')->nullable();     // mpesa, tigopesa, bank, etc
            $table->string('reference')->nullable();  // transid
            $table->string('status')->default('pending')->index(); // pending|paid|failed
            $table->timestamp('paid_at')->nullable();

            $table->timestamps();

            $table->index(['provider_id','status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_payments');
    }
};
