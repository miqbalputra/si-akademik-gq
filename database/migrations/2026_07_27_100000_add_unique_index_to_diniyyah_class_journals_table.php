<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Bersihkan jurnal duplikat (race kondisi double-submit pra-migrasi):
        // pertahankan jurnal dengan id terkecil per (assignment, date, session_hour),
        // hapus sisanya. Catatan: absence rows anak ikut terhapus via cascadeOnDelete.
        DB::table('diniyyah_class_journals')
            ->whereIn('id', function ($query) {
                $query->select('dup.id')
                    ->from('diniyyah_class_journals as dup')
                    ->join(
                        DB::raw('(SELECT MIN(id) AS keep_id, diniyyah_teacher_assignment_id, date, session_hour FROM diniyyah_class_journals GROUP BY diniyyah_teacher_assignment_id, date, session_hour) AS kept'),
                        function ($join) {
                            $join->on('dup.diniyyah_teacher_assignment_id', '=', 'kept.diniyyah_teacher_assignment_id')
                                ->on('dup.date', '=', 'kept.date')
                                ->on('dup.session_hour', '=', 'kept.session_hour');
                        }
                    )
                    ->whereColumn('dup.id', '<>', 'kept.keep_id');
            })
            ->delete();

        Schema::table('diniyyah_class_journals', function (Blueprint $table) {
            $table->unique(
                ['diniyyah_teacher_assignment_id', 'date', 'session_hour'],
                'diniyyah_class_journals_unique_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('diniyyah_class_journals', function (Blueprint $table) {
            $table->dropUnique('diniyyah_class_journals_unique_idx');
        });
    }
};