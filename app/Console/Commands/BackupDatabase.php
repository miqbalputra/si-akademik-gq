<?php

namespace App\Console\Commands;

use App\Services\DatabaseBackupService;
use Illuminate\Console\Command;
use Throwable;

class BackupDatabase extends Command
{
    protected $signature = 'db:backup';

    protected $description = 'Buat arsip PostgreSQL dan simpan pada disk backup privat.';

    public function handle(DatabaseBackupService $backup): int
    {
        try {
            $path = $backup->create();
            $this->info("Backup database tersimpan: {$path}");

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Backup database gagal. Periksa konfigurasi penyimpanan dan log server.');

            return self::FAILURE;
        }
    }
}
