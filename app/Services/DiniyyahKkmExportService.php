<?php

namespace App\Services;

use App\Services\Exports\SpreadsheetTheme;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

class DiniyyahKkmExportService
{
    public function download(Collection $revisions, string $format)
    {
        abort_unless(in_array($format, ['xlsx', 'pdf'], true), 404);
        $revisions->each->load(['scope.subject', 'scope.academicTerm.academicYear', 'scope.assignedTeacher', 'submitter', 'reviewer']);
        $headers = ['Cache-Control' => 'private, no-store, max-age=0', 'X-Content-Type-Options' => 'nosniff'];
        if ($format === 'pdf') {
            return Pdf::loadView('kkm.pdf', ['revisions' => $revisions, 'criteriaLabels' => DiniyyahKkmCalculator::CRITERIA])->setPaper('a4', 'landscape')->download('penetapan-kkm.pdf')->withHeaders($headers);
        }

        return response($this->xlsx($revisions), 200, $headers + ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'Content-Disposition' => 'attachment; filename="penetapan-kkm.xlsx"']);
    }

    public function xlsx(Collection $revisions): string
    {
        $theme = app(SpreadsheetTheme::class);
        $workbook = $theme->workbook();
        $summary = $workbook->getActiveSheet()->setTitle('Rekap KKM');
        $theme->title($summary, 'PENETAPAN KKM DINIYYAH', 'Mapel umum: KKM = ROUND((Kompleksitas + Daya Dukung + Intake) / 3, 0). Suluk dan Praktik Ibadah: ketetapan sekolah 70.', 11);
        $theme->tableHeader($summary, 5, ['Semester', 'Mustawa', 'Mapel', 'Versi', 'Status', 'Kompleksitas', 'Daya Dukung', 'Intake', 'Rata-rata', 'KKM', 'Penetap / ACC oleh / waktu']);
        $detail = $workbook->createSheet()->setTitle('Pertimbangan');
        $theme->title($detail, 'DASAR PENETAPAN KKM', 'Mapel umum menggunakan unsur KKM. Suluk dan Praktik Ibadah menggunakan ketetapan sekolah 70. Draf belum menjadi KKM resmi.', 9);
        $theme->tableHeader($detail, 5, ['Semester', 'Mustawa', 'Mapel / Versi', 'Penetap / Penyusun', 'Status', 'Unsur', 'Angka', 'Kategori', 'Alasan']);
        $row = $detailRow = 6;
        foreach ($revisions as $revision) {
            $scope = $revision->scope;
            $term = $scope->academicTerm?->academicYear?->name.' '.$scope->academicTerm?->name;
            $state = in_array($revision->status, ['approved', 'superseded', 'school_fixed'], true) ? $revision->status_label : 'BELUM DISETUJUI — '.$revision->status_label;
            $scores = array_map(fn ($key) => $revision->criteria[$key]['score'] ?? null, array_keys(DiniyyahKkmCalculator::CRITERIA));
            $values = [$term, (int) $scope->mustawa, $scope->subject?->name, $revision->version, $state, ...$scores, $revision->average, $revision->final_kkm, ($revision->is_school_fixed ? 'Sekolah/Kabag Diniyyah' : $revision->reviewer?->name).' / '.$revision->reviewed_at?->format('d-m-Y H:i')];
            $this->writeRow($summary, $row++, $values);
            if ($revision->is_school_fixed) {
                $this->writeRow($detail, $detailRow, [$term, (int) $scope->mustawa, $scope->subject?->name.' / v'.$revision->version, 'Sekolah/Kabag Diniyyah', $state, 'Ketetapan sekolah', $revision->final_kkm, 'KKM Tetap', 'Ditetapkan Sekolah/Kabag Diniyyah. Tidak melalui perhitungan unsur, pengajuan, perubahan, revisi, atau ACC guru.']);
                $detail->getRowDimension($detailRow++)->setRowHeight(52);

                continue;
            }
            foreach (DiniyyahKkmCalculator::CRITERIA as $key => $label) {
                $criterion = $revision->criteria[$key] ?? [];
                $lines = [];
                foreach (preg_split('/\R/u', $criterion['reason'] ?? '') as $line) {
                    array_push($lines, ...(mb_str_split($line, 70) ?: ['']));
                }
                foreach (array_chunk($lines ?: [''], 10) as $part => $chunk) {
                    $values = [$term, (int) $scope->mustawa, $scope->subject?->name.' / v'.$revision->version, $revision->submitter?->name ?? $scope->assignedTeacher?->name, $state, $label.($part ? ' (lanjutan)' : ''), $criterion['score'] ?? null, $criterion['category'] ?? '-', implode("\n", $chunk)];
                    $this->writeRow($detail, $detailRow, $values);
                    $detail->getRowDimension($detailRow++)->setRowHeight(max(52, 15 * count($chunk)));
                }
            }
        }
        $theme->widths($summary, [25, 10, 24, 9, 28, 15, 15, 12, 13, 10, 30]);
        $theme->widths($detail, [24, 10, 25, 24, 28, 23, 10, 22, 80]);
        $theme->finaliseTable($summary, 5, max(5, $row - 1), 11);
        $theme->finaliseTable($detail, 5, max(5, $detailRow - 1), 9);
        $summary->getStyle('I6:I'.max(6, $row - 1))->getNumberFormat()->setFormatCode('0.00');

        return $theme->save($workbook);
    }

    private function writeRow($sheet, int $row, array $values): void
    {
        foreach ($values as $column => $value) {
            $address = Coordinate::stringFromColumnIndex($column + 1).$row;
            // Never allow a teacher's reason/name to become an executable Excel formula.
            $sheet->setCellValueExplicit($address, $value, is_int($value) || is_float($value) ? DataType::TYPE_NUMERIC : ($value === null ? DataType::TYPE_NULL : DataType::TYPE_STRING));
        }
    }
}
