<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();

            $table->string('name');                // Twist, Box Braids, Pedicure, etc.
            $table->string('slug')->index();
            $table->string('short_desc')->nullable();
            $table->decimal('base_price', 12, 2)->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedSmallInteger('sort_order')->default(0)->index();

            $table->timestamps();

            $table->unique(['category_id','slug']);
            $table->index(['category_id','is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
