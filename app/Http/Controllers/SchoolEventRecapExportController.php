<?php

namespace App\Http\Controllers;

use App\Models\SchoolEvent;
use App\Services\SchoolEventRecapService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SchoolEventRecapExportController extends Controller
{
    public function __invoke(Request $request, SchoolEvent $event, SchoolEventRecapService $recapService): StreamedResponse
    {
        abort_unless(auth()->user()?->hasAnyRole(['admin', 'kabag_diniyyah', 'kepala_sekolah']), 403);

        $recap = $recapService->build($event);
        $stats = $recap['stats'];
        $rows = collect($recap['guardian_rows']);
        $status = (string) $request->query('status', 'all');
        $search = trim((string) $request->query('search', ''));

        if ($status !== 'all') {
            $rows = $rows->where('attendance_status', $status);
        }

        if ($search !== '') {
            $rows = $rows->filter(function (array $row) use ($search): bool {
                $haystack = collect([
                    $row['guardian_name'],
                    implode(', ', $row['student_names']),
                    $row['phone'],
                    $row['email'],
                ])->filter()->implode(' ');

                return str_contains(strtolower($haystack), strtolower($search));
            });
        }

        $filename = 'rekap-event-'.str($event->title)->slug().($status !== 'all' ? '-'.$status : '').'.csv';

        return response()->streamDownload(function () use ($event, $stats, $rows, $status, $search): void {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, ['Rekap Event Sekolah']);
            fputcsv($handle, ['Nama Event', $event->title]);
            fputcsv($handle, ['Jenis Event', $event->typeLabel()]);
            fputcsv($handle, ['Tanggal', $event->starts_on->locale('id')->translatedFormat('d F Y').($event->starts_on->equalTo($event->ends_on) ? '' : ' s.d. '.$event->ends_on->locale('id')->translatedFormat('d F Y'))]);
            fputcsv($handle, ['Target', $event->targetSummary(10)]);
            fputcsv($handle, ['Filter Status', $status === 'all' ? 'Semua' : $status]);
            fputcsv($handle, ['Filter Pencarian', $search !== '' ? $search : '-']);
            fputcsv($handle, ['Baris Diexport', $rows->count()]);
            fputcsv($handle, []);
            fputcsv($handle, ['Statistik', 'Nilai']);
            fputcsv($handle, ['Santri Target', $stats['target_students']]);
            fputcsv($handle, ['Wali Target', $stats['target_guardians']]);
            fputcsv($handle, ['Sudah Respon', $stats['responded']]);
            fputcsv($handle, ['Belum Respon', $stats['pending']]);
            fputcsv($handle, ['Tingkat Respon', $stats['response_rate'].'%']);
            fputcsv($handle, ['Hadir', $stats['attending']]);
            fputcsv($handle, ['Izin', $stats['permission']]);
            fputcsv($handle, ['Tidak Hadir', $stats['not_attending']]);
            fputcsv($handle, ['Bapak Target', $stats['father_target']]);
            fputcsv($handle, ['Bapak Sudah Respon', $stats['father_responded']]);
            fputcsv($handle, ['Ibu Target', $stats['mother_target']]);
            fputcsv($handle, ['Ibu Sudah Respon', $stats['mother_responded']]);
            fputcsv($handle, []);
            fputcsv($handle, ['Nama Wali', 'Peran', 'Jenis Kelamin', 'Anak Terhubung', 'Kontak', 'Email', 'Status', 'Waktu Respon', 'Catatan']);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row['guardian_name'],
                    implode(', ', $row['relationship_labels']),
                    $row['guardian_gender'],
                    implode(', ', $row['student_names']),
                    $row['phone'],
                    $row['email'],
                    $row['attendance_label'],
                    $row['responded_at']?->format('Y-m-d H:i:s') ?? '',
                    $row['notes'],
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
