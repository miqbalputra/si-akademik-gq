<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tahfidz_halaqahs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_term_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->foreignId('teacher_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assistant_teacher_id')->nullable()->constrained('teachers')->nullOnDelete();
            $table->string('status')->default('active');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['academic_term_id', 'name']);
            $table->index('academic_term_id');
            $table->index('teacher_id');
            $table->index('status');
        });

        Schema::create('tahfidz_halaqah_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tahfidz_halaqah_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_enrollment_id')->nullable()->constrained()->nullOnDelete();
            $table->date('joined_at')->nullable();
            $table->date('left_at')->nullable();
            $table->string('status')->default('active');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['tahfidz_halaqah_id', 'student_id']);
            $table->index('student_id');
            $table->index('class_enrollment_id');
            $table->index('status');
        });

        Schema::create('tahfidz_weeks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_term_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('week_number');
            $table->string('month_label')->nullable();
            $table->unsignedInteger('month_number')->nullable();
            $table->string('date_label')->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['academic_term_id', 'week_number']);
            $table->index('academic_term_id');
            $table->index('starts_on');
            $table->index('month_number');
        });

        Schema::create('tahfidz_weekly_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tahfidz_halaqah_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tahfidz_halaqah_member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tahfidz_week_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('surah_ayat')->nullable();
            $table->string('sabaq_amount')->nullable();
            $table->unsignedInteger('sabaq_baris')->nullable();
            $table->decimal('score', 5, 2)->nullable();
            $table->string('category')->default('sabaq');
            $table->text('notes')->nullable();
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('input_at')->nullable();
            $table->string('status')->default('draft');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tahfidz_halaqah_member_id', 'tahfidz_week_id']);
            $table->index('tahfidz_halaqah_id');
            $table->index('tahfidz_week_id');
            $table->index('student_id');
            $table->index('score');
            $table->index('status');
            $table->index('category');
        });

        Schema::create('tahfidz_monthly_recaps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tahfidz_halaqah_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tahfidz_halaqah_member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_term_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('month_number');
            $table->string('month_label')->nullable();
            $table->string('sabaq_monthly')->nullable();
            $table->unsignedInteger('sabaq_monthly_baris')->default(0);
            $table->decimal('average_score', 5, 2)->nullable();
            $table->string('total_hafalan')->nullable();
            $table->string('manzil_submitted')->nullable();
            $table->decimal('manzil_score', 5, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['tahfidz_halaqah_member_id', 'month_number']);
            $table->index('tahfidz_halaqah_id');
            $table->index('student_id');
            $table->index('academic_term_id');
            $table->index('month_number');
        });

        Schema::create('tahfidz_semester_recaps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tahfidz_halaqah_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tahfidz_halaqah_member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_term_id')->constrained()->cascadeOnDelete();
            $table->decimal('sabaq_semester_score', 5, 2)->nullable();
            $table->string('sabaq_category')->nullable();
            $table->decimal('manzil_average_score', 5, 2)->nullable();
            $table->string('manzil_category')->nullable();
            $table->decimal('sabqi_score', 5, 2)->nullable();
            $table->text('semester_notes')->nullable();
            $table->string('status')->default('draft');
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();

            $table->unique('tahfidz_halaqah_member_id');
            $table->index('tahfidz_halaqah_id');
            $table->index('student_id');
            $table->index('academic_term_id');
            $table->index('status');
        });

        Schema::create('tahfidz_uas_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_term_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('max_score')->default(20);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['academic_term_id', 'code']);
            $table->index('academic_term_id');
            $table->index('is_active');
            $table->index('sort_order');
        });

        Schema::create('tahfidz_uas_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_term_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('day_number');
            $table->date('test_date')->nullable();
            $table->string('label')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['academic_term_id', 'day_number']);
            $table->index('academic_term_id');
            $table->index('test_date');
        });

        Schema::create('tahfidz_uas_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_term_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tahfidz_uas_day_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tahfidz_uas_category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tahfidz_halaqah_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('score', 5, 2)->nullable();
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['tahfidz_uas_day_id', 'tahfidz_uas_category_id', 'student_id'], 'uas_score_unique');
            $table->index('academic_term_id');
            $table->index('student_id');
            $table->index('tahfidz_uas_day_id');
            $table->index('tahfidz_uas_category_id');
            $table->index('tahfidz_halaqah_id');
        });

        Schema::create('tahfidz_uas_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_term_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tahfidz_halaqah_id')->nullable()->constrained()->nullOnDelete();
            $table->string('juz_tested')->nullable();
            $table->json('daily_totals')->nullable();
            $table->decimal('final_score', 5, 2)->nullable();
            $table->string('predicate')->nullable();
            $table->boolean('is_complete')->default(false);
            $table->timestamp('calculated_at')->nullable();
            $table->string('status')->default('draft');
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable();
            $table->timestamps();

            $table->unique(['academic_term_id', 'student_id']);
            $table->index('student_id');
            $table->index('tahfidz_halaqah_id');
            $table->index('status');
            $table->index('final_score');
        });

        Schema::create('tahfidz_validations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tahfidz_halaqah_id')->constrained()->cascadeOnDelete();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status');
            $table->timestamp('validated_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('tahfidz_halaqah_id');
            $table->index('validated_by');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tahfidz_validations');
        Schema::dropIfExists('tahfidz_uas_results');
        Schema::dropIfExists('tahfidz_uas_scores');
        Schema::dropIfExists('tahfidz_uas_days');
        Schema::dropIfExists('tahfidz_uas_categories');
        Schema::dropIfExists('tahfidz_semester_recaps');
        Schema::dropIfExists('tahfidz_monthly_recaps');
        Schema::dropIfExists('tahfidz_weekly_scores');
        Schema::dropIfExists('tahfidz_weeks');
        Schema::dropIfExists('tahfidz_halaqah_members');
        Schema::dropIfExists('tahfidz_halaqahs');
    }
};