<?php

namespace App\Filament\Resources\DiniyyahAssessmentSets\Tables;

use App\Services\DiniyyahAssessmentComponentBuilder;
use App\Services\DiniyyahAssessmentWorkflow;
use App\Services\DiniyyahScoreCalculator;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class DiniyyahAssessmentSetsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('classSubject.classroomTerm.name')->label('Kelas')->searchable(),
                TextColumn::make('classSubject.subject.name')->label('Mapel')->searchable(),
                    ->label('Mata Pelajaran')
                TextColumn::make('title')->searchable()->sortable(),
                    ->label('Judul')
                TextColumn::make('assessment_method')->badge(),
                    ->label('Metode Penilaian')
                TextColumn::make('kkm')->numeric(),
                    ->label('KKM')
                TextColumn::make('status')->badge(),
                    ->label('Status')
                IconColumn::make('appears_on_report')->boolean(),
                    ->label('Tampil di Rapor')
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                Action::make('generate_components')
                    ->label('Generate Komponen')
                    ->authorize(fn (): bool => self::canManageDiniyyah())
                    ->requiresConfirmation()
                    ->action(function ($record): void {
                        app(DiniyyahAssessmentComponentBuilder::class)->createDefaults($record);

                        Notification::make()
                            ->title('Komponen default dibuat')
                            ->success()
                            ->send();
                    }),
                Action::make('recalculate_scores')
                    ->label('Hitung Ulang')
                    ->authorize(fn (): bool => self::canManageDiniyyah())
                    ->requiresConfirmation()
                    ->action(function ($record): void {
                        $count = app(DiniyyahScoreCalculator::class)->calculateAssessmentSet($record);

                        Notification::make()
                            ->title('Nilai berhasil dihitung ulang')
                            ->body("{$count} santri diproses.")
                            ->success()
                            ->send();
                    }),
                Action::make('submit_scores')
                    ->label('Submit')
                    ->authorize(fn (): bool => self::canManageDiniyyah())
                    ->requiresConfirmation()
                    ->visible(fn ($record): bool => self::canManageDiniyyah() && in_array($record->status, ['draft', 'active', 'needs_revision'], true))
                    ->action(function ($record): void {
                        app(DiniyyahAssessmentWorkflow::class)->submit($record);

                        Notification::make()
                            ->title('Nilai berhasil disubmit')
                            ->success()
                            ->send();
                    }),
                Action::make('approve_scores')
                    ->label('Validasi')
                    ->authorize(fn (): bool => self::canManageDiniyyah())
                    ->requiresConfirmation()
                    ->visible(fn ($record): bool => self::canManageDiniyyah() && $record->status === 'submitted')
                    ->action(function ($record): void {
                        app(DiniyyahAssessmentWorkflow::class)->approve($record, auth()->user());

                        Notification::make()
                            ->title('Nilai berhasil divalidasi')
                            ->success()
                            ->send();
                    }),
                Action::make('request_revision')
                    ->label('Perlu Revisi')
                    ->authorize(fn (): bool => self::canManageDiniyyah())
                    ->requiresConfirmation()
                    ->visible(fn ($record): bool => self::canManageDiniyyah() && in_array($record->status, ['submitted', 'validated'], true))
                    ->action(function ($record): void {
                        app(DiniyyahAssessmentWorkflow::class)->requestRevision($record, auth()->user());

                        Notification::make()
                            ->title('Nilai dikembalikan untuk revisi')
                            ->warning()
                            ->send();
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    private static function canManageDiniyyah(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'kabag_diniyyah']) ?? false;
    }
}
