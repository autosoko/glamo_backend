<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('career_jobs', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('employment_type', 40)->default('full_time')->index();
            $table->string('location', 120)->nullable();
            $table->unsignedSmallInteger('positions_count')->nullable();
            $table->string('status', 20)->default('draft')->index();
            $table->boolean('is_active')->default(true)->index();
            $table->date('application_deadline')->nullable()->index();
            $table->text('summary')->nullable();
            $table->longText('description');
            $table->text('requirements')->nullable();
            $table->foreignId('created_by_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('career_jobs');
    }
};

