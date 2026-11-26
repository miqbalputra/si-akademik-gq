<?php

namespace App\Services;

use App\Models\DiniyyahAssessmentSet;
use App\Models\DiniyyahLedgerSnapshot;
use App\Models\User;
use DomainException;

class DiniyyahLedgerWorkflow
{
    public function lock(DiniyyahLedgerSnapshot $snapshot, User $user): void
    {
        app(DiniyyahKkmResolver::class)->withClassLocks($snapshot->classroomTerm, function () use ($snapshot, $user): void {
            $snapshot = DiniyyahLedgerSnapshot::whereKey($snapshot->id)->lockForUpdate()->firstOrFail();
            $this->ensureKkmCurrent($snapshot);
            $this->lockOpenSnapshot($snapshot, $user);
            if (($snapshot->snapshot_data['layout_version'] ?? '') === 'combined-v1') {
                app(ReportCardAutomation::class)->createMissing($snapshot, $user->id);
            }
        });
        $snapshot->refresh();
    }

    private function lockOpenSnapshot(DiniyyahLedgerSnapshot $snapshot, User $user): void
    {
        if (in_array($snapshot->status, ['locked', 'published'], true)) {
            throw new DomainException('Leger yang sudah terkunci atau terbit tidak bisa dikunci ulang.');
        }

        if ($reason = $this->completionIssue($snapshot, $this->completion($snapshot))) {
            throw new DomainException($reason);
        }

        $snapshot->update([
            'status' => 'locked',
            'locked_at' => now(),
            'locked_by' => $user->id,
        ]);

        $this->notifyLocked($snapshot, $user);
    }

    public function validate(DiniyyahLedgerSnapshot $snapshot, User $user): void
    {
        $this->ensureKkmCurrent($snapshot);
        if (in_array($snapshot->status, ['locked', 'published'], true)) {
            throw new DomainException('Leger terkunci tidak bisa divalidasi ulang.');
        }
        if ($reason = $this->completionIssue($snapshot, $this->completion($snapshot))) {
            throw new DomainException($reason);
        }

        $snapshot->update([
            'status' => 'validated',
            'validated_at' => now(),
            'validated_by' => $user->id,
        ]);

        $this->notifyValidated($snapshot, $user);
    }

    // ── Notifikasi ────────────────────────────────────────────────────────
    public function readiness(DiniyyahLedgerSnapshot $snapshot): array
    {
        $completion = $this->completion($snapshot);
        if (in_array($snapshot->status, ['locked', 'published'], true)) {
            return $completion + ['blocking_reason' => null];
        }
        try {
            $this->ensureSourcesCurrent($snapshot);
        } catch (DomainException $exception) {
            return $completion + ['blocking_reason' => $exception->getMessage()];
        }

        return $completion + ['blocking_reason' => $this->completionIssue($snapshot, $completion)];
    }

    private function completion(DiniyyahLedgerSnapshot $snapshot): array
    {
        $snapshot->loadMissing('rows.cells');
        $frozen = in_array($snapshot->status, ['locked', 'published'], true);
        $enrollmentIds = $frozen ? $snapshot->rows->pluck('class_enrollment_id')
            : $snapshot->classroomTerm->enrollments()->where('status', 'active')->forVisibleStudents()->pluck('id');
        $total = $enrollmentIds->count();
        $columns = collect($snapshot->snapshot_data['columns'] ?? []);
        $hasAssessments = $columns->contains('source_type', 'diniyyah_assessment_set');
        $numericKeys = $columns->whereIn('source_type', ['diniyyah_assessment_set', 'student_attendance_recap'])->pluck('key');
        $combined = ($snapshot->snapshot_data['layout_version'] ?? '') === 'combined-v1';
        if ($combined) {
            $numericKeys = $numericKeys->merge(['tahfidz_uas', 'tahfidz_sabaq', 'tahfidz_sabqi', 'tahfidz_manzil',
                'attendance_sick', 'attendance_permission', 'attendance_absent', 'combined_total', 'combined_average', 'combined_rank'])->unique();
        }
        $complete = $hasAssessments ? $snapshot->rows->whereIn('class_enrollment_id', $enrollmentIds)->filter(function ($row) use ($numericKeys, $combined): bool {
            $cells = $row->cells->keyBy('column_key');

            return $row->total_diniyyah_score !== null && $row->average_diniyyah_score !== null && $row->rank_in_class !== null
                && $numericKeys->every(fn ($key) => $cells->get($key)?->value_numeric !== null)
                && (! $combined || filled($cells->get('tahfidz_juz')?->value_text));
        })->count() : 0;

        return ['total' => $total, 'complete' => $complete, 'percentage' => $total > 0 ? intdiv($complete * 100, $total) : 0];
    }

    private function completionIssue(DiniyyahLedgerSnapshot $snapshot, array $completion): ?string
    {
        if ($completion['percentage'] < 100) {
            return $completion['total'] === 0
                ? 'Leger belum memiliki santri aktif.'
                : 'Lengkapi nilai seluruh santri hingga 100%, lalu segarkan draf leger.';
        }

        return ($snapshot->snapshot_data['summary']['blocking_issues'] ?? 0) > 0
            ? 'Periksa kendala yang tercatat di leger, lalu segarkan setelah sumber diperbarui.'
            : null;
    }

    private function ensureSourcesCurrent(DiniyyahLedgerSnapshot $snapshot): void
    {
        if (isset($snapshot->snapshot_data['attendance_fingerprint'])
            && $snapshot->snapshot_data['attendance_fingerprint'] !== app(AttendanceRecapService::class)->ledgerFingerprint($snapshot)) {
            throw new DomainException('Presensi peserta didik berubah. Segarkan leger agar ketidakhadiran rapor sesuai presensi asli.');
        }
        if (($snapshot->snapshot_data['layout_version'] ?? '') === 'combined-v1' && isset($snapshot->snapshot_data['attendance_fingerprint'])) {
            $current = $snapshot->classroomTerm->enrollments()->where('status', 'active')->forVisibleStudents()->orderBy('id')->pluck('id')->all();
            $saved = $snapshot->rows()->orderBy('class_enrollment_id')->pluck('class_enrollment_id')->all();
            if ($current !== $saved) {
                throw new DomainException('Daftar santri aktif berubah. Segarkan leger sebelum dikunci.');
            }
            if ($snapshot->classroomTerm->diniyyahClassSubjects()->where('is_active', true)->where('appears_on_ledger', true)->whereDoesntHave('assessmentSets')->exists()) {
                throw new DomainException('Masih ada mapel kelas yang belum memiliki set penilaian. Minta guru pengampu membuka Input Nilai.');
            }
        }
        $unnamedPractice = DiniyyahAssessmentSet::where('appears_on_ledger', true)
            ->whereHas('classSubject', fn ($q) => $q->where('classroom_term_id', $snapshot->classroom_term_id)->where('is_active', true)->where('appears_on_ledger', true))
            ->with('classSubject.subject')->get()->contains(fn ($set) => $set->missingExamName());
        if ($unnamedPractice) {
            throw new DomainException('Nama ujian Praktik Ibadah belum diisi. Minta guru pengampu melengkapinya, lalu segarkan draf leger.');
        }
        app(DiniyyahCombinedLedger::class)->ensureCurrent($snapshot);
    }

    private function ensureKkmCurrent(DiniyyahLedgerSnapshot $snapshot): void
    {
        $this->ensureSourcesCurrent($snapshot);
        if (! $snapshot->classroomTerm?->academicTerm?->kkm_workflow_enabled) {
            return;
        }
        if ($snapshot->snapshot_data['kkm_stale'] ?? false) {
            throw new DomainException('KKM berubah. Generate ulang leger.');
        }
        $sets = DiniyyahAssessmentSet::where('appears_on_ledger', true)
            ->whereHas('classSubject', fn ($q) => $q->where('classroom_term_id', $snapshot->classroom_term_id)->where('is_active', true)->where('appears_on_ledger', true))->get();
        $columns = collect($snapshot->snapshot_data['columns'] ?? [])->where('source_type', 'diniyyah_assessment_set');
        if ($sets->isEmpty() || $sets->count() !== $columns->count()) {
            throw new DomainException('Struktur leger berubah atau belum lengkap. Generate ulang leger.');
        }
        foreach ($sets as $set) {
            $column = $columns->firstWhere('assessment_set_id', $set->id);
            $current = app(DiniyyahKkmResolver::class)->ensureApproved($set);
            if (($column['kkm_revision_id'] ?? null) !== $current['kkm_revision_id'] || $set->status !== 'validated') {
                throw new DomainException('Versi KKM atau validasi nilai berubah. Generate ulang leger.');
            }
        }
    }

    private function notifyValidated(DiniyyahLedgerSnapshot $snapshot, User $user): void
    {
        $kelas = $snapshot->classroomTerm?->name ?? 'kelas';
        $dispatcher = app(NotificationDispatcher::class);
        $linkUrl = route('diniyyah.ledger.show', $snapshot);

        // Wali kelas kelas tsb.
        if ($snapshot->classroom_term_id) {
            $dispatcher->dispatchToHomeroomTeacher(
                $snapshot->classroom_term_id,
                "Leger {$kelas} divalidasi",
                "Leger kelas {$kelas} telah divalidasi oleh {$user->name}. Siap untuk dikunci.",
                'ledger_validated',
                $linkUrl,
                'success',
            );
        }

        // Kabag diniyyah (info).
        $dispatcher->dispatchToRole(
            'kabag_diniyyah',
            "Leger {$kelas} divalidasi",
            "Leger kelas {$kelas} divalidasi oleh {$user->name}.",
            'ledger_validated',
            $linkUrl,
            'info',
        );
    }

    private function notifyLocked(DiniyyahLedgerSnapshot $snapshot, User $user): void
    {
        $kelas = $snapshot->classroomTerm?->name ?? 'kelas';
        $dispatcher = app(NotificationDispatcher::class);
        $linkUrl = route('diniyyah.ledger.show', $snapshot);
        $body = "Leger kelas {$kelas} dikunci oleh {$user->name}. Perubahan nilai diniyyah untuk kelas ini dinonaktifkan.";

        // Kepala sekolah (info).
        $dispatcher->dispatchToRole(
            'kepala_sekolah',
            "Leger {$kelas} dikunci",
            $body,
            'ledger_locked',
            $linkUrl,
            'info',
        );

        // Kabag diniyyah.
        $dispatcher->dispatchToRole(
            'kabag_diniyyah',
            "Leger {$kelas} dikunci",
            $body,
            'ledger_locked',
            $linkUrl,
            'info',
        );

        // Wali kelas kelas tsb.
        if ($snapshot->classroom_term_id) {
            $dispatcher->dispatchToHomeroomTeacher(
                $snapshot->classroom_term_id,
                "Leger {$kelas} dikunci",
                $body,
                'ledger_locked',
                $linkUrl,
                'warning',
            );
        }

        // Guru pemilik nilai kelas tsb (nilai dibekukan).
        $assessmentSets = DiniyyahAssessmentSet::query()
            ->whereHas('classSubject', function ($q) use ($snapshot) {
                $q->where('classroom_term_id', $snapshot->classroom_term_id);
            })
            ->pluck('id');
        foreach ($assessmentSets as $setId) {
            $dispatcher->dispatchToSubjectTeachers(
                $setId,
                "Leger {$kelas} dikunci",
                $body.' Input nilai dinonaktifkan.',
                'ledger_locked',
                route('guru.diniyyah-scores.index'),
                'warning',
            );
        }
    }
}
