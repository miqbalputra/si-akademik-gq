<?php

namespace App\Filament\Resources\TahfidzHalaqahs\RelationManagers;

use App\Models\Student;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Actions\AttachAction;
use Filament\Actions\CreateAction;
use Filament\Actions\DetachAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MembersRelationManager extends RelationManager
{
    protected static string $relationship = 'members';

    protected static ?string $title = 'Santri Halaqah';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('student_id')
                    ->label('Santri')
                    ->relationship('student', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('class_enrollment_id')
                    ->label('Penempatan Kelas')
                    ->relationship('classEnrollment', 'id')
                    ->searchable()
                    ->preload()
                    ->helperText('Opsional: hubungkan ke penempatan kelas periode ini.'),
                Select::make('status')
                    ->label('Status')
                    ->options([
                        'active' => 'Aktif',
                        'moved' => 'Pindah',
                        'inactive' => 'Tidak Aktif',
                    ])
                    ->required()
                    ->default('active'),
                TextInput::make('sort_order')
                    ->label('Urutan')
                    ->numeric()
                    ->default(0),
                TextInput::make('joined_at')
                    ->label('Tanggal Bergabung')
                    ->type('date'),
                TextInput::make('left_at')
                    ->label('Tanggal Keluar')
                    ->type('date')
                    ->helperText('Isi jika santri pindah halaqah.'),
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
                TextColumn::make('student.name')
                    ->label('Nama Santri')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('student.nis')
                    ->label('NIS'),
                TextColumn::make('student.gender')
                    ->label('L/P')
                    ->formatStateUsing(fn ($state) => $state === 'male' ? 'L' : 'P')
                    ->width('50px'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'active' => 'success',
                        'moved' => 'warning',
                        'inactive' => 'gray',
                        default => 'gray',
                    }),
                TextColumn::make('joined_at')
                    ->date('d M Y')
                    ->label('Bergabung')
                    ->toggleable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Tambah Santri'),
            ])
            ->recordActions([
                EditAction::make(),
                DetachAction::make()
                    ->label('Keluarkan'),
            ]);
    }
}