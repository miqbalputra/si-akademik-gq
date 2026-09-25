<?php

namespace App\Services;

use RuntimeException;

class JournalReminderImageRenderer
{
    private const WIDTH = 1440;

    private const MARGIN = 64;

    private const CONTENT_WIDTH = self::WIDTH - (self::MARGIN * 2);

    private const CELL_PADDING = 16;

    private const ROW_PADDING = 14;

    /** @var array<int, array{key:string,label:string,width:int}> */
    private const COLUMNS = [
        ['key' => 'date', 'label' => 'Tanggal', 'width' => 230],
        ['key' => 'session', 'label' => 'Sesi / Jam', 'width' => 260],
        ['key' => 'classes', 'label' => 'Kelas', 'width' => 390],
        ['key' => 'subjects', 'label' => 'Mapel', 'width' => 432],
    ];

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

        if (! function_exists('imagettftext') || ! function_exists('imagettfbbox')) {
            throw new RuntimeException('Unduhan gambar PNG/JPG memerlukan GD dengan dukungan font TrueType/FreeType. Hubungi admin server untuk mengaktifkannya.');
        }

        $fonts = $this->fontPaths();
        foreach ($fonts as $font) {
            if (! is_file($font)) {
                throw new RuntimeException('Font laporan tidak ditemukan. Hubungi admin server untuk memeriksa aset font.');
            }
        }

        $layout = $this->buildLayout($report, $fonts);
        $image = imagecreatetruecolor(self::WIDTH, $layout['height']);
        if (! $image) {
            throw new RuntimeException('Ruang gambar tidak dapat disiapkan. Silakan perkecil rentang laporan.');
        }

        $palette = $this->palette($image);
        imagefill($image, 0, 0, $palette['white']);
        imagefilledrectangle($image, 0, 0, self::WIDTH - 1, 17, $palette['green']);
        $bufferLevel = ob_get_level();

        try {
            foreach ($layout['blocks'] as $block) {
                match ($block['kind']) {
                    'text' => $this->drawTextBlock($image, $block, $fonts, $palette),
                    'teacher' => $this->drawTeacherBlock($image, $block, $fonts, $palette),
                    'table_header' => $this->drawTableHeader($image, $block, $fonts['bold'], $palette),
                    'table_row' => $this->drawTableRow($image, $block, $fonts, $palette),
                    'separator' => $this->drawSeparator($image, $block['y'], $palette),
                    default => null,
                };
            }

            ob_start();
            $encoded = $format === 'png'
                ? imagepng($image, null, 8)
                : imagejpeg($image, null, 92);
            $content = (string) ob_get_clean();

            if (! $encoded || $content === '') {
                throw new RuntimeException('Gambar pengingat belum dapat dibuat. Silakan coba lagi.');
            }

            return $content;
        } finally {
            while (ob_get_level() > $bufferLevel) {
                ob_end_clean();
            }
            imagedestroy($image);
        }
    }

    /** @param array<string, mixed> $report
     * @param  array{regular:string,bold:string}  $fonts
     * @return array{height:int,blocks:array<int, array<string, mixed>>}
     */
    private function buildLayout(array $report, array $fonts): array
    {
        $blocks = [];
        $y = 52;
        $term = $report['term'];
        $period = $report['start']->translatedFormat('d M Y').' - '.$report['end']->translatedFormat('d M Y');
        $yearName = $term->academicYear?->name ?? '-';
        $termName = $term->name ?? '-';

        $this->pushTextBlock($blocks, $y, 'PENGINGAT PENGISIAN JURNAL KBM', 'bold', 32, 'green', $fonts, gapAfter: 10);
        $this->pushTextBlock(
            $blocks,
            $y,
            'Tahun ajaran: '.$yearName.'  |  Semester: '.$termName,
            'regular',
            18,
            'muted',
            $fonts,
            gapAfter: 2,
        );
        $this->pushTextBlock(
            $blocks,
            $y,
            'Rentang: '.$period.'  |  Dibuat: '.$report['generated_at']->translatedFormat('d M Y, H:i').' WIB',
            'regular',
            16,
            'muted',
            $fonts,
            gapAfter: 16,
        );

        $summary = sprintf(
            '%d guru perlu diingatkan  |  %d jurnal belum diisi',
            $report['stats']['teachers_to_remind'],
            $report['stats']['total_missing'],
        );
        $this->pushTextBlock(
            $blocks,
            $y,
            $summary,
            'bold',
            20,
            'red',
            $fonts,
            fill: 'soft_red',
            paddingX: 18,
            paddingY: 14,
            gapAfter: 16,
        );

        if ($report['stats']['attendance_unverified_teachers'] > 0) {
            $this->pushTextBlock(
                $blocks,
                $y,
                'Catatan: pengecualian izin/sakit belum dapat diverifikasi untuk sebagian guru.',
                'regular',
                16,
                'amber',
                $fonts,
                fill: 'soft_amber',
                paddingX: 18,
                paddingY: 12,
                gapAfter: 18,
            );
        }

        if ($report['teachers']->isEmpty()) {
            $this->pushTextBlock(
                $blocks,
                $y,
                'Semua jurnal pada rentang ini sudah lengkap.',
                'bold',
                20,
                'green',
                $fonts,
                fill: 'soft_green',
                paddingX: 18,
                paddingY: 18,
            );

            return ['height' => max(900, $y + 48), 'blocks' => $blocks];
        }

        foreach ($report['teachers'] as $teacher) {
            $this->pushTeacherBlock($blocks, $y, $teacher, $fonts);
            $blocks[] = ['kind' => 'table_header', 'y' => $y, 'height' => 54];
            $y += 54;

            foreach ($teacher['rows'] as $index => $row) {
                $cells = $this->layoutRow($row, $fonts);
                $rowHeight = max(
                    count($cells['date']['lines']) * $cells['date']['line_height'],
                    count($cells['session']['main']) * $cells['session']['main_line_height']
                        + 4
                        + count($cells['session']['time']) * $cells['session']['time_line_height'],
                    count($cells['classes']['lines']) * $cells['classes']['line_height'],
                    count($cells['subjects']['lines']) * $cells['subjects']['line_height'],
                    34,
                ) + (self::ROW_PADDING * 2);
                $blocks[] = [
                    'kind' => 'table_row',
                    'y' => $y,
                    'height' => $rowHeight,
                    'cells' => $cells,
                    'alternate' => $index % 2 === 1,
                ];
                $y += $rowHeight;
            }

            $blocks[] = ['kind' => 'separator', 'y' => $y + 14];
            $y += 38;
        }

        return ['height' => max(900, $y + 48), 'blocks' => $blocks];
    }

    /** @param array<int, array<string, mixed>> $blocks
     * @param  array{regular:string,bold:string}  $fonts
     * @param  array<string, mixed>  $teacher
     */
    private function pushTeacherBlock(array &$blocks, int &$y, array $teacher, array $fonts): void
    {
        $padding = 16;
        $nameSize = 23;
        $detailSize = 16;
        $nameLines = $this->wrapText((string) ($teacher['teacher_name'] ?: '-'), $fonts['bold'], $nameSize, self::CONTENT_WIDTH - ($padding * 2));
        $detail = 'NIY: '.($teacher['niy'] ?: '-').'  |  '.(int) $teacher['missing_count'].' jurnal kosong';
        $detailLines = $this->wrapText($detail, $fonts['regular'], $detailSize, self::CONTENT_WIDTH - ($padding * 2));
        $nameLineHeight = $this->lineHeight($fonts['bold'], $nameSize);
        $detailLineHeight = $this->lineHeight($fonts['regular'], $detailSize);
        $height = ($padding * 2)
            + (count($nameLines) * $nameLineHeight)
            + 4
            + (count($detailLines) * $detailLineHeight);

        $blocks[] = [
            'kind' => 'teacher',
            'y' => $y,
            'height' => $height,
            'padding' => $padding,
            'name_lines' => $nameLines,
            'name_line_height' => $nameLineHeight,
            'detail_lines' => $detailLines,
            'detail_line_height' => $detailLineHeight,
        ];
        $y += $height + 14;
    }

    /** @param array<string, mixed> $row
     * @param  array{regular:string,bold:string}  $fonts
     * @return array<string, mixed>
     */
    private function layoutRow(array $row, array $fonts): array
    {
        $widths = collect(self::COLUMNS)->keyBy('key');
        $dateSize = 17;
        $mainSize = 17;
        $timeSize = 14;
        $classSize = 17;

        $date = (string) ($row['date_label'] ?: '-');
        $session = (string) ($row['session'] ?: '-');
        $time = (string) ($row['session_time'] ?: '-');
        $classes = $this->joinedValues($row['classes'] ?? []);
        $subjects = $this->joinedValues($row['subjects'] ?? []);

        return [
            'date' => [
                'lines' => $this->wrapText($date, $fonts['regular'], $dateSize, $widths->get('date')['width'] - (self::CELL_PADDING * 2)),
                'size' => $dateSize,
                'line_height' => $this->lineHeight($fonts['regular'], $dateSize),
                'color' => 'ink',
            ],
            'session' => [
                'main' => $this->wrapText($session, $fonts['regular'], $mainSize, $widths->get('session')['width'] - (self::CELL_PADDING * 2)),
                'time' => $this->wrapText($time, $fonts['regular'], $timeSize, $widths->get('session')['width'] - (self::CELL_PADDING * 2)),
                'main_size' => $mainSize,
                'time_size' => $timeSize,
                'main_line_height' => $this->lineHeight($fonts['regular'], $mainSize),
                'time_line_height' => $this->lineHeight($fonts['regular'], $timeSize),
                'color' => 'ink',
                'secondary_color' => 'muted',
            ],
            'classes' => [
                'lines' => $this->wrapText($classes, $fonts['regular'], $classSize, $widths->get('classes')['width'] - (self::CELL_PADDING * 2)),
                'size' => $classSize,
                'line_height' => $this->lineHeight($fonts['regular'], $classSize),
                'color' => 'ink',
            ],
            'subjects' => [
                'lines' => $this->wrapText($subjects, $fonts['regular'], $classSize, $widths->get('subjects')['width'] - (self::CELL_PADDING * 2)),
                'size' => $classSize,
                'line_height' => $this->lineHeight($fonts['regular'], $classSize),
                'color' => 'ink',
            ],
        ];
    }

    /** @param array<int, array<string, mixed>> $blocks
     * @param  array{regular:string,bold:string}  $fonts
     */
    private function pushTextBlock(
        array &$blocks,
        int &$y,
        string $text,
        string $face,
        int $size,
        string $color,
        array $fonts,
        ?string $fill = null,
        int $paddingX = 0,
        int $paddingY = 0,
        int $gapAfter = 0,
    ): void {
        $lineHeight = $this->lineHeight($fonts[$face], $size);
        $lines = $this->wrapText($text, $fonts[$face], $size, self::CONTENT_WIDTH - ($paddingX * 2));
        $height = (count($lines) * $lineHeight) + ($paddingY * 2);
        $blocks[] = [
            'kind' => 'text',
            'y' => $y,
            'height' => $height,
            'lines' => $lines,
            'face' => $face,
            'size' => $size,
            'line_height' => $lineHeight,
            'color' => $color,
            'fill' => $fill,
            'padding_x' => $paddingX,
            'padding_y' => $paddingY,
        ];
        $y += $height + $gapAfter;
    }

    /** @param array<int, string>|string $row */
    private function joinedValues(mixed $row): string
    {
        if (! is_array($row)) {
            return trim((string) $row) !== '' ? (string) $row : '-';
        }

        $values = array_values(array_filter(array_map(
            fn (mixed $value): string => trim((string) $value),
            $row,
        ), fn (string $value): bool => $value !== ''));

        return $values === [] ? '-' : implode(', ', $values);
    }

    /** @param array{regular:string,bold:string} $fonts
     * @return array<int, string>
     */
    private function wrapText(string $text, string $font, int $size, int $maxWidth): array
    {
        $words = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY);
        if ($words === false) {
            $words = preg_split('/\s+/', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        }

        $lines = [];
        $line = '';
        foreach ($words as $word) {
            foreach ($this->splitLongWord((string) $word, $font, $size, $maxWidth) as $part) {
                $candidate = $line === '' ? $part : $line.' '.$part;
                if ($line !== '' && $this->textWidth($candidate, $font, $size) > $maxWidth) {
                    $lines[] = $line;
                    $line = $part;

                    continue;
                }

                $line = $candidate;
            }
        }

        if ($line !== '') {
            $lines[] = $line;
        }

        return $lines ?: ['-'];
    }

    /** @return array<int, string> */
    private function splitLongWord(string $word, string $font, int $size, int $maxWidth): array
    {
        if ($this->textWidth($word, $font, $size) <= $maxWidth) {
            return [$word];
        }

        $characters = preg_split('//u', $word, -1, PREG_SPLIT_NO_EMPTY);
        if ($characters === false || $characters === []) {
            $characters = str_split($word);
        }

        $parts = [];
        $part = '';
        foreach ($characters as $character) {
            $candidate = $part.$character;
            if ($part !== '' && $this->textWidth($candidate, $font, $size) > $maxWidth) {
                $parts[] = $part;
                $part = $character;
            } else {
                $part = $candidate;
            }
        }
        if ($part !== '') {
            $parts[] = $part;
        }

        return $parts ?: [$word];
    }

    private function textWidth(string $text, string $font, int $size): int
    {
        if (function_exists('imagettfbbox')) {
            $bounds = imagettfbbox($size, 0, $font, $text);
            if (is_array($bounds)) {
                return abs($bounds[2] - $bounds[0]);
            }
        }

        $characters = function_exists('mb_strwidth') ? mb_strwidth($text, 'UTF-8') : strlen($text);

        return (int) ceil($characters * $size * 0.6);
    }

    private function lineHeight(string $font, int $size): int
    {
        if (function_exists('imagettfbbox')) {
            $bounds = imagettfbbox($size, 0, $font, 'Ag');
            if (is_array($bounds)) {
                $height = max($bounds[1], $bounds[3], $bounds[5], $bounds[7]) - min($bounds[1], $bounds[3], $bounds[5], $bounds[7]);

                return max((int) ceil($size * 1.25), $height + 5);
            }
        }

        return (int) ceil($size * 1.5);
    }

    /** @param array{regular:string,bold:string} $fonts */
    private function drawTextBlock(\GdImage $image, array $block, array $fonts, array $palette): void
    {
        $x = self::MARGIN;
        if ($block['fill']) {
            imagefilledrectangle($image, $x, $block['y'], self::WIDTH - self::MARGIN - 1, $block['y'] + $block['height'] - 1, $palette[$block['fill']]);
        }
        $textX = $x + $block['padding_x'];
        $textY = $block['y'] + $block['padding_y'];

        foreach ($block['lines'] as $index => $line) {
            $this->drawText(
                $image,
                $fonts[$block['face']],
                $block['size'],
                $textX,
                $textY + ($index * $block['line_height']),
                $line,
                $palette[$block['color']],
            );
        }
    }

    /** @param array{regular:string,bold:string} $fonts */
    private function drawTeacherBlock(\GdImage $image, array $block, array $fonts, array $palette): void
    {
        $x = self::MARGIN;
        $top = $block['y'];
        $padding = $block['padding'];
        imagefilledrectangle($image, $x, $top, self::WIDTH - self::MARGIN - 1, $top + $block['height'] - 1, $palette['soft_green']);
        imagefilledrectangle($image, $x, $top, $x + 6, $top + $block['height'] - 1, $palette['green']);

        $nameY = $top + $padding;
        foreach ($block['name_lines'] as $index => $line) {
            $this->drawText($image, $fonts['bold'], 23, $x + $padding, $nameY + ($index * $block['name_line_height']), $line, $palette['green']);
        }
        $detailY = $nameY + (count($block['name_lines']) * $block['name_line_height']) + 4;
        foreach ($block['detail_lines'] as $index => $line) {
            $this->drawText($image, $fonts['regular'], 16, $x + $padding, $detailY + ($index * $block['detail_line_height']), $line, $palette['muted']);
        }
    }

    private function drawTableHeader(\GdImage $image, array $block, string $font, array $palette): void
    {
        $x = self::MARGIN;
        $y = $block['y'];
        imagefilledrectangle($image, $x, $y, self::WIDTH - self::MARGIN - 1, $y + $block['height'] - 1, $palette['green']);

        foreach (self::COLUMNS as $column) {
            $this->drawText($image, $font, 17, $x + self::CELL_PADDING, $y + 15, $column['label'], $palette['white']);
            $x += $column['width'];
        }
    }

    /** @param array{regular:string,bold:string} $fonts */
    private function drawTableRow(\GdImage $image, array $block, array $fonts, array $palette): void
    {
        $x = self::MARGIN;
        $y = $block['y'];
        $background = $block['alternate'] ? $palette['row_alt'] : $palette['white'];
        imagefilledrectangle($image, $x, $y, self::WIDTH - self::MARGIN - 1, $y + $block['height'] - 1, $background);
        imageline($image, $x, $y, self::WIDTH - self::MARGIN - 1, $y, $palette['line']);

        foreach (self::COLUMNS as $column) {
            $cell = $block['cells'][$column['key']];
            $textX = $x + self::CELL_PADDING;
            $textY = $y + self::ROW_PADDING;

            if ($column['key'] === 'session') {
                foreach ($cell['main'] as $index => $line) {
                    $this->drawText($image, $fonts['regular'], $cell['main_size'], $textX, $textY + ($index * $cell['main_line_height']), $line, $palette[$cell['color']]);
                }
                $timeY = $textY + (count($cell['main']) * $cell['main_line_height']) + 4;
                foreach ($cell['time'] as $index => $line) {
                    $this->drawText($image, $fonts['regular'], $cell['time_size'], $textX, $timeY + ($index * $cell['time_line_height']), $line, $palette[$cell['secondary_color']]);
                }
            } else {
                foreach ($cell['lines'] as $index => $line) {
                    $this->drawText($image, $fonts['regular'], $cell['size'], $textX, $textY + ($index * $cell['line_height']), $line, $palette[$cell['color']]);
                }
            }

            $x += $column['width'];
            if ($x < self::WIDTH - self::MARGIN) {
                imageline($image, $x, $y, $x, $y + $block['height'], $palette['line']);
            }
        }

        imageline($image, self::MARGIN, $y + $block['height'] - 1, self::WIDTH - self::MARGIN - 1, $y + $block['height'] - 1, $palette['line']);
    }

    private function drawSeparator(\GdImage $image, int $y, array $palette): void
    {
        imageline($image, self::MARGIN, $y, self::WIDTH - self::MARGIN - 1, $y, $palette['line']);
    }

    private function drawText(\GdImage $image, string $font, int $size, int $x, int $top, string $text, int $color): void
    {
        $bounds = imagettfbbox($size, 0, $font, $text);
        if (! is_array($bounds)) {
            throw new RuntimeException('Teks laporan tidak dapat diukur dengan font TrueType.');
        }
        $minimumY = min($bounds[1], $bounds[3], $bounds[5], $bounds[7]);

        if (imagettftext($image, $size, 0, $x, $top - $minimumY, $color, $font, $text) === false) {
            throw new RuntimeException('Teks laporan tidak dapat digambar dengan font TrueType.');
        }
    }

    /** @return array{regular:string,bold:string} */
    private function fontPaths(): array
    {
        return [
            'regular' => resource_path('fonts/NotoSans-Regular.ttf'),
            'bold' => resource_path('fonts/NotoSans-Bold.ttf'),
        ];
    }

    /** @return array<string, int> */
    private function palette(\GdImage $image): array
    {
        return [
            'white' => imagecolorallocate($image, 255, 255, 255),
            'ink' => imagecolorallocate($image, 30, 41, 59),
            'muted' => imagecolorallocate($image, 71, 85, 105),
            'green' => imagecolorallocate($image, 6, 95, 70),
            'red' => imagecolorallocate($image, 185, 28, 28),
            'amber' => imagecolorallocate($image, 146, 64, 14),
            'soft_red' => imagecolorallocate($image, 254, 242, 242),
            'soft_green' => imagecolorallocate($image, 236, 253, 245),
            'soft_amber' => imagecolorallocate($image, 255, 251, 235),
            'row_alt' => imagecolorallocate($image, 248, 250, 252),
            'line' => imagecolorallocate($image, 226, 232, 240),
        ];
    }
}
