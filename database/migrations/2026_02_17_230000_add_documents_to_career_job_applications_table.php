<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('career_job_applications', function (Blueprint $table): void {
            if (! Schema::hasColumn('career_job_applications', 'cv_file_path')) {
                $table->string('cv_file_path')->nullable()->after('cover_letter');
            }

            if (! Schema::hasColumn('career_job_applications', 'application_letter_file_path')) {
                $table->string('application_letter_file_path')->nullable()->after('cv_file_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('career_job_applications', function (Blueprint $table): void {
            if (Schema::hasColumn('career_job_applications', 'application_letter_file_path')) {
                $table->dropColumn('application_letter_file_path');
            }

            if (Schema::hasColumn('career_job_applications', 'cv_file_path')) {
                $table->dropColumn('cv_file_path');
            }
        });
    }
};

