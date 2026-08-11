<?php

namespace App\Filament\Resources\TasmiRecords\Tables;

use App\Models\TasmiRecord;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TasmiRecordsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('exam_date')->label('Tanggal')->date('d M Y')->sortable(),
                TextColumn::make('hijri_date')->label('Hijriyah')->toggleable(),
                TextColumn::make('student.name')->label('Santri')->searchable()->sortable(),
                TextColumn::make('student.nis')->label('NIS')->searchable()->toggleable(),
                TextColumn::make('classroomTerm.classroom.name')->label('Kelas')->sortable(),
                TextColumn::make('examinerTeacher.name')->label('Penguji')->searchable()->sortable(),
                TextColumn::make('exam_type')
                    ->label('Tipe')
                    ->badge()
                    ->formatStateUsing(fn ($state) => TasmiRecord::examTypeOptions()[$state] ?? $state),
                TextColumn::make('juz_range_label')->label('Juz')->toggleable(),
                TextColumn::make('juz_start')->label('Juz Awal')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('juz_end')->label('Juz Akhir')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('predicate')
                    ->label('Predikat')
                    ->badge()
                    ->formatStateUsing(fn ($state) => TasmiRecord::predicateLabel($state))
                    ->color(fn ($state) => match ($state) {
                        TasmiRecord::PREDICATE_MUMTAZ => 'purple',
                        TasmiRecord::PREDICATE_JAYYID_JIDDAN => 'success',
                        TasmiRecord::PREDICATE_JAYYID => 'info',
                        TasmiRecord::PREDICATE_MAQBUL => 'gray',
                        default => 'gray',
                    }),
                TextColumn::make('academicTerm.name')->label('Periode')->toggleable(),
                TextColumn::make('updated_at')->label('Diperbarui')->dateTime('d M Y H:i')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('exam_type')
                    ->label('Tipe Ujian')
                    ->options(TasmiRecord::examTypeOptions()),
                SelectFilter::make('predicate')
                    ->label('Predikat')
                    ->options(TasmiRecord::predicateOptions()),
                SelectFilter::make('academic_term_id')
                    ->label('Periode')
                    ->relationship('academicTerm', 'name'),
                SelectFilter::make('examiner_teacher_id')
                    ->label('Penguji')
                    ->relationship('examinerTeacher', 'name'),
                SelectFilter::make('classroom_term_id')
                    ->label('Kelas')
                    ->relationship('classroomTerm', 'name'),
                Filter::make('exam_date')
                    ->label('Rentang Tanggal')
                    ->form([
                        'from' => \Filament\Forms\Components\DatePicker::make('from')->label('Dari'),
                        'until' => \Filament\Forms\Components\DatePicker::make('until')->label('Sampai'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn ($q, $v) => $q->where('exam_date', '>=', $v))
                            ->when($data['until'] ?? null, fn ($q, $v) => $q->where('exam_date', '<=', $v));
                    }),
            ])
            ->defaultSort('exam_date', 'desc');
    }
}