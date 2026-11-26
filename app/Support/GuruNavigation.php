<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Str;

/** Presentation only: destination controllers and policies remain authoritative. */
class GuruNavigation
{
    public function groups(User $user, bool $hasTafsirSchedule = false): array
    {
        $teacher = $user->teacher;
        // Preserve existing historical homeroom visibility; each destination scopes its data.
        $isHomeroom = $teacher?->homeroomAssignments()->exists() ?? false;
        $item = fn (string $label, string $route, array $match, string $aliases = '', array $except = []): array => [
            'label' => $label, 'href' => route($route), 'match' => $match,
            'aliases' => $aliases, 'except' => $except,
        ];

        $teaching = [
            $item('Jurnal Mengajar', 'guru.diniyyah-journals.index', ['guru.diniyyah-journals.*'], 'Jurnal reguler', ['guru.diniyyah-journals.riwayat']),
        ];
        if ($teacher) {
            $teaching[] = $item('Jurnal Guru Pengganti', 'guru.diniyyah-substitute-journals.index', ['guru.diniyyah-substitute-journals.*'], 'Jurnal Pengganti');
        }
        if ($hasTafsirSchedule) {
            $teaching[] = $item('Jurnal Tafsir', 'guru.diniyyah-tafsir-journals.index', ['guru.diniyyah-tafsir-journals.*'], 'Tafsir serentak');
        }
        if ($teacher) {
            $teaching[] = $item('Jurnal Pengganti Tafsir', 'guru.diniyyah-substitute-tafsir-journals.index', ['guru.diniyyah-substitute-tafsir-journals.*']);
        }
        if ($user->canAccessAttendance()) {
            $teaching[] = $item('Presensi Santri', 'attendance.index', ['attendance.*'], 'Absensi kehadiran siswa peserta didik');
        }
        $teaching[] = $item('Kalender Akademik', 'guru.calendar', ['guru.calendar']);

        $reports = [
            $item('Alur Nilai & Rapor', 'guru.report-workflow', ['guru.report-workflow']),
            $item('Input Nilai Pelajaran', 'guru.diniyyah-scores.index', ['guru.diniyyah-scores.*'], 'Diniyyah Suluk Praktik Ibadah'),
            $item('KKM Mata Pelajaran', 'guru.kkm.index', ['guru.kkm.*']),
            $item('Nilai Final Tahfidz Rapor', 'guru.tahfidz-report.index', ['guru.tahfidz-report.index']),
        ];
        $classes = [];
        if ($isHomeroom) {
            $reports[] = $item('Leger Kelas Saya', 'diniyyah.ledger.index', ['diniyyah.ledger.*'], 'Legger ranking peringkat');
            $reports[] = $item('Rapor Kelas Saya', 'report-cards.index', ['report-cards.*', 'guru.tahfidz-report.monitor'], 'Cetak kelengkapan rapor');
            $classes = [
                $item('Hasil Tasmi Kelas Saya', 'guru.tasmi-wali.index', ['guru.tasmi-wali.*']),
                $item('Pantau Jurnal Kelas', 'wali.diniyyah-journals.index', ['wali.diniyyah-journals.*'], 'Monitoring Jurnal Kelas'),
                $item('Rekap Jam Mengajar Kelas', 'wali.jp-recap.index', ['wali.jp-recap.*'], 'Rekap JP Kelas'),
            ];
        }
        $tahfidz = [$item('Penilaian Tahfidz', 'guru.tahfidz.index', ['guru.tahfidz.*'])];
        if ($user->isTasmiExaminer()) {
            $tahfidz[] = $item('Ujian Tasmi', 'guru.tasmi.index', ['guru.tasmi.*'], 'PJ Tasmi riwayat tasmi');
        }

        $learning = [];
        if ($teacher) {
            $learning[] = $item('RPP Saya', 'guru.rpp.index', ['guru.rpp.index', 'guru.rpp.show', 'guru.rpp.edit']);
            if (! config('rpp_sync.enabled')) {
                $learning[] = $item('Buat RPP', 'guru.rpp.create', ['guru.rpp.create']);
            }
            $learning[] = $item('Referensi RPP', 'guru.rpp.references', ['guru.rpp.references']);
            $learning[] = $item('Program Semester Saya', 'guru.rpp.promes', ['guru.rpp.promes'], 'Promes Saya');
            if (! config('rpp_sync.enabled')) {
                $learning[] = $item('Sampah RPP', 'guru.rpp.trash', ['guru.rpp.trash']);
            }
        }
        $history = [];
        if ($teacher) {
            $history[] = $item('Kehadiran Saya', 'guru.attendance-report.index', ['guru.attendance-report.*'], 'Presensi Saya GeoPresensi');
        }
        $history[] = $item('Rekap Jurnal Mengajar', 'guru.performa', ['guru.performa'], 'Performa Jurnal Saya');
        $history[] = $item('Riwayat Jurnal', 'guru.diniyyah-journals.riwayat', ['guru.diniyyah-journals.riwayat']);
        if ($teacher) {
            $history[] = $item('Riwayat Jadwal', 'guru.jadwal.riwayat', ['guru.jadwal.riwayat']);
        }
        $coordination = [];
        if ($user->hasRole('kabag_tahfidz')) {
            $coordination[] = $item("Monitoring Tasmi' Semua Kelas", 'admin.tasmi-report.index', ['admin.tasmi-report.*']);
        }

        $groups = [];
        foreach ([
            'Mengajar' => $teaching, 'Tahfidz & Tasmi' => $tahfidz,
            'Kelas Saya' => $classes, 'RPP & Program Semester' => $learning,
            'Riwayat Saya' => $history, 'Koordinasi' => $coordination, 'Rapor' => $reports,
        ] as $label => $items) {
            if ($items === []) {
                continue;
            }
            foreach ($items as &$entry) {
                $entry['active'] = self::matches($entry, request()->route()?->getName() ?? '');
            }
            unset($entry);
            $groups[] = [
                'key' => Str::slug($label), 'label' => $label, 'items' => $items,
                'active' => collect($items)->contains('active', true),
                'default_open' => in_array($label, ['Mengajar', 'Rapor'], true),
            ];
        }

        return $groups;
    }

    public static function matches(array $item, string $route): bool
    {
        return Str::is($item['match'], $route) && ! Str::is($item['except'] ?? [], $route);
    }

    public function storageKey(User $user): string
    {
        $roles = $user->getRoleNames()->sort()->values()->implode(',');

        return 'gq:guru-navigation:v1:'.$user->id.':'.sha1($roles);
    }
}
