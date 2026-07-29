<?php

namespace App\Filament\Pages;

use App\Models\AcademicTerm;
use App\Models\ClassroomTerm;
use App\Models\DiniyyahSubject;
use App\Models\DiniyyahTeacherAssignment;
use App\Models\Teacher;
use App\Services\RingkasanPenugasanGuruService;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use UnitEnum;

class RingkasanPenugasanGuru extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|UnitEnum|null $navigationGroup = 'Diniyyah';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $navigationLabel = 'Ringkasan Penugasan Guru';

    protected static ?int $navigationSort = 31;

    protected string $view = 'filament.pages.ringkasan-penugasan-guru';

    /** @var array<int, array{id: int, label: string}> */
    public array $termOptions = [];

    public ?int $academicTermId = null;

    /** @var array<string, int> */
    public array $stats = [];

    public string $termLabel = '-';

    public function mount(RingkasanPenugasanGuruService $service): void
    {
        $terms = AcademicTerm::query()
            ->with('academicYear')
            ->orderByDesc('starts_at')
            ->get();

        $this->termOptions = $terms
            ->map(fn (AcademicTerm $term) => [
                'id' => $term->id,
                'label' => trim(($term->academicYear?->name ?? '-').' - '.$term->name),
            ])
            ->values()
            ->all();

        $selected = $terms->firstWhere('id', (int) request()->query('term'))
            ?? $terms->firstWhere('is_active', true)
            ?? $terms->first();

        $this->academicTermId = $selected?->id;

        $this->rebuildStats($service);
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'kabag_diniyyah', 'kepala_sekolah']) ?? false;
    }

    public function getTitle(): string|Htmlable
    {
        return 'Ringkasan Data Penugasan Guru';
    }

    public function updatedAcademicTermId(RingkasanPenugasanGuruService $service): void
    {
        $this->resetTable();
        $this->rebuildStats($service);
    }

    private function rebuildStats(RingkasanPenugasanGuruService $service): void
    {
        $result = $service->stats($this->academicTermId);
        $this->termLabel = $result['term_label'];
        $this->stats = $result['stats'];
    }

    public function table(Table $table): Table
    {
        $today = Carbon::now('Asia/Jakarta')->toDateString();
        $days = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];

        return $table
            ->query(fn (): Builder => DiniyyahTeacherAssignment::query()
                ->with([
                    'classSubject.classroomTerm.academicTerm.academicYear',
                    'classSubject.subject',
                    'teacher',
                    'schedules.classSession',
                ])
                ->join('diniyyah_class_subjects', 'diniyyah_class_subjects.id', '=', 'diniyyah_teacher_assignments.diniyyah_class_subject_id')
                ->join('classroom_terms', 'classroom_terms.id', '=', 'diniyyah_class_subjects.classroom_term_id')
                ->where('classroom_terms.academic_term_id', $this->academicTermId)
                ->whereNull('diniyyah_class_subjects.deleted_at')
                ->orderBy('classroom_terms.name')
                ->orderBy('diniyyah_class_subjects.sort_order')
                ->orderBy('diniyyah_teacher_assignments.assignment_role')
                ->select('diniyyah_teacher_assignments.*'))
            ->columns([
                TextColumn::make('classSubject.classroomTerm.name')
                    ->label('Kelas')
                    ->searchable()
                    ->placeholder('-'),
                TextColumn::make('classSubject.subject.name')
                    ->label('Mapel')
                    ->searchable()
                    ->placeholder('-'),
                TextColumn::make('teacher.name')
                    ->label('Guru')
                    ->searchable()
                    ->sortable()
                    ->placeholder('(belum diisi)'),
                TextColumn::make('assignment_role')
                    ->label('Peran')
                    ->badge()
                    ->colors([
                        'success' => 'primary',
                        'gray' => 'assistant',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'primary' => 'Utama',
                        'assistant' => 'Pendamping',
                        default => $state,
                    }),
                TextColumn::make('schedules_list')
                    ->label('Jadwal')
                    ->getStateUsing(function (DiniyyahTeacherAssignment $record) use ($days): array {
                        return $record->schedules
                            ->filter(fn ($s) => $s->classSession && ! $s->classSession->is_break)
                            ->sortBy('day_of_week')
                            ->map(function ($s) use ($days): string {
                                $start = $s->classSession->starts_at ? Carbon::parse($s->classSession->starts_at)->format('H:i') : '?';
                                $end = $s->classSession->ends_at ? Carbon::parse($s->classSession->ends_at)->format('H:i') : '?';
                                $day = $days[$s->day_of_week - 1] ?? '?';

                                return "{$day} {$start}-{$end}";
                            })
                            ->values()
                            ->all();
                    })
                    ->listWithLineBreaks()
                    ->placeholder('belum dijadwalkan'),
                TextColumn::make('starts_at')
                    ->label('Mulai')
                    ->date()
                    ->sortable()
                    ->placeholder('-'),
                TextColumn::make('ends_at')
                    ->label('Selesai')
                    ->date()
                    ->sortable()
                    ->placeholder('-'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->getStateUsing(fn (DiniyyahTeacherAssignment $record): string => (blank($record->ends_at) || $record->ends_at->greaterThanOrEqualTo($today)) ? 'Aktif' : 'Berakhir')
                    ->colors([
                        'success' => 'Aktif',
                        'gray' => 'Berakhir',
                    ]),
            ])
            ->filters([
                Filter::make('classroom_term_id')
                    ->label('Kelas')
                    ->schema([
                        Select::make('value')
                            ->label('Kelas')
                            ->options(fn () => ClassroomTerm::orderBy('name')->pluck('name', 'id')->all())
                            ->placeholder('Semua kelas')
                            ->searchable(),
                    ])
                    ->query(fn (Builder $q, array $data) => filled($data['value'] ?? null)
                        ? $q->whereHas('classSubject.classroomTerm', fn (Builder $qq) => $qq->where('classroom_terms.id', $data['value']))
                        : $q),
                Filter::make('subject_id')
                    ->label('Mapel')
                    ->schema([
                        Select::make('value')
                            ->label('Mapel')
                            ->options(fn () => DiniyyahSubject::orderBy('name')->pluck('name', 'id')->all())
                            ->placeholder('Semua mapel')
                            ->searchable(),
                    ])
                    ->query(fn (Builder $q, array $data) => filled($data['value'] ?? null)
                        ? $q->whereHas('classSubject.subject', fn (Builder $qq) => $qq->where('diniyyah_subjects.id', $data['value']))
                        : $q),
                Filter::make('teacher_id')
                    ->label('Guru')
                    ->schema([
                        Select::make('value')
                            ->label('Guru')
                            ->options(fn () => Teacher::orderBy('name')->pluck('name', 'id')->all())
                            ->placeholder('Semua guru')
                            ->searchable(),
                    ])
                    ->query(fn (Builder $q, array $data) => filled($data['value'] ?? null)
                        ? $q->where('diniyyah_teacher_assignments.teacher_id', $data['value'])
                        : $q),
                TernaryFilter::make('is_active')
                    ->label('Status')
                    ->trueLabel('Aktif')
                    ->falseLabel('Berakhir')
                    ->placeholder('Semua')
                    ->queries(
                        true: fn (Builder $q) => $q->where(fn (Builder $x) => $x->whereNull('ends_at')->orWhere('ends_at', '>=', $today)),
                        false: fn (Builder $q) => $q->whereNotNull('ends_at')->where('ends_at', '<', $today),
                    ),
            ], FiltersLayout::Dropdown)
            ->paginated([10, 25, 50, 100]);
    }
}