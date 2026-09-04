<?php

use App\Http\Controllers\AdminMonthlyJpReportController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\DiniyyahJournalExportController;
use App\Http\Controllers\DiniyyahJournalReportController;
use App\Http\Controllers\DiniyyahLedgerController;
use App\Http\Controllers\DiniyyahMonitoringController;
use App\Http\Controllers\GuardianDashboardController;
use App\Http\Controllers\GuardianSchoolEventResponseController;
use App\Http\Controllers\GuardianTahfidzController;
use App\Http\Controllers\GuruAttendanceReportController;
use App\Http\Controllers\GuruDashboardController;
use App\Http\Controllers\GuruDiniyyahJournalController;
use App\Http\Controllers\GuruDiniyyahScoreController;
use App\Http\Controllers\GuruDiniyyahSubstituteJournalController;
use App\Http\Controllers\GuruDiniyyahSubstituteTafsirJournalController;
use App\Http\Controllers\GuruDiniyyahTafsirJournalController;
use App\Http\Controllers\GuruJadwalController;
use App\Http\Controllers\GuruJournalReminderController;
use App\Http\Controllers\GuruRppController;
use App\Http\Controllers\GuruTahfidzController;
use App\Http\Controllers\GuruTasmiController;
use App\Http\Controllers\KabagDiniyyahDashboardController;
use App\Http\Controllers\KabagTahfidzDashboardController;
use App\Http\Controllers\ManagementTasmiReportController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\RekapJurnalGuruExportController;
use App\Http\Controllers\ReportCardController;
use App\Http\Controllers\SchoolCalendarController;
use App\Http\Controllers\SchoolEventRecapExportController;
use App\Http\Controllers\WaliClassJournalMonitoringController;
use App\Http\Controllers\WaliJpRecapController;
use App\Http\Controllers\WaliKelasTasmiController;
use App\Http\Controllers\WaliKelasTasmiReminderController;
use App\Http\Controllers\WorkspaceSelectionController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    // Throttle the credential submission endpoint to mitigate brute-force
    // attempts (5 attempts per minute per IP).
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('login.store');
});

Route::get('/auth/google', [GoogleController::class, 'redirectToGoogle'])->name('auth.google');
Route::get('/auth/google/callback', [GoogleController::class, 'handleGoogleCallback'])->name('auth.google.callback');

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware('auth')->prefix('pilih-ruang-kerja')->name('workspace.')->group(function () {
    Route::get('/', [WorkspaceSelectionController::class, 'create'])->name('choose');
    Route::post('/', [WorkspaceSelectionController::class, 'store'])->name('select');
});

Route::middleware('auth')->group(function () {
    Route::get('/kabag/tahfidz', KabagTahfidzDashboardController::class)->name('kabag-tahfidz.dashboard');
    Route::get('/kabag/diniyyah', KabagDiniyyahDashboardController::class)->name('kabag-diniyyah.dashboard');
});

// Notifikasi pusat — bell icon di pojok kanan atas (semua role).
Route::middleware('auth')->prefix('notifications')->name('notifications.')->group(function () {
    Route::get('/', [NotificationController::class, 'index'])->name('index');
    Route::get('/feed', [NotificationController::class, 'feed'])->name('feed');
    Route::post('/{notification}/read', [NotificationController::class, 'markAsRead'])->name('read');
    Route::post('/read-all', [NotificationController::class, 'markAllRead'])->name('read-all');
    Route::delete('/{notification}', [NotificationController::class, 'archive'])->name('archive');
});

Route::middleware('auth')->prefix('guru')->name('guru.')->group(function () {
    Route::get('/', [GuruDashboardController::class, 'index'])->name('dashboard');
    Route::get('/performa', [GuruDashboardController::class, 'performa'])->name('performa');
    Route::get('/performa/export/{format}', [GuruDashboardController::class, 'performaExport'])
        ->whereIn('format', ['xlsx', 'pdf'])
        ->name('performa.export');
    Route::get('/presensi-saya', [GuruAttendanceReportController::class, 'index'])
        ->middleware('throttle:20,1')
        ->name('attendance-report.index');
    Route::get('/presensi-saya/export/{format}', [GuruAttendanceReportController::class, 'export'])
        ->whereIn('format', ['xlsx', 'pdf'])
        ->name('attendance-report.export');
    Route::post('/journal-reminder/snooze', [GuruJournalReminderController::class, 'snooze'])
        ->name('journal-reminder.snooze');
    Route::get('/jadwal/riwayat', [GuruJadwalController::class, 'riwayat'])->name('jadwal.riwayat');
    Route::get('/diniyyah-scores', [GuruDiniyyahScoreController::class, 'index'])->name('diniyyah-scores.index');
    Route::get('/diniyyah-scores/{assessmentSet}', [GuruDiniyyahScoreController::class, 'edit'])->name('diniyyah-scores.edit');
    Route::put('/diniyyah-scores/{assessmentSet}', [GuruDiniyyahScoreController::class, 'update'])->name('diniyyah-scores.update');
    Route::post('/diniyyah-scores/{assessmentSet}/submit', [GuruDiniyyahScoreController::class, 'submit'])->name('diniyyah-scores.submit');
    Route::get('/calendar', [SchoolCalendarController::class, 'guru'])->name('calendar');
    Route::get('/tahfidz', [GuruTahfidzController::class, 'index'])->name('tahfidz.index');
    Route::get('/tahfidz/{halaqah}', [GuruTahfidzController::class, 'show'])->name('tahfidz.show');
    Route::put('/tahfidz/{halaqah}', [GuruTahfidzController::class, 'update'])->name('tahfidz.update');
    Route::put('/tahfidz/{halaqah}/single', [GuruTahfidzController::class, 'updateSingle'])->name('tahfidz.update-single');
    Route::get('/tahfidz/{halaqah}/uas', [GuruTahfidzController::class, 'uasIndex'])->name('tahfidz.uas');
    Route::put('/tahfidz/{halaqah}/uas', [GuruTahfidzController::class, 'uasUpdate'])->name('tahfidz.uas.update');
    Route::get('/diniyyah-journals', [GuruDiniyyahJournalController::class, 'index'])->name('diniyyah-journals.index');
    Route::post('/diniyyah-journals', [GuruDiniyyahJournalController::class, 'store'])->name('diniyyah-journals.store');
    Route::get('/diniyyah-journals/riwayat', [GuruDiniyyahJournalController::class, 'riwayat'])->name('diniyyah-journals.riwayat');
    Route::get('/diniyyah-journals/laporan', [DiniyyahJournalReportController::class, 'guru'])->name('diniyyah-journals.report');
    Route::get('/diniyyah-journals/laporan/export/{format}', [DiniyyahJournalReportController::class, 'guruExport'])
        ->whereIn('format', ['xlsx', 'pdf'])
        ->name('diniyyah-journals.report.export');
    Route::get('/diniyyah-journals/{diniyyah_journal}/edit', [GuruDiniyyahJournalController::class, 'edit'])->name('diniyyah-journals.edit');
    Route::put('/diniyyah-journals/{diniyyah_journal}', [GuruDiniyyahJournalController::class, 'update'])->name('diniyyah-journals.update');
    Route::delete('/diniyyah-journals/{diniyyah_journal}', [GuruDiniyyahJournalController::class, 'destroy'])->name('diniyyah-journals.destroy');

    // Menu "Jurnal Guru Pengganti" — semua guru (akun terhubung Teacher) dapat
    // mengisi jurnal menggantikan guru asli yang berhalangan.
    Route::get('/diniyyah-substitute-journals', [GuruDiniyyahSubstituteJournalController::class, 'index'])->name('diniyyah-substitute-journals.index');
    Route::post('/diniyyah-substitute-journals', [GuruDiniyyahSubstituteJournalController::class, 'store'])->name('diniyyah-substitute-journals.store');
    Route::delete('/diniyyah-substitute-journals/{diniyyah_journal}', [GuruDiniyyahSubstituteJournalController::class, 'destroy'])->name('diniyyah-substitute-journals.destroy');

    // Menu "Jurnal Pengganti Tafsir" — pengganti menggantikan guru Tafsir asli
    // untuk beberapa kelas sekaligus (sesi Kamis 09:50-10:20).
    Route::get('/diniyyah-substitute-tafsir-journals', [GuruDiniyyahSubstituteTafsirJournalController::class, 'index'])->name('diniyyah-substitute-tafsir-journals.index');
    Route::post('/diniyyah-substitute-tafsir-journals', [GuruDiniyyahSubstituteTafsirJournalController::class, 'store'])->name('diniyyah-substitute-tafsir-journals.store');

    // Menu "Jurnal Tafsir" — input serentak 1 materi → 1 jurnal per kelas Tafsir.
    Route::get('/diniyyah-tafsir-journals', [GuruDiniyyahTafsirJournalController::class, 'index'])->name('diniyyah-tafsir-journals.index');
    Route::post('/diniyyah-tafsir-journals', [GuruDiniyyahTafsirJournalController::class, 'store'])->name('diniyyah-tafsir-journals.store');

    // Menu "Tasmi'" — khusus guru yang ditugaskan sebagai PJ Tasmi' (tasmi_examiner_assignments).
    // Ustadz hanya melihat kelas ikhwan, ustadzah hanya melihat kelas akhwat.
    Route::get('/tasmi', [GuruTasmiController::class, 'index'])->name('tasmi.index');
    Route::get('/tasmi/create', [GuruTasmiController::class, 'create'])->name('tasmi.create');
    Route::post('/tasmi', [GuruTasmiController::class, 'store'])->name('tasmi.store');
    Route::get('/tasmi/records', [GuruTasmiController::class, 'records'])->name('tasmi.records');
    Route::get('/tasmi/records/export/{format}', [GuruTasmiController::class, 'export'])
        ->whereIn('format', ['xlsx', 'pdf'])
        ->name('tasmi.export');
    Route::get('/tasmi/{tasmi_record}/edit', [GuruTasmiController::class, 'edit'])->name('tasmi.edit');
    Route::put('/tasmi/{tasmi_record}', [GuruTasmiController::class, 'update'])->name('tasmi.update');
    Route::delete('/tasmi/{tasmi_record}', [GuruTasmiController::class, 'destroy'])->name('tasmi.destroy');

    // Menu "Tasmi' Kelas Saya" — wali kelas (homeroom teacher) lihat data tasmi' santri di kelasnya (read-only).
    Route::get('/tasmi-wali', [WaliKelasTasmiController::class, 'index'])->name('tasmi-wali.index');
    Route::get('/tasmi-wali/export/{format}', [WaliKelasTasmiController::class, 'export'])
        ->whereIn('format', ['xlsx', 'pdf'])
        ->name('tasmi-wali.export');
    Route::post('/tasmi-wali/reminder/dismiss', [WaliKelasTasmiReminderController::class, 'dismiss'])
        ->name('tasmi-wali.reminder.dismiss');
    Route::get('/tasmi-wali/{tasmi_record}', [WaliKelasTasmiController::class, 'show'])->name('tasmi-wali.show');

    // Perangkat pembelajaran RPP Diniyyah — seluruh pilihan kelas/mapel
    // dibatasi oleh penugasan Diniyyah aktif milik guru login.
    Route::prefix('rpp')->name('rpp.')->group(function () {
        Route::get('/', [GuruRppController::class, 'index'])->name('index');
        Route::get('/buat', [GuruRppController::class, 'create'])->name('create');
        Route::post('/', [GuruRppController::class, 'store'])->name('store');
        Route::post('/draft-ai', [GuruRppController::class, 'aiDraft'])->name('ai-draft');
        Route::post('/bantuan', [GuruRppController::class, 'requestHelp'])->name('help');
        Route::get('/referensi', [GuruRppController::class, 'references'])->name('references');
        Route::get('/promes', [GuruRppController::class, 'promes'])->name('promes');
        Route::get('/sampah', [GuruRppController::class, 'trash'])->name('trash');
        Route::post('/sampah/{rpp}/pulihkan', [GuruRppController::class, 'restore'])->name('restore');
        Route::get('/{rpp}', [GuruRppController::class, 'show'])->name('show');
        Route::get('/{rpp}/edit', [GuruRppController::class, 'edit'])->name('edit');
        Route::put('/{rpp}', [GuruRppController::class, 'update'])->name('update');
        Route::delete('/{rpp}', [GuruRppController::class, 'destroy'])->name('destroy');
        Route::post('/{rpp}/duplikat', [GuruRppController::class, 'duplicate'])->name('duplicate');
        Route::get('/{rpp}/ekspor/{type}', [GuruRppController::class, 'downloadExport'])->whereIn('type', ['pdf', 'png', 'docx'])->name('export');
        Route::get('/{rpp}/bagikan/{type}', [GuruRppController::class, 'share'])->whereIn('type', ['pdf', 'png', 'docx'])->name('share');
        Route::get('/{rpp}/berkas/{file}', [GuruRppController::class, 'downloadFile'])->name('file');
    });
});

Route::get('/rpp/shared/{export}', [GuruRppController::class, 'sharedDownload'])
    ->middleware('signed')
    ->name('rpp.shared-download');

// Laporan pengawasan Tasmi' lintas PJ untuk Kabag Tahfidz dan admin.
Route::middleware('auth')->prefix('admin/tasmi-report')->name('admin.tasmi-report.')->group(function () {
    Route::get('/', [ManagementTasmiReportController::class, 'index'])->name('index');
    Route::get('/export/{format}', [ManagementTasmiReportController::class, 'export'])
        ->whereIn('format', ['xlsx', 'pdf'])
        ->name('export');
    Route::get('/{tasmi_record}', [ManagementTasmiReportController::class, 'show'])->name('show');
});

Route::middleware('auth')->prefix('attendance')->name('attendance.')->group(function () {
    Route::get('/', [AttendanceController::class, 'index'])->name('index');
    Route::get('/{classroomTerm}', [AttendanceController::class, 'edit'])->name('edit');
    Route::put('/{classroomTerm}', [AttendanceController::class, 'update'])->name('update');
    Route::put('/{classroomTerm}/single', [AttendanceController::class, 'updateSingle'])->name('update-single');
});

Route::middleware('auth')->prefix('diniyyah')->name('diniyyah.')->group(function () {
    Route::get('/monitoring', [DiniyyahMonitoringController::class, 'index'])->name('monitoring.index');
    Route::post('/assessment-sets/{assessmentSet}/approve', [DiniyyahMonitoringController::class, 'approve'])->name('assessment-sets.approve');
    Route::post('/assessment-sets/{assessmentSet}/revision', [DiniyyahMonitoringController::class, 'requestRevision'])->name('assessment-sets.revision');
    Route::post('/ledger/generate/{classroomTerm}', [DiniyyahLedgerController::class, 'generate'])->name('ledger.generate');
    Route::get('/ledger/{snapshot}', [DiniyyahLedgerController::class, 'show'])->name('ledger.show');
    Route::get('/ledger/{snapshot}/export-excel', [DiniyyahLedgerController::class, 'exportExcel'])->name('ledger.export-excel');
});

// Ekspor lengkap seluruh jurnal diniyyah (reguler + pengganti) untuk admin/kabag/kepala_sekolah.
Route::middleware('auth')->prefix('admin/diniyyah-journals')->name('admin.diniyyah-journals.')->group(function () {
    Route::get('/report', [DiniyyahJournalReportController::class, 'management'])->name('report');
    Route::get('/export', [DiniyyahJournalExportController::class, 'export'])->name('export');
});

// Export CSV rekap JP per guru diniyyah (asli/pengganti/tafsir) untuk admin/kabag/kepala_sekolah.
Route::middleware('auth')->prefix('admin/rekap-jurnal-guru')->name('admin.rekap-jurnal-guru.')->group(function () {
    Route::get('/export', RekapJurnalGuruExportController::class)->name('export');
});

Route::middleware('auth')->prefix('admin/rekap-jp-bulanan')->name('admin.monthly-jp-recap.')->group(function () {
    Route::get('/', [AdminMonthlyJpReportController::class, 'index'])->name('index');
    Route::get('/export/{format}', [AdminMonthlyJpReportController::class, 'export'])->whereIn('format', ['xlsx', 'pdf'])->name('export');
    Route::post('/tafsir-normalizations', [AdminMonthlyJpReportController::class, 'normalizeTafsir'])->name('tafsir-normalizations.store');
    Route::post('/tafsir-normalizations/revert', [AdminMonthlyJpReportController::class, 'revertTafsirNormalization'])->name('tafsir-normalizations.revert');
});

Route::middleware('auth')->group(function () {
    Route::post('/report-cards/generate/{snapshot}', [ReportCardController::class, 'generate'])->name('report-cards.generate');
    Route::post('/report-cards/ledger/{snapshot}/lock', [ReportCardController::class, 'lockFromLedgerSnapshot'])->name('report-cards.ledger.lock');
    Route::post('/report-cards/ledger/{snapshot}/publish', [ReportCardController::class, 'publishFromLedgerSnapshot'])->name('report-cards.ledger.publish');
    Route::get('/report-cards/{reportCard}/print', [ReportCardController::class, 'print'])->name('report-cards.print');
    Route::get('/report-cards/{reportCard}/download-pdf', [ReportCardController::class, 'downloadPdf'])->name('report-cards.download-pdf');
    Route::post('/report-cards/{reportCard}/generate-pdf', [ReportCardController::class, 'generatePdf'])->name('report-cards.generate-pdf');
    Route::get('/report-cards/{reportCard}', [ReportCardController::class, 'show'])->name('report-cards.show');
    Route::get('/wali', [GuardianDashboardController::class, 'index'])->name('wali.dashboard');
    Route::get('/wali/calendar', [SchoolCalendarController::class, 'guardian'])->name('wali.calendar');
    Route::get('/wali/tahfidz', [GuardianTahfidzController::class, 'index'])->name('wali.tahfidz');
    Route::get('/wali/diniyyah-journals', [WaliClassJournalMonitoringController::class, 'index'])->name('wali.diniyyah-journals.index');
    Route::get('/wali/diniyyah-journals/export-pdf', [WaliClassJournalMonitoringController::class, 'exportPdf'])->name('wali.diniyyah-journals.export-pdf');
    Route::get('/wali/diniyyah-journals/export-excel', [WaliClassJournalMonitoringController::class, 'exportExcel'])->name('wali.diniyyah-journals.export-excel');
    Route::get('/wali/rekap-jp', [WaliJpRecapController::class, 'index'])->name('wali.jp-recap.index');
    Route::post('/wali/rekap-jp/confirm', [WaliJpRecapController::class, 'confirm'])->name('wali.jp-recap.confirm');
    Route::get('/wali/rekap-jp/export-pdf', [WaliJpRecapController::class, 'exportPdf'])->name('wali.jp-recap.export-pdf');
    Route::get('/wali/rekap-jp/export-excel', [WaliJpRecapController::class, 'exportExcel'])->name('wali.jp-recap.export-excel');
    Route::post('/wali/events/{event}/response', [GuardianSchoolEventResponseController::class, 'store'])->name('wali.events.response');
    Route::get('/school-events/{event}/recap/export', SchoolEventRecapExportController::class)->name('school-events.recap.export');
});
