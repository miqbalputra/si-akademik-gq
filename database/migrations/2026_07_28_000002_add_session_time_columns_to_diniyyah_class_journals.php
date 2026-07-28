<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot jam mulai/selesai sesi saat jurnal dibuat, supaya jurnal lama tetap
 * menampilkan jam yang benar meski matrix `class_session_times` tahun ajaran
 * berikutnya berubah. Jurnal lama (NULL) tetap di-resolve dari matrix saat
 * ditampilkan (lihat App\Support\SessionTimetable).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('diniyyah_class_journals', function (Blueprint $table) {
            $table->time('session_starts_at')->nullable()->after('session_hour');
            $table->time('session_ends_at')->nullable()->after('session_starts_at');
        });
    }

    public function down(): void
    {
        Schema::table('diniyyah_class_journals', function (Blueprint $table) {
            $table->dropColumn(['session_starts_at', 'session_ends_at']);
        });
    }
};