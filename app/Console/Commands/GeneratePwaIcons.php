<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class GeneratePwaIcons extends Command
{
    protected $signature = 'pwa:generate-icons';

    protected $description = 'Generate PWA PNG icons from scratch (requires GD extension)';

    public function handle(): int
    {
        if (! extension_loaded('gd')) {
            $this->error('PHP GD extension is required to generate PNG icons.');
            $this->info('Install GD and run: php artisan pwa:generate-icons');
            $this->info('SVG fallback icons are already available in public/icons/.');

            return self::FAILURE;
        }

        $iconDir = public_path('icons');
        if (! is_dir($iconDir)) {
            mkdir($iconDir, 0755, true);
        }

        foreach ([192, 512] as $size) {
            foreach (["icon-{$size}.png", "icon-{$size}-maskable.png"] as $filename) {
                $path = "{$iconDir}/{$filename}";
                $img = imagecreatetruecolor($size, $size);

                // Full-bleed bright gradient keeps the mark safe inside maskable icons.
                for ($row = 0; $row < $size; $row++) {
                    $ratio = $row / max(1, $size - 1);
                    $red = (int) round(37 * (1 - $ratio) + 8 * $ratio);
                    $green = (int) round(237 * (1 - $ratio) + 116 * $ratio);
                    $blue = (int) round(130 * (1 - $ratio) + 66 * $ratio);
                    $color = imagecolorallocate($img, $red, $green, $blue);
                    imageline($img, 0, $row, $size, $row, $color);
                }

                // Subtle highlights add depth while preserving the safe central area.
                $glow = imagecolorallocatealpha($img, 239, 255, 245, 88);
                imagefilledellipse($img, (int) ($size * .18), (int) ($size * .1), (int) ($size * 1.15), (int) ($size * 1.15), $glow);
                $shadow = imagecolorallocatealpha($img, 0, 79, 45, 104);
                imagefilledellipse($img, (int) ($size * .94), (int) ($size * .96), (int) ($size * .7), (int) ($size * .7), $shadow);

                $neon = imagecolorallocate($img, 255, 255, 255);
                $accent = imagecolorallocate($img, 216, 255, 231);
                $fontPath = $this->fontPath();
                $fontSize = (int) ($size * 0.35);
                $text = 'GQ';
                $textBox = $fontPath !== '' ? imagettfbbox($fontSize, 0, $fontPath, $text) : false;
                if ($textBox === false) {
                    // Fallback to built-in bitmap font if no TTF is available.
                    $builtInSize = (int) ($size / 192 * 5);
                    $textWidth = strlen($text) * $builtInSize * imagefontwidth($builtInSize);
                    $textHeight = imagefontheight($builtInSize);
                    $x = (int) (($size - $textWidth) / 2);
                    $y = (int) (($size - $textHeight) / 2);
                    imagestring($img, $builtInSize, $x, $y, $text, $neon);
                } else {
                    $textWidth = $textBox[2] - $textBox[0];
                    $textHeight = $textBox[1] - $textBox[7];
                    $x = (int) (($size - $textWidth) / 2 - $textBox[0]);
                    $y = (int) (($size - $textHeight) / 2 - $textBox[7] - ($size * .015));
                    imagettftext($img, $fontSize, 0, $x, $y, $neon, $fontPath, $text);
                }

                $barWidth = (int) ($size * .18);
                $barHeight = max(2, (int) ($size * .018));
                imagefilledrectangle($img, (int) (($size - $barWidth) / 2), (int) ($size * .74), (int) (($size + $barWidth) / 2), (int) ($size * .74 + $barHeight), $accent);

                imagepng($img, $path);
                imagedestroy($img);
                $this->info("Generated: {$path}");
            }
        }

        $this->info('PWA icons generated successfully!');

        return self::SUCCESS;
    }

    private function fontPath(): string
    {
        // Try common font paths
        $candidates = [
            public_path('fonts/filament/filament/inter/Inter.ttf'),
            resource_path('fonts/Inter.ttf'),
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            'C:\Windows\Fonts\arialbd.ttf',
        ];

        foreach ($candidates as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        return '';
    }
}
