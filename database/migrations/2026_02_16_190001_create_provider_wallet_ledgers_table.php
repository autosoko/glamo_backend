<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('provider_wallet_ledgers')) {
            return;
        }

        Schema::create('provider_wallet_ledgers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();

            $table->string('type')->index();
            // escrow_release|withdrawal_debit|adjustment

            // signed amount: + adds to wallet, - subtracts from wallet
            $table->decimal('amount', 12, 2);
            $table->decimal('balance_after', 12, 2);
            $table->string('note')->nullable();

            $table->timestamps();

            $table->index(['provider_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_wallet_ledgers');
    }
};

