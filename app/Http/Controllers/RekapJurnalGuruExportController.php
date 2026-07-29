<?php

namespace App\Http\Controllers;

use App\Services\RekapJurnalGuruService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Export CSV rekap JP per guru diniyyah (asli/pengganti/tafsir).
 * Untuk admin/kabag_diniyyah/kepala_sekolah.
 */
class RekapJurnalGuruExportController extends Controller
{
    public function __invoke(Request $request, RekapJurnalGuruService $service): StreamedResponse
    {
        abort_unless(
            $request->user()?->hasAnyRole(['admin', 'kabag_diniyyah', 'kepala_sekolah']),
            403
        );

        $academicTermId = $request->query('academic_term_id') ? (int) $request->query('academic_term_id') : null;
        $dateFrom = $request->query('date_from') ? (string) $request->query('date_from') : null;
        $dateUntil = $request->query('date_until') ? (string) $request->query('date_until') : null;

        $recap = $service->build($academicTermId, $dateFrom, $dateUntil);
        $stats = $recap['stats'];
        $term = $recap['term'];
        $rows = $recap['teachers'];

        $termSlug = $term ? str(($term->academicYear?->name ?? '').'-'.$term->name)->slug() : 'semua';
        $range = '';
        if ($dateFrom && $dateUntil) {
            $range = '-'.str_replace('-', '', $dateFrom).'_'.str_replace('-', '', $dateUntil);
        } elseif ($dateFrom) {
            $range = '-dari-'.str_replace('-', '', $dateFrom);
        } elseif ($dateUntil) {
            $range = '-sd-'.str_replace('-', '', $dateUntil);
        }
        $filename = 'rekap-jurnal-guru-'.$termSlug.$range.'.csv';

        return response()->streamDownload(function () use ($term, $recap, $stats, $rows): void {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, ['Rekap Jurnal Kelas Semua Guru']);
            fputcsv($handle, ['Periode', $term ? ($term->academicYear?->name.' - '.$term->name) : '-']);
            fputcsv($handle, ['Dari Tanggal', $recap['date_from'] ?? '-']);
            fputcsv($handle, ['Sampai Tanggal', $recap['date_until'] ?? '-']);
            fputcsv($handle, []);

            fputcsv($handle, ['Statistik', 'Nilai']);
            fputcsv($handle, ['Total Guru', $stats['total_teachers']]);
            fputcsv($handle, ['Total Sesi Asli', $stats['total_sesi_asli']]);
            fputcsv($handle, ['Total Sesi Pengganti', $stats['total_sesi_pengganti']]);
            fputcsv($handle, ['Total Sesi Tafsir', $stats['total_sesi_tafsir']]);
            fputcsv($handle, ['Total JP', $stats['total_jp']]);
            fputcsv($handle, []);

            fputcsv($handle, ['Nama', 'NIY', 'Sesi Asli', 'Sesi Pengganti', 'Sesi Tafsir', 'Total JP']);
            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row['name'],
                    $row['niy'] ?: '-',
                    $row['sesi_asli'],
                    $row['sesi_pengganti'],
                    $row['sesi_tafsir'],
                    $row['total_jp'],
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}