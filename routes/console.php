<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Memerlukan scheduler aplikasi/cron (`php artisan schedule:run`) di produksi.
// Guard dijalankan tiap pagi WIB setelah jadwal mengajar tersedia.
Schedule::command('rpp:send-reminders')->dailyAt('06:30')->timezone('Asia/Jakarta')->withoutOverlapping();
