<?php

namespace App\Filament\Resources\ReportCards\RelationManagers;

use App\Models\Teacher;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SignaturesRelationManager extends RelationManager
{
    protected static string $relationship = 'signatures';

    protected static ?string $title = 'Tanda Tangan Rapor';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('role_label')
                    ->label('Jabatan')
                    ->options([
                        'Wali Kelas' => 'Wali Kelas',
                        'Orang Tua/Wali Santri' => 'Orang Tua/Wali Santri',
                        'Kepala Kelompok Tahfidz' => 'Kepala Kelompok Tahfidz',
                        'Kepala Bagian Diniyyah' => 'Kepala Bagian Diniyyah',
                        'Kepala Sekolah' => 'Kepala Sekolah',
                    ])
                    ->required(),
                TextInput::make('person_name')
                    ->label('Nama Penanda Tangan')
                    ->required(),
                Select::make('teacher_id')
                    ->label('Guru Terhubung')
                    ->relationship('teacher', 'name')
                    ->searchable()
                    ->preload()
                    ->helperText('Opsional: hubungkan ke data guru untuk auto-fill nama.'),
                TextInput::make('sort_order')
                    ->label('Urutan')
                    ->numeric()
                    ->default(0)
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('sort_order')
                    ->label('Urutan')
                    ->width('80px'),
                TextColumn::make('role_label')
                    ->label('Jabatan')
                    ->searchable(),
                TextColumn::make('person_name')
                    ->label('Nama')
                    ->searchable(),
                TextColumn::make('teacher.name')
                    ->label('Guru Terhubung')
                    ->toggleable()
                    ->placeholder('-'),
            ])
            ->headerActions([
                \Filament\Actions\CreateAction::make(),
            ])
            ->recordActions([
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make(),
            ]);
    }
}