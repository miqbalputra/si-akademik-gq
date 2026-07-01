<?php

namespace App\Filament\Resources\ReportCards\Tables;

use App\Services\ReportCardBulkWorkflow;
use App\Services\ReportCardWorkflow;
use DomainException;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class ReportCardsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('student.name')->label('Santri')->searchable()->sortable(),
                TextColumn::make('classroomTerm.name')->label('Kelas')->searchable(),
                TextColumn::make('academicTerm.name')->label('Periode')->searchable(),
                    ->label('Periode Akademik')
                TextColumn::make('status')->badge(),
                    ->label('Status')
                TextColumn::make('total_score')->numeric(),
                    ->label('Total Nilai')
                TextColumn::make('average_score')->numeric(),
                    ->label('Nilai Rata-rata')
                TextColumn::make('rank_in_class')->numeric(),
                    ->label('Peringkat Kelas')
            ])
            ->filters([
                //
            ])
            ->recordActions([
                Action::make('preview')
                    ->label('Preview')
                    ->url(fn ($record): string => route('report-cards.show', $record))
                    ->openUrlInNewTab(),
                Action::make('lock')
                    ->label('Lock')
                    ->authorize(fn (): bool => self::canManageReportCards())
                    ->requiresConfirmation()
                    ->visible(fn ($record): bool => self::canManageReportCards() && $record->status === 'draft')
                    ->action(function ($record): void {
                        try {
                            app(ReportCardWorkflow::class)->lock($record, auth()->user());

                            Notification::make()->title('Rapor berhasil dikunci')->success()->send();
                        } catch (DomainException $exception) {
                            Notification::make()->title($exception->getMessage())->danger()->send();
                        }
                    }),
                Action::make('publish')
                    ->label('Publish')
                    ->authorize(fn (): bool => self::canManageReportCards())
                    ->requiresConfirmation()
                    ->visible(fn ($record): bool => self::canManageReportCards() && $record->status === 'locked')
                    ->action(function ($record): void {
                        try {
                            app(ReportCardWorkflow::class)->publish($record, auth()->user());
                            Notification::make()->title('Rapor berhasil dipublish')->success()->send();
                        } catch (DomainException $exception) {
                            Notification::make()->title($exception->getMessage())->danger()->send();
                        }
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('lockSelected')
                        ->label('Lock Terpilih')
                        ->authorize(fn (): bool => self::canManageReportCards())
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records): void {
                            $result = app(ReportCardBulkWorkflow::class)->lockMany($records, auth()->user());

                            Notification::make()
                                ->title("{$result['locked']} rapor dikunci, {$result['skipped']} dilewati")
                                ->success()
                                ->send();
                        }),
                    BulkAction::make('publishSelected')
                        ->label('Publish Terpilih')
                        ->authorize(fn (): bool => self::canManageReportCards())
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records): void {
                            $result = app(ReportCardBulkWorkflow::class)->publishMany($records, auth()->user());

                            Notification::make()
                                ->title("{$result['published']} rapor dipublish, {$result['skipped']} dilewati")
                                ->success()
                                ->send();
                        }),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    private static function canManageReportCards(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'kabag_diniyyah']) ?? false;
    }
}
