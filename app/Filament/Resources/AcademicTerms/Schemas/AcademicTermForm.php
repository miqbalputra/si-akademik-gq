<?php

namespace App\Filament\Resources\AcademicTerms\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class AcademicTermForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Hidden::make('kkm_workflow_enabled')->default(true),
                Select::make('academic_year_id')->label('Tahun Ajaran')
                    ->relationship('academicYear', 'name')
                    ->required(),
                TextInput::make('name')->label('Nama')
                    ->required(),
                TextInput::make('semester')->label('Semester')
                    ->required(),
                DatePicker::make('starts_at')->label('Dimulai Pada'),
                DatePicker::make('ends_at')->label('Selesai Pada'),
                DatePicker::make('report_issue_date')->label('Tanggal Rapor')->helperText('Ditetapkan admin untuk rapor baru pada periode ini. Rapor lama/terkunci tetap memakai tanggal tersimpan.'),
                Toggle::make('is_active')->label('Status Aktif')
                    ->required(),
            ]);
    }
}
