<?php

namespace App\Console\Commands;

use App\Services\LegacyRppImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ImportLegacyRpp extends Command
{
    protected $signature = 'rpp:import-legacy {--connection=legacy_rpp : Nama koneksi database backup RPP} {--files= : Folder root berkas dari aplikasi RPP lama} {--dry-run : Validasi dan buat laporan tanpa mengubah data RPP}';
    protected $description = 'Impor idempoten RPP lama dan buat laporan rekonsiliasi mapping.';

    public function handle(LegacyRppImporter $importer): int
    {
        try {
            $report = $importer->import(DB::connection((string) $this->option('connection')), (bool) $this->option('dry-run'), $this->option('files') ?: null);
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());
            return self::FAILURE;
        }

        $path = 'rpp-import/reconciliation-'.now()->format('Ymd-His').'.json';
        Storage::disk('local')->put($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->table(['Diimpor', 'Dilewati', 'Konflik', 'Berkas', 'Promes'], [[ $report['imported'], $report['skipped'], count($report['conflicts']), $report['files'], $report['promes'] ]]);
        $this->line('Laporan rekonsiliasi: '.Storage::disk('local')->path($path));
        return self::SUCCESS;
    }
}
