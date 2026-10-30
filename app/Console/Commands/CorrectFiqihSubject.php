<?php

namespace App\Console\Commands;

use App\Services\FiqihSubjectCorrection;
use Illuminate\Console\Command;
use Throwable;

class CorrectFiqihSubject extends Command
{
    protected $signature = 'diniyyah:correct-fiqih
        {--apply : Terapkan koreksi setelah pratinjau, backup, dan verifikasi}
        {--fingerprint= : Sidik data dari pratinjau yang telah ditinjau}
        {--coolify-backup= : Nama file backup penuh Coolify yang sudah diunduh}
        {--coolify-backup-sha256= : SHA-256 file backup Coolify yang sudah diunduh}';

    protected $description = 'Pratinjau/koreksi satu kali Fiqih Ibadah menjadi Fiqih tanpa menghapus riwayat.';

    public function handle(FiqihSubjectCorrection $correction): int
    {
        try {
            if ($this->option('apply')) {
                if (! $this->option('fingerprint')) {
                    throw new \RuntimeException('Jalankan pratinjau dahulu dan sertakan --fingerprint. Tidak ada data diubah.');
                }
                $coolifyFile = $this->option('coolify-backup');
                $coolifyHash = $this->option('coolify-backup-sha256');
                $coolifyBackup = $coolifyFile === null && $coolifyHash === null
                    ? null : ['file' => $coolifyFile, 'sha256' => $coolifyHash];
                $result = $correction->apply((string) $this->option('fingerprint'), $coolifyBackup);
            } else {
                $result = $correction->preview();
            }
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

            return empty($result['blockers']) ? self::SUCCESS : self::FAILURE;
        } catch (Throwable $exception) {
            $this->error('Koreksi dihentikan: '.$exception->getMessage());

            return self::FAILURE;
        }
    }
}
