<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_term_id')->constrained()->cascadeOnDelete();
            $table->foreignId('classroom_term_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_enrollment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('report_type')->default('diniyyah');
            $table->string('status')->default('draft')->index();
            $table->date('issue_date')->nullable();
            $table->decimal('total_score', 8, 2)->nullable();
            $table->decimal('average_score', 5, 2)->nullable();
            $table->unsignedInteger('rank_in_class')->nullable();
            $table->text('homeroom_note')->nullable();
            $table->timestamp('published_at')->nullable()->index();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['academic_term_id', 'class_enrollment_id', 'report_type']);
            $table->index('student_id');
            $table->index('class_enrollment_id');
        });

        Schema::create('report_card_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_card_id')->constrained()->cascadeOnDelete();
            $table->string('line_type');
            $table->string('source_type');
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('subject_name')->nullable();
            $table->string('subject_arabic_name')->nullable();
            $table->string('tested_material')->nullable();
            $table->decimal('kkm', 5, 2)->nullable();
            $table->decimal('score_numeric', 8, 2)->nullable();
            $table->string('score_letter')->nullable();
            $table->string('score_words')->nullable();
            $table->boolean('is_passed')->default(false);
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->timestamps();

            $table->index('report_card_id');
            $table->index('line_type');
            $table->index(['source_type', 'source_id']);
        });

        Schema::create('report_card_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_card_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sick_count')->default(0);
            $table->unsignedInteger('permission_count')->default(0);
            $table->unsignedInteger('absent_count')->default(0);
            $table->timestamps();

            $table->unique('report_card_id');
        });

        Schema::create('report_card_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_card_id')->constrained()->cascadeOnDelete();
            $table->string('layout_version')->default('diniyyah-v1');
            $table->json('snapshot_data');
            $table->string('pdf_path')->nullable();
            $table->timestamp('generated_at');
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('report_card_id');
            $table->index('generated_at');
        });

        Schema::create('report_card_signatures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_card_id')->constrained()->cascadeOnDelete();
            $table->string('role_label');
            $table->string('person_name')->nullable();
            $table->foreignId('teacher_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('report_card_id');
            $table->index('teacher_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_card_signatures');
        Schema::dropIfExists('report_card_snapshots');
        Schema::dropIfExists('report_card_attendances');
        Schema::dropIfExists('report_card_lines');
        Schema::dropIfExists('report_cards');
    }
};
