<?php

namespace App\Filament\Pages;

use App\Models\AcademicTerm;
use App\Services\RekapJurnalGuruService;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use UnitEnum;

class RekapJurnalGuru extends Page
{
    protected static string|UnitEnum|null $navigationGroup = 'Diniyyah';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $navigationLabel = 'Rekap Jurnal Guru';

    protected static ?int $navigationSort = 36;

    protected string $view = 'filament.pages.rekap-jurnal-guru';

    /** @var array<int, array{id: int, label: string}> */
    public array $termOptions = [];

    public ?int $academicTermId = null;

    public ?string $dateFrom = null;

    public ?string $dateUntil = null;

    /** @var array<string, mixed> */
    public array $recap = [];

    public function mount(RekapJurnalGuruService $service): void
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
        $this->dateFrom = (string) (request()->query('date_from') ?? ($selected?->starts_at?->toDateString() ?? ''));
        $this->dateUntil = (string) (request()->query('date_until') ?? ($selected?->ends_at?->toDateString() ?? ''));
        $this->dateFrom = $this->dateFrom !== '' ? $this->dateFrom : null;
        $this->dateUntil = $this->dateUntil !== '' ? $this->dateUntil : null;

        $this->rebuild($service);
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'kabag_diniyyah', 'kepala_sekolah']) ?? false;
    }

    public function getTitle(): string|Htmlable
    {
        return 'Rekap Jurnal Kelas Semua Guru';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('openFullReport')
                ->label('Laporan Full Data')
                ->icon('heroicon-o-table-cells')
                ->url(fn (): string => route('admin.diniyyah-journals.report', [
                    'academic_term_id' => $this->academicTermId,
                    'date_from' => $this->dateFrom,
                    'date_until' => $this->dateUntil,
                ]))
                ->openUrlInNewTab(),
            Action::make('downloadCsv')
                ->label('Export CSV Ringkasan')
                ->icon('heroicon-o-arrow-down-tray')
                ->url(fn (): string => route('admin.rekap-jurnal-guru.export', [
                    'academic_term_id' => $this->academicTermId,
                    'date_from' => $this->dateFrom,
                    'date_until' => $this->dateUntil,
                ])),
        ];
    }

    public function updatedAcademicTermId(RekapJurnalGuruService $service): void
    {
        $term = AcademicTerm::with('academicYear')->find($this->academicTermId);
        $this->dateFrom = $term?->starts_at?->toDateString() ?? $this->dateFrom;
        $this->dateUntil = $term?->ends_at?->toDateString() ?? $this->dateUntil;
        $this->rebuild($service);
    }

    public function updatedDateFrom(RekapJurnalGuruService $service): void
    {
        $this->rebuild($service);
    }

    public function updatedDateUntil(RekapJurnalGuruService $service): void
    {
        $this->rebuild($service);
    }

    private function rebuild(RekapJurnalGuruService $service): void
    {
        $this->recap = $service->build($this->academicTermId, $this->dateFrom, $this->dateUntil);
    }

    /** @return Collection<int, array<string, mixed>> */
    public function teacherRows(): Collection
    {
        return collect($this->recap['teachers'] ?? []);
    }
}
