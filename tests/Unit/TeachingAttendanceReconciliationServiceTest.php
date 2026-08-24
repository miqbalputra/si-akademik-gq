<?php

namespace Tests\Unit;

use App\Services\TeachingAttendanceReconciliationService;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TeachingAttendanceReconciliationServiceTest extends TestCase
{
    public function test_it_classifies_the_three_actionable_reconciliation_states(): void
    {
        $service = app(TeachingAttendanceReconciliationService::class);
        $now = Carbon::parse('2026-08-11 09:00:00', 'Asia/Jakarta');

        $hadirTanpaJurnal = $service->reconcile('2026-08-10', '11:00:00', 'hadir_terlambat', true, false, false, false, $now);
        $presensiBelumTercatat = $service->reconcile('2026-08-10', '11:00:00', null, true, true, false, false, $now);
        $keduanyaBelumTercatat = $service->reconcile('2026-08-10', '11:00:00', null, true, false, false, false, $now);

        $this->assertSame(TeachingAttendanceReconciliationService::HADIR_TANPA_JURNAL, $hadirTanpaJurnal['state']);
        $this->assertSame('Hadir terlambat', $hadirTanpaJurnal['attendance_label']);
        $this->assertSame(TeachingAttendanceReconciliationService::PRESENSI_BELUM_TERCATAT, $presensiBelumTercatat['state']);
        $this->assertSame(TeachingAttendanceReconciliationService::PRESENSI_DAN_JURNAL_BELUM_TERCATAT, $keduanyaBelumTercatat['state']);
        $this->assertTrue($hadirTanpaJurnal['actionable']);
        $this->assertTrue($presensiBelumTercatat['actionable']);
        $this->assertTrue($keduanyaBelumTercatat['actionable']);
    }

    public function test_today_slot_is_checked_only_thirty_minutes_after_it_ends(): void
    {
        $service = app(TeachingAttendanceReconciliationService::class);

        $beforeGracePeriod = $service->reconcile(
            '2026-08-10',
            '11:00:00',
            'hadir',
            true,
            false,
            now: Carbon::parse('2026-08-10 11:29:59', 'Asia/Jakarta'),
        );
        $afterGracePeriod = $service->reconcile(
            '2026-08-10',
            '11:00:00',
            'hadir',
            true,
            false,
            now: Carbon::parse('2026-08-10 11:30:00', 'Asia/Jakarta'),
        );
        $withoutSessionTime = $service->reconcile(
            '2026-08-10',
            null,
            'hadir',
            true,
            false,
            now: Carbon::parse('2026-08-10 23:59:59', 'Asia/Jakarta'),
        );

        $this->assertFalse($beforeGracePeriod['due']);
        $this->assertSame('belum_jatuh_tempo', $beforeGracePeriod['state']);
        $this->assertTrue($afterGracePeriod['due']);
        $this->assertSame(TeachingAttendanceReconciliationService::HADIR_TANPA_JURNAL, $afterGracePeriod['state']);
        $this->assertFalse($withoutSessionTime['due']);
    }

    public function test_exemptions_and_unverified_attendance_do_not_create_false_alarms(): void
    {
        $service = app(TeachingAttendanceReconciliationService::class);
        $now = Carbon::parse('2026-08-11 09:00:00', 'Asia/Jakarta');

        foreach (['izin', 'sakit'] as $status) {
            $result = $service->reconcile('2026-08-10', '11:00:00', $status, true, false, false, false, $now);
            $this->assertSame('selaras', $result['state']);
            $this->assertFalse($result['actionable']);
        }

        $holidayOrAgenda = $service->reconcile('2026-08-10', '11:00:00', 'hadir', true, false, false, true, $now);
        $substituteJournal = $service->reconcile('2026-08-10', '11:00:00', 'hadir', true, true, true, false, $now);
        $unverified = $service->reconcile('2026-08-10', '11:00:00', null, false, false, false, false, $now);

        $this->assertSame('selaras', $holidayOrAgenda['state']);
        $this->assertSame('selaras', $substituteJournal['state']);
        $this->assertSame('belum_terverifikasi', $unverified['state']);
        $this->assertFalse($unverified['actionable']);
    }
}
