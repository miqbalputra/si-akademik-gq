<?php

namespace App\Filament\Resources\DiniyyahClassJournals\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class DiniyyahClassJournalInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('diniyyah_teacher_assignment_id')
                    ->numeric(),
                TextEntry::make('date')
                    ->date(),
                TextEntry::make('session_hour'),
                TextEntry::make('material')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('status')->label('Status Validasi')->badge(),
                TextEntry::make('validated_at')->label('Divalidasi Pada')->dateTime()->placeholder('-'),
                TextEntry::make('validationHistory')
                    ->label('Riwayat Validasi')
                    ->state(fn ($record): string => $record->validationLogs
                        ->map(fn ($log): string => ($log->action === 'validated' ? 'Divalidasi' : 'Validasi dibatalkan').
                            ' oleh '.($log->performer?->name ?? 'akun yang sudah dihapus').
                            ' pada '.$log->performed_at?->timezone('Asia/Jakarta')->format('d-m-Y H:i').
                            ($log->reason ? ' — '.$log->reason : ''))
                        ->implode("\n"))
                    ->placeholder('Belum ada perubahan status')
                    ->columnSpanFull(),
                TextEntry::make('jp_count')
                    ->numeric(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
