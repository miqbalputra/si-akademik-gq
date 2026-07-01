<?php

namespace App\Filament\Resources\SchoolEvents\Schemas;

use App\Models\Classroom;
use App\Models\ClassroomTerm;
use App\Models\School;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Illuminate\Database\Eloquent\Builder;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class SchoolEventForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(2)->schema([
                Select::make('school_id')
                    ->relationship('school', 'name')
                    ->label('Sekolah')
                    ->searchable()
                    ->preload()
                    ->default(fn () => request()->integer('school_id') ?: School::query()->value('id'))
                    ->required(),
                Select::make('academic_term_id')
                    ->relationship('academicTerm', 'name')
                    ->label('Periode Ajaran')
                    ->getOptionLabelFromRecordUsing(fn ($record) => trim(sprintf(
                        '%s - %s',
                        $record->academicYear?->name ?? '-',
                        $record->name
                    )))
                    ->searchable()
                    ->preload()
                    ->default(fn () => request()->integer('academic_term_id'))
                    ->required(),
                TextInput::make('title')
                    ->label('Nama Event')
                    ->required()
                    ->maxLength(255),
                Select::make('event_type')
                    ->label('Jenis Event')
                    ->options([
                        'general' => 'Agenda Sekolah',
                        'outdoor' => 'Outdoor',
                        'exam' => 'Ujian',
                        'meeting' => 'Pertemuan',
                        'religious' => 'Agenda Diniyyah',
                    ])
                    ->default('general')
                    ->required(),
                DatePicker::make('starts_on')
                    ->label('Mulai')
                    ->native(false)
                    ->displayFormat('d F Y')
                    ->default(fn () => request()->query('starts_on') ?: request()->query('date'))
                    ->required(),
                DatePicker::make('ends_on')
                    ->label('Selesai')
                    ->native(false)
                    ->displayFormat('d F Y')
                    ->default(fn () => request()->query('ends_on') ?: request()->query('date'))
                    ->required(),
                Select::make('target_scope')
                    ->label('Mode Target Event')
                    ->options([
                        'all' => 'Semua Sekolah',
                        'classes' => 'Kelas Tertentu',
                        'level' => 'Jenjang Tertentu',
                        'gender' => 'Kelompok Gender',
                        'level_gender' => 'Jenjang + Gender',
                    ])
                    ->default('all')
                    ->native(false)
                    ->live()
                    ->required(),
                TextInput::make('location')
                    ->label('Lokasi'),
                Toggle::make('show_to_teachers')
                    ->label('Tampilkan ke Guru')
                    ->default(true)
                    ->required(),
                Toggle::make('show_to_guardians')
                    ->label('Tampilkan ke Wali Santri')
                    ->default(true)
                    ->required(),
            ]),
            Select::make('targetClassroomTerms')
                ->label('Target Kelas / Jenjang')
                ->relationship(
                    name: 'targetClassroomTerms',
                    titleAttribute: 'name',
                    modifyQueryUsing: function (Builder $query): Builder {
                        return $query
                            ->with('classroom')
                            ->orderBy('name');
                    },
                )
                ->getOptionLabelFromRecordUsing(function (ClassroomTerm $record): string {
                    $levelLabel = $record->classroom?->level_name ? ' - '.$record->classroom->level_name : '';
                    $genderLabel = match ($record->classroom?->gender_group) {
                        'male' => 'Ikhwan',
                        'female' => 'Akhwat',
                        'mixed' => 'Campuran',
                        default => null,
                    };

                    return trim($record->name.$levelLabel.($genderLabel ? ' - '.$genderLabel : ''));
                })
                ->multiple()
                ->preload()
                ->searchable()
                ->visible(fn (Get $get): bool => $get('target_scope') === 'classes')
                ->helperText('Pilih satu atau beberapa kelas jika event hanya berlaku untuk kelas tertentu.')
                ->columnSpanFull(),
            Select::make('target_level_name')
                ->label('Target Jenjang')
                ->options(fn () => Classroom::query()
                    ->whereNotNull('level_name')
                    ->orderBy('level_name')
                    ->pluck('level_name', 'level_name')
                    ->all())
                ->searchable()
                ->preload()
                ->visible(fn (Get $get): bool => in_array($get('target_scope'), ['level', 'level_gender'], true))
                ->required(fn (Get $get): bool => in_array($get('target_scope'), ['level', 'level_gender'], true))
                ->helperText('Pilih jenjang seperti Mustawa 1, Mustawa 2, atau Mustawa 3.')
                ->columnSpanFull(),
            Select::make('target_gender_group')
                ->label('Target Kelompok Gender')
                ->options([
                    'male' => 'Ikhwan',
                    'female' => 'Akhwat',
                    'mixed' => 'Campuran',
                ])
                ->native(false)
                ->visible(fn (Get $get): bool => in_array($get('target_scope'), ['gender', 'level_gender'], true))
                ->required(fn (Get $get): bool => in_array($get('target_scope'), ['gender', 'level_gender'], true))
                ->helperText('Pilih jika event hanya untuk kelompok ikhwan, akhwat, atau kelas campuran.')
                ->columnSpanFull(),
            Textarea::make('description')
                ->label('Deskripsi Event')
                ->rows(4)
                ->columnSpanFull(),
        ]);
    }
}
