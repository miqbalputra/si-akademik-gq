<?php

namespace App\Filament\Pages;

use App\Models\AcademicTerm;
use App\Services\RingkasanPenugasanGuruService;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use UnitEnum;

class RingkasanPenugasanGuru extends Page
{
    protected static string|UnitEnum|null $navigationGroup = 'Diniyyah';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $navigationLabel = 'Ringkasan Penugasan Guru';

    protected static ?int $navigationSort = 31;

    protected string $view = 'filament.pages.ringkasan-penugasan-guru';

    /** @var array<int, array{id: int, label: string}> */
    public array $termOptions = [];

    public ?int $academicTermId = null;

    /** @var array<string, mixed> */
    public array $recap = [];

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

        $this->rebuild($service);
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
        $this->rebuild($service);
    }

    private function rebuild(RingkasanPenugasanGuruService $service): void
    {
        $this->recap = $service->build($this->academicTermId);
    }

    /** @return Collection<int, array<string, mixed>> */
    public function classroomBlocks(): Collection
    {
        return collect($this->recap['classrooms'] ?? []);
    }
}