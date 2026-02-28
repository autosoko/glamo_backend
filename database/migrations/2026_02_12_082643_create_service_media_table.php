<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('service_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->string('file_path');
            $table->unsignedSmallInteger('sort_order')->default(0)->index();
            $table->timestamps();

            $table->index(['service_id','sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_media');
    }
};
