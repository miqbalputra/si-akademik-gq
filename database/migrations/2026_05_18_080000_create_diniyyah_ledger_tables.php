<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diniyyah_ledger_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_term_id')->constrained()->cascadeOnDelete();
            $table->foreignId('classroom_term_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('status')->default('draft')->index();
            $table->timestamp('generated_at')->nullable()->index();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('snapshot_data')->nullable();
            $table->timestamps();

            $table->unique(['academic_term_id', 'classroom_term_id']);
        });

        Schema::create('diniyyah_ledger_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diniyyah_ledger_snapshot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_enrollment_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_number')->nullable();
            $table->string('student_name');
            $table->string('student_nis')->nullable();
            $table->decimal('total_diniyyah_score', 8, 2)->nullable();
            $table->decimal('average_diniyyah_score', 5, 2)->nullable();
            $table->unsignedInteger('rank_in_class')->nullable()->index();
            $table->timestamps();

            $table->unique(['diniyyah_ledger_snapshot_id', 'class_enrollment_id']);
            $table->index('diniyyah_ledger_snapshot_id');
            $table->index('class_enrollment_id');
        });

        Schema::create('diniyyah_ledger_cells', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diniyyah_ledger_row_id')->constrained()->cascadeOnDelete();
            $table->string('column_key');
            $table->string('label');
            $table->string('source_type');
            $table->unsignedBigInteger('source_id')->nullable();
            $table->decimal('value_numeric', 8, 2)->nullable();
            $table->text('value_text')->nullable();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->timestamps();

            $table->unique(['diniyyah_ledger_row_id', 'column_key']);
            $table->index(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diniyyah_ledger_cells');
        Schema::dropIfExists('diniyyah_ledger_rows');
        Schema::dropIfExists('diniyyah_ledger_snapshots');
    }
};
