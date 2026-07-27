<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tambah pencatat guru pengganti. Kolom diniyyah_teacher_assignment_id TETAP
        // menunjuk assignment guru asli (yang digantikan), sehingga jurnal pengganti
        // mengisi slot jadwal asli dan tetap muncul di daftar jurnal guru asli.
        // substitute_teacher_id nullable = guru yang benar-benar mengajar (untuk hitung gaji).
        Schema::table('diniyyah_class_journals', function (Blueprint $table) {
            $table->foreignId('substitute_teacher_id')
                ->nullable()
                ->after('diniyyah_teacher_assignment_id')
                ->constrained('teachers')
                ->nullOnDelete();
            $table->index('substitute_teacher_id');
        });
    }

    public function down(): void
    {
        Schema::table('diniyyah_class_journals', function (Blueprint $table) {
            $table->dropIndex('diniyyah_class_journals_substitute_teacher_id_index');
            $table->dropForeign(['substitute_teacher_id']);
            $table->dropColumn('substitute_teacher_id');
        });
    }
};