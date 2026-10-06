<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicate = DB::table('student_attendances')
            ->select('student_id', 'attendance_date')
            ->groupBy('student_id', 'attendance_date')
            ->havingRaw('COUNT(*) > 1')
            ->first();

        if ($duplicate) {
            throw new RuntimeException(
                "Presensi ganda ditemukan untuk santri {$duplicate->student_id} pada {$duplicate->attendance_date}. " .
                'Rekonsiliasi status tersebut sebelum menjalankan migrasi ini.'
            );
        }

        Schema::table('student_attendances', function ($table): void {
            $table->dropUnique('student_attendances_class_enrollment_id_attendance_date_unique');
            $table->unique(['student_id', 'attendance_date']);
        });
    }

    public function down(): void
    {
        Schema::table('student_attendances', function ($table): void {
            $table->dropUnique('student_attendances_student_id_attendance_date_unique');
            $table->unique(['class_enrollment_id', 'attendance_date']);
        });
    }
};
