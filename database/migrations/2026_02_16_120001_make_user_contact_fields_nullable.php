<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $driver = DB::getDriverName();

        // SQLite column alterations are limited; keep current schema for local dev using sqlite.
        if ($driver === 'sqlite') {
            return;
        }

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE users MODIFY name VARCHAR(255) NULL');
            DB::statement('ALTER TABLE users MODIFY email VARCHAR(255) NULL');
            DB::statement('ALTER TABLE users MODIFY password VARCHAR(255) NULL');
            DB::statement('ALTER TABLE users MODIFY phone VARCHAR(20) NULL');
            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE users ALTER COLUMN name DROP NOT NULL');
            DB::statement('ALTER TABLE users ALTER COLUMN email DROP NOT NULL');
            DB::statement('ALTER TABLE users ALTER COLUMN password DROP NOT NULL');
            DB::statement('ALTER TABLE users ALTER COLUMN phone DROP NOT NULL');
        }
    }

    public function down(): void
    {
        // Intentionally left blank: making columns NOT NULL again can fail if rows already contain NULLs.
    }
};

