<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('providers')) {
            return;
        }

        if (Schema::hasColumn('providers', 'wallet_balance')) {
            return;
        }

        Schema::table('providers', function (Blueprint $table) {
            $table->decimal('wallet_balance', 12, 2)
                ->default(0)
                ->after('debt_balance')
                ->index();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('providers')) {
            return;
        }

        if (!Schema::hasColumn('providers', 'wallet_balance')) {
            return;
        }

        Schema::table('providers', function (Blueprint $table) {
            $table->dropColumn('wallet_balance');
        });
    }
};

