<?php

namespace App\Filament\Pages;

use App\Services\SessionTimeMatrixService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use UnitEnum;

/**
 * Atur jam sesi diniyyah per-kelas (matrix hari × sesi). Override harian
 * tanpa deploy. Default jam tetap dari kode (SessionTimetable::definitionForClassroom);
 * tombol "Reset ke Default" memulihkan.
 */
class SessionTimeMatrix extends Page
{
    protected static string|UnitEnum|null $navigationGroup = 'Data Sekolah';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static ?string $navigationLabel = 'Atur Jadwal Sesi Diniyyah';

    protected static ?int $navigationSort = 11;

    protected string $view = 'filament.pages.session-time-matrix';

    public ?int $classroomId = null;

    /** @var array<int, array{day:int, session_id:int, session_name:string, is_break:bool, starts_at:?string, ends_at:?string, exists:bool}> */
    public array $rows = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['admin']) ?? false;
    }

    public function getTitle(): string|Htmlable
    {
        return 'Atur Jadwal Sesi Diniyyah per Kelas';
    }

    public function mount(SessionTimeMatrixService $service): void
    {
        $options = $service->mustawaClassroomOptions();
        $this->classroomId = array_key_first($options) ?: null;
        $this->rebuild($service);
    }

    public function updatedClassroomId(SessionTimeMatrixService $service): void
    {
        $this->rebuild($service);
    }

    protected function getViewData(): array
    {
        return [
            'classroomOptions' => app(SessionTimeMatrixService::class)->mustawaClassroomOptions(),
        ];
    }

    public function save(SessionTimeMatrixService $service): void
    {
        if (! $this->classroomId) {
            Notification::make()->danger()->title('Pilih kelas dulu')->send();
            return;
        }
        $service->saveMatrix($this->classroomId, $this->rows);
        $this->rebuild($service);
        Notification::make()->success()->title('Jam sesi disimpan')->send();
    }

    public function propagate(SessionTimeMatrixService $service, string $gender): void
    {
        if (! $this->classroomId) {
            Notification::make()->danger()->title('Pilih kelas dulu')->send();
            return;
        }
        $res = $service->applyToGender($this->classroomId, $gender);
        $body = sprintf("Disalin %d baris ke %d kelas.\n%s", $res['copied'], $res['targets'], implode("\n", $res['warnings']));
        Notification::make()->success()->title('Propagasi selesai')->body($body)->send();
    }

    public function resetToDefault(SessionTimeMatrixService $service): void
    {
        if (! $this->classroomId) {
            return;
        }
        $count = $service->resetToDefault($this->classroomId);
        $this->rebuild($service);
        Notification::make()->success()->title('Direset ke default kode')->body(sprintf('%d baris dipulihkan.', $count))->send();
    }

    private function rebuild(SessionTimeMatrixService $service): void
    {
        $this->rows = $this->classroomId ? $service->matrixFor($this->classroomId) : [];
    }
}