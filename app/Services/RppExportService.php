<?php

namespace App\Services;

use App\Models\Rpp;
use App\Models\RppExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class RppExportService
{
    public function export(Rpp $rpp, string $type): RppExport
    {
        abort_unless(in_array($type, ['pdf', 'png', 'docx'], true), 404);
        $rpp->loadMissing(['classSubject.subject', 'classSubject.classroomTerm.academicTerm.academicYear', 'teacher', 'meetings', 'assessment']);
        $hash = $this->contentHash($rpp);
        $existing = $rpp->exports()->where('type', $type)->first();

        if ($existing && $existing->content_hash === $hash && Storage::disk($existing->disk)->exists($existing->path)) {
            return $existing;
        }

        [$content, $extension, $mime] = match ($type) {
            'pdf' => [app(RppChromiumRenderer::class)->render($rpp, 'pdf') ?? $this->pdf($rpp), 'pdf', 'application/pdf'],
            'png' => [app(RppChromiumRenderer::class)->render($rpp, 'png') ?? $this->png($rpp), 'png', 'image/png'],
            'docx' => [$this->docx($rpp), 'docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        };

        [$export] = app(RppStorageService::class)->putExport($rpp, $type, $extension, $mime, $content, $hash);

        return $export;
    }

    public function contentHash(Rpp $rpp): string
    {
        $payload = [
            'template' => 'rpp-v1-2026-09',
            'rpp' => $rpp->only(['no_rpp', 'materi', 'alokasi_waktu', 'tujuan_pembelajaran', 'tanggal_pengesahan', 'input_method']),
            'subject' => $rpp->classSubject?->subject?->name,
            'class' => $rpp->classSubject?->classroomTerm?->name,
            'teacher' => $rpp->teacher?->name,
            'meetings' => $rpp->meetings->map->only(['urutan', 'isi_kegiatan', 'tanggal_kbm'])->all(),
            'assessment' => $rpp->assessment?->only(['pengetahuan', 'keterampilan', 'sikap']),
        ];

        return hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function pdf(Rpp $rpp): string
    {
        return Pdf::loadView('rpp.exports.document', ['rpp' => $rpp])
            ->setPaper('a4')
            ->output();
    }

    private function png(Rpp $rpp): string
    {
        if (! function_exists('imagecreatetruecolor')) {
            throw new \RuntimeException('Ekspor PNG memerlukan ekstensi GD.');
        }

        $lines = $this->plainTextLines($rpp);
        $width = 1600;
        $height = max(1200, 150 + (count($lines) * 34));
        $image = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($image, 255, 255, 255);
        $ink = imagecolorallocate($image, 30, 41, 59);
        $accent = imagecolorallocate($image, 6, 95, 70);
        imagefill($image, 0, 0, $white);

        imagestring($image, 5, 60, 45, 'RENCANA PELAKSANAAN PEMBELAJARAN', $accent);
        $y = 110;
        foreach ($lines as $line) {
            imagestring($image, 4, 60, $y, mb_strimwidth($line, 0, 150, '...'), $ink);
            $y += 34;
        }

        ob_start();
        imagepng($image, null, 8);
        $content = (string) ob_get_clean();
        imagedestroy($image);

        return $content;
    }

    private function docx(Rpp $rpp): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new \RuntimeException('Ekspor DOCX memerlukan ekstensi ZIP.');
        }

        $tmp = tempnam(sys_get_temp_dir(), 'rpp-docx-');
        if ($tmp === false) {
            throw new \RuntimeException('Tidak dapat menyiapkan berkas DOCX.');
        }

        $zip = new ZipArchive;
        if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            @unlink($tmp);
            throw new \RuntimeException('Tidak dapat membuat berkas DOCX.');
        }

        $paragraphs = array_map(fn (string $line) => '<w:p><w:r><w:t xml:space="preserve">'.$this->xml($line).'</w:t></w:r></w:p>', $this->plainTextLines($rpp));
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>');
        $zip->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>'.implode('', $paragraphs).'<w:sectPr/></w:body></w:document>');
        $zip->close();
        $content = file_get_contents($tmp);
        @unlink($tmp);

        if ($content === false) {
            throw new \RuntimeException('Tidak dapat membaca berkas DOCX.');
        }

        return $content;
    }

    /** @return array<int, string> */
    private function plainTextLines(Rpp $rpp): array
    {
        $classSubject = $rpp->classSubject;
        $class = $classSubject?->classroomTerm;
        $lines = [
            'RENCANA PELAKSANAAN PEMBELAJARAN',
            'No. RPP: '.($rpp->no_rpp ?: '-'),
            '',
            'Mata Pelajaran: '.($classSubject?->subject?->name ?: '-'),
            'Kelas: '.($class?->name ?: '-'),
            'Materi: '.$rpp->materi,
            'Alokasi Waktu: '.($rpp->alokasi_waktu ?: '-'),
            'Tujuan Pembelajaran: '.($rpp->tujuan_pembelajaran ?: '-'),
            '',
            'Kegiatan Pembelajaran',
        ];
        foreach ($rpp->meetings as $meeting) {
            $lines[] = "Pertemuan {$meeting->urutan}: {$meeting->isi_kegiatan}";
        }
        $lines = array_merge($lines, [
            '', 'Penilaian Pengetahuan: '.($rpp->assessment?->pengetahuan ?: '-'),
            'Penilaian Keterampilan: '.($rpp->assessment?->keterampilan ?: '-'),
            'Penilaian Sikap: '.($rpp->assessment?->sikap ?: '-'),
            '', 'Guru Pengampu: '.($rpp->teacher?->name ?: '-'),
        ]);

        return $lines;
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
