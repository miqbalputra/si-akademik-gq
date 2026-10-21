<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('academic_terms', fn (Blueprint $table) => $table->boolean('kkm_workflow_enabled')->default(false));
        Schema::create('diniyyah_kkm_scopes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academic_term_id')->constrained()->restrictOnDelete();
            $table->foreignId('subject_id')->constrained('diniyyah_subjects')->restrictOnDelete();
            $table->unsignedTinyInteger('mustawa');
            $table->foreignId('assigned_teacher_id')->nullable()->constrained('teachers')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['academic_term_id', 'subject_id', 'mustawa'], 'kkm_scope_unique');
        });
        Schema::create('diniyyah_kkm_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('diniyyah_kkm_scope_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->string('status')->default('draft')->index();
            $table->json('criteria')->nullable();
            $table->decimal('average', 7, 4)->nullable();
            $table->unsignedTinyInteger('final_kkm')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamps();
            $table->unique(['diniyyah_kkm_scope_id', 'version'], 'kkm_revision_version_unique');
        });
        Schema::table('diniyyah_kkm_scopes', function (Blueprint $table): void {
            $table->foreignId('active_revision_id')->nullable()->constrained('diniyyah_kkm_revisions')->restrictOnDelete();
            $table->foreignId('working_revision_id')->nullable()->constrained('diniyyah_kkm_revisions')->restrictOnDelete();
        });
        foreach (['diniyyah_class_subjects', 'diniyyah_assessment_sets', 'diniyyah_assessment_results', 'report_card_lines'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->foreignId('kkm_revision_id')->nullable()->constrained('diniyyah_kkm_revisions')->restrictOnDelete());
        }

        // Existing frozen semesters remain historical. Open semesters require approval,
        // without treating any old KKM value as an approved revision.
        $frozen = DB::table('diniyyah_ledger_snapshots')->whereIn('status', ['locked', 'published'])->pluck('academic_term_id')
            ->merge(DB::table('report_cards')->whereIn('status', ['locked', 'published'])->pluck('academic_term_id'))->unique();
        DB::table('academic_terms')->whereNotIn('id', $frozen)
            ->where(fn ($q) => $q->where('is_active', true)->orWhere('ends_at', '>=', now()->toDateString()))
            ->update(['kkm_workflow_enabled' => true]);
    }

    public function down(): void
    {
        foreach (['report_card_lines', 'diniyyah_assessment_results', 'diniyyah_assessment_sets', 'diniyyah_class_subjects'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropConstrainedForeignId('kkm_revision_id'));
        }
        Schema::table('diniyyah_kkm_scopes', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('active_revision_id');
            $table->dropConstrainedForeignId('working_revision_id');
        });
        Schema::dropIfExists('diniyyah_kkm_revisions');
        Schema::dropIfExists('diniyyah_kkm_scopes');
        Schema::table('academic_terms', fn (Blueprint $table) => $table->dropColumn('kkm_workflow_enabled'));
    }
};
