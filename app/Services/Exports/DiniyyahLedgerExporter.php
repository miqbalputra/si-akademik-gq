<?php

namespace App\Services\Exports;

use App\Models\DiniyyahLedgerSnapshot;
use Illuminate\Support\Collection;

class DiniyyahLedgerExporter
{
    /**
     * Generate an HTML-based Excel file for a ledger snapshot.
     *
     * This produces a .xls file that opens correctly in Microsoft Excel,
     * LibreOffice Calc, and Google Sheets without requiring external packages.
     */
    public function export(?int $snapshotId): string
    {
        $snapshot = DiniyyahLedgerSnapshot::with(['rows.cells', 'classroomTerm', 'academicTerm.academicYear'])
            ->findOrFail($snapshotId);

        $columns = collect($snapshot->snapshot_data['columns'] ?? []);
        $rows = $snapshot->rows->sortBy('row_number');
        $summary = $snapshot->snapshot_data['summary'] ?? [];

        $html = $this->buildHtml($snapshot, $columns, $rows, $summary);

        return $html;
    }

    /**
     * @param  Collection  $columns
     * @param  Collection  $rows
     * @param  array<string, mixed>  $summary
     */
    private function buildHtml(DiniyyahLedgerSnapshot $snapshot, Collection $columns, Collection $rows, array $summary): string
    {
        $title = $snapshot->title;
        $termName = $snapshot->academicTerm?->name ?? '-';
        $yearName = $snapshot->academicTerm?->academicYear?->name ?? '-';
        $className = $snapshot->classroomTerm?->name ?? '-';

        $html = "<!DOCTYPE html>\n<html xmlns:o=\"urn:schemas-microsoft-com:office:office\" xmlns:x=\"urn:schemas-microsoft-com:office:excel\" xmlns=\"http://www.w3.org/TR/REC-html40\">\n";
        $html .= "<head>\n<meta charset=\"utf-8\">\n<!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>Leger</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]-->\n";
        $html .= "<style>\n";
        $html .= "table { border-collapse: collapse; font-family: Arial, sans-serif; font-size: 11px; }\n";
        $html .= "th, td { border: 1px solid #333; padding: 4px 6px; }\n";
        $html .= "th { background: #f0f0f0; font-weight: bold; text-align: center; }\n";
        $html .= ".title { font-size: 16px; font-weight: bold; text-align: center; }\n";
        $html .= ".subtitle { font-size: 12px; text-align: center; }\n";
        $html .= ".meta { font-size: 11px; margin: 8px 0; }\n";
        $html .= ".center { text-align: center; }\n";
        $html .= ".right { text-align: right; }\n";
        $html .= ".header-row { background: #d9e2f3; }\n";
        $html .= ".total-row { font-weight: bold; background: #fff2cc; }\n";
        $html .= "</style>\n</head>\n<body>\n";

        $html .= "<div class=\"title\">LEGER NILAI DINIYYAH</div>\n";
        $html .= "<div class=\"subtitle\">{$title}</div>\n";
        $html .= "<div class=\"subtitle\">{$className} - {$termName} - {$yearName}</div>\n";

        $html .= "<div class=\"meta\">Status: " . strtoupper($snapshot->status) . " | Generated: " . ($snapshot->generated_at?->format('d M Y H:i') ?? '-') . "</div>\n";

        $html .= "<table>\n";

        // Header row
        $html .= "<tr class=\"header-row\">\n";
        $html .= "<th>No</th>\n";
        $html .= "<th>Nama</th>\n";
        $html .= "<th>NIS</th>\n";
        foreach ($columns as $column) {
            $label = e($column['label'] ?? '');
            $html .= "<th>{$label}</th>\n";
        }
        $html .= "<th>Total</th>\n";
        $html .= "<th>Rata-rata</th>\n";
        $html .= "<th>Peringkat</th>\n";
        $html .= "</tr>\n";

        // Data rows
        foreach ($rows as $row) {
            $html .= "<tr>\n";
            $html .= "<td class=\"center\">{$row->row_number}</td>\n";
            $html .= "<td>" . e($row->student_name) . "</td>\n";
            $html .= "<td class=\"center\">" . e($row->student_nis ?? '') . "</td>\n";

            $cells = $row->cells->keyBy('column_key');
            foreach ($columns as $column) {
                $cell = $cells->get($column['key'] ?? '');
                $value = $cell?->value_numeric;
                if ($value !== null) {
                    $html .= "<td class=\"center\">" . number_format((float) $value, 2) . "</td>\n";
                } else {
                    $html .= "<td class=\"center\">-</td>\n";
                }
            }

            $total = $row->total_diniyyah_score !== null ? number_format((float) $row->total_diniyyah_score, 2) : '-';
            $average = $row->average_diniyyah_score !== null ? number_format((float) $row->average_diniyyah_score, 2) : '-';
            $rank = $row->rank_in_class ?? '-';

            $html .= "<td class=\"center\">{$total}</td>\n";
            $html .= "<td class=\"center\">{$average}</td>\n";
            $html .= "<td class=\"center\">{$rank}</td>\n";
            $html .= "</tr>\n";
        }

        // Summary row
        $html .= "<tr class=\"total-row\">\n";
        $html .= "<td colspan=\"3\">RINGKASAN</td>\n";
        foreach ($columns as $column) {
            $html .= "<td></td>\n";
        }
        $html .= "<td class=\"center\">Total Siswa: " . ($summary['total_students'] ?? 0) . "</td>\n";
        $html .= "<td class=\"center\">Lengkap: " . ($summary['complete_rows'] ?? 0) . "</td>\n";
        $html .= "<td class=\"center\">Belum: " . ($summary['incomplete_rows'] ?? 0) . "</td>\n";
        $html .= "</tr>\n";

        $html .= "</table>\n";

        if (! empty($summary['blocking_issues'])) {
            $html .= "<div class=\"meta\" style=\"color: #cc0000; font-weight: bold;\">Peringatan: Masih ada {$summary['blocking_issues']} masalah kelengkapan.</div>\n";
        }

        $html .= "</body>\n</html>";

        return $html;
    }
}