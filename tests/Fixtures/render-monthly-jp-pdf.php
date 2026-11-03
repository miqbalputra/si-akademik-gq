<?php

use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Services\AdminMonthlyJpPdfRenderer;
use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__, 2).'/vendor/autoload.php';

// This renderer regression fixture never connects to the school's database.
foreach (['APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'DB_URL' => '', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array'] as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $_SERVER[$key] = $value;
}
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || config('database.connections.sqlite.database') !== ':memory:') {
    throw new RuntimeException('PDF fixture requires an isolated testing environment.');
}

$term = new AcademicTerm(['name' => 'Ganjil']);
$term->setRelation('academicYear', new AcademicYear(['name' => '2026/2027']));
$count = (int) ($argv[1] ?? 486);
$report = [
    'term' => $term,
    'month' => 10,
    'year' => 2026,
    'stats' => ['total_jp' => $count, 'total_missing' => 22],
    'teachers' => collect(range(1, 37))->map(fn ($i) => [
        'name' => sprintf('GURU-%03d', $i), 'niy' => 'GQ'.$i, 'status' => 'active',
        'classes' => ['Mustawa 1 Ikhwan', 'Mustawa 2 Akhwat'], 'subjects' => ['Fiqih', 'Bahasa Arab'],
        'sesi_asli' => intdiv($count, 37) + ($i <= $count % 37 ? 1 : 0),
        'sesi_pengganti' => 0, 'sesi_tafsir' => 0, 'total_jp' => intdiv($count, 37) + ($i <= $count % 37 ? 1 : 0),
        'missing_count' => $i <= 22 ? 1 : 0,
    ]),
    'realized' => collect(range(1, $count))->map(fn ($i) => [
        'date_label' => 'Kamis, 01 Oktober 2026', 'session' => 'Jam 1', 'session_time' => '08:00 - 09:00',
        'classes' => ['Mustawa 1 Ikhwan'], 'subjects' => ['Fiqih'], 'teacher_name' => sprintf('GURU-%03d', (($i - 1) % 37) + 1),
        'type' => 'Reguler', 'material' => sprintf('MATERI-%04d ', $i).str_repeat('Pembahasan materi dan latihan santri. ', 5), 'jp' => 1,
    ]),
    'missing' => collect(range(1, 22))->map(fn ($i) => [
        'teacher_name' => sprintf('KOSONG-%03d', $i), 'niy' => 'GQ'.$i, 'classes' => ['Mustawa 1 Ikhwan'],
        'subjects' => ['Fiqih'], 'date_label' => 'Jumat, 02 Oktober 2026', 'session' => 'Jam 2', 'session_time' => '09:00 - 10:00',
    ]),
];

$pdf = $app->make(AdminMonthlyJpPdfRenderer::class)->render($report);
if (isset($argv[2])) {
    file_put_contents($argv[2], $pdf);
    fwrite(STDOUT, json_encode(['bytes' => strlen($pdf), 'peak_memory' => memory_get_peak_usage(true)]).PHP_EOL);
} else {
    fwrite(STDOUT, $pdf);
}
