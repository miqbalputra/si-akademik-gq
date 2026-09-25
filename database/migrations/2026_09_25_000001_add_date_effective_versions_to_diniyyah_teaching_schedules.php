<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('diniyyah_teaching_schedules', function (Blueprint $table): void {
            $table->dropUnique('dts_unique');
            $table->date('effective_from')->nullable()->after('day_of_week');
            $table->date('effective_until')->nullable()->after('effective_from');
            $table->string('version_status', 16)->default('legacy')->after('effective_until');
            $table->string('change_type', 24)->nullable()->after('version_status');
            $table->text('change_reason')->nullable()->after('change_type');
            $table->string('request_reference', 120)->nullable()->after('change_reason');
            $table->index(['diniyyah_teacher_assignment_id', 'version_status', 'effective_from', 'effective_until'], 'dts_version_range_index');
            $table->index(['diniyyah_teacher_assignment_id', 'day_of_week', 'class_session_id'], 'dts_slot_lookup_index');
        });
    }

    public function down(): void
    {
        if (DB::table('diniyyah_teaching_schedules')->where('version_status', '!=', 'legacy')->exists()) {
            throw new RuntimeException('Tidak dapat membatalkan versi jadwal setelah ada perubahan bertanggal. Ekspor atau pulihkan data secara terencana terlebih dahulu.');
        }

        Schema::table('diniyyah_teaching_schedules', function (Blueprint $table): void {
            $table->dropIndex('dts_version_range_index');
            $table->dropIndex('dts_slot_lookup_index');
            $table->dropColumn([
                'effective_from',
                'effective_until',
                'version_status',
                'change_type',
                'change_reason',
                'request_reference',
            ]);
            $table->unique(['diniyyah_teacher_assignment_id', 'day_of_week', 'class_session_id'], 'dts_unique');
        });
    }
};
