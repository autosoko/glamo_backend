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

        $needsPaymentRef = !Schema::hasColumn('orders', 'payment_reference');
        $needsPaymentProvider = !Schema::hasColumn('orders', 'payment_provider');

        $needsOnTheWayAt = !Schema::hasColumn('orders', 'on_the_way_at');
        $needsProviderArrivedAt = !Schema::hasColumn('orders', 'provider_arrived_at');
        $needsClientArrivalConfirmedAt = !Schema::hasColumn('orders', 'client_arrival_confirmed_at');

        $needsPayoutAmount = !Schema::hasColumn('orders', 'payout_amount');
        $needsEscrowReleasedAt = !Schema::hasColumn('orders', 'escrow_released_at');

        if (
            !$needsPaymentRef &&
            !$needsPaymentProvider &&
            !$needsOnTheWayAt &&
            !$needsProviderArrivedAt &&
            !$needsClientArrivalConfirmedAt &&
            !$needsPayoutAmount &&
            !$needsEscrowReleasedAt
        ) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) use (
            $needsPaymentRef,
            $needsPaymentProvider,
            $needsOnTheWayAt,
            $needsProviderArrivedAt,
            $needsClientArrivalConfirmedAt,
            $needsPayoutAmount,
            $needsEscrowReleasedAt
        ) {
            if ($needsPaymentProvider) {
                $table->string('payment_provider')->nullable()->after('payment_status');
            }
            if ($needsPaymentRef) {
                $after = $needsPaymentProvider ? 'payment_provider' : 'payment_status';
                $table->string('payment_reference')->nullable()->after($after);
            }

            if ($needsOnTheWayAt) {
                $table->timestamp('on_the_way_at')->nullable()->after('accepted_at');
            }
            if ($needsProviderArrivedAt) {
                $table->timestamp('provider_arrived_at')->nullable()->after($needsOnTheWayAt ? 'on_the_way_at' : 'accepted_at');
            }
            if ($needsClientArrivalConfirmedAt) {
                $after = $needsProviderArrivedAt ? 'provider_arrived_at' : ($needsOnTheWayAt ? 'on_the_way_at' : 'accepted_at');
                $table->timestamp('client_arrival_confirmed_at')->nullable()->after($after);
            }

            if ($needsPayoutAmount) {
                $table->decimal('payout_amount', 12, 2)->nullable()->after('commission_amount');
            }
            if ($needsEscrowReleasedAt) {
                $table->timestamp('escrow_released_at')->nullable()->after($needsPayoutAmount ? 'payout_amount' : 'commission_amount');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('orders')) {
            return;
        }

        $hasPaymentRef = Schema::hasColumn('orders', 'payment_reference');
        $hasPaymentProvider = Schema::hasColumn('orders', 'payment_provider');

        $hasOnTheWayAt = Schema::hasColumn('orders', 'on_the_way_at');
        $hasProviderArrivedAt = Schema::hasColumn('orders', 'provider_arrived_at');
        $hasClientArrivalConfirmedAt = Schema::hasColumn('orders', 'client_arrival_confirmed_at');

        $hasPayoutAmount = Schema::hasColumn('orders', 'payout_amount');
        $hasEscrowReleasedAt = Schema::hasColumn('orders', 'escrow_released_at');

        if (
            !$hasPaymentRef &&
            !$hasPaymentProvider &&
            !$hasOnTheWayAt &&
            !$hasProviderArrivedAt &&
            !$hasClientArrivalConfirmedAt &&
            !$hasPayoutAmount &&
            !$hasEscrowReleasedAt
        ) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) use (
            $hasPaymentRef,
            $hasPaymentProvider,
            $hasOnTheWayAt,
            $hasProviderArrivedAt,
            $hasClientArrivalConfirmedAt,
            $hasPayoutAmount,
            $hasEscrowReleasedAt
        ) {
            if ($hasEscrowReleasedAt) {
                $table->dropColumn('escrow_released_at');
            }
            if ($hasPayoutAmount) {
                $table->dropColumn('payout_amount');
            }
            if ($hasClientArrivalConfirmedAt) {
                $table->dropColumn('client_arrival_confirmed_at');
            }
            if ($hasProviderArrivedAt) {
                $table->dropColumn('provider_arrived_at');
            }
            if ($hasOnTheWayAt) {
                $table->dropColumn('on_the_way_at');
            }

            if ($hasPaymentRef) {
                $table->dropColumn('payment_reference');
            }
            if ($hasPaymentProvider) {
                $table->dropColumn('payment_provider');
            }
        });
    }
};

