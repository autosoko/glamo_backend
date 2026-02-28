<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('provider_portfolios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained()->cascadeOnDelete();
            $table->string('type')->index(); // image|video
            $table->string('file_path');
            $table->timestamps();

            $table->index(['provider_id','type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_portfolios');
    }
};
