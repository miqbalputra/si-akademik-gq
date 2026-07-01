<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\DiniyyahLedgerController;
use App\Http\Controllers\DiniyyahMonitoringController;
use App\Http\Controllers\GuardianDashboardController;
use App\Http\Controllers\GuardianTahfidzController;
use App\Http\Controllers\GuruTahfidzController;
use App\Http\Controllers\GuardianSchoolEventResponseController;
use App\Http\Controllers\GuruDiniyyahScoreController;
use App\Http\Controllers\ReportCardController;
use App\Http\Controllers\SchoolCalendarController;
use App\Http\Controllers\SchoolEventRecapExportController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::get('/auth/google', [GoogleController::class, 'redirectToGoogle'])->name('auth.google');
Route::get('/auth/google/callback', [GoogleController::class, 'handleGoogleCallback'])->name('auth.google.callback');

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware('auth')->prefix('guru')->name('guru.')->group(function () {
    Route::get('/', [\App\Http\Controllers\GuruDashboardController::class, 'index'])->name('dashboard');
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
    Route::get('/diniyyah-scores/{assessmentSet}/attendance', [\App\Http\Controllers\GuruDiniyyahAttendanceController::class, 'edit'])->name('diniyyah-attendance.edit');
    Route::put('/diniyyah-scores/{assessmentSet}/attendance/single', [\App\Http\Controllers\GuruDiniyyahAttendanceController::class, 'updateSingle'])->name('diniyyah-attendance.update-single');
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
    Route::post('/wali/events/{event}/response', [GuardianSchoolEventResponseController::class, 'store'])->name('wali.events.response');
    Route::get('/school-events/{event}/recap/export', SchoolEventRecapExportController::class)->name('school-events.recap.export');
});
