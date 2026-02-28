<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('orders')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'suspended_at')) {
                $table->timestamp('suspended_at')->nullable()->after('completed_at');
            }
            if (!Schema::hasColumn('orders', 'suspended_until_at')) {
                $table->timestamp('suspended_until_at')->nullable()->after('suspended_at');
            }
            if (!Schema::hasColumn('orders', 'suspension_note')) {
                $table->string('suspension_note', 255)->nullable()->after('suspended_until_at');
            }
            if (!Schema::hasColumn('orders', 'resumed_at')) {
                $table->timestamp('resumed_at')->nullable()->after('suspension_note');
            }
            if (!Schema::hasColumn('orders', 'schedule_notified_at')) {
                $table->timestamp('schedule_notified_at')->nullable()->after('resumed_at');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('orders')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            foreach (['schedule_notified_at', 'resumed_at', 'suspension_note', 'suspended_until_at', 'suspended_at'] as $col) {
                if (Schema::hasColumn('orders', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
