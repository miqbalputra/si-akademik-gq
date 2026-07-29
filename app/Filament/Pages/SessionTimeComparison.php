<?php

namespace App\Filament\Pages;

use App\Services\SessionTimeMatrixService;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use UnitEnum;

/**
 * Perbandingan matrix jam sesi Ikhwan vs Akhwat per (hari × sesi).
 * Baris yg berbeda di-highlight — menjawab "sesi apa yg sama/beda antara
 * Ikhwan & Akhwat + harinya". Read-only.
 */
class SessionTimeComparison extends Page
{
    protected static string|UnitEnum|null $navigationGroup = 'Data Sekolah';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static ?string $navigationLabel = 'Perbandingan Sesi Ikhwan vs Akhwat';

    protected static ?int $navigationSort = 12;

    protected string $view = 'filament.pages.session-time-comparison';

    public ?int $ikhwanClassroomId = null;

    public ?int $akhwatClassroomId = null;

    /** @var array<int, array{day:int, session_name:string, ikhwan:?array, akhwat:?array, differs:bool}> */
    public array $rows = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'kabag_diniyyah', 'kepala_sekolah']) ?? false;
    }

    public function getTitle(): string|Htmlable
    {
        return 'Perbandingan Sesi Diniyyah: Ikhwan vs Akhwat';
    }

    public function mount(SessionTimeMatrixService $service): void
    {
        $this->ikhwanClassroomId = $service->firstMustawaClassroomId('ikhwan');
        $this->akhwatClassroomId = $service->firstMustawaClassroomId('akhwat');
        $this->rebuild($service);
    }

    public function updatedIkhwanClassroomId(SessionTimeMatrixService $service): void
    {
        $this->rebuild($service);
    }

    public function updatedAkhwatClassroomId(SessionTimeMatrixService $service): void
    {
        $this->rebuild($service);
    }

    protected function getViewData(): array
    {
        $service = app(SessionTimeMatrixService::class);
        $options = $service->mustawaClassroomOptions();

        // Pisahkan opsi per gender utk dropdown.
        $ikhwanOptions = [];
        $akhwatOptions = [];
        foreach ($options as $id => $name) {
            $p = \App\Support\SessionTimetable::parseClassroom(new \App\Models\Classroom(['name' => $name]));
            if ($p && $p[0] === 'ikhwan') {
                $ikhwanOptions[$id] = $name;
            } elseif ($p && $p[0] === 'akhwat') {
                $akhwatOptions[$id] = $name;
            }
        }

        return [
            'ikhwanOptions' => $ikhwanOptions,
            'akhwatOptions' => $akhwatOptions,
        ];
    }

    private function rebuild(SessionTimeMatrixService $service): void
    {
        if ($this->ikhwanClassroomId && $this->akhwatClassroomId) {
            $this->rows = $service->compare($this->ikhwanClassroomId, $this->akhwatClassroomId);
        } else {
            $this->rows = [];
        }
    }
}