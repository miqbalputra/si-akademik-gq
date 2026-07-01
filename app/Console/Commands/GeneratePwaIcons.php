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
            $path = "{$iconDir}/icon-{$size}.png";
            $img = imagecreatetruecolor($size, $size);

            // Background: amber-600 (#d97706)
            $bg = imagecolorallocate($img, 217, 119, 6);
            imagefill($img, 0, 0, $bg);

            // Draw "GQ" text
            $white = imagecolorallocate($img, 255, 255, 255);
            $fontSize = (int) ($size * 0.35);
            $text = 'GQ';
            $textBox = imagettfbbox($fontSize, 0, $this->fontPath(), $text);
            if ($textBox === false) {
                // Fallback to built-in bitmap font if no TTF available
                $builtInSize = (int) ($size / 192 * 5);
                $textWidth = strlen($text) * $builtInSize * imagefontwidth($builtInSize);
                $textHeight = imagefontheight($builtInSize);
                $x = (int) (($size - $textWidth) / 2);
                $y = (int) (($size - $textHeight) / 2);
                imagestring($img, $builtInSize, $x, $y, $text, $white);
            } else {
                $textWidth = $textBox[2] - $textBox[0];
                $textHeight = $textBox[1] - $textBox[7];
                $x = (int) (($size - $textWidth) / 2 - $textBox[0]);
                $y = (int) (($size - $textHeight) / 2 - $textBox[7]);
                imagettftext($img, $fontSize, 0, $x, $y, $white, $this->fontPath(), $text);
            }

            imagepng($img, $path);
            imagedestroy($img);
            $this->info("Generated: {$path}");
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