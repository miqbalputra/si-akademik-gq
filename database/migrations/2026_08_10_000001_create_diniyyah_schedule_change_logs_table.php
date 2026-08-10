<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Riwayat perubahan jadwal mengajar & penugasan diniyyah (audit log).
 *
 * Meniru pola `score_change_logs` (tabel log custom + observer). Setiap baris
 * mencatat satu perubahan (created/updated/deleted) pada DiniyyahTeachingSchedule
 * atau DiniyyahTeacherAssignment, beserta summary human-readable Indonesia
 * yang dibangun saat log-time (survive setelah relasi terkait dihapus).
 *
 * `teacher_id` denormalized (guru pemilik SETELAH perubahan) + `old_teacher_id`
 * (guru lama saat swap) memudahkan filter portal guru
 * (`where teacher_id=? or old_teacher_id=?`). FK memakai nullOnDelete agar
 * log tetap utuh saat assignment/schedule/teacher/user dihapus — hanya FK
 * di-null-out, sedangkan `change_summary` + `teacher_id` tetap terbaca.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diniyyah_schedule_change_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->nullable()->constrained('teachers')->nullOnDelete();
            $table->foreignId('old_teacher_id')->nullable()->constrained('teachers')->nullOnDelete();
            $table->foreignId('diniyyah_teacher_assignment_id')->nullable()->constrained('diniyyah_teacher_assignments')->nullOnDelete();
            $table->foreignId('diniyyah_teaching_schedule_id')->nullable()->constrained('diniyyah_teaching_schedules')->nullOnDelete();
            $table->string('entity_type', 16); // 'schedule' | 'assignment'
            $table->string('event', 16);       // 'created' | 'updated' | 'deleted'
            $table->text('change_summary');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['teacher_id', 'created_at'], 'dscl_teacher_created_index');
            $table->index('old_teacher_id', 'dscl_old_teacher_index');
            $table->index(['diniyyah_teacher_assignment_id', 'created_at'], 'dscl_assignment_created_index');
            $table->index(['entity_type', 'event'], 'dscl_entity_event_index');
            $table->index('changed_by', 'dscl_changed_by_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diniyyah_schedule_change_logs');
    }
};