<?php

namespace App\Filament\Pages;

use App\Models\AcademicTerm;
use App\Models\ClassEnrollment;
use App\Models\Student;
use App\Models\TahfidzHalaqah;
use App\Models\TahfidzHalaqahMember;
use App\Services\PlacementService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use UnitEnum;

class HalaqahPlacementBoard extends Page
{
    protected static string|UnitEnum|null $navigationGroup = 'Tahfidz';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $navigationLabel = 'Penempatan Halaqah (Drag & Drop)';

    protected static ?int $navigationSort = 5;

    protected string $view = 'filament.pages.halaqah-placement-board';

    public ?int $academicTermId = null;

    public string $search = '';

    public ?string $gender = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'kabag_tahfidz']) ?? false;
    }

    public function getTitle(): string|Htmlable
    {
        return 'Penempatan Halaqah (Drag & Drop)';
    }

    public function mount(): void
    {
        $this->academicTermId = AcademicTerm::query()
            ->where('is_active', true)
            ->orderByDesc('id')
            ->value('id');
    }

    public function updated(): void
    {
        $this->dispatch('board-refresh');
    }

    public function assignToHalaqah(int $studentId, ?int $halaqahId): void
    {
        if (! $this->academicTermId) {
            return;
        }

        try {
            app(PlacementService::class)->assignHalaqah($this->academicTermId, $studentId, $halaqahId);
            Notification::make()->success()->title('Santri ditempatkan ke halaqah')->send();
        } catch (\Throwable $e) {
            Notification::make()->danger()->title('Gagal menempatkan santri')->body($e->getMessage())->send();
        }

        $this->dispatch('board-refresh');
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $termId = $this->academicTermId;

        if (! $termId) {
            return [
                'academicTerms' => $this->academicTermOptions(),
                'halaqahs' => [],
                'placed' => [],
                'unassigned' => [],
                'hasTerm' => false,
            ];
        }

        $halaqahs = TahfidzHalaqah::query()
            ->where('academic_term_id', $termId)
            ->orderBy('name')
            ->get();

        // Member aktif periode ini.
        $members = TahfidzHalaqahMember::query()
            ->where('status', 'active')
            ->whereHas('halaqah', fn ($q) => $q->where('academic_term_id', $termId))
            ->with('student')
            ->get();

        // Map kelas saat ini per santri (dari enrollment aktif periode ini) untuk
        // ditampilkan di kartu sebagai identifikasi (banyak nama sama).
        $classMap = ClassEnrollment::query()
            ->where('academic_term_id', $termId)
            ->where('status', 'active')
            ->with('classroomTerm')
            ->get()
            ->mapWithKeys(fn (ClassEnrollment $e) => [$e->student_id => $e->classroomTerm?->name ?? '—']);

        $placedStudentIds = $members->pluck('student_id')->all();

        $unassigned = Student::query()
            ->where('status', 'active')
            ->whereNotIn('id', $placedStudentIds)
            ->orderBy('name')
            ->get();

        $filter = fn (Student $s): bool => $this->matchesFilters($s);

        $placed = [];
        foreach ($halaqahs as $halaqah) {
            $placed[$halaqah->id] = $members
                ->filter(fn (TahfidzHalaqahMember $m) => (int) $m->tahfidz_halaqah_id === (int) $halaqah->id && $m->student && $filter($m->student))
                ->map(fn (TahfidzHalaqahMember $m) => $this->cardData($m->student, $classMap[$m->student_id] ?? '—'))
                ->values();
        }

        $unassignedCards = $unassigned
            ->filter($filter)
            ->map(fn (Student $s) => $this->cardData($s, $classMap[$s->id] ?? '—'))
            ->values();

        return [
            'academicTerms' => $this->academicTermOptions(),
            'halaqahs' => $halaqahs,
            'placed' => $placed,
            'unassigned' => $unassignedCards,
            'hasTerm' => true,
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function academicTermOptions(): array
    {
        return AcademicTerm::query()
            ->with('academicYear')
            ->orderByDesc('id')
            ->get()
            ->mapWithKeys(fn (AcademicTerm $t) => [
                $t->id => trim(sprintf('%s — %s', $t->academicYear?->name ?? '-', $t->name)),
            ])
            ->all();
    }

    protected function matchesFilters(Student $student): bool
    {
        if ($this->gender && $student->gender !== $this->gender) {
            return false;
        }

        if ($this->search !== '') {
            $needle = mb_strtolower(trim($this->search));
            $haystack = mb_strtolower($student->name.' '.$student->nis);

            if (! str_contains($haystack, $needle)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    protected function cardData(Student $student, ?string $classroomName): array
    {
        return [
            'id' => $student->id,
            'name' => $student->name,
            'nis' => $student->nis,
            'gender' => $student->gender,
            'classroom' => $classroomName ?? '—',
        ];
    }
}