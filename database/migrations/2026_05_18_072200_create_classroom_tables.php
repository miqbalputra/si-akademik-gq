<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classrooms', function (Blueprint $table) {
            $table->id();
            $table->string('name')->index();
            $table->string('level_name')->nullable();
            $table->string('gender_group')->default('mixed');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('classroom_terms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_term_id')->constrained()->cascadeOnDelete();
            $table->foreignId('classroom_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('capacity')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();

            $table->unique(['academic_term_id', 'classroom_id']);
            $table->index('academic_term_id');
            $table->index('classroom_id');
        });

        Schema::create('class_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_term_id')->constrained()->cascadeOnDelete();
            $table->foreignId('classroom_term_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('roll_number')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();

            $table->unique(['academic_term_id', 'student_id']);
            $table->index('academic_term_id');
            $table->index('classroom_term_id');
            $table->index('student_id');
        });

        Schema::create('homeroom_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classroom_term_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained()->cascadeOnDelete();
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->timestamps();

            $table->index('classroom_term_id');
            $table->index('teacher_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('homeroom_assignments');
        Schema::dropIfExists('class_enrollments');
        Schema::dropIfExists('classroom_terms');
        Schema::dropIfExists('classrooms');
    }
};
