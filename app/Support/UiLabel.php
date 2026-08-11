<?php

namespace App\Support;

final class UiLabel
{
    /**
     * Convert workflow values into labels that make sense to non-technical users.
     */
    public static function statusLabel(?string $status): string
    {
        return self::labels()[strtolower((string) $status)] ?? self::title($status);
    }

    public static function statusColor(?string $status): string
    {
        return match (strtolower((string) $status)) {
            'published', 'validated', 'approved', 'active', 'present' => 'success',
            'submitted', 'draft', 'pending', 'needs_revision' => 'warning',
            'rejected', 'locked', 'closed', 'absent' => 'danger',
            default => 'neutral',
        };
    }

    public static function absenceLabel(?string $status): string
    {
        return [
            'present' => 'Hadir',
            'sick' => 'Sakit',
            'permission' => 'Izin',
            'absent' => 'Alpa',
            'skipped' => 'Bolos',
            'holiday' => 'Libur',
        ][strtolower((string) $status)] ?? self::title($status);
    }

    public static function genderLabel(?string $gender): string
    {
        return [
            'male' => 'Laki-laki',
            'female' => 'Perempuan',
            'ikhwan' => 'Ikhwan',
            'akhwat' => 'Akhwat',
        ][strtolower((string) $gender)] ?? self::title($gender);
    }

    public static function label(?string $value): string
    {
        return self::labels()[strtolower((string) $value)] ?? self::title($value);
    }

    private static function labels(): array
    {
        return [
            'draft' => 'Draf',
            'active' => 'Aktif',
            'submitted' => 'Dikirim',
            'needs_revision' => 'Perlu diperbaiki',
            'validated' => 'Tervalidasi',
            'approved' => 'Disetujui',
            'published' => 'Sudah terbit',
            'locked' => 'Dikunci',
            'closed' => 'Ditutup',
            'pending' => 'Menunggu',
            'present' => 'Hadir',
            'sick' => 'Sakit',
            'permission' => 'Izin',
            'absent' => 'Alpa',
            'skipped' => 'Bolos',
            'holiday' => 'Libur',
            'male' => 'Laki-laki',
            'female' => 'Perempuan',
            'ikhwan' => 'Ikhwan',
            'akhwat' => 'Akhwat',
        ];
    }

    private static function title(?string $value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '—';
        }

        return ucfirst(str_replace(['_', '-'], ' ', strtolower($value)));
    }
}
