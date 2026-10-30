<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class FiqihCorrectionBackup
{
    /** Private undo snapshot of the only tables this correction may update. No backup pruning. */
    public function save(array $snapshot, ?array $coolifyBackup = null): array
    {
        if ($coolifyBackup !== null) {
            return $this->saveWithDownloadedCoolifyBackup($snapshot, $coolifyBackup);
        }

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

    /**
     * The full dump is held by the operator outside the application. Keep an
     * additional undo snapshot in the application's persistent database, not
     * in an ephemeral container file. No S3 settings or backup files are changed.
     */
    private function saveWithDownloadedCoolifyBackup(array $snapshot, array $backup): array
    {
        $file = $backup['file'] ?? '';
        $sha256 = $backup['sha256'] ?? '';
        if (! is_string($file) || ! preg_match('/^[\p{L}\p{N}][\p{L}\p{N}._ ()-]{0,239}$/u', $file)
            || ! is_string($sha256) || ! preg_match('/^[a-fA-F0-9]{64}$/', $sha256)) {
            throw new RuntimeException('Isi nama file backup Coolify yang sudah diunduh dan SHA-256 lengkapnya. Jangan gunakan URL atau path.');
        }
        if (DB::transactionLevel() === 0) {
            throw new RuntimeException('Snapshot koreksi wajib disimpan di dalam transaksi koreksi.');
        }
        $external = [
            'provider' => 'Coolify',
            'file' => $file,
            'sha256' => strtolower($sha256),
            // This records the operator's confirmation. The application does
            // not claim it has opened or validated the downloaded full dump.
            'verification' => 'operator_confirmed_download',
        ];
        $snapshotHash = $this->checksum($snapshot);
        $properties = [
            'archive_version' => 1, 'external_backup' => $external,
            'snapshot' => $snapshot, 'snapshot_sha256' => $snapshotHash,
        ];
        // Use the same database connection directly so optional activity-log
        // disabling or response buffering cannot postpone the safety archive.
        $now = now();
        $id = DB::table('activity_log')->insertGetId([
            'log_name' => 'data-correction', 'event' => 'fiqih_correction_snapshot',
            'description' => 'Snapshot sebelum koreksi Fiqih; backup penuh Coolify disimpan operator',
            'properties' => json_encode($properties, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $stored = json_decode(
            DB::table('activity_log')->where('id', $id)->value('properties'),
            true, flags: JSON_THROW_ON_ERROR,
        );
        if (! is_array($stored) || ! hash_equals($this->checksum($properties), $this->checksum($stored))) {
            throw new RuntimeException('Snapshot database gagal diverifikasi. Koreksi dibatalkan.');
        }

        return [
            'mode' => 'coolify_download_and_database_snapshot',
            'external_backup' => $external, 'activity_log_id' => $id,
            'sha256' => $snapshotHash, 'snapshot_verified' => true,
        ];
    }

    /** Ignore JSON object key order, which may differ after a database round trip. */
    private function checksum(array $data): string
    {
        $normalize = function (mixed $value) use (&$normalize): mixed {
            if (! is_array($value)) {
                return $value;
            }
            if (! array_is_list($value)) {
                ksort($value);
            }

            return array_map($normalize, $value);
        };

        return hash('sha256', json_encode($normalize($data), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }
}
