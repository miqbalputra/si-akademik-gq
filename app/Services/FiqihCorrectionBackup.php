<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class FiqihCorrectionBackup
{
    /** Private undo snapshot of the only tables this correction may update. No backup pruning. */
    public function save(array $snapshot): array
    {
        $name = (string) config('backup.disk');
        $config = config('filesystems.disks.'.$name);
        if ($name === '' || ! is_array($config) || $name === 'public') {
            throw new RuntimeException('Disk backup privat belum dikonfigurasi.');
        }
        if (app()->environment('production') && (($config['driver'] ?? null) !== 's3'
            || (filled($config['endpoint'] ?? null) && parse_url($config['endpoint'], PHP_URL_SCHEME) !== 'https'))) {
            throw new RuntimeException('Snapshot koreksi production wajib disimpan pada backup S3 privat melalui HTTPS.');
        }

        $path = 'fiqih-corrections/'.now('Asia/Jakarta')->format('Y/m/d').'/'.Str::uuid().'-before.json';
        $json = json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $disk = Storage::disk($name);
        if (! $disk->put($path, $json, ['visibility' => 'private'])
            || ! hash_equals(hash('sha256', $json), hash('sha256', $disk->get($path)))) {
            throw new RuntimeException('Snapshot backup gagal diverifikasi. Koreksi dibatalkan.');
        }

        return ['disk' => $name, 'path' => $path, 'sha256' => hash('sha256', $json)];
    }
}
