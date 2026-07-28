<?php

use App\Models\ClassSessionTime;
use App\Models\Classroom;
use App\Support\SessionTimetable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Refactor matrix jam sesi diniyyah dari per-gender (`classroom_group`) ke
 * per-classroom (`classroom_id` FK).
 *
 * Alasan: matrix per-gender tidak bisa mengekspresikan pengecualian Mustawa 1
 * di Kamis — M1 09:50 adalah Tahfidz (bukan Tafsir), sedangkan M2-M6 punya
 * Tafsir 09:50-10:20. Per-classroom membuat tiap kelas punya matrix sendiri.
 *
 * Berjalan otomatis saat migrate (deploy Coolify). Idempoten via firstOrCreate
 * pada reseed (lihat App\Support\SessionTimetable::seedForClassroom).
 *
 * Catatan: baris lama (22 baris per-gender) dihapus lalu di-reseed per-classroom
 * untuk classroom yang cocok pola "Mustawa N Ikhwan/Akhwat" (prod: 12 kelas).
 * Classroom non-Mustawa di-skip (tidak punya matrix diniyyah). Di environment
 * test (RefreshDatabase) tidak ada classroom saat migrate → reseed menghasilkan
 * 0 baris; test men-seed sendiri via SessionTimetable::seedForClassroom().
 */
return new class extends Migration
{
    public function up(): void
    {
        // Bersihkan baris per-gender lama sebelum mengubah skema.
        ClassSessionTime::query()->delete();

        Schema::table('class_session_times', function (Blueprint $table) {
            $table->dropUnique('cst_unique');
            $table->dropIndex('class_session_times_classroom_group_day_of_week_index');
            $table->dropColumn('classroom_group');

            $table->foreignId('classroom_id')
                ->after('id')
                ->constrained('classrooms')
                ->cascadeOnDelete();

            $table->unique(['classroom_id', 'day_of_week', 'class_session_id'], 'cst_unique');
            $table->index(['classroom_id', 'day_of_week'], 'cst_classroom_day_index');
        });

        // Reseed per-classroom untuk classroom yang cocok pola Mustawa.
        foreach (Classroom::all() as $classroom) {
            SessionTimetable::seedForClassroom($classroom);
        }
    }

    public function down(): void
    {
        Schema::table('class_session_times', function (Blueprint $table) {
            $table->dropForeign(['classroom_id']);
            $table->dropUnique('cst_unique');
            $table->dropIndex('cst_classroom_day_index');
            $table->dropColumn('classroom_id');

            $table->string('classroom_group')->after('id');
            $table->unique(['classroom_group', 'day_of_week', 'class_session_id'], 'cst_unique');
            $table->index(['classroom_group', 'day_of_week'], 'class_session_times_classroom_group_day_of_week_index');
        });

        // Catatan: reseed per-gender lama tidak dilakukan di down() — matrix
        // per-gender sebelumnya di-seed oleh migration 000003 yang sudah berjalan.
    }
};