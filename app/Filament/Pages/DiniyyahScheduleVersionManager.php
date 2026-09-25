<?php

namespace App\Filament\Pages;

use App\Models\ClassSession;
use App\Models\DiniyyahScheduleChangeLog;
use App\Models\DiniyyahTeacherAssignment;
use App\Models\DiniyyahTeachingSchedule;
use App\Services\AdminMonthlyJpReportService;
use App\Services\DiniyyahScheduleVersionService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use UnitEnum;

class DiniyyahScheduleVersionManager extends Page
{
    protected static string|UnitEnum|null $navigationGroup = 'Diniyyah';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $navigationLabel = 'Versi & Koreksi Jadwal';

    protected static ?int $navigationSort = 29;

    protected string $view = 'filament.pages.diniyyah-schedule-version-manager';

    public ?int $assignmentId = null;

    public string $changeType = 'correction';

    public string $effectiveFrom = '';

    public ?string $effectiveUntil = '';

    public string $reason = '';

    public ?string $requestReference = null;

    public ?int $legacyLogId = null;

    /** @var array<int, array{day_of_week:string, class_session_id:string}> */
    public array $weeklySlots = [];

    /** @var array{gone:array, appeared:array, range_label:string}|null */
    public ?array $preview = null;

    public ?string $previewInputSignature = null;

    public ?string $previewScheduleFingerprint = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'kabag_diniyyah']) ?? false;
    }

    public function mount(): void
    {
        $this->effectiveFrom = Carbon::now('Asia/Jakarta')->toDateString();
        $this->effectiveUntil = $this->effectiveFrom;
        $this->assignmentId = DiniyyahTeacherAssignment::query()
            ->whereHas('classSubject.classroomTerm', fn ($query) => $query->where('status', 'active'))
            ->orderBy('id')
            ->value('id');
        $this->loadCurrentPattern(app(DiniyyahScheduleVersionService::class));
    }

    public function getTitle(): string|Htmlable
    {
        return 'Versi & Koreksi Jadwal Mengajar';
    }

    public function updated(string $property): void
    {
        if (! str_starts_with($property, 'preview')) {
            $this->clearPreview();
        }
    }

    public function updatedAssignmentId(DiniyyahScheduleVersionService $versions): void
    {
        $this->legacyLogId = null;
        $this->loadCurrentPattern($versions);
    }

    public function updatedLegacyLogId(DiniyyahScheduleVersionService $versions): void
    {
        if (! $this->legacyLogId) {
            return;
        }
        $log = $this->pendingLegacyLogs()->firstWhere('id', $this->legacyLogId);
        if ($log) {
            $this->assignmentId = (int) $log->diniyyah_teacher_assignment_id;
            $this->loadCurrentPattern($versions);
        }
    }

    public function addSlot(): void
    {
        $this->weeklySlots[] = ['day_of_week' => '', 'class_session_id' => ''];
        $this->clearPreview();
    }

    public function removeSlot(int $index): void
    {
        unset($this->weeklySlots[$index]);
        $this->weeklySlots = array_values($this->weeklySlots);
        $this->clearPreview();
    }

    public function loadCurrentPattern(DiniyyahScheduleVersionService $versions): void
    {
        if (! $this->assignmentId) {
            $this->weeklySlots = [['day_of_week' => '', 'class_session_id' => '']];

            return;
        }

        $date = $this->effectiveFrom ?: Carbon::now('Asia/Jakarta')->toDateString();
        $this->weeklySlots = $versions->schedulesForDate($date, $this->assignmentId)
            ->filter(fn (DiniyyahTeachingSchedule $schedule): bool => $schedule->appliesOn($date))
            ->map(fn (DiniyyahTeachingSchedule $schedule): array => [
                'day_of_week' => (string) $schedule->day_of_week,
                'class_session_id' => (string) $schedule->class_session_id,
            ])
            ->unique(fn (array $slot): string => $slot['day_of_week'].'|'.$slot['class_session_id'])
            ->values()
            ->all();

        if ($this->weeklySlots === []) {
            $this->weeklySlots = [['day_of_week' => '', 'class_session_id' => '']];
        }
        $this->clearPreview();
    }

    public function preview(DiniyyahScheduleVersionService $versions, AdminMonthlyJpReportService $reports): void
    {
        $this->validateInput($versions);
        $start = Carbon::parse($this->effectiveFrom, 'Asia/Jakarta')->startOfDay();
        $until = filled($this->effectiveUntil) ? Carbon::parse($this->effectiveUntil, 'Asia/Jakarta')->startOfDay() : null;
        $reportEnd = $until ?? $start->copy()->addDays(6);

        if ($this->changeType === 'correction') {
            $this->effectiveUntil = $until?->toDateString();
        }

        $assignment = DiniyyahTeacherAssignment::with('classSubject.classroomTerm')->findOrFail($this->assignmentId);
        $termId = $assignment->classSubject?->classroomTerm?->academic_term_id;
        $before = $reports->buildForRange($termId, $start, $reportEnd, includeFuture: true);
        $override = $versions->previewSchedules($this->assignmentId, $start, $reportEnd, $this->weeklySlots);
        $after = $reports->buildForRange($termId, $start, $reportEnd, $override, includeFuture: true);

        $beforeRows = collect($before['missing'])->keyBy(fn (array $row): string => $this->missingSlotKey($row));
        $afterRows = collect($after['missing'])->keyBy(fn (array $row): string => $this->missingSlotKey($row));
        $this->preview = [
            'gone' => $beforeRows->reject(fn ($row, string $key) => $afterRows->has($key))->values()->all(),
            'appeared' => $afterRows->reject(fn ($row, string $key) => $beforeRows->has($key))->values()->all(),
            'range_label' => $start->locale('id')->translatedFormat('d M Y').' – '.$reportEnd->locale('id')->translatedFormat('d M Y'),
        ];
        $this->previewInputSignature = $this->inputSignature();
        $this->previewScheduleFingerprint = $versions->fingerprint($this->assignmentId);

        Notification::make()->success()->title('Pratinjau siap')->body('Periksa slot kosong yang akan hilang atau muncul sebelum menerapkan.')->send();
    }

    public function apply(DiniyyahScheduleVersionService $versions): void
    {
        if (! $this->preview || $this->previewInputSignature !== $this->inputSignature()) {
            throw ValidationException::withMessages(['preview' => 'Buat pratinjau kembali sebelum menerapkan perubahan.']);
        }
        if ($this->previewScheduleFingerprint !== $versions->fingerprint((int) $this->assignmentId)) {
            $this->clearPreview();
            throw ValidationException::withMessages(['preview' => 'Jadwal berubah setelah pratinjau. Periksa ulang sebelum menerapkan.']);
        }

        $versions->apply(
            (int) $this->assignmentId,
            $this->changeType,
            $this->effectiveFrom,
            filled($this->effectiveUntil) ? $this->effectiveUntil : null,
            $this->weeklySlots,
            $this->reason,
            $this->requestReference,
            $this->legacyLogId,
            $this->previewScheduleFingerprint,
        );

        $this->clearPreview();
        $this->reason = '';
        $this->requestReference = null;
        $this->legacyLogId = null;
        $this->loadCurrentPattern($versions);
        Notification::make()->success()->title('Versi jadwal diterapkan')->body('Jurnal dan log lama tetap tersimpan; laporan menggunakan jadwal sesuai tanggal.')->send();
    }

    protected function getViewData(): array
    {
        $assignments = DiniyyahTeacherAssignment::query()
            ->with(['teacher', 'classSubject.subject', 'classSubject.classroomTerm'])
            ->whereHas('classSubject.classroomTerm')
            ->orderBy('id')
            ->get();
        $sessions = ClassSession::query()->orderByRaw('CAST(session_name AS UNSIGNED)')->orderBy('session_name')->get();
        $pendingLogs = $this->pendingLegacyLogs()->with(['teacher', 'changer', 'assignment.classSubject.subject', 'assignment.classSubject.classroomTerm'])
            ->limit(500)->get()
            ->reject(fn (DiniyyahScheduleChangeLog $log): bool => array_key_exists('diniyyah_teacher_assignment_id', $log->old_values ?? [])
                || array_key_exists('diniyyah_teacher_assignment_id', $log->new_values ?? []))
            ->take(100)
            ->values();
        $versions = DiniyyahTeachingSchedule::query()->with([
            'teacherAssignment.teacher',
            'teacherAssignment.classSubject.subject',
            'teacherAssignment.classSubject.classroomTerm',
            'classSession',
        ])->orderBy('diniyyah_teacher_assignment_id')->orderBy('effective_from')->orderBy('day_of_week')->get();

        return compact('assignments', 'sessions', 'pendingLogs', 'versions');
    }

    private function pendingLegacyLogs()
    {
        return DiniyyahScheduleChangeLog::query()
            ->where('entity_type', 'schedule')
            ->whereIn('event', ['updated', 'deleted'])
            ->whereNull('reviewed_at')
            ->whereNotNull('diniyyah_teacher_assignment_id')
            ->orderByDesc('created_at');
    }

    private function validateInput(DiniyyahScheduleVersionService $versions): void
    {
        Validator::make([
            'assignmentId' => $this->assignmentId,
            'changeType' => $this->changeType,
            'effectiveFrom' => $this->effectiveFrom,
            'effectiveUntil' => $this->effectiveUntil,
            'reason' => $this->reason,
            'requestReference' => $this->requestReference,
            'legacyLogId' => $this->legacyLogId,
        ], [
            'assignmentId' => ['required', 'integer', 'exists:diniyyah_teacher_assignments,id'],
            'changeType' => ['required', 'in:correction,approved'],
            'effectiveFrom' => ['required', 'date'],
            'effectiveUntil' => ['nullable', 'date', 'after_or_equal:effectiveFrom'],
            'reason' => ['required', 'string', 'min:4', 'max:3000'],
            'requestReference' => ['nullable', 'string', 'max:120'],
            'legacyLogId' => ['nullable', 'integer', 'exists:diniyyah_schedule_change_logs,id'],
        ])->validate();

        $end = filled($this->effectiveUntil) ? $this->effectiveUntil : null;
        $versions->validateChange((int) $this->assignmentId, $this->changeType, $this->effectiveFrom, $end, $this->weeklySlots);
        if ($this->legacyLogId) {
            $log = $this->pendingLegacyLogs()->find($this->legacyLogId);
            if (! $log
                || (int) $log->diniyyah_teacher_assignment_id !== (int) $this->assignmentId
                || array_key_exists('diniyyah_teacher_assignment_id', $log->old_values ?? [])
                || array_key_exists('diniyyah_teacher_assignment_id', $log->new_values ?? [])) {
                throw ValidationException::withMessages(['legacyLogId' => 'Pilih log lama yang belum ditinjau dan sesuai dengan penugasan.']);
            }
        }
    }

    private function missingSlotKey(array $row): string
    {
        return hash('sha256', json_encode([
            $row['date'] ?? null,
            $row['teacher_id'] ?? null,
            $row['session'] ?? null,
            $row['session_time'] ?? null,
            $row['classes'] ?? [],
            $row['subjects'] ?? [],
        ], JSON_UNESCAPED_UNICODE) ?: '');
    }

    private function inputSignature(): string
    {
        return hash('sha256', json_encode([
            $this->assignmentId,
            $this->changeType,
            $this->effectiveFrom,
            $this->effectiveUntil,
            trim($this->reason),
            trim((string) $this->requestReference),
            $this->legacyLogId,
            collect($this->weeklySlots)->filter(fn ($slot) => filled($slot['day_of_week'] ?? null) || filled($slot['class_session_id'] ?? null))->values()->all(),
        ], JSON_UNESCAPED_UNICODE) ?: '');
    }

    private function clearPreview(): void
    {
        $this->preview = null;
        $this->previewInputSignature = null;
        $this->previewScheduleFingerprint = null;
    }
}
