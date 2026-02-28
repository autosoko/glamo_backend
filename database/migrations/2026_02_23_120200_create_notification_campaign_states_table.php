<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('notification_campaign_states')) {
            return;
        }

        Schema::create('notification_campaign_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('campaign_key', 80);
            $table->timestamp('last_sent_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'campaign_key']);
            $table->index(['campaign_key', 'last_sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_campaign_states');
    }
};
