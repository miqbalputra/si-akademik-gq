<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diniyyah_assessment_sets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diniyyah_class_subject_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('tested_material')->nullable();
            $table->string('assessment_method');
            $table->decimal('kkm', 5, 2)->nullable();
            $table->unsignedTinyInteger('daily_weight')->nullable();
            $table->unsignedTinyInteger('exam_weight')->nullable();
            $table->boolean('appears_on_ledger')->default(true);
            $table->boolean('appears_on_report')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('status')->default('draft')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('diniyyah_class_subject_id');
        });

        Schema::create('diniyyah_score_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diniyyah_assessment_set_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->string('component_group')->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_required')->default(true);
            $table->timestamps();

            $table->unique(['diniyyah_assessment_set_id', 'code']);
            $table->index('diniyyah_assessment_set_id');
        });

        Schema::create('diniyyah_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diniyyah_assessment_set_id')->constrained()->cascadeOnDelete();
            $table->foreignId('diniyyah_score_component_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_enrollment_id')->constrained()->cascadeOnDelete();
            $table->decimal('score', 5, 2)->nullable();
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('input_at')->nullable();
            $table->string('status')->default('draft')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['diniyyah_score_component_id', 'class_enrollment_id']);
            $table->index('diniyyah_assessment_set_id');
            $table->index('class_enrollment_id');
            $table->index('input_by');
        });

        Schema::create('diniyyah_assessment_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diniyyah_assessment_set_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_enrollment_id')->constrained()->cascadeOnDelete();
            $table->decimal('daily_raw_score', 5, 2)->nullable();
            $table->decimal('exam_raw_score', 5, 2)->nullable();
            $table->decimal('daily_weighted_score', 5, 2)->nullable();
            $table->decimal('exam_weighted_score', 5, 2)->nullable();
            $table->decimal('final_score', 5, 2)->nullable()->index();
            $table->decimal('kkm', 5, 2)->nullable();
            $table->boolean('is_complete')->default(false)->index();
            $table->boolean('is_passed')->default(false);
            $table->timestamp('calculated_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();

            $table->unique(['diniyyah_assessment_set_id', 'class_enrollment_id']);
            $table->index('class_enrollment_id');
        });

        Schema::create('diniyyah_score_validations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diniyyah_assessment_set_id')->constrained()->cascadeOnDelete();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('pending')->index();
            $table->timestamp('validated_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('diniyyah_assessment_set_id');
            $table->index('validated_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diniyyah_score_validations');
        Schema::dropIfExists('diniyyah_assessment_results');
        Schema::dropIfExists('diniyyah_scores');
        Schema::dropIfExists('diniyyah_score_components');
        Schema::dropIfExists('diniyyah_assessment_sets');
    }
};
