<?php

namespace App\Filament\Pages;

use App\Models\AcademicTerm;
use App\Models\ClassEnrollment;
use App\Models\ClassroomTerm;
use App\Models\Student;
use App\Services\PlacementService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use UnitEnum;

class ClassPlacementBoard extends Page
{
    protected static string|UnitEnum|null $navigationGroup = 'Struktur Kelas';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static ?string $navigationLabel = 'Penempatan Santri (Drag & Drop)';

    protected static ?int $navigationSort = 5;

    protected string $view = 'filament.pages.class-placement-board';

    public ?int $academicTermId = null;

    public string $search = '';

    public ?string $gender = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['admin']) ?? false;
    }

    public function getTitle(): string|Htmlable
    {
        return 'Penempatan Santri (Drag & Drop)';
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
        // Kolom/kartu berubah saat periode/filter diganti → re-init Sortable.
        $this->dispatch('board-refresh');
    }

    public function assignToClass(int $studentId, ?int $classroomTermId): void
    {
        if (! $this->academicTermId) {
            return;
        }

        try {
            app(PlacementService::class)->assignClass($this->academicTermId, $studentId, $classroomTermId);
            Notification::make()->success()->title('Santri ditempatkan')->send();
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
                'classroomTerms' => [],
                'placed' => [],
                'unassigned' => [],
                'hasTerm' => false,
            ];
        }

        $classroomTerms = ClassroomTerm::query()
            ->where('academic_term_id', $termId)
            ->orderBy('name')
            ->get();

        // Enrollment aktif periode ini, muat santrinya.
        $enrollments = ClassEnrollment::query()
            ->where('academic_term_id', $termId)
            ->where('status', 'active')
            ->with('student')
            ->get();

        $placedStudentIds = $enrollments->pluck('student_id')->all();

        // Santri aktif yang belum ditempatkan (tidak punya enrollment aktif periode ini).
        $unassigned = Student::query()
            ->where('status', 'active')
            ->whereNotIn('id', $placedStudentIds)
            ->orderBy('name')
            ->get();

        // Terapkan filter search & gender ke semua kartu.
        $filter = fn (Student $s): bool => $this->matchesFilters($s);

        $placed = [];
        foreach ($classroomTerms as $ct) {
            $placed[$ct->id] = $enrollments
                ->filter(fn (ClassEnrollment $e) => (int) $e->classroom_term_id === (int) $ct->id && $e->student && $filter($e->student))
                ->map(fn (ClassEnrollment $e) => $this->cardData($e->student, $ct->name))
                ->values();
        }

        $unassignedCards = $unassigned
            ->filter($filter)
            ->map(fn (Student $s) => $this->cardData($s, null))
            ->values();

        return [
            'academicTerms' => $this->academicTermOptions(),
            'classroomTerms' => $classroomTerms,
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