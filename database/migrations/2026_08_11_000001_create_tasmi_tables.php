<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Penugasan guru sebagai PJ Tasmi' per periode akademik.
        // Ustadz (gender = male) hanya bisa menguji kelas laki-laki (ikhwan),
        // ustadzah (gender = female) hanya bisa menguji kelas perempuan (akhwat).
        // Aturan ini di-enforce di controller berdasarkan teacher.gender,
        // bukan disimpan di tabel, agar konsisten dengan data guru.
        Schema::create('tasmi_examiner_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_term_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('active');
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['academic_term_id', 'teacher_id'], 'tasmi_examiner_unique_idx');
            $table->index('academic_term_id');
            $table->index('teacher_id');
            $table->index('status');
        });

        // Record ujian tasmi' per santri.
        // exam_type: '1_juz' (setoran 1 juz full) atau '5_juz' (setoran 5 juz full).
        // Untuk 1_juz: juz_start = juz_end = juz yang diujikan (1-30).
        // Untuk 5_juz: juz_start sampai juz_end (rentang 5 juz, mis. 1-5, 26-30).
        // predicate: Maqbul, Jayyid, Jayyid Jiddan, Mumtaz.
        // Data disimpan langsung final. Setiap perubahan score/predicate/field penting
        // dicatat di score_change_logs oleh TasmiRecordObserver.
        Schema::create('tasmi_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_term_id')->constrained()->cascadeOnDelete();
            $table->foreignId('classroom_term_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('class_enrollment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('examiner_teacher_id')->constrained('teachers')->cascadeOnDelete();
            $table->foreignId('tasmi_examiner_assignment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('exam_type'); // '1_juz' | '5_juz'
            $table->unsignedSmallInteger('juz_start');
            $table->unsignedSmallInteger('juz_end');
            $table->string('exam_day_label')->nullable(); // mis. "Hari 1", "Hari 2" — label hari ujian
            $table->date('exam_date'); // tanggal masehi
            $table->string('hijri_date')->nullable(); // tanggal hijriyah (teks bebas)
            $table->string('predicate'); // 'maqbul' | 'jayyid' | 'jayyid_jiddan' | 'mumtaz'
            $table->text('notes')->nullable();
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('input_at')->nullable();
            $table->foreignId('last_updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Mencegah dobel input untuk santri yang sama pada periode, tanggal, dan tipe ujian yang sama.
            $table->unique(['academic_term_id', 'student_id', 'exam_type', 'exam_date'], 'tasmi_record_unique_idx');
            $table->index('academic_term_id');
            $table->index('classroom_term_id');
            $table->index('class_enrollment_id');
            $table->index('student_id');
            $table->index('examiner_teacher_id');
            $table->index('tasmi_examiner_assignment_id');
            $table->index('exam_type');
            $table->index('exam_date');
            $table->index('predicate');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasmi_records');
        Schema::dropIfExists('tasmi_examiner_assignments');
    }
};