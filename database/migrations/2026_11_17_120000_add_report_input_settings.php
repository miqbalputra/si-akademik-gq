<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('academic_terms', function (Blueprint $table) {
            $table->date('report_issue_date')->nullable();
        });
        Schema::create('tahfidz_report_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_term_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->string('juz_tested')->nullable();
            foreach (['uas_score', 'sabaq_score', 'sabqi_score', 'manzil_score'] as $field) {
                $table->decimal($field, 5, 2)->nullable();
            }
            $table->string('status');
            $table->foreignId('input_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->unique(['academic_term_id', 'student_id', 'version'], 'tahfidz_report_entry_version');
        });
    }

    public function down(): void
    {
        if (DB::table('tahfidz_report_entries')->exists()
            || DB::table('academic_terms')->whereNotNull('report_issue_date')->exists()) {
            throw new RuntimeException('Rollback dibatalkan: nilai final/tanggal rapor sudah terisi. Data harus diamankan sebelum perubahan skema.');
        }
        Schema::dropIfExists('tahfidz_report_entries');
        Schema::table('academic_terms', fn (Blueprint $table) => $table->dropColumn('report_issue_date'));
    }
};
