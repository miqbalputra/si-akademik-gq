<?php

namespace App\Console\Commands;

use App\Services\FiqihSubjectCorrection;
use Illuminate\Console\Command;
use Throwable;

class CorrectFiqihSubject extends Command
{
    protected $signature = 'diniyyah:correct-fiqih
        {--apply : Terapkan koreksi setelah pratinjau, backup, dan verifikasi}
        {--fingerprint= : Sidik data dari pratinjau yang telah ditinjau}';

    protected $description = 'Pratinjau/koreksi satu kali Fiqih Ibadah menjadi Fiqih tanpa menghapus riwayat.';

    public function handle(FiqihSubjectCorrection $correction): int
    {
        try {
            if ($this->option('apply')) {
                if (! $this->option('fingerprint')) {
                    throw new \RuntimeException('Jalankan pratinjau dahulu dan sertakan --fingerprint. Tidak ada data diubah.');
                }
                $result = $correction->apply((string) $this->option('fingerprint'));
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
