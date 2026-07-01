<?php

namespace App\Filament\Pages;

use App\Models\ClassroomTerm;
use App\Models\DiniyyahAssessmentSet;
use App\Models\DiniyyahLedgerSnapshot;
use App\Models\ReportCard;
use App\Models\StudentAttendance;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use UnitEnum;

class DemoFlow extends Page
{
    protected static string|UnitEnum|null $navigationGroup = 'Data Sekolah';

    protected static bool $shouldRegisterNavigation = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPlayCircle;

    protected static ?string $navigationLabel = 'Alur Demo';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.demo-flow';

    /** @var array<string, mixed> */
    public array $demo = [];

    public function mount(): void
    {
        $classroomTerm = ClassroomTerm::query()
            ->withCount('enrollments')
            ->where('name', 'M3 Ikhwan Demo')
            ->first();
        $ledger = DiniyyahLedgerSnapshot::query()
            ->where('title', 'Leger Diniyyah M3 Ikhwan Demo')
            ->first();
        $inputAssessmentSet = DiniyyahAssessmentSet::query()
            ->where('title', 'Bahasa Arab Latihan Input')
            ->first();
        $reportCard = ReportCard::query()
            ->where('status', 'published')
            ->whereHas('student', fn ($query) => $query->where('nis', 'DEMO-M3-001'))
            ->first();

        $this->demo = [
            'classroom_term_id' => $classroomTerm?->id,
            'classroom_name' => $classroomTerm?->name ?? 'M3 Ikhwan Demo',
            'student_count' => $classroomTerm?->enrollments_count ?? 0,
            'attendance_count' => StudentAttendance::query()
                ->when($classroomTerm, fn ($query) => $query->where('classroom_term_id', $classroomTerm->id))
                ->count(),
            'ledger_status' => $ledger?->status ?? 'belum ada',
            'ledger_id' => $ledger?->id,
            'published_report_count' => ReportCard::where('status', 'published')->count(),
            'report_card_id' => $reportCard?->id,
            'input_assessment_set_id' => $inputAssessmentSet?->id,
            'links' => [
                'attendance' => $classroomTerm
                    ? route('attendance.edit', ['classroomTerm' => $classroomTerm, 'month' => '2025-07'])
                    : route('attendance.index'),
                'score_input' => $inputAssessmentSet
                    ? route('guru.diniyyah-scores.edit', $inputAssessmentSet)
                    : route('guru.diniyyah-scores.index'),
                'monitoring' => route('diniyyah.monitoring.index'),
                'ledger' => $ledger ? route('diniyyah.ledger.show', $ledger) : '#',
                'report_card' => $reportCard ? route('report-cards.show', $reportCard) : '#',
                'guardian_dashboard' => route('wali.dashboard'),
                'login' => route('login'),
            ],
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'kabag_diniyyah', 'kepala_sekolah']) ?? false;
    }

    public function getTitle(): string|Htmlable
    {
        return 'Alur Demo';
    }
}
