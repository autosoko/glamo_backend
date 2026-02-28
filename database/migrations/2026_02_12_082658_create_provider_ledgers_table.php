<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('provider_ledgers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained()->cascadeOnDelete();

            $table->string('type')->index(); 
            // commission_debit|payment_credit|adjustment

            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();

            $table->decimal('amount', 12, 2);      // debit positive (ongeza deni), credit negative (punguza deni) au reverse
            $table->decimal('balance_after', 12, 2);

            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['provider_id','created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_ledgers');
    }
};
