<?php

namespace App\Filament\Pages;

use App\Services\NotificationDispatcher;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class RppBroadcast extends Page
{
    protected static string|UnitEnum|null $navigationGroup = 'Kurikulum & RPP';
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedPaperAirplane;
    protected static ?string $navigationLabel = 'Pesan RPP';
    protected static ?int $navigationSort = 40;
    protected string $view = 'filament.pages.rpp-broadcast';

    public string $audience = 'guru';
    public string $broadcastTitle = '';
    public string $body = '';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'kabag_diniyyah']) ?? false;
    }

    public function send(NotificationDispatcher $dispatcher): void
    {
        $data = $this->validate([
            'audience' => ['required', 'in:guru,admin,kabag_diniyyah,kepala_sekolah'],
            'broadcastTitle' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:2000'],
        ]);
        $dispatcher->dispatchToRole($data['audience'], $data['broadcastTitle'], $data['body'], 'rpp_broadcast', route('filament.admin.resources.rpps.index'), 'info');
        $this->reset(['broadcastTitle', 'body']);
        Notification::make()->title('Pesan RPP dikirim')->success()->send();
    }
}
