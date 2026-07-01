<?php

namespace App\Filament\Resources\SchoolEvents\Tables;

use App\Filament\Resources\SchoolEvents\SchoolEventResource;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SchoolEventsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('starts_on')
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with('targetClassroomTerms.classroom')
                ->withCount('responses')
                ->withCount([
                    'responses as attending_count' => fn (Builder $query) => $query->where('attendance_status', 'attending'),
                    'responses as permission_count' => fn (Builder $query) => $query->where('attendance_status', 'permission'),
                    'responses as not_attending_count' => fn (Builder $query) => $query->where('attendance_status', 'not_attending'),
                ]))
            ->columns([
                TextColumn::make('title')
                    ->label('Event')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('event_type')
                    ->label('Jenis')
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'outdoor' => 'Outdoor',
                        'exam' => 'Ujian',
                        'meeting' => 'Pertemuan',
                        'religious' => 'Agenda Diniyyah',
                        default => 'Agenda Sekolah',
                    }),
                TextColumn::make('target_scope')
                    ->label('Mode Target')
                    ->formatStateUsing(fn ($state, $record) => $record->targetScopeLabel())
                    ->toggleable(),
                TextColumn::make('academicTerm.name')
                    ->label('Periode')
                    ->formatStateUsing(fn ($state, $record) => trim(sprintf(
                        '%s - %s',
                        $record->academicTerm?->academicYear?->name ?? '-',
                        $state
                    )))
                    ->wrap(),
                TextColumn::make('target_summary')
                    ->label('Target')
                    ->state(fn ($record) => $record->targetSummary())
                    ->wrap(),
                TextColumn::make('responses_count')
                    ->label('Respon')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('attending_count')
                    ->label('Hadir')
                    ->badge()
                    ->color('success'),
                TextColumn::make('permission_count')
                    ->label('Izin')
                    ->badge()
                    ->color('warning'),
                TextColumn::make('not_attending_count')
                    ->label('Tidak Hadir')
                    ->badge()
                    ->color('danger'),
                TextColumn::make('starts_on')
                    ->label('Mulai')
                    ->date('d F Y'),
                TextColumn::make('ends_on')
                    ->label('Selesai')
                    ->date('d F Y'),
                TextColumn::make('location')
                    ->label('Lokasi')
                    ->toggleable(),
                IconColumn::make('show_to_teachers')
                    ->label('Guru')
                    ->boolean(),
                IconColumn::make('show_to_guardians')
                    ->label('Wali')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('academic_term_id')
                    ->label('Periode')
                    ->relationship('academicTerm', 'name'),
            ])
            ->recordActions([
                Action::make('recap')
                    ->label('Rekap')
                    ->icon('heroicon-o-chart-bar-square')
                    ->url(fn ($record): string => SchoolEventResource::getUrl('recap', ['record' => $record])),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
