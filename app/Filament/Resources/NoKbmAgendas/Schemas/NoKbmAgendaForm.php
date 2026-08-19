<?php

namespace App\Filament\Resources\NoKbmAgendas\Schemas;

use App\Models\ClassroomTerm;
use App\Models\School;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class NoKbmAgendaForm
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
                    ->live()
                    ->default(fn () => request()->integer('academic_term_id'))
                    ->required(),
                TextInput::make('title')
                    ->label('Nama Agenda')
                    ->placeholder('Contoh: Outdoor')
                    ->required()
                    ->maxLength(255),
                Select::make('event_type')
                    ->label('Jenis Kegiatan')
                    ->options([
                        'general' => 'Agenda Sekolah',
                        'outdoor' => 'Outdoor',
                        'meeting' => 'Pertemuan',
                        'religious' => 'Agenda Diniyyah',
                        'exam' => 'Ujian',
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
                    ->afterOrEqual('starts_on')
                    ->default(fn () => request()->query('ends_on') ?: request()->query('date'))
                    ->required(),
                Select::make('target_scope')
                    ->label('Berlaku Untuk')
                    ->options([
                        'all' => 'Semua kelas',
                        'gender' => 'Kelompok gender',
                        'classes' => 'Kelas tertentu',
                    ])
                    ->default('all')
                    ->live()
                    ->required(),
                TextInput::make('location')->label('Lokasi'),
                Toggle::make('show_to_teachers')->label('Tampilkan ke Guru')->default(true)->required(),
                Toggle::make('show_to_guardians')->label('Tampilkan ke Wali Santri')->default(true)->required(),
            ]),
            Select::make('target_gender_group')
                ->label('Kelompok Gender')
                ->options(['male' => 'Ikhwan / Putra', 'female' => 'Akhwat / Putri'])
                ->visible(fn (Get $get): bool => $get('target_scope') === 'gender')
                ->required(fn (Get $get): bool => $get('target_scope') === 'gender'),
            CheckboxList::make('targetClassroomTerms')
                ->label('Kelas yang Dibebaskan')
                ->relationship('targetClassroomTerms', 'name')
                ->options(function (Get $get): array {
                    $academicTermId = (int) $get('academic_term_id');
                    if ($academicTermId <= 0) {
                        return [];
                    }

                    return ClassroomTerm::query()
                    ->with('classroom')
                    ->where('academic_term_id', $academicTermId)
                    ->orderBy('name')
                    ->get()
                    ->mapWithKeys(function (ClassroomTerm $term): array {
                        $gender = match ($term->classroom?->gender_group) {
                            'male' => 'Ikhwan',
                            'female' => 'Akhwat',
                            'mixed' => 'Campuran',
                            default => null,
                        };

                        return [$term->id => trim($term->name.($gender ? ' - '.$gender : ''))];
                    })
                    ->all();
                })
                ->columns(2)
                ->visible(fn (Get $get): bool => $get('target_scope') === 'classes')
                ->required(fn (Get $get): bool => $get('target_scope') === 'classes')
                ->helperText('Checklist hanya menampilkan kelas pada periode yang dipilih.')
                ->columnSpanFull(),
            Textarea::make('description')
                ->label('Deskripsi / Catatan')
                ->rows(4)
                ->columnSpanFull(),
        ]);
    }
}
