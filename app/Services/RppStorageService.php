<?php

namespace App\Services;

use App\Models\Rpp;
use App\Models\RppFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RppStorageService
{
    public const DISK = 'rpp';

    public function storeUpload(Rpp $rpp, UploadedFile $file): RppFile
    {
        $safeName = Str::of($file->getClientOriginalName())->basename()->replaceMatches('/[^\\pL\\pN._ -]/u', '_')->limit(180, '')->toString() ?: 'rpp.pdf';
        $path = "uploads/{$rpp->id}/".Str::uuid().'.pdf';
        $contents = file_get_contents($file->getRealPath());
        abort_unless($contents !== false, 422, 'Berkas RPP tidak dapat dibaca.');
        abort_unless(str_starts_with($contents, '%PDF-'), 422, 'Berkas harus berupa PDF yang valid.');

        Storage::disk(self::DISK)->put($path, $contents);

        return $rpp->files()->create([
            'kind' => 'upload',
            'disk' => self::DISK,
            'path' => $path,
            'nama_file' => $safeName,
            'mime_type' => 'application/pdf',
            'ukuran_byte' => strlen($contents),
            'checksum' => hash('sha256', $contents),
        ]);
    }

    public function putExport(Rpp $rpp, string $type, string $extension, string $mimeType, string $content, string $hash): array
    {
        $path = "exports/{$rpp->id}/{$type}-{$hash}.{$extension}";
        Storage::disk(self::DISK)->put($path, $content);

        $export = $rpp->exports()->updateOrCreate(
            ['type' => $type],
            ['disk' => self::DISK, 'path' => $path, 'mime_type' => $mimeType, 'content_hash' => $hash, 'ukuran_byte' => strlen($content)],
        );

        return [$export, $path];
    }
}
