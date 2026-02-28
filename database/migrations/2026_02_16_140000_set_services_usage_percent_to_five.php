<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('services') || !Schema::hasColumn('services', 'usage_percent')) {
            return;
        }

        DB::table('services')->update([
            'usage_percent' => 5,
        ]);

        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE services MODIFY usage_percent DECIMAL(5,2) NOT NULL DEFAULT 5');
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE services ALTER COLUMN usage_percent SET DEFAULT 5');
            DB::statement('ALTER TABLE services ALTER COLUMN usage_percent SET NOT NULL');
        }
    }

    public function down(): void
    {
        // Intentionally left blank.
    }
};

