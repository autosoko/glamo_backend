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

        $needsCancellationReason = !Schema::hasColumn('orders', 'cancellation_reason');
        $needsRefundReason = !Schema::hasColumn('orders', 'refund_reason');

        if (!$needsCancellationReason && !$needsRefundReason) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) use ($needsCancellationReason, $needsRefundReason) {
            if ($needsCancellationReason) {
                $after = Schema::hasColumn('orders', 'completion_note') ? 'completion_note' : 'status';
                $table->text('cancellation_reason')->nullable()->after($after);
            }

            if ($needsRefundReason) {
                $after = Schema::hasColumn('orders', 'refund_reference')
                    ? 'refund_reference'
                    : (Schema::hasColumn('orders', 'payment_reference') ? 'payment_reference' : 'payment_status');
                $table->text('refund_reason')->nullable()->after($after);
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('orders')) {
            return;
        }

        $hasCancellationReason = Schema::hasColumn('orders', 'cancellation_reason');
        $hasRefundReason = Schema::hasColumn('orders', 'refund_reason');

        if (!$hasCancellationReason && !$hasRefundReason) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) use ($hasCancellationReason, $hasRefundReason) {
            if ($hasRefundReason) {
                $table->dropColumn('refund_reason');
            }

            if ($hasCancellationReason) {
                $table->dropColumn('cancellation_reason');
            }
        });
    }
};
