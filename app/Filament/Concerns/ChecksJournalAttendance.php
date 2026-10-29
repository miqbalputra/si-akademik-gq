<?php

namespace App\Filament\Concerns;

use App\Services\JournalAttendanceEligibilityService;
use Illuminate\Validation\ValidationException;

trait ChecksJournalAttendance
{
    public ?array $journalAttendanceEligibility = null;

    /** Read-only refresh: keeps the current form state, including unsaved material. */
    public function checkJournalAttendance(): void
    {
        $this->journalAttendanceEligibility = app(JournalAttendanceEligibilityService::class)
            ->checkFormData($this->form->getRawState());
    }

    protected function enforceJournalAttendance(array $data): void
    {
        $this->journalAttendanceEligibility = app(JournalAttendanceEligibilityService::class)->checkFormData($data);
        if (! $this->journalAttendanceEligibility['allowed']) {
            throw ValidationException::withMessages(['data.date' => $this->journalAttendanceEligibility['message']]);
        }
    }
}
