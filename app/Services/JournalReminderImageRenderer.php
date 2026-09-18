<?php

namespace App\Services;

use RuntimeException;

class JournalReminderImageRenderer
{
    private const WIDTH = 1440;

    /** @param array<string, mixed> $report */
    public function render(array $report, string $format = 'png'): string
    {
        $encoder = match ($format) {
            'png' => 'imagepng',
            'jpg' => 'imagejpeg',
            default => throw new RuntimeException('Format gambar tidak didukung.'),
        };
        if (! function_exists('imagecreatetruecolor') || ! function_exists($encoder)) {
            throw new RuntimeException('Unduhan gambar PNG/JPG memerlukan ekstensi GD. Hubungi admin server untuk mengaktifkannya.');
        }

        $font = $this->fontPath();
        $lines = $this->lines($report);
        $height = max(900, 96 + array_sum(array_map(fn (array $line): int => $line['height'], $lines)) + 72);
        $image = imagecreatetruecolor(self::WIDTH, $height);
        $white = imagecolorallocate($image, 255, 255, 255);
        $ink = imagecolorallocate($image, 30, 41, 59);
        $muted = imagecolorallocate($image, 71, 85, 105);
        $green = imagecolorallocate($image, 6, 95, 70);
        $red = imagecolorallocate($image, 185, 28, 28);
        $softRed = imagecolorallocate($image, 254, 242, 242);
        $line = imagecolorallocate($image, 226, 232, 240);
        imagefill($image, 0, 0, $white);

        imagefilledrectangle($image, 0, 0, self::WIDTH, 18, $green);
        $y = 64;
        foreach ($lines as $row) {
            if ($row['kind'] === 'teacher') {
                imagefilledrectangle($image, 52, $y - 10, self::WIDTH - 52, $y + $row['height'] - 10, $softRed);
            }
            if ($row['kind'] === 'rule') {
                imageline($image, 52, $y, self::WIDTH - 52, $y, $line);
                $y += $row['height'];

                continue;
            }

            $color = match ($row['kind']) {
                'title', 'teacher' => $green,
                'alert' => $red,
                'detail' => $ink,
                default => $muted,
            };
            $this->draw($image, $font, $row['size'], 70, $y, $row['text'], $color);
            $y += $row['height'];
        }

        ob_start();
        $rendered = $format === 'png'
            ? imagepng($image, null, 8)
            : imagejpeg($image, null, 90);
        $content = (string) ob_get_clean();
        imagedestroy($image);

        if (! $rendered || $content === '') {
            throw new RuntimeException('Gambar pengingat belum dapat dibuat. Silakan coba lagi.');
        }

        return $content;
    }

    /** @param array<string, mixed> $report
     * @return array<int, array{kind:string,text:string,size:int,height:int}>
     */
    private function lines(array $report): array
    {
        $term = $report['term'];
        $period = $report['start']->translatedFormat('d M Y').' — '.$report['end']->translatedFormat('d M Y');
        $lines = [
            ['kind' => 'title', 'text' => 'PENGINGAT PENGISIAN JURNAL KBM', 'size' => 30, 'height' => 52],
            ['kind' => 'meta', 'text' => ($term->academicYear?->name ?? '-').' · '.$term->name.' · '.$period, 'size' => 20, 'height' => 36],
            ['kind' => 'meta', 'text' => 'Dibuat '.$report['generated_at']->translatedFormat('d M Y, H:i').' WIB', 'size' => 18, 'height' => 42],
            ['kind' => 'alert', 'text' => sprintf('%d guru perlu diingatkan · %d jurnal belum diisi', $report['stats']['teachers_to_remind'], $report['stats']['total_missing']), 'size' => 22, 'height' => 52],
            ['kind' => 'rule', 'text' => '', 'size' => 1, 'height' => 28],
        ];

        if ($report['stats']['attendance_unverified_teachers'] > 0) {
            $lines[] = ['kind' => 'alert', 'text' => 'Catatan: pengecualian izin/sakit belum dapat diverifikasi untuk sebagian guru.', 'size' => 17, 'height' => 34];
        }

        if ($report['teachers']->isEmpty()) {
            $lines[] = ['kind' => 'teacher', 'text' => 'Semua jurnal pada rentang ini sudah lengkap.', 'size' => 22, 'height' => 56];

            return $lines;
        }

        foreach ($report['teachers'] as $teacher) {
            $lines[] = ['kind' => 'teacher', 'text' => sprintf('%s · %d jurnal kosong', $teacher['teacher_name'], $teacher['missing_count']), 'size' => 22, 'height' => 44];
            $lines[] = ['kind' => 'meta', 'text' => 'NIY: '.($teacher['niy'] ?: '-'), 'size' => 16, 'height' => 30];
            foreach ($teacher['rows'] as $row) {
                $detail = $row['date_label'].' · '.$row['session'].' ('.$row['session_time'].') · '.implode(', ', $row['classes']).' · '.implode(', ', $row['subjects']);
                foreach ($this->wrap($detail, 112) as $index => $text) {
                    $lines[] = ['kind' => 'detail', 'text' => ($index === 0 ? '• ' : '  ').$text, 'size' => 16, 'height' => 28];
                }
            }
            $lines[] = ['kind' => 'rule', 'text' => '', 'size' => 1, 'height' => 26];
        }

        return $lines;
    }

    /** @return array<int, string> */
    private function wrap(string $text, int $length): array
    {
        $words = preg_split('/\s+/', trim($text)) ?: [];
        $lines = [];
        $line = '';
        foreach ($words as $word) {
            $candidate = trim($line.' '.$word);
            if ($line !== '' && mb_strwidth($candidate) > $length) {
                $lines[] = $line;
                $line = $word;
            } else {
                $line = $candidate;
            }
        }
        if ($line !== '') {
            $lines[] = $line;
        }

        return $lines ?: ['-'];
    }

    private function draw(\GdImage $image, ?string $font, int $size, int $x, int $y, string $text, int $color): void
    {
        if ($font) {
            imagettftext($image, $size, 0, $x, $y + $size, $color, $font, $text);

            return;
        }

        imagestring($image, min(5, max(1, (int) round($size / 6))), $x, $y, $text, $color);
    }

    private function fontPath(): ?string
    {
        foreach ([
            resource_path('fonts/Inter.ttf'),
            public_path('fonts/filament/filament/inter/Inter.ttf'),
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            'C:\\Windows\\Fonts\\arial.ttf',
        ] as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }
}
