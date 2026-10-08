<?php

namespace App\Services;

use App\Models\SiteAppearanceSetting;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ThemePaletteService
{
    /** @var array<string, string> */
    private const DEFAULT_PALETTE = [
        'primary' => '#465fff',
        'info' => '#0ba5ec',
        'success' => '#12b76a',
        'warning' => '#f79009',
        'danger' => '#f04438',
    ];

    /** @var array<int, int> */
    private const TINTS = [
        25 => 96,
        50 => 94,
        100 => 88,
        200 => 76,
        300 => 58,
        400 => 28,
    ];

    /** @var array<int, int> */
    private const SHADES = [
        600 => 10,
        700 => 24,
        800 => 38,
        900 => 54,
        950 => 70,
    ];

    /** @var array<string, string>|null */
    private ?array $resolvedPalette = null;

    /**
     * @return array<string, string>
     */
    public function defaults(): array
    {
        return self::DEFAULT_PALETTE;
    }

    /**
     * @return array<string, string>
     */
    public function palette(): array
    {
        if ($this->resolvedPalette !== null) {
            return $this->resolvedPalette;
        }

        try {
            $stored = SiteAppearanceSetting::query()->find(1)?->palette ?? [];
        } catch (QueryException) {
            // The theme must still render during a database outage or before its migration.
            $stored = [];
        }
        $palette = [];

        foreach (self::DEFAULT_PALETTE as $name => $default) {
            $value = strtoupper((string) ($stored[$name] ?? $default));
            $palette[$name] = preg_match('/^#[0-9A-F]{6}$/', $value) ? $value : $default;
        }

        return $this->resolvedPalette = $palette;
    }

    /**
     * @param  array<string, string>  $palette
     * @return array<string, string>
     *
     * @throws ValidationException
     */
    public function save(array $palette): array
    {
        $validated = Validator::make(
            ['palette' => $palette],
            collect(self::DEFAULT_PALETTE)
                ->keys()
                ->mapWithKeys(fn (string $name): array => ["palette.{$name}" => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/']])
                ->all(),
        )->validate();

        $normalized = collect($validated['palette'])
            ->map(fn (string $color): string => strtoupper($color))
            ->all();

        SiteAppearanceSetting::query()->updateOrCreate(
            ['id' => 1],
            ['palette' => $normalized],
        );

        return $this->resolvedPalette = $normalized;
    }

    /**
     * Build safe CSS custom properties for both the TailAdmin Blade theme and Filament.
     */
    public function cssVariables(?array $palette = null): string
    {
        $palette ??= $this->palette();
        $families = [
            'primary' => ['brand', 'primary', 'indigo'],
            'info' => ['info', 'blue-light', 'blue', 'sky', 'cyan'],
            'success' => ['success', 'emerald', 'green'],
            'warning' => ['warning', 'amber', 'orange'],
            'danger' => ['danger', 'error', 'red', 'rose'],
        ];
        $properties = [];

        foreach ($families as $role => $names) {
            $shades = $this->shades($palette[$role]);

            foreach ($names as $name) {
                foreach ($shades as $shade => $hex) {
                    $properties["--color-{$name}-{$shade}"] = $hex;
                }
            }

            foreach ($shades as $shade => $hex) {
                $properties["--{$role}-{$shade}"] = $hex;
            }
        }

        $properties['--color-neon'] = $palette['primary'];
        $properties['--color-neon-ink'] = $this->contrastColor($palette['primary']);
        $properties['--color-school-50'] = $this->shades($palette['success'])[50];
        $properties['--color-school-100'] = $this->shades($palette['success'])[100];
        $properties['--color-school-600'] = $this->shades($palette['success'])[700];
        $properties['--color-school-800'] = $this->shades($palette['success'])[800];

        $declarations = collect($properties)
            ->map(fn (string $value, string $property): string => "{$property}:{$value}")
            ->implode(';');

        return ":root{{$declarations}}";
    }

    /**
     * @return array<int, string>
     */
    private function shades(string $hex): array
    {
        $rgb = $this->rgb($hex);
        $shades = [500 => $hex];

        foreach (self::TINTS as $shade => $whitePercent) {
            $shades[$shade] = $this->mix($rgb, [255, 255, 255], $whitePercent);
        }

        foreach (self::SHADES as $shade => $blackPercent) {
            $shades[$shade] = $this->mix($rgb, [0, 0, 0], $blackPercent);
        }

        ksort($shades);

        return $shades;
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private function rgb(string $hex): array
    {
        return [
            hexdec(substr($hex, 1, 2)),
            hexdec(substr($hex, 3, 2)),
            hexdec(substr($hex, 5, 2)),
        ];
    }

    /**
     * @param  array{0: int, 1: int, 2: int}  $from
     * @param  array{0: int, 1: int, 2: int}  $to
     */
    private function mix(array $from, array $to, int $percent): string
    {
        $channels = array_map(
            fn (int $start, int $end): int => (int) round(($start * (100 - $percent) + $end * $percent) / 100),
            $from,
            $to,
        );

        return sprintf('#%02X%02X%02X', ...$channels);
    }

    private function contrastColor(string $hex): string
    {
        [$red, $green, $blue] = $this->rgb($hex);
        $luminance = (0.2126 * $red + 0.7152 * $green + 0.0722 * $blue) / 255;

        return $luminance > 0.62 ? '#101828' : '#FFFFFF';
    }
}
