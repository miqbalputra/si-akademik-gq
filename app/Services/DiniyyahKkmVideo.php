<?php

namespace App\Services;

use App\Models\DiniyyahKkmSetting;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class DiniyyahKkmVideo
{
    public function url(): ?string
    {
        return $this->normalize(DiniyyahKkmSetting::find(1)?->youtube_url);
    }

    public function save(mixed $url, User $actor): void
    {
        abort_unless($actor->hasRole('admin'), 403);
        $validated = Validator::make(['youtube_url' => $url], [
            'youtube_url' => ['bail', 'nullable', 'string', 'max:2048', function (string $attribute, mixed $value, Closure $fail): void {
                if ($this->normalize($value) === null) {
                    $fail('Masukkan link video YouTube yang valid, misalnya https://www.youtube.com/watch?v=VIDEO_ID.');
                }
            }],
        ], [
            'youtube_url.string' => 'Link video YouTube harus berupa teks.',
            'youtube_url.max' => 'Link video YouTube maksimal 2048 karakter.',
        ])->validate();
        $normalized = $this->normalize($validated['youtube_url'] ?? null);
        DB::transaction(function () use ($normalized, $actor): void {
            $setting = DiniyyahKkmSetting::firstOrNew(['id' => 1]);
            $before = $setting->youtube_url;
            $setting->fill(['youtube_url' => $normalized])->save();
            activity('kkm')->performedOn($setting)->causedBy($actor)->event('video_updated')
                ->withProperties(['old_url' => $before, 'new_url' => $normalized])
                ->log('Admin memperbarui video panduan KKM');
        });
    }

    private function normalize(?string $url): ?string
    {
        $parts = parse_url(trim($url ?? ''));
        if (! is_array($parts) || ! in_array(strtolower($parts['scheme'] ?? ''), ['https', 'http'], true)
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])) {
            return null;
        }
        $host = strtolower($parts['host'] ?? '');
        $path = $parts['path'] ?? '';
        $id = null;
        if ($host === 'youtu.be') {
            $id = ltrim($path, '/');
        } elseif (in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'music.youtube.com'], true)) {
            if ($path === '/watch') {
                parse_str($parts['query'] ?? '', $query);
                $id = $query['v'] ?? null;
            } elseif (preg_match('~^/(?:shorts|embed|live)/([A-Za-z0-9_-]{11})/?$~D', $path, $match)) {
                $id = $match[1];
            }
        }

        return is_string($id) && preg_match('/^[A-Za-z0-9_-]{11}$/D', $id)
            ? 'https://www.youtube.com/watch?v='.$id : null;
    }
}
