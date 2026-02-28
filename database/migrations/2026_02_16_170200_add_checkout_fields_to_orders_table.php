<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $needsPaymentMethod = !Schema::hasColumn('orders', 'payment_method');
        $needsPaymentChannel = !Schema::hasColumn('orders', 'payment_channel');
        $needsPaymentStatus = !Schema::hasColumn('orders', 'payment_status');

        $needsSubtotal = !Schema::hasColumn('orders', 'price_subtotal');
        $needsDiscount = !Schema::hasColumn('orders', 'discount_amount');
        $needsCouponId = !Schema::hasColumn('orders', 'coupon_id');
        $needsCouponCode = !Schema::hasColumn('orders', 'coupon_code');

        if (
            !$needsPaymentMethod &&
            !$needsPaymentChannel &&
            !$needsPaymentStatus &&
            !$needsSubtotal &&
            !$needsDiscount &&
            !$needsCouponId &&
            !$needsCouponCode
        ) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) use (
            $needsPaymentMethod,
            $needsPaymentChannel,
            $needsPaymentStatus,
            $needsSubtotal,
            $needsDiscount,
            $needsCouponId,
            $needsCouponCode
        ) {
            if ($needsPaymentMethod) {
                $table->string('payment_method')->nullable()->after('status'); // cash|prepay
            }
            if ($needsPaymentChannel) {
                $table->string('payment_channel')->nullable()->after($needsPaymentMethod ? 'payment_method' : 'status');
            }
            if ($needsPaymentStatus) {
                $table->string('payment_status')->nullable()->after($needsPaymentChannel ? 'payment_channel' : ($needsPaymentMethod ? 'payment_method' : 'status'));
            }

            if ($needsSubtotal) {
                $table->decimal('price_subtotal', 12, 2)->nullable()->after('price_total');
            }
            if ($needsDiscount) {
                $table->decimal('discount_amount', 12, 2)->default(0)->after($needsSubtotal ? 'price_subtotal' : 'price_total');
            }
            if ($needsCouponId) {
                $table->foreignId('coupon_id')
                    ->nullable()
                    ->constrained('coupons')
                    ->nullOnDelete()
                    ->after($needsDiscount ? 'discount_amount' : ($needsSubtotal ? 'price_subtotal' : 'price_total'));
            }
            if ($needsCouponCode) {
                $table->string('coupon_code')
                    ->nullable()
                    ->after($needsCouponId ? 'coupon_id' : ($needsDiscount ? 'discount_amount' : ($needsSubtotal ? 'price_subtotal' : 'price_total')));
            }
        });
    }

    public function down(): void
    {
        $hasPaymentMethod = Schema::hasColumn('orders', 'payment_method');
        $hasPaymentChannel = Schema::hasColumn('orders', 'payment_channel');
        $hasPaymentStatus = Schema::hasColumn('orders', 'payment_status');

        $hasSubtotal = Schema::hasColumn('orders', 'price_subtotal');
        $hasDiscount = Schema::hasColumn('orders', 'discount_amount');
        $hasCouponId = Schema::hasColumn('orders', 'coupon_id');
        $hasCouponCode = Schema::hasColumn('orders', 'coupon_code');

        if (
            !$hasPaymentMethod &&
            !$hasPaymentChannel &&
            !$hasPaymentStatus &&
            !$hasSubtotal &&
            !$hasDiscount &&
            !$hasCouponId &&
            !$hasCouponCode
        ) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) use (
            $hasPaymentMethod,
            $hasPaymentChannel,
            $hasPaymentStatus,
            $hasSubtotal,
            $hasDiscount,
            $hasCouponId,
            $hasCouponCode
        ) {
            if ($hasCouponCode) {
                $table->dropColumn('coupon_code');
            }
            if ($hasCouponId) {
                $table->dropConstrainedForeignId('coupon_id');
            }
            if ($hasDiscount) {
                $table->dropColumn('discount_amount');
            }
            if ($hasSubtotal) {
                $table->dropColumn('price_subtotal');
            }

            if ($hasPaymentStatus) {
                $table->dropColumn('payment_status');
            }
            if ($hasPaymentChannel) {
                $table->dropColumn('payment_channel');
            }
            if ($hasPaymentMethod) {
                $table->dropColumn('payment_method');
            }
        });
    }
};

