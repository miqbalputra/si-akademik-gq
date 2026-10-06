<?php

namespace App\Services;

use RuntimeException;
use Symfony\Component\Process\Process;

class DatabaseDumpRunner
{
    public function dumpTo(string $path): void
    {
        $connection = config('database.connections.'.config('database.default'));
        if (($connection['driver'] ?? null) !== 'pgsql') {
            throw new RuntimeException('Backup otomatis saat ini memerlukan koneksi PostgreSQL.');
        }

        $arguments = [
            config('backup.pg_dump_binary'),
            '--format=custom',
            '--no-owner',
            '--no-acl',
            '--host='.($connection['host'] ?? '127.0.0.1'),
            '--port='.($connection['port'] ?? 5432),
            '--username='.($connection['username'] ?? ''),
            '--file='.$path,
            $connection['database'] ?? '',
        ];
        $processEnvironment = [];
        if (filled($connection['password'] ?? null)) {
            $processEnvironment['PGPASSWORD'] = $connection['password'];
        }

        $process = new Process($arguments, null, $processEnvironment);
        $process->setTimeout(900);
        $process->run();

        if (! $process->isSuccessful() || ! is_file($path) || filesize($path) < 1) {
            throw new RuntimeException('pg_dump gagal membuat arsip database. Periksa log server dan konfigurasi koneksi.');
        }
    }
}
