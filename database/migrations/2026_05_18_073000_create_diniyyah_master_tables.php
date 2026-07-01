<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diniyyah_subjects', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('arabic_name')->nullable();
            $table->string('default_assessment_method')->default('weighted');
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('diniyyah_class_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classroom_term_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('diniyyah_subjects')->cascadeOnDelete();
            $table->string('assessment_method');
            $table->decimal('kkm', 5, 2)->nullable();
            $table->unsignedTinyInteger('daily_weight')->nullable();
            $table->unsignedTinyInteger('exam_weight')->nullable();
            $table->boolean('appears_on_ledger')->default(true);
            $table->boolean('appears_on_report')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['classroom_term_id', 'subject_id']);
            $table->index('classroom_term_id');
            $table->index('subject_id');
        });

        Schema::create('diniyyah_teacher_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diniyyah_class_subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained()->cascadeOnDelete();
            $table->string('assignment_role')->default('primary');
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['diniyyah_class_subject_id', 'teacher_id']);
            $table->index('diniyyah_class_subject_id');
            $table->index('teacher_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diniyyah_teacher_assignments');
        Schema::dropIfExists('diniyyah_class_subjects');
        Schema::dropIfExists('diniyyah_subjects');
    }
};
