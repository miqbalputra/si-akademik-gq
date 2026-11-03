<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;

class AdminMonthlyJpPdfRenderer
{
    /** @param array<string, mixed> $report */
    public function render(array $report): string
    {
        $merged = new Fpdi;
        $merged->SetAutoPageBreak(false);
        $merged->SetTitle('Rekap Penggajian JP Guru');
        $merged->SetCreator('Ruang GQ');

        try {
            foreach (['teachers', 'realized', 'missing'] as $section) {
                $chunks = collect($report[$section])->values()->chunk(25);
                if ($chunks->isEmpty()) {
                    $chunks = collect([collect()]);
                }
                foreach ($chunks as $rows) {
                    // Dompdf keeps a layout tree for every cell. Bound each render
                    // instead of raising PHP's memory limit for the whole worker.
                    $pdf = Pdf::loadView('admin.monthly-jp-report.pdf', [
                        'report' => $report,
                        'section' => $section,
                        'rows' => $this->sanitize($rows),
                    ])->setPaper('a3', 'landscape')->setOption('isFontSubsettingEnabled', true);
                    $content = $pdf->output();
                    unset($pdf);
                    gc_collect_cycles();

                    $pages = $merged->setSourceFile(StreamReader::createByString($content));
                    unset($content);
                    for ($page = 1; $page <= $pages; $page++) {
                        $template = $merged->importPage($page);
                        $size = $merged->getTemplateSize($template);
                        $merged->AddPage($size['orientation'], [$size['width'], $size['height']]);
                        $merged->useTemplate($template);
                    }
                }
            }

            return $merged->Output('S');
        } finally {
            $merged->cleanUp(true);
        }
    }

    private function sanitize(mixed $value): mixed
    {
        if ($value instanceof Collection) {
            return $value->map(fn ($item) => $this->sanitize($item));
        }
        if (is_array($value)) {
            return array_map(fn ($item) => $this->sanitize($item), $value);
        }
        if (! is_string($value)) {
            return $value;
        }

        return mb_check_encoding($value, 'UTF-8') ? $value : mb_convert_encoding($value, 'UTF-8', 'UTF-8');
    }
}
