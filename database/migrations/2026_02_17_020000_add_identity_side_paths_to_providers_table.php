<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('providers')) {
            return;
        }

        Schema::table('providers', function (Blueprint $table) {
            if (!Schema::hasColumn('providers', 'id_document_front_path')) {
                $table->string('id_document_front_path')->nullable()->after('id_document_path');
            }

            if (!Schema::hasColumn('providers', 'id_document_back_path')) {
                $table->string('id_document_back_path')->nullable()->after('id_document_front_path');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('providers')) {
            return;
        }

        Schema::table('providers', function (Blueprint $table) {
            if (Schema::hasColumn('providers', 'id_document_back_path')) {
                $table->dropColumn('id_document_back_path');
            }

            if (Schema::hasColumn('providers', 'id_document_front_path')) {
                $table->dropColumn('id_document_front_path');
            }
        });
    }
};

