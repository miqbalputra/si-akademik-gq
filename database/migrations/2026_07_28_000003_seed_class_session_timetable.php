<?php

use App\Models\ClassSession;
use App\Models\ClassSessionTime;
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
        // Pastikan identitas label sesi ada.
        $session1 = ClassSession::firstOrCreate(
            ['session_name' => '1'],
            ['starts_at' => '10:30:00', 'ends_at' => '11:00:00', 'is_break' => false],
        );
        $session2 = ClassSession::firstOrCreate(
            ['session_name' => '2'],
            ['starts_at' => '11:00:00', 'ends_at' => '11:30:00', 'is_break' => false],
        );
        $sessionTafsir = ClassSession::firstOrCreate(
            ['session_name' => 'tafsir'],
            ['starts_at' => '09:50:00', 'ends_at' => '10:20:00', 'is_break' => false],
        );

        // [group, day, session, starts_at, ends_at]
        $matrix = [
            // IKHWAN
            ['ikhwan', 1, $session1, '07:40:00', '08:10:00'],
            ['ikhwan', 1, $session2, '08:10:00', '08:40:00'],
            ['ikhwan', 2, $session1, '10:30:00', '11:00:00'],
            ['ikhwan', 2, $session2, '11:00:00', '11:30:00'],
            ['ikhwan', 3, $session1, '10:30:00', '11:00:00'],
            ['ikhwan', 3, $session2, '11:00:00', '11:30:00'],
            ['ikhwan', 4, $sessionTafsir, '09:50:00', '10:20:00'],
            ['ikhwan', 4, $session1, '10:30:00', '11:00:00'],
            ['ikhwan', 4, $session2, '11:00:00', '11:30:00'],
            ['ikhwan', 5, $session1, '08:50:00', '09:20:00'],
            ['ikhwan', 5, $session2, '09:20:00', '09:50:00'],

            // AKHWAT (Senin berbeda dari Ikhwan; hari lain identik)
            ['akhwat', 1, $session1, '10:30:00', '11:00:00'],
            ['akhwat', 1, $session2, '11:00:00', '11:30:00'],
            ['akhwat', 2, $session1, '10:30:00', '11:00:00'],
            ['akhwat', 2, $session2, '11:00:00', '11:30:00'],
            ['akhwat', 3, $session1, '10:30:00', '11:00:00'],
            ['akhwat', 3, $session2, '11:00:00', '11:30:00'],
            ['akhwat', 4, $sessionTafsir, '09:50:00', '10:20:00'],
            ['akhwat', 4, $session1, '10:30:00', '11:00:00'],
            ['akhwat', 4, $session2, '11:00:00', '11:30:00'],
            ['akhwat', 5, $session1, '08:50:00', '09:20:00'],
            ['akhwat', 5, $session2, '09:20:00', '09:50:00'],
        ];

        foreach ($matrix as [$group, $day, $session, $startsAt, $endsAt]) {
            ClassSessionTime::firstOrCreate(
                [
                    'classroom_group' => $group,
                    'day_of_week' => $day,
                    'class_session_id' => $session->id,
                ],
                [
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                ],
            );
        }
    }

    public function down(): void
    {
        ClassSessionTime::query()->delete();
    }
};