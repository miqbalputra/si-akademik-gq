<?php

namespace App\Console\Commands;

use App\Models\AcademicTerm;
use App\Models\ClassEnrollment;
use App\Models\Classroom;
use App\Models\ClassroomTerm;
use App\Models\Student;
use App\Services\PlacementService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

/**
 * Tempatkan santri ke kelasnya (Mustawa 1-6 Ikhwan / Akhwat) dari file CSV
 * mapping, memakai PlacementService bawaan aplikasi (bukan insert DB mentah)
 * sehingga menghormati unique constraint class_enrollments(term, student) dan
 * aman dijalankan berulang (idempoten).
 *
 * Command ini sekaligus find-or-create:
 *   - Classroom (Mustawa N Ikhwan / Mustawa N Akhwat) bila belum ada
 *   - ClassroomTerm untuk academic term aktif
 * lalu menempatkan setiap santri (dicocokkan via NIS) ke ClassroomTerm tsb.
 *
 * Kolom CSV (header bebas besar/kecil, spasi -> snake_case):
 *   - nis           (wajib)  — dicocokkan ke students.nis
 *   - nama_kelas    (wajib)  — contoh: "Mustawa 1 Ikhwan"
 *   - level_name    (opsional) — contoh: "Mustawa 1"
 *   - gender_group  (opsional) — male | female | mixed (default: dari akhiran Ikhwan/Akhwat)
 *
 * Jalankan:
 *   php artisan santri:map-kelas mapping-santri-kelas.csv
 *   php artisan santri:map-kelas --dry-run        # preview tanpa menulis
 *   php artisan santri:map-kelas --term=2 file.csv
 */
#[Signature('santri:map-kelas {path? : Path ke file CSV mapping (nis, nama_kelas, level_name, gender_group)} {--term= : ID academic term (default: term aktif terbaru)} {--dry-run : Preview tanpa menulis ke DB}')]
#[Description('Tempatkan santri ke kelas (Mustawa 1-6 Ikhwan/Akhwat) dari CSV via PlacementService. Find-or-create Classroom + ClassroomTerm. Idempoten.')]
class MapSantriKelas extends Command
{
    /** @var array<int, string> */
    private const REQUIRED_HEADERS = ['nis', 'nama_kelas'];

    public function handle(): int
    {
        $path = $this->resolvePath((string) ($this->argument('path') ?? ''));
        if ($path === null) {
            $this->error('File CSV tidak ditemukan. Berikan path, atau letakkan mapping-santri-kelas.csv di root project / storage/app.');
            $this->info('Contoh: php artisan santri:map-kelas mapping-santri-kelas.csv');

            return self::FAILURE;
        }

        if (! is_file($path) || ! is_readable($path)) {
            $this->error("File tidak bisa dibaca: {$path}");

            return self::FAILURE;
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            $this->error('File gagal dibuka.');

            return self::FAILURE;
        }

        $headers = $this->readHeaders($handle);
        foreach (self::REQUIRED_HEADERS as $required) {
            if (! in_array($required, $headers, true)) {
                $this->error("Kolom wajib '{$required}' belum ada di header. Ditemukan: " . implode(', ', $headers));
                fclose($handle);

                return self::FAILURE;
            }
        }

        $dry = (bool) $this->option('dry-run');

        $term = $this->resolveTerm((string) ($this->option('term') ?? ''));
        if ($term === null) {
            $this->error('Tidak ada academic term aktif. Aktifkan satu term atau lewat --term={id}.');
            fclose($handle);

            return self::FAILURE;
        }

        // Baca semua baris dulu agar bisa dry-run + ringkasan sebelum menulis.
        $rows = [];
        while (($row = fgetcsv($handle)) !== false) {
            if ($this->isEmptyRow($row)) {
                continue;
            }
            $rows[] = $this->combineRow($headers, $row);
        }
        fclose($handle);

        if ($rows === []) {
            $this->error('CSV tidak berisi baris data.');

            return self::FAILURE;
        }

        $this->info("Term aktif: #{$term->id} — {$term->name}");
        $this->info('Total baris CSV: ' . count($rows) . ($dry ? '  [DRY-RUN]' : ''));

        // 1) Kumpulkan nama kelas unik + buat/find Classroom & ClassroomTerm.
        $kelasUnik = [];
        foreach ($rows as $r) {
            $key = trim((string) $r['nama_kelas']);
            if ($key !== '') {
                $kelasUnik[$key] = [
                    'level_name' => trim((string) ($r['level_name'] ?? '')),
                    'gender_group' => trim((string) ($r['gender_group'] ?? '')),
                ];
            }
        }

        $classroomTermByKelas = [];
        $classroomsCreated = 0;
        $classroomsExisting = 0;
        $termsCreated = 0;
        $termsExisting = 0;

        foreach ($kelasUnik as $namaKelas => $meta) {
            $gender = $this->normalizeGenderGroup($meta['gender_group'], $namaKelas);
            $level = $meta['level_name'] !== '' ? $meta['level_name'] : $this->inferLevel($namaKelas);
            $sort = $this->inferSortOrder($namaKelas);

            if ($dry) {
                $classroomTermByKelas[$namaKelas] = ['classroom_id' => 0, 'classroom_term_id' => 0];
                continue;
            }

            DB::transaction(function () use (
                $namaKelas, $gender, $level, $sort, $term,
                &$classroomTermByKelas, &$classroomsCreated, &$classroomsExisting, &$termsCreated, &$termsExisting
            ): void {
                $classroom = Classroom::withTrashed()
                    ->whereRaw('LOWER(name) = ?', [mb_strtolower($namaKelas)])
                    ->first();

                if ($classroom) {
                    $classroomsExisting++;
                    if ($classroom->trashed()) {
                        $classroom->restore();
                    }
                    $patch = [];
                    if (! $classroom->level_name && $level) {
                        $patch['level_name'] = $level;
                    }
                    if ($classroom->gender_group === 'mixed' && $gender && $gender !== 'mixed') {
                        $patch['gender_group'] = $gender;
                    }
                    if (((int) $classroom->sort_order) === 0 && $sort) {
                        $patch['sort_order'] = $sort;
                    }
                    if ($patch !== []) {
                        $classroom->update($patch);
                    }
                } else {
                    $classroom = Classroom::create([
                        'name' => $namaKelas,
                        'level_name' => $level,
                        'gender_group' => $gender ?: 'mixed',
                        'sort_order' => $sort,
                        'is_active' => true,
                    ]);
                    $classroomsCreated++;
                }

                $classroomTerm = ClassroomTerm::firstOrNew([
                    'academic_term_id' => $term->id,
                    'classroom_id' => $classroom->id,
                ]);
                if (! $classroomTerm->exists) {
                    $classroomTerm->name = $namaKelas;
                    $classroomTerm->status = 'active';
                    $classroomTerm->save();
                    $termsCreated++;
                } else {
                    $termsExisting++;
                }

                $classroomTermByKelas[$namaKelas] = [
                    'classroom_id' => $classroom->id,
                    'classroom_term_id' => $classroomTerm->id,
                ];
            });
        }

        if (! $dry) {
            $this->info("Classroom: dibuat {$classroomsCreated} | sudah ada {$classroomsExisting}");
            $this->info("ClassroomTerm (term ini): dibuat {$termsCreated} | sudah ada {$termsExisting}");
        }

        // 2) Tempatkan setiap santri.
        $placement = app(PlacementService::class);
        $placed = 0;
        $notFound = [];
        $trashed = [];
        $kelasTakDikenal = [];

        foreach ($rows as $r) {
            $nis = trim((string) $r['nis']);
            $namaKelas = trim((string) $r['nama_kelas']);
            if ($nis === '' || $namaKelas === '') {
                continue;
            }

            if (! isset($classroomTermByKelas[$namaKelas])) {
                $kelasTakDikenal[] = $namaKelas;
                continue;
            }

            $student = Student::withTrashed()->where('nis', $nis)->first();
            if (! $student) {
                $notFound[] = "{$nis} — " . trim((string) ($r['nama'] ?? ''));
                continue;
            }
            if ($student->trashed()) {
                $trashed[] = "{$nis} — " . trim($student->name);
                continue;
            }

            if ($dry) {
                $placed++;
                continue;
            }

            $classroomTermId = $classroomTermByKelas[$namaKelas]['classroom_term_id'];
            $placement->assignClass($term->id, $student->id, $classroomTermId);
            $placed++;
        }

        $this->newLine();
        $this->info(($dry ? '[DRY-RUN] Akan ditempatkan: ' : 'Ditempatkan: ') . $placed . ' santri');

        if ($notFound !== []) {
            $this->warn('NIS tidak ditemukan di DB (' . count($notFound) . '):');
            foreach (array_slice($notFound, 0, 30) as $line) {
                $this->line('  - ' . $line);
            }
            if (count($notFound) > 30) {
                $this->line('  ... dan ' . (count($notFound) - 30) . ' lainnya');
            }
            $this->comment('Tip: import santri dulu via `php artisan students:import`, lalu jalankan ulang command ini.');
        }

        if ($trashed !== []) {
            $this->warn('Santri ditemukan tapi status terhapus (' . count($trashed) . ') — dilewati:');
            foreach ($trashed as $line) {
                $this->line('  - ' . $line);
            }
        }

        foreach (array_unique($kelasTakDikenal) as $k) {
            $this->warn("Kelas tak dikenal dilewati: {$k}");
        }

        return self::SUCCESS;
    }

    private function resolvePath(string $given): ?string
    {
        if ($given !== '') {
            return realpath($given) ?: (is_file($given) ? $given : null);
        }
        $candidates = [
            base_path('mapping-santri-kelas.csv'),
            base_path('../mapping-santri-kelas.csv'),
            storage_path('app/mapping-santri-kelas.csv'),
            getcwd() . '/mapping-santri-kelas.csv',
        ];
        foreach ($candidates as $c) {
            if (is_file($c)) {
                return realpath($c);
            }
        }

        return null;
    }

    private function resolveTerm(string $termId): ?AcademicTerm
    {
        if ($termId !== '') {
            return AcademicTerm::find((int) $termId);
        }

        return AcademicTerm::query()
            ->where('is_active', true)
            ->orderByDesc('id')
            ->first();
    }

    /** @return array<int, string> */
    private function readHeaders(mixed $handle): array
    {
        $headers = fgetcsv($handle);
        if ($headers === false) {
            return [];
        }

        return array_map(fn (?string $header): string => Str::of((string) $header)
            ->replace("\xEF\xBB\xBF", '')
            ->trim()
            ->lower()
            ->snake()
            ->toString(), $headers);
    }

    /** @param array<int, string|null> $row */
    private function isEmptyRow(array $row): bool
    {
        return collect($row)->every(fn ($value): bool => trim((string) $value) === '');
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, string|null>  $row
     * @return array<string, string>
     */
    private function combineRow(array $headers, array $row): array
    {
        $data = [];
        foreach ($headers as $index => $header) {
            $data[$header] = trim((string) ($row[$index] ?? ''));
        }

        return $data;
    }

    private function normalizeGenderGroup(string $value, string $namaKelas): string
    {
        $v = mb_strtolower(trim($value));
        if (in_array($v, ['male', 'female', 'mixed'], true)) {
            return $v;
        }
        // Fallback: infer dari akhiran kelas.
        $lower = mb_strtolower($namaKelas);
        if (str_contains($lower, 'ikhwan')) {
            return 'male';
        }
        if (str_contains($lower, 'akhwat')) {
            return 'female';
        }

        return 'mixed';
    }

    private function inferLevel(string $namaKelas): string
    {
        if (preg_match('/Mustawa\s*(\d+)/i', $namaKelas, $m)) {
            return 'Mustawa ' . $m[1];
        }

        return '';
    }

    private function inferSortOrder(string $namaKelas): int
    {
        if (! preg_match('/Mustawa\s*(\d+)/i', $namaKelas, $m)) {
            return 0;
        }
        $num = (int) $m[1];
        $isFemale = str_contains(mb_strtolower($namaKelas), 'akhwat');

        return $isFemale ? $num + 6 : $num;
    }
}