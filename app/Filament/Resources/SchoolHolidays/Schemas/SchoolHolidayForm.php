<?php

namespace App\Filament\Resources\SchoolHolidays\Schemas;

use App\Models\School;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SchoolHolidayForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
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
                DatePicker::make('holiday_date')
                    ->label('Tanggal Libur')
                    ->native(false)
                    ->displayFormat('d F Y')
                    ->default(fn () => request()->query('holiday_date'))
                    ->required(),
                TextInput::make('title')
                    ->label('Keterangan Libur')
                    ->placeholder('Contoh: Libur Idul Adha / Agenda Pesantren')
                    ->required()
                    ->maxLength(255),
                Textarea::make('description')
                    ->label('Catatan Tambahan')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }
}
