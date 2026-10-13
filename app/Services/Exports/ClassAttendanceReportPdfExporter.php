<?php

namespace App\Services\Exports;

use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;

class ClassAttendanceReportPdfExporter
{
    /** @param array<string, mixed> $report */
    public function export(array $report): string
    {
        $merged = new class extends Fpdi
        {
            public function Footer(): void
            {
                $this->SetY(-9);
                $this->SetFont('Helvetica', '', 8);
                $this->SetTextColor(76, 89, 102);
                $this->Cell(0, 5, 'Ruang GQ | Laporan Presensi Kelas', 0, 0, 'L');
                $this->SetX(10);
                $this->Cell(0, 5, 'Halaman '.$this->PageNo().' / {nb}', 0, 0, 'R');
            }
        };
        $merged->AliasNbPages();
        $merged->SetAutoPageBreak(false);
        $merged->SetTitle('Laporan Presensi Kelas');
        $merged->SetCreator('Ruang GQ');
        foreach ($report['classes'] as $class) {
            $studentChunks = array_chunk($class['students'], 20) ?: [[]];
            foreach ($studentChunks as $chunkIndex => $students) {
                $this->append($merged, $report, array_replace($class, ['students' => $students]), [
                    'includeSummary' => true, 'includeDetails' => false, 'showReportNotes' => $chunkIndex === 0,
                ]);
            }
            foreach ($class['months'] as $month) {
                $selected = array_values(array_filter($month['days'], fn ($day) => $day['selected']));
                $chunkSize = $selected ? (int) ceil(count($selected) / ceil(count($selected) / 15)) : 15;
                foreach (array_chunk($selected, $chunkSize) as $days) {
                    foreach ($studentChunks as $students) {
                        $this->append($merged, $report, array_replace($class, ['students' => $students]), [
                            'includeSummary' => false, 'includeDetails' => true, 'onlyMonth' => $month['key'], 'onlyDays' => $days,
                        ]);
                    }
                }
            }
        }

        try {
            return $merged->Output('S');
        } finally {
            $merged->cleanUp();
        }
    }

    private function append(Fpdi $merged, array $report, array $class, array $options): void
    {
        // Fresh renderers and bounded tables keep all-class exports within the
        // PHP memory limit. No document settings leak into the next Octane request.
        $part = array_replace($report, ['classes' => [$class]]);
        $pdf = app('dompdf.wrapper')->loadView('reports.class-attendance', array_merge(['report' => $part], $options))
            ->setPaper('a4', 'landscape')->setOption('isFontSubsettingEnabled', true);
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
