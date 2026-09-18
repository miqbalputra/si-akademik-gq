<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;

class JournalReminderPdfRenderer
{
    /** @param array<string, mixed> $report */
    public function render(array $report): string
    {
        return Pdf::loadView('admin.journal-reminders.pdf', compact('report'))
            ->setPaper('a4', 'portrait')
            ->output();
    }
}
