<?php

namespace App\Services;

use App\Models\ClassSession;
use App\Models\DiniyyahClassSubject;
use App\Models\DiniyyahScheduleChangeLog;
use App\Models\DiniyyahTeacherAssignment;
use App\Models\DiniyyahTeachingSchedule;
use App\Models\Teacher;
use App\Support\SessionTimetable;
use Illuminate\Support\Facades\Auth;

/**
 * Mencatat riwayat perubahan jadwal mengajar & penugasan diniyyah ke
 * {@see DiniyyahScheduleChangeLog}. Dipanggil oleh
 * DiniyyahTeachingScheduleObserver & DiniyyahTeacherAssignmentObserver.
 *
 * Summary human-readable (Bahasa Indonesia) dibangun saat log-time dengan
 * me-resolve label hari/sesi/mapel/kelas/guru dari relasi, sehingga riwayat
 * tetap terbaca setelah record terkait dihapus (FK di-null-out, summary utuh).
 *
 * Jam sesi di-resolve via {@see SessionTimetable} (matrix per-classroom, sumber
 * kebenaran) lalu fallback ke jam global ClassSession.
 */
class DiniyyahScheduleChangeLogger
{
    /** Field jadwal yang dilacak perubahannya. */
    private const SCHEDULE_FIELDS = ['day_of_week', 'class_session_id', 'diniyyah_teacher_assignment_id'];

    /** Field penugasan yang dilacak perubahannya. */
    private const ASSIGNMENT_FIELDS = ['teacher_id', 'diniyyah_class_subject_id', 'assignment_role', 'starts_at', 'ends_at'];

    public function logScheduleCreated(DiniyyahTeachingSchedule $schedule): void
    {
        $ctx = $this->scheduleContext(
            (int) $schedule->diniyyah_teacher_assignment_id,
            (int) $schedule->class_session_id,
            (int) $schedule->day_of_week,
        );

        $this->write([
            'teacher_id' => $ctx['teacher_id'],
            'old_teacher_id' => null,
            'diniyyah_teacher_assignment_id' => $schedule->diniyyah_teacher_assignment_id,
            'diniyyah_teaching_schedule_id' => $schedule->id,
            'entity_type' => 'schedule',
            'event' => 'created',
            'change_summary' => "Jadwal baru dibuat: {$ctx['subject_label']} — {$ctx['day_label']} {$ctx['session_label']}, guru: {$ctx['teacher_name']}.",
            'old_values' => null,
            'new_values' => null,
        ]);
    }

    public function logScheduleUpdated(DiniyyahTeachingSchedule $schedule, array $original): void
    {
        $old = $this->onlyChanged($original, $schedule, self::SCHEDULE_FIELDS);
        if (empty($old)) {
            return;
        }

        $oldCtx = $this->scheduleContext(
            (int) ($original['diniyyah_teacher_assignment_id'] ?? $schedule->diniyyah_teacher_assignment_id),
            (int) ($original['class_session_id'] ?? $schedule->class_session_id),
            (int) ($original['day_of_week'] ?? $schedule->day_of_week),
        );
        $newCtx = $this->scheduleContext(
            (int) $schedule->diniyyah_teacher_assignment_id,
            (int) $schedule->class_session_id,
            (int) $schedule->day_of_week,
        );

        $assignmentSwapped = ($original['diniyyah_teacher_assignment_id'] ?? null) !== null
            && (string) $original['diniyyah_teacher_assignment_id'] !== (string) $schedule->diniyyah_teacher_assignment_id;

        if ($assignmentSwapped) {
            $summary = "Jadwal diubah: guru {$oldCtx['teacher_name']} ({$oldCtx['subject_label']}) → {$newCtx['teacher_name']} ({$newCtx['subject_label']}), {$newCtx['day_label']} {$newCtx['session_label']}.";
            $oldTeacherId = $oldCtx['teacher_id'];
            $teacherId = $newCtx['teacher_id'];
        } else {
            $changes = [];
            if (array_key_exists('day_of_week', $old)) {
                $changes[] = "hari {$oldCtx['day_label']} → {$newCtx['day_label']}";
            }
            if (array_key_exists('class_session_id', $old)) {
                $changes[] = "{$oldCtx['session_label']} → {$newCtx['session_label']}";
            }
            $summary = "Jadwal diubah: {$newCtx['subject_label']} — ".implode(', ', $changes).", guru: {$newCtx['teacher_name']}.";
            $oldTeacherId = null;
            $teacherId = $newCtx['teacher_id'];
        }

        $this->write([
            'teacher_id' => $teacherId,
            'old_teacher_id' => $oldTeacherId,
            'diniyyah_teacher_assignment_id' => $schedule->diniyyah_teacher_assignment_id,
            'diniyyah_teaching_schedule_id' => $schedule->id,
            'entity_type' => 'schedule',
            'event' => 'updated',
            'change_summary' => $summary,
            'old_values' => $old,
            'new_values' => $this->currentValues($schedule, array_keys($old)),
        ]);
    }

    public function logScheduleDeleted(DiniyyahTeachingSchedule $schedule): void
    {
        $ctx = $this->scheduleContext(
            (int) $schedule->diniyyah_teacher_assignment_id,
            (int) $schedule->class_session_id,
            (int) $schedule->day_of_week,
        );

        $this->write([
            'teacher_id' => $ctx['teacher_id'],
            'old_teacher_id' => null,
            'diniyyah_teacher_assignment_id' => $schedule->diniyyah_teacher_assignment_id,
            'diniyyah_teaching_schedule_id' => null, // schedule akan dihapus → null-out FK
            'entity_type' => 'schedule',
            'event' => 'deleted',
            'change_summary' => "Jadwal dihapus: {$ctx['subject_label']} — {$ctx['day_label']} {$ctx['session_label']}, guru: {$ctx['teacher_name']}. (Jurnal kelas tidak terpengaruh.)",
            'old_values' => null,
            'new_values' => null,
        ]);
    }

    public function logAssignmentCreated(DiniyyahTeacherAssignment $assignment): void
    {
        $subjectLabel = $this->classSubjectLabel($assignment->diniyyah_class_subject_id);
        $teacherName = $this->teacherLabel($assignment->teacher_id);
        $role = $this->roleLabel((string) $assignment->assignment_role);

        $this->write([
            'teacher_id' => $assignment->teacher_id,
            'old_teacher_id' => null,
            'diniyyah_teacher_assignment_id' => $assignment->id,
            'diniyyah_teaching_schedule_id' => null,
            'entity_type' => 'assignment',
            'event' => 'created',
            'change_summary' => "Penugasan baru: {$teacherName} mengampu {$subjectLabel} (peran: {$role}).",
            'old_values' => null,
            'new_values' => null,
        ]);
    }

    public function logAssignmentUpdated(DiniyyahTeacherAssignment $assignment, array $original): void
    {
        $old = $this->onlyChanged($original, $assignment, self::ASSIGNMENT_FIELDS);
        if (empty($old)) {
            return;
        }

        $changes = [];
        if (array_key_exists('teacher_id', $old)) {
            $changes[] = "guru {$this->teacherLabel((int) $old['teacher_id'])} → {$this->teacherLabel((int) $assignment->teacher_id)}";
        }
        if (array_key_exists('diniyyah_class_subject_id', $old)) {
            $changes[] = "mapel {$this->classSubjectLabel((int) $old['diniyyah_class_subject_id'])} → {$this->classSubjectLabel((int) $assignment->diniyyah_class_subject_id)}";
        }
        if (array_key_exists('assignment_role', $old)) {
            $changes[] = "peran {$this->roleLabel((string) $old['assignment_role'])} → {$this->roleLabel((string) $assignment->assignment_role)}";
        }
        if (array_key_exists('starts_at', $old) || array_key_exists('ends_at', $old)) {
            $changes[] = "periode {$this->rangeLabel($old['starts_at'] ?? null, $old['ends_at'] ?? null)} → {$this->rangeLabel($assignment->starts_at, $assignment->ends_at)}";
        }

        $summary = 'Penugasan diubah: '.implode('; ', $changes).'.';
        $teacherSwapped = array_key_exists('teacher_id', $old);
        if ($teacherSwapped) {
            $summary .= ' (Jurnal yang sudah terisi tetap menempel pada penugasan ini.)';
        }

        $this->write([
            'teacher_id' => $assignment->teacher_id,
            'old_teacher_id' => $teacherSwapped ? (int) $old['teacher_id'] : null,
            'diniyyah_teacher_assignment_id' => $assignment->id,
            'diniyyah_teaching_schedule_id' => null,
            'entity_type' => 'assignment',
            'event' => 'updated',
            'change_summary' => $summary,
            'old_values' => $old,
            'new_values' => $this->currentValues($assignment, array_keys($old)),
        ]);
    }

    public function logAssignmentDeleted(DiniyyahTeacherAssignment $assignment): void
    {
        $subjectLabel = $this->classSubjectLabel($assignment->diniyyah_class_subject_id);
        $teacherName = $this->teacherLabel($assignment->teacher_id);
        $scheduleCount = (int) $assignment->schedules()->count();

        $this->write([
            'teacher_id' => $assignment->teacher_id,
            'old_teacher_id' => null,
            'diniyyah_teacher_assignment_id' => null, // assignment dihapus → null-out FK
            'diniyyah_teaching_schedule_id' => null,
            'entity_type' => 'assignment',
            'event' => 'deleted',
            'change_summary' => "Penugasan dihapus: {$teacherName} mengampu {$subjectLabel}. ({$scheduleCount} jadwal terkait juga dihapus; tidak ada jurnal terkait.)",
            'old_values' => null,
            'new_values' => null,
        ]);
    }

    // ----- Helpers -----

    /**
     * Tulis satu baris log. `changed_by` dari Auth::id() (null saat CLI/tinker).
     */
    private function write(array $attributes): void
    {
        $attributes['changed_by'] = Auth::id();

        DiniyyahScheduleChangeLog::create($attributes);
    }

    /**
     * Diff: kembalikan subset $original hanya untuk field yang berubah vs nilai
     * saat ini pada $model.
     *
     * @param  array<string, mixed>  $original
     * @param  list<string>  $fields
     * @return array<string, mixed>
     */
    private function onlyChanged(array $original, DiniyyahTeachingSchedule|DiniyyahTeacherAssignment $model, array $fields): array
    {
        $changed = [];
        foreach ($fields as $field) {
            if (! array_key_exists($field, $original)) {
                continue;
            }
            if ((string) $original[$field] !== (string) $model->getAttribute($field)) {
                $changed[$field] = $original[$field];
            }
        }

        return $changed;
    }

    /**
     * Nilai saat ini untuk field yang berubah (untuk kolom new_values).
     *
     * @param  list<string>  $fields
     * @return array<string, mixed>
     */
    private function currentValues(DiniyyahTeachingSchedule|DiniyyahTeacherAssignment $model, array $fields): array
    {
        $current = [];
        foreach ($fields as $field) {
            $current[$field] = $model->getAttribute($field);
        }

        return $current;
    }

    /**
     * Konteks label sebuah schedule snapshot (guru/mapel/kelas/hari/sesi).
     *
     * @return array{teacher_id: ?int, teacher_name: string, subject_label: string, classroom_id: ?int, classroom_name: string, day_label: string, session_label: string}
     */
    private function scheduleContext(int $assignmentId, int $classSessionId, int $dayOfWeek): array
    {
        $assignment = DiniyyahTeacherAssignment::with([
            'classSubject.subject',
            'classSubject.classroomTerm.classroom',
            'teacher',
        ])->find($assignmentId);

        $classroom = $assignment?->classSubject?->classroomTerm?->classroom;

        return [
            'teacher_id' => $assignment?->teacher_id,
            'teacher_name' => $assignment?->teacher?->name ?? '-',
            'subject_label' => $this->classSubjectLabel($assignment?->diniyyah_class_subject_id),
            'classroom_id' => $classroom?->id,
            'classroom_name' => $assignment?->classSubject?->classroomTerm?->name ?? '-',
            'day_label' => $this->dayLabel($dayOfWeek),
            'session_label' => $this->sessionLabel($classSessionId, $classroom?->id, $dayOfWeek),
        ];
    }

    private function dayLabel(int $day): string
    {
        return match ($day) {
            1 => 'Senin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => 'Jumat',
            6 => 'Sabtu',
            7 => 'Minggu',
            default => (string) $day,
        };
    }

    private function sessionLabel(int $classSessionId, ?int $classroomId, ?int $dayOfWeek): string
    {
        $session = ClassSession::find($classSessionId);
        if (! $session) {
            return "Sesi #{$classSessionId}";
        }

        $label = SessionTimetable::label((string) $session->session_name);

        $startsAt = $session->starts_at;
        $endsAt = $session->ends_at;
        if ($classroomId && $dayOfWeek) {
            $resolved = SessionTimetable::resolve($classroomId, $dayOfWeek, (string) $session->session_name);
            if ($resolved) {
                $startsAt = $resolved['starts_at'];
                $endsAt = $resolved['ends_at'];
            }
        }

        $jam = $this->formatTimeRange($startsAt, $endsAt);

        return $label.$jam;
    }

    private function classSubjectLabel(?int $id): string
    {
        if (! $id) {
            return '-';
        }
        $cs = DiniyyahClassSubject::with(['subject', 'classroomTerm'])->find($id);
        if (! $cs) {
            return "Mapel #{$id}";
        }

        return ($cs->subject?->name ?? '-').' / '.($cs->classroomTerm?->name ?? '-');
    }

    private function teacherLabel(?int $id): string
    {
        if (! $id) {
            return '-';
        }

        return Teacher::find($id)?->name ?? "Guru #{$id}";
    }

    private function roleLabel(string $role): string
    {
        return match ($role) {
            'primary' => 'Guru Utama',
            'assistant' => 'Guru Pendamping',
            default => $role,
        };
    }

    /**
     * Label rentang periode penugasan: "01/08/2026 – 31/12/2026" / "– 31/12/2026"
     * / "tanpa batas".
     */
    private function rangeLabel(mixed $startsAt, mixed $endsAt): string
    {
        $start = $this->formatDate($startsAt);
        $end = $this->formatDate($endsAt);
        if ($start === null && $end === null) {
            return 'tanpa batas';
        }

        return ($start ?? '–').' – '.($end ?? 'tanpa batas');
    }

    private function formatDate(mixed $date): ?string
    {
        if ($date === null) {
            return null;
        }
        try {
            return \Illuminate\Support\Carbon::parse($date)->format('d/m/Y');
        } catch (\Throwable) {
            return (string) $date;
        }
    }

    private function formatTimeRange(mixed $startsAt, mixed $endsAt): string
    {
        $start = $this->formatTime($startsAt);
        $end = $this->formatTime($endsAt);
        if ($start === null && $end === null) {
            return '';
        }

        return ' ('.($start ?? '–').'-'.($end ?? '–').')';
    }

    private function formatTime(mixed $time): ?string
    {
        if ($time === null) {
            return null;
        }
        $s = (string) $time;
        if ($s === '') {
            return null;
        }

        return substr($s, 0, 5) ?: null;
    }
}