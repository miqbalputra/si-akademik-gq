<?php

namespace App\Services;

use App\Services\Exports\TasmiReportXlsxExporter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;

class TasmiReportDownloadService
{
    public function __construct(private readonly TasmiReportXlsxExporter $xlsxExporter) {}

    public function download(array $report, string $format, string $title, string $scope)
    {
        abort_unless(in_array($format, ['xlsx', 'pdf'], true), 404);
        $fileName = Str::slug($title).'-'.now('Asia/Jakarta')->format('Ymd-His');

        if ($format === 'xlsx') {
            $content = $this->xlsxExporter->export($report, $scope);

            return response($content, 200, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="'.$fileName.'.xlsx"',
                'Content-Length' => strlen($content),
            ]);
        }

        return Pdf::loadView('reports.tasmi-report', compact('report', 'title', 'scope'))
            ->setPaper('a3', 'landscape')
            ->download($fileName.'.pdf');
    }
}
