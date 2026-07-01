<?php

namespace App\Filament\Resources\SchoolEvents\Pages;

use App\Filament\Resources\SchoolEvents\SchoolEventResource;
use App\Models\SchoolEvent;
use App\Services\SchoolEventRecapService;
use Filament\Actions\Action;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

class SchoolEventRecap extends Page
{
    use InteractsWithRecord;

    protected static string $resource = SchoolEventResource::class;

    protected string $view = 'filament.resources.school-events.pages.school-event-recap';

    /** @var array<string, mixed> */
    public array $recap = [];

    public string $filterStatus = 'all';

    public string $guardianSearch = '';

    public function mount(int | string $record, SchoolEventRecapService $recapService): void
    {
        $this->record = $this->resolveRecord($record);
        $this->recap = $recapService->build($this->getRecord());
    }

    public function getTitle(): string|Htmlable
    {
        return 'Rekap Event Sekolah';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('downloadCsv')
                ->label('Export CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->url(fn (): string => route('school-events.recap.export', [
                    'event' => $this->getRecord(),
                    'status' => $this->filterStatus,
                    'search' => $this->guardianSearch,
                ])),
            Action::make('editEvent')
                ->label('Edit Event')
                ->icon('heroicon-o-pencil-square')
                ->url(fn (): string => SchoolEventResource::getUrl('edit', ['record' => $this->getRecord()])),
        ];
    }

    public static function canAccess(array $parameters = []): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'kabag_diniyyah', 'kepala_sekolah']) ?? false;
    }

    /** @return \Illuminate\Support\Collection<int, array<string, mixed>> */
    public function filteredGuardianRows()
    {
        return collect($this->recap['guardian_rows'] ?? [])
            ->filter(function (array $row): bool {
                if ($this->filterStatus !== 'all' && $row['attendance_status'] !== $this->filterStatus) {
                    return false;
                }

                if ($this->guardianSearch === '') {
                    return true;
                }

                $haystack = collect([
                    $row['guardian_name'],
                    implode(', ', $row['student_names']),
                    $row['phone'],
                    $row['email'],
                ])->filter()->implode(' ');

                return str_contains(strtolower($haystack), strtolower($this->guardianSearch));
            })
            ->values();
    }

    /** @return array<string, int> */
    public function filteredStats(): array
    {
        $rows = $this->filteredGuardianRows();

        return [
            'total' => $rows->count(),
            'responded' => $rows->where('attendance_status', '!=', 'pending')->count(),
            'pending' => $rows->where('attendance_status', 'pending')->count(),
            'attending' => $rows->where('attendance_status', 'attending')->count(),
            'permission' => $rows->where('attendance_status', 'permission')->count(),
            'not_attending' => $rows->where('attendance_status', 'not_attending')->count(),
        ];
    }

    /** @return \Illuminate\Support\Collection<int, array<string, mixed>> */
    public function followUpRows()
    {
        return collect($this->recap['guardian_rows'] ?? [])
            ->filter(fn (array $row): bool => in_array($row['attendance_status'], ['pending', 'permission', 'not_attending'], true))
            ->values();
    }
}
