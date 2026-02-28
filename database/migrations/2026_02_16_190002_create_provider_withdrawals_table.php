<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('provider_withdrawals')) {
            return;
        }

        Schema::create('provider_withdrawals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained()->cascadeOnDelete();

            $table->decimal('amount', 12, 2);
            $table->string('method')->nullable();      // mpesa, tigopesa, bank, etc
            $table->string('destination')->nullable(); // phone or account

            $table->string('status')->default('pending')->index();
            // pending|processing|paid|failed|rejected

            $table->string('reference')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('rejected_reason')->nullable();

            $table->timestamps();

            $table->index(['provider_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_withdrawals');
    }
};

