<?php

namespace App\Services;

use App\Models\RppAiSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

class RppAiService
{
    public function setting(): RppAiSetting
    {
        return RppAiSetting::firstOrCreate(['id' => 1], ['enabled' => false]);
    }

    /** @return array{materi:string, alokasi_waktu:string, tujuan_pembelajaran:string, meetings:array<int, array{isi_kegiatan:string}>, pengetahuan:string, keterampilan:string, sikap:string} */
    public function draftFromImage(UploadedFile $image): array
    {
        $setting = $this->setting();
        abort_unless($setting->enabled && $setting->endpoint && $setting->api_key && $setting->model, 422, 'Asisten AI belum dikonfigurasi oleh Admin.');
        abort_unless(in_array($image->getMimeType(), ['image/jpeg', 'image/png', 'image/webp'], true), 422, 'Foto materi harus JPG, PNG, atau WebP.');
        abort_unless($image->getSize() <= 5 * 1024 * 1024, 422, 'Ukuran foto materi maksimal 5 MB.');

        $base64 = base64_encode((string) file_get_contents($image->getRealPath()));
        $response = Http::acceptJson()
            ->withToken($setting->api_key)
            ->timeout(90)
            ->post(rtrim($setting->endpoint, '/').'/chat/completions', [
                'model' => $setting->model,
                'temperature' => 0.3,
                'response_format' => ['type' => 'json_object'],
                'messages' => [[
                    'role' => 'user',
                    'content' => [
                        ['type' => 'text', 'text' => $this->prompt()],
                        ['type' => 'image_url', 'image_url' => ['url' => 'data:'.$image->getMimeType().';base64,'.$base64]],
                    ],
                ]],
            ]);

        abort_unless($response->successful(), 422, 'AI tidak dapat membuat draft RPP saat ini.');
        $content = data_get($response->json(), 'choices.0.message.content');
        $draft = is_string($content) ? json_decode($content, true) : null;
        abort_unless(is_array($draft), 422, 'AI mengembalikan draft dengan format tidak valid.');

        return [
            'materi' => trim((string) ($draft['materi'] ?? '')),
            'alokasi_waktu' => trim((string) ($draft['alokasiWaktu'] ?? $draft['alokasi_waktu'] ?? '')),
            'tujuan_pembelajaran' => trim((string) ($draft['tujuanPembelajaran'] ?? $draft['tujuan_pembelajaran'] ?? '')),
            'meetings' => collect($draft['pertemuan'] ?? $draft['meetings'] ?? [])
                ->map(fn ($item) => ['isi_kegiatan' => trim((string) data_get($item, 'isiKegiatan', data_get($item, 'isi_kegiatan', '')))])
                ->filter(fn ($item) => $item['isi_kegiatan'] !== '')
                ->take(4)->values()->all(),
            'pengetahuan' => trim((string) data_get($draft, 'penilaian.pengetahuan', '')),
            'keterampilan' => trim((string) data_get($draft, 'penilaian.keterampilan', '')),
            'sikap' => trim((string) data_get($draft, 'penilaian.sikap', '')),
        ];
    }

    private function prompt(): string
    {
        return 'Baca foto materi pembelajaran dan buatkan JSON valid tanpa markdown. Struktur wajib: {"materi":"","alokasiWaktu":"","tujuanPembelajaran":"","pertemuan":[{"isiKegiatan":""}],"penilaian":{"pengetahuan":"","keterampilan":"","sikap":""}}. Gunakan bahasa Indonesia dan 1 sampai 4 pertemuan yang konkret.';
    }
}
