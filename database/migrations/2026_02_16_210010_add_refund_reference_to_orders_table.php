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

        if (Schema::hasColumn('orders', 'refund_reference')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            $after = Schema::hasColumn('orders', 'payment_reference') ? 'payment_reference' : 'payment_status';
            $table->string('refund_reference')->nullable()->after($after);
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('orders')) {
            return;
        }
        if (!Schema::hasColumn('orders', 'refund_reference')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('refund_reference');
        });
    }
};

