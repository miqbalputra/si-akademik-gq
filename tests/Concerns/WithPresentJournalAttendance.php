<?php

namespace Tests\Concerns;

use App\Models\Teacher;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Existing journal regressions run with a real attendance gate and a present
 * source fixture. Absence/error/exception rules have separate feature coverage.
 */
trait WithPresentJournalAttendance
{
    protected function setUpWithPresentJournalAttendance(): void
    {
        config([
            'services.attendance_journal.enabled' => true,
            'services.attendance_journal.base_url' => 'https://geo.example.test',
            'services.attendance_journal.api_key' => 'test-journal-key',
        ]);
        Teacher::creating(function (Teacher $teacher): void {
            if (trim((string) $teacher->niy) === '') {
                $teacher->niy = 'JOURNAL-'.Str::uuid();
            }
        });
        Http::preventStrayRequests();
        Http::fake([
            'https://geo.example.test/api/v1/integrations/journal/teacher-report*' => fn (Request $request) => Http::response([
                'success' => true,
                'data' => [
                    'teacher' => ['id_guru' => $request['id_guru']],
                    'summary' => [],
                    'rows' => [['tanggal' => $request['start_date'], 'status' => 'hadir']],
                ],
            ]),
        ]);
    }
}
