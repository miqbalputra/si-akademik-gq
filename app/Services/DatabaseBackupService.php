<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class DatabaseBackupService
{
    public function __construct(private readonly DatabaseDumpRunner $dumpRunner) {}

    public function create(): string
    {
        $diskName = (string) config('backup.disk');
        if ($diskName === '') {
            throw new RuntimeException('BACKUP_DISK belum dikonfigurasi.');
        }

        $diskConfig = config('filesystems.disks.'.$diskName);
        if (! is_array($diskConfig) || ! isset($diskConfig['driver'])) {
            throw new RuntimeException('Disk backup tidak tersedia. Periksa konfigurasi BACKUP_DISK.');
        }
        if (app()->environment('production') && $diskConfig['driver'] === 'local') {
            throw new RuntimeException('Backup produksi harus dikirim ke penyimpanan jarak jauh, bukan ke disk kontainer.');
        }
        if (app()->environment('production') && filled($diskConfig['endpoint'] ?? null)
            && parse_url((string) $diskConfig['endpoint'], PHP_URL_SCHEME) !== 'https') {
            throw new RuntimeException('Endpoint backup produksi harus memakai HTTPS.');
        }

        $temporaryFile = tempnam(sys_get_temp_dir(), 'edu-db-backup-');
        if ($temporaryFile === false) {
            throw new RuntimeException('Tidak dapat membuat file sementara untuk backup.');
        }

        try {
            $this->dumpRunner->dumpTo($temporaryFile);
            $databaseName = preg_replace('/[^A-Za-z0-9._-]+/', '-', (string) config('database.connections.'.config('database.default').'.database'));
            $fileName = ($databaseName ?: 'database').'-'.now('UTC')->format('Ymd-His-u').'.dump';
            $path = trim(config('backup.prefix').'/'.now('UTC')->format('Y/m').'/'.$fileName, '/');
            $stream = fopen($temporaryFile, 'rb');
            if ($stream === false) {
                throw new RuntimeException('Tidak dapat membaca arsip backup sementara.');
            }

            try {
                if (! Storage::disk($diskName)->put($path, $stream, ['visibility' => 'private'])) {
                    throw new RuntimeException('Penyimpanan backup menolak arsip.');
                }
            } finally {
                fclose($stream);
            }

            $this->pruneExpiredBackups($diskName);

            return $path;
        } catch (Throwable $exception) {
            throw $exception;
        } finally {
            @unlink($temporaryFile);
        }
    }

    private function pruneExpiredBackups(string $diskName): void
    {
        $disk = Storage::disk($diskName);
        $expiresBefore = now('UTC')->subDays((int) config('backup.retention_days'))->timestamp;

        foreach ($disk->allFiles(config('backup.prefix')) as $file) {
            try {
                if ($disk->lastModified($file) < $expiresBefore) {
                    $disk->delete($file);
                }
            } catch (Throwable $exception) {
                report($exception);
            }
        }
    }
}
