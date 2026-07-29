<?php

namespace App\Filament\Resources\TahfidzHalaqahs\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class TahfidzHalaqahForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('academic_term_id')->label('Periode Akademik')
                    ->relationship('academicTerm', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('name')
                    ->label('Nama Halaqah')
                    ->required(),
                Select::make('teacher_id')
                    ->relationship('teacher', 'name', fn (\Illuminate\Database\Eloquent\Builder $q) =>
                        $q->whereNotNull('user_id')->orderBy('name'))
                    ->getOptionLabelFromRecordUsing(function (\App\Models\Teacher $record) {
                        $email = $record->user?->email ?? '(tanpa akun)';

                        return "{$record->name} — {$email}";
                    })
                    ->searchable()
                    ->preload()
                    ->label('Guru Pengampu'),
                Select::make('assistant_teacher_id')
                    ->relationship('assistantTeacher', 'name', fn (\Illuminate\Database\Eloquent\Builder $q) =>
                        $q->whereNotNull('user_id')->orderBy('name'))
                    ->getOptionLabelFromRecordUsing(function (\App\Models\Teacher $record) {
                        $email = $record->user?->email ?? '(tanpa akun)';

                        return "{$record->name} — {$email}";
                    })
                    ->searchable()
                    ->preload()
                    ->label('Asisten Guru'),
                Select::make('status')->label('Status')
                    ->options([
                        'active' => 'Aktif',
                        'closed' => 'Ditutup',
                    ])
                    ->required()
                    ->default('active'),
                Textarea::make('notes')
                    ->label('Catatan'),
            ]);
    }
}