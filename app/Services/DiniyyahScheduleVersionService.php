<?php

namespace App\Services;

use App\Models\DiniyyahScheduleChangeLog;
use App\Models\DiniyyahTeacherAssignment;
use App\Models\DiniyyahTeachingSchedule;
use App\Models\ClassSession;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/** Resolves, previews, and applies date-bounded weekly schedule versions. */
class DiniyyahScheduleVersionService
{
    public function schedulesForDate(Carbon|string $date, ?int $assignmentId = null): EloquentCollection
    {
        return DiniyyahTeachingSchedule::query()
            ->forDate($date)
            ->when($assignmentId, fn ($query, int $id) => $query->where('diniyyah_teacher_assignment_id', $id))
            ->with($this->relations())
            ->get();
    }

    public function schedulesForRange(Carbon|string $start, Carbon|string $end, ?int $assignmentId = null): EloquentCollection
    {
        return DiniyyahTeachingSchedule::query()
            ->overlappingRange($start, $end)
            ->when($assignmentId, fn ($query, int $id) => $query->where('diniyyah_teacher_assignment_id', $id))
            ->with($this->relations())
            ->get();
    }

    /**
     * Create an in-memory schedule collection for a correction preview.
     * Reports only inspect dates inside this range, so the selected assignment's
     * current versions can be replaced by the proposed pattern here.
     *
     * @param  array<int, array{day_of_week:int|string, class_session_id:int|string}>  $slots
     * @return EloquentCollection<int, DiniyyahTeachingSchedule>
     */
    public function previewSchedules(int $assignmentId, Carbon|string $start, Carbon|string $end, array $slots): EloquentCollection
    {
        $this->validateChange($assignmentId, 'correction', $start, $end, $slots);
        $schedules = $this->schedulesForRange($start, $end);
        $schedules = $schedules->reject(fn (DiniyyahTeachingSchedule $schedule): bool => (int) $schedule->diniyyah_teacher_assignment_id === $assignmentId)->values();
        $assignment = DiniyyahTeacherAssignment::with([
            'teacher',
            'classSubject.subject',
            'classSubject.classroomTerm.classroom',
        ])->findOrFail($assignmentId);

        foreach ($this->normalizedSlots($slots) as $index => $slot) {
            $schedule = new DiniyyahTeachingSchedule([
                'diniyyah_teacher_assignment_id' => $assignmentId,
                'class_session_id' => $slot['class_session_id'],
                'day_of_week' => $slot['day_of_week'],
                'effective_from' => Carbon::parse($start, 'Asia/Jakarta')->toDateString(),
                'effective_until' => Carbon::parse($end, 'Asia/Jakarta')->toDateString(),
                'version_status' => DiniyyahTeachingSchedule::STATUS_ACTIVE,
                'change_type' => 'correction',
            ]);
            $schedule->id = -1 - $index;
            $schedule->setRelation('teacherAssignment', $assignment);
            $schedule->setRelation('classSession', \App\Models\ClassSession::find($slot['class_session_id']));
            $schedules->push($schedule);
        }

        return $schedules->values();
    }

    /** @param array<int, array{day_of_week:int|string, class_session_id:int|string}> $slots */
    public function validateChange(int $assignmentId, string $type, Carbon|string $start, Carbon|string|null $end, array $slots): void
    {
        if (! in_array($type, ['correction', 'approved'], true)) {
            throw ValidationException::withMessages(['changeType' => 'Pilih jenis perubahan yang tersedia.']);
        }
        if ($type === 'correction' && ! $end) {
            throw ValidationException::withMessages(['effectiveUntil' => 'Koreksi kesalahan harus memiliki tanggal akhir.']);
        }
        if ($end && Carbon::parse($end, 'Asia/Jakarta')->lt(Carbon::parse($start, 'Asia/Jakarta'))) {
            throw ValidationException::withMessages(['effectiveUntil' => 'Tanggal akhir harus sama atau setelah tanggal mulai.']);
        }
        DiniyyahTeacherAssignment::query()->findOrFail($assignmentId);

        foreach ($slots as $slot) {
            $hasDay = filled($slot['day_of_week'] ?? null);
            $hasSession = filled($slot['class_session_id'] ?? null);
            if ($hasDay !== $hasSession) {
                throw ValidationException::withMessages(['slots' => 'Lengkapi hari dan sesi pada setiap baris, atau hapus baris yang tidak digunakan.']);
            }
        }

        $normalized = $this->normalizedSlots($slots);
        $keys = [];
        foreach ($normalized as $slot) {
            if ($slot['day_of_week'] < 1 || $slot['day_of_week'] > 7) {
                throw ValidationException::withMessages(['slots' => 'Pilih hari yang valid untuk setiap sesi.']);
            }
            $key = $slot['day_of_week'].'|'.$slot['class_session_id'];
            if (isset($keys[$key])) {
                throw ValidationException::withMessages(['slots' => 'Hari dan sesi yang sama hanya boleh dicantumkan satu kali.']);
            }
            $keys[$key] = true;
        }
        if (count($normalized) !== 0 && ClassSession::query()->whereIn('id', collect($normalized)->pluck('class_session_id'))->count() !== count(collect($normalized)->pluck('class_session_id')->unique())) {
            throw ValidationException::withMessages(['slots' => 'Salah satu sesi tidak lagi tersedia. Muat ulang halaman lalu coba lagi.']);
        }
        $rows = DiniyyahTeachingSchedule::query()
            ->where('diniyyah_teacher_assignment_id', $assignmentId)
            ->whereIn('version_status', [DiniyyahTeachingSchedule::STATUS_LEGACY, DiniyyahTeachingSchedule::STATUS_ACTIVE])
            ->get();
        $this->assertNoSlotOverlap($rows, Carbon::parse($start, 'Asia/Jakarta')->startOfDay(), $end ? Carbon::parse($end, 'Asia/Jakarta')->startOfDay() : null, $normalized);
    }

    /**
     * Persist one whole weekly pattern for an assignment and effective range.
     * Old rows are retained as superseded history; unaffected date slices are
     * copied into bounded active versions.
     *
     * @param  array<int, array{day_of_week:int|string, class_session_id:int|string}>  $slots
     */
    public function apply(
        int $assignmentId,
        string $type,
        Carbon|string $start,
        Carbon|string|null $end,
        array $slots,
        string $reason,
        ?string $requestReference = null,
        ?int $legacyLogId = null,
        ?string $expectedFingerprint = null,
    ): DiniyyahScheduleChangeLog {
        if (! filled($reason)) {
            throw ValidationException::withMessages(['reason' => 'Alasan perubahan wajib diisi.']);
        }
        $this->validateChange($assignmentId, $type, $start, $end, $slots);
        $start = Carbon::parse($start, 'Asia/Jakarta')->startOfDay();
        $end = $end ? Carbon::parse($end, 'Asia/Jakarta')->startOfDay() : null;
        $normalizedSlots = $this->normalizedSlots($slots);

        $changeLog = DB::transaction(function () use ($assignmentId, $type, $start, $end, $normalizedSlots, $reason, $requestReference, $legacyLogId, $expectedFingerprint): DiniyyahScheduleChangeLog {
            $assignment = DiniyyahTeacherAssignment::query()->with([
                'classSubject.subject',
                'classSubject.classroomTerm',
                'teacher',
            ])->lockForUpdate()->findOrFail($assignmentId);
            $rows = DiniyyahTeachingSchedule::query()
                ->where('diniyyah_teacher_assignment_id', $assignmentId)
                ->whereIn('version_status', [DiniyyahTeachingSchedule::STATUS_LEGACY, DiniyyahTeachingSchedule::STATUS_ACTIVE])
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($expectedFingerprint !== null && ! hash_equals($expectedFingerprint, $this->fingerprintRows($rows))) {
                throw ValidationException::withMessages(['preview' => 'Jadwal berubah setelah pratinjau dibuat. Periksa ulang pratinjau sebelum menerapkan.']);
            }

            $reviewLog = null;
            if ($legacyLogId) {
                $reviewLog = DiniyyahScheduleChangeLog::query()->lockForUpdate()->findOrFail($legacyLogId);
                if ($reviewLog->entity_type !== 'schedule'
                    || ! in_array($reviewLog->event, ['updated', 'deleted'], true)
                    || (int) $reviewLog->diniyyah_teacher_assignment_id !== $assignmentId
                    || $reviewLog->reviewed_at !== null
                    || array_key_exists('diniyyah_teacher_assignment_id', $reviewLog->old_values ?? [])
                    || array_key_exists('diniyyah_teacher_assignment_id', $reviewLog->new_values ?? [])) {
                    throw ValidationException::withMessages(['legacyLogId' => 'Log lama sudah ditinjau atau tidak terkait dengan penugasan ini.']);
                }
            }

            $this->assertNoSlotOverlap($rows, $start, $end, $normalizedSlots);
            $oldRows = $rows->map(fn (DiniyyahTeachingSchedule $row): array => $this->rowSnapshot($row))->all();
            $oldPattern = $rows
                ->filter(fn (DiniyyahTeachingSchedule $row): bool => $this->rowOverlaps($row, $start, $end))
                ->map(fn (DiniyyahTeachingSchedule $row): array => [
                    'day_of_week' => (int) $row->day_of_week,
                    'class_session_id' => (int) $row->class_session_id,
                ])->values()->all();

            foreach ($rows as $row) {
                if (! $this->rowOverlaps($row, $start, $end)) {
                    continue;
                }

                $oldFrom = $row->version_status === DiniyyahTeachingSchedule::STATUS_LEGACY || ! $row->effective_from
                    ? null
                    : Carbon::parse($row->effective_from, 'Asia/Jakarta')->startOfDay();
                $oldUntil = $row->version_status === DiniyyahTeachingSchedule::STATUS_LEGACY || ! $row->effective_until
                    ? null
                    : Carbon::parse($row->effective_until, 'Asia/Jakarta')->startOfDay();

                if ($oldFrom === null || $oldFrom->lt($start)) {
                    $this->copySlice($row, $oldFrom, $start->copy()->subDay());
                }
                if ($end !== null && ($oldUntil === null || $oldUntil->gt($end))) {
                    $this->copySlice($row, $end->copy()->addDay(), $oldUntil);
                }

                $row->version_status = DiniyyahTeachingSchedule::STATUS_SUPERSEDED;
                $row->saveQuietly();
            }

            foreach ($normalizedSlots as $slot) {
                $newSchedule = new DiniyyahTeachingSchedule([
                    'diniyyah_teacher_assignment_id' => $assignmentId,
                    'class_session_id' => $slot['class_session_id'],
                    'day_of_week' => $slot['day_of_week'],
                    'effective_from' => $start->toDateString(),
                    'effective_until' => $end?->toDateString(),
                    'version_status' => DiniyyahTeachingSchedule::STATUS_ACTIVE,
                    'change_type' => $type,
                    'change_reason' => trim($reason),
                    'request_reference' => filled($requestReference) ? trim($requestReference) : null,
                ]);
                $newSchedule->saveQuietly();
            }

            if ($reviewLog) {
                $reviewLog->forceFill([
                    'reviewed_at' => now(),
                    'reviewed_by' => Auth::id(),
                    'change_type' => $type,
                    'effective_from' => $start->toDateString(),
                    'effective_until' => $end?->toDateString(),
                    'reason' => trim($reason),
                    'request_reference' => filled($requestReference) ? trim($requestReference) : null,
                ])->save();
            }

            $change = $type === 'correction' ? 'Koreksi kesalahan jadwal' : 'Perubahan jadwal disetujui';
            $assignmentLabel = collect([
                $assignment->teacher?->name,
                $assignment->classSubject?->classroomTerm?->name,
                $assignment->classSubject?->subject?->name,
            ])->filter()->implode(' — ');

            return DiniyyahScheduleChangeLog::create([
                'teacher_id' => $assignment->teacher_id,
                'old_teacher_id' => null,
                'diniyyah_teacher_assignment_id' => $assignmentId,
                'diniyyah_teaching_schedule_id' => null,
                'entity_type' => 'schedule',
                'event' => $type,
                'change_summary' => "{$change}: {$assignmentLabel} ({$start->format('d/m/Y')} – ".($end?->format('d/m/Y') ?? 'tanpa batas').').',
                'old_values' => ['schedule_rows' => $oldRows, 'pattern_in_range' => $oldPattern],
                'new_values' => ['pattern' => $normalizedSlots],
                'changed_by' => Auth::id(),
                'change_type' => $type,
                'effective_from' => $start->toDateString(),
                'effective_until' => $end?->toDateString(),
                'reason' => trim($reason),
                'request_reference' => filled($requestReference) ? trim($requestReference) : null,
            ]);
        });

        try {
            app(NotificationDispatcher::class)->dispatchToTasmiExaminer(
                (int) $changeLog->teacher_id,
                'Jadwal mengajar diperbarui',
                'Pola jadwal mengajar diperbarui dan berlaku mulai '.$start->format('d/m/Y').'. Alasan: '.trim($reason),
                'schedule_changed',
                route('guru.jadwal.riwayat'),
                $type === 'correction' ? 'warning' : 'info',
            );
        } catch (Throwable $exception) {
            Log::warning('Schedule version was saved but its teacher notification could not be sent.', [
                'schedule_change_log_id' => $changeLog->id,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }

        return $changeLog;
    }

    public function fingerprint(int $assignmentId): string
    {
        $rows = DiniyyahTeachingSchedule::query()
            ->where('diniyyah_teacher_assignment_id', $assignmentId)
            ->whereIn('version_status', [DiniyyahTeachingSchedule::STATUS_LEGACY, DiniyyahTeachingSchedule::STATUS_ACTIVE])
            ->orderBy('id')
            ->get();

        return $this->fingerprintRows($rows);
    }

    /** @return array<int, array{day_of_week:int, class_session_id:int}> */
    private function normalizedSlots(array $slots): array
    {
        return collect($slots)
            ->filter(fn ($slot) => filled($slot['day_of_week'] ?? null) && filled($slot['class_session_id'] ?? null))
            ->map(fn ($slot): array => [
                'day_of_week' => (int) $slot['day_of_week'],
                'class_session_id' => (int) $slot['class_session_id'],
            ])
            ->values()
            ->all();
    }

    private function assertNoSlotOverlap(EloquentCollection $rows, Carbon $start, ?Carbon $end, array $slots): void
    {
        $periods = [];
        foreach ($rows as $row) {
            $from = $row->version_status === DiniyyahTeachingSchedule::STATUS_LEGACY || ! $row->effective_from
                ? null
                : Carbon::parse($row->effective_from, 'Asia/Jakarta')->startOfDay();
            $until = $row->version_status === DiniyyahTeachingSchedule::STATUS_LEGACY || ! $row->effective_until
                ? null
                : Carbon::parse($row->effective_until, 'Asia/Jakarta')->startOfDay();
            if (! $this->rowOverlaps($row, $start, $end)) {
                $periods[] = [(int) $row->day_of_week, (int) $row->class_session_id, $from, $until];

                continue;
            }
            if ($from === null || $from->lt($start)) {
                $periods[] = [(int) $row->day_of_week, (int) $row->class_session_id, $from, $start->copy()->subDay()];
            }
            if ($end !== null && ($until === null || $until->gt($end))) {
                $periods[] = [(int) $row->day_of_week, (int) $row->class_session_id, $end->copy()->addDay(), $until];
            }
        }

        foreach ($slots as $slot) {
            $periods[] = [$slot['day_of_week'], $slot['class_session_id'], $start, $end];
        }

        for ($left = 0; $left < count($periods); $left++) {
            for ($right = $left + 1; $right < count($periods); $right++) {
                [$leftDay, $leftSession, $leftFrom, $leftUntil] = $periods[$left];
                [$rightDay, $rightSession, $rightFrom, $rightUntil] = $periods[$right];
                if ($leftDay !== $rightDay || $leftSession !== $rightSession) {
                    continue;
                }
                $leftBeforeRightEnds = $rightUntil === null || $leftFrom === null || $leftFrom->lte($rightUntil);
                $rightBeforeLeftEnds = $leftUntil === null || $rightFrom === null || $rightFrom->lte($leftUntil);
                if ($leftBeforeRightEnds && $rightBeforeLeftEnds) {
                    throw ValidationException::withMessages(['slots' => 'Rentang slot jadwal bertumpang tindih dengan versi lain. Periksa periode dan jadwal yang sudah ada.']);
                }
            }
        }
    }

    private function rowOverlaps(DiniyyahTeachingSchedule $row, Carbon $start, ?Carbon $end): bool
    {
        return $this->rowOverlapsPeriod($row, $start, $end);
    }

    private function rowOverlapsPeriod(DiniyyahTeachingSchedule $row, Carbon $start, ?Carbon $end): bool
    {
        if ($row->version_status === DiniyyahTeachingSchedule::STATUS_LEGACY) {
            return true;
        }
        $rowFrom = $row->effective_from ? Carbon::parse($row->effective_from, 'Asia/Jakarta')->startOfDay() : null;
        $rowUntil = $row->effective_until ? Carbon::parse($row->effective_until, 'Asia/Jakarta')->startOfDay() : null;

        return ($end === null || $rowFrom === null || $rowFrom->lte($end))
            && ($rowUntil === null || $rowUntil->gte($start));
    }

    private function copySlice(DiniyyahTeachingSchedule $source, ?Carbon $start, ?Carbon $end): void
    {
        if ($start && $end && $start->gt($end)) {
            return;
        }
        $copy = new DiniyyahTeachingSchedule([
            'diniyyah_teacher_assignment_id' => $source->diniyyah_teacher_assignment_id,
            'class_session_id' => $source->class_session_id,
            'day_of_week' => $source->day_of_week,
            'effective_from' => $start?->toDateString(),
            'effective_until' => $end?->toDateString(),
            'version_status' => DiniyyahTeachingSchedule::STATUS_ACTIVE,
            'change_type' => $source->change_type,
            'change_reason' => $source->change_reason,
            'request_reference' => $source->request_reference,
        ]);
        $copy->saveQuietly();
    }

    private function fingerprintRows(EloquentCollection $rows): string
    {
        $serialized = json_encode($rows->map(fn (DiniyyahTeachingSchedule $row): array => [
            $row->id,
            $row->day_of_week,
            $row->class_session_id,
            $row->version_status,
            $row->effective_from?->toDateString(),
            $row->effective_until?->toDateString(),
            $row->updated_at?->toISOString(),
        ])->all());

        return hash('sha256', $serialized === false ? '' : $serialized);
    }

    private function rowSnapshot(DiniyyahTeachingSchedule $row): array
    {
        return [
            'id' => $row->id,
            'day_of_week' => (int) $row->day_of_week,
            'class_session_id' => (int) $row->class_session_id,
            'version_status' => $row->version_status,
            'effective_from' => $row->effective_from?->toDateString(),
            'effective_until' => $row->effective_until?->toDateString(),
        ];
    }

    private function relations(): array
    {
        return [
            'teacherAssignment.teacher',
            'teacherAssignment.classSubject.subject',
            'teacherAssignment.classSubject.classroomTerm.classroom',
            'classSession',
        ];
    }
}
