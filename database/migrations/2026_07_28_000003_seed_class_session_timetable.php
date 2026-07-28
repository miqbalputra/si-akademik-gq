<?php

use App\Models\ClassSession;
use Illuminate\Database\Migrations\Migration;

/**
 * Seed matrix jam sesi diniyyah per gender + hari (tabel `class_session_times`).
 *
 * Sumber: jadwal_mustawa_ikhwan.md & jadwal_mustawa_akhwat_2026_2027.md.
 * Berjalan otomatis saat migrate (deploy Coolify) — tidak perlu `db:seed` manual.
 * Idempoten: pakai Eloquent firstOrCreate, aman di-re-run.
 *
 * day_of_week = ISO-8601 (1=Senin .. 7=Minggu).
 *
 * Catatan: `class_sessions.starts_at/ends_at` masih NOT NULL dari migration awal.
 * Baris '1'/'2' biasanya sudah ada di prod; bila belum, dibuat dengan jam default
 * representatif (times ini tidak dipakai form jurnal lagi — form memakai matrix
 * ini). Baris 'tafsir' dibuat baru bila belum ada.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Pastikan identitas label sesi ada. Matrix jam sesi per-gender yang
        // dulu di-seed di sini sekarang di-seed per-classroom oleh migration
        // 2026_07_28_000004 (refactor ke classroom_id). Lihat
        // App\Support\SessionTimetable::definitionForClassroom().
        ClassSession::firstOrCreate(
            ['session_name' => '1'],
            ['starts_at' => '10:30:00', 'ends_at' => '11:00:00', 'is_break' => false],
        );
        ClassSession::firstOrCreate(
            ['session_name' => '2'],
            ['starts_at' => '11:00:00', 'ends_at' => '11:30:00', 'is_break' => false],
        );
        ClassSession::firstOrCreate(
            ['session_name' => 'tafsir'],
            ['starts_at' => '09:50:00', 'ends_at' => '10:20:00', 'is_break' => false],
        );
    }

    public function down(): void
    {
        ClassSessionTime::query()->delete();
    }
};