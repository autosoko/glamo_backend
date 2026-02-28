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

        $addColumn = function (string $column, callable $definition): void {
            if (Schema::hasColumn('providers', $column)) {
                return;
            }

            Schema::table('providers', function (Blueprint $table) use ($definition) {
                $definition($table);
            });
        };

        $addColumn('first_name', fn (Blueprint $table) => $table->string('first_name', 80)->nullable());
        $addColumn('middle_name', fn (Blueprint $table) => $table->string('middle_name', 80)->nullable());
        $addColumn('last_name', fn (Blueprint $table) => $table->string('last_name', 80)->nullable());

        $addColumn('id_type', fn (Blueprint $table) => $table->string('id_type', 40)->nullable()->index());
        $addColumn('id_number', fn (Blueprint $table) => $table->string('id_number', 120)->nullable());
        $addColumn('id_document_path', fn (Blueprint $table) => $table->string('id_document_path')->nullable());

        $addColumn('selected_skills', fn (Blueprint $table) => $table->json('selected_skills')->nullable());
        $addColumn('education_status', fn (Blueprint $table) => $table->string('education_status', 30)->nullable()->index());
        $addColumn('certificate_path', fn (Blueprint $table) => $table->string('certificate_path')->nullable());
        $addColumn('qualification_docs', fn (Blueprint $table) => $table->json('qualification_docs')->nullable());
        $addColumn('references_text', fn (Blueprint $table) => $table->text('references_text')->nullable());
        $addColumn('demo_interview_acknowledged', fn (Blueprint $table) => $table->boolean('demo_interview_acknowledged')->default(false));

        $addColumn('interview_required', fn (Blueprint $table) => $table->boolean('interview_required')->default(false)->index());
        $addColumn('interview_status', fn (Blueprint $table) => $table->string('interview_status', 40)->nullable()->index());
        $addColumn('interview_scheduled_at', fn (Blueprint $table) => $table->dateTime('interview_scheduled_at')->nullable());
        $addColumn('interview_type', fn (Blueprint $table) => $table->string('interview_type', 120)->nullable());
        $addColumn('interview_location', fn (Blueprint $table) => $table->string('interview_location', 180)->nullable());

        $addColumn('region', fn (Blueprint $table) => $table->string('region', 80)->nullable());
        $addColumn('district', fn (Blueprint $table) => $table->string('district', 80)->nullable());
        $addColumn('ward', fn (Blueprint $table) => $table->string('ward', 80)->nullable());
        $addColumn('village', fn (Blueprint $table) => $table->string('village', 120)->nullable());
        $addColumn('house_number', fn (Blueprint $table) => $table->string('house_number', 80)->nullable());

        $addColumn('gender', fn (Blueprint $table) => $table->string('gender', 20)->nullable());
        $addColumn('date_of_birth', fn (Blueprint $table) => $table->date('date_of_birth')->nullable());
        $addColumn('years_experience', fn (Blueprint $table) => $table->unsignedTinyInteger('years_experience')->nullable());
        $addColumn('alt_phone', fn (Blueprint $table) => $table->string('alt_phone', 20)->nullable());
        $addColumn('emergency_contact_name', fn (Blueprint $table) => $table->string('emergency_contact_name', 120)->nullable());
        $addColumn('emergency_contact_phone', fn (Blueprint $table) => $table->string('emergency_contact_phone', 20)->nullable());

        $addColumn('approval_note', fn (Blueprint $table) => $table->text('approval_note')->nullable());
        $addColumn('onboarding_submitted_at', fn (Blueprint $table) => $table->timestamp('onboarding_submitted_at')->nullable()->index());
        $addColumn('onboarding_completed_at', fn (Blueprint $table) => $table->timestamp('onboarding_completed_at')->nullable()->index());
    }

    public function down(): void
    {
        if (!Schema::hasTable('providers')) {
            return;
        }

        $dropColumn = function (string $column): void {
            if (!Schema::hasColumn('providers', $column)) {
                return;
            }

            Schema::table('providers', function (Blueprint $table) use ($column) {
                $table->dropColumn($column);
            });
        };

        $columns = [
            'first_name',
            'middle_name',
            'last_name',
            'id_type',
            'id_number',
            'id_document_path',
            'selected_skills',
            'education_status',
            'certificate_path',
            'qualification_docs',
            'references_text',
            'demo_interview_acknowledged',
            'interview_required',
            'interview_status',
            'interview_scheduled_at',
            'interview_type',
            'interview_location',
            'region',
            'district',
            'ward',
            'village',
            'house_number',
            'gender',
            'date_of_birth',
            'years_experience',
            'alt_phone',
            'emergency_contact_name',
            'emergency_contact_phone',
            'approval_note',
            'onboarding_submitted_at',
            'onboarding_completed_at',
        ];

        foreach ($columns as $column) {
            $dropColumn($column);
        }
    }
};

