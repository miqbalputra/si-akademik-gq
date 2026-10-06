<?php

namespace App\Filament\Resources\ReportCards\Tables;

use App\Services\ReportCardBulkWorkflow;
use App\Services\ReportCardWorkflow;
use Filament\Forms\Components\Textarea;
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
                TextColumn::make('status')->label('Status')->badge()
                    ->formatStateUsing(fn ($state): string => \App\Support\UiLabel::statusLabel($state))
                    ->color(fn ($state): string => \App\Support\UiLabel::statusColor($state)),
                TextColumn::make('total_score')->label('Total Nilai')->numeric(),
                TextColumn::make('average_score')->label('Nilai Rata-rata')->numeric(),
                TextColumn::make('rank_in_class')->label('Peringkat Kelas')->numeric(),
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
                Action::make('revise')
                    ->label('Buka revisi')
                    ->color('warning')
                    ->authorize(fn (): bool => self::canManageReportCards())
                    ->visible(fn ($record): bool => self::canManageReportCards() && $record->status === 'published')
                    ->requiresConfirmation()
                    ->form([
                        Textarea::make('reason')->label('Alasan revisi')->required()->minLength(10)->maxLength(1000),
                    ])
                    ->action(function ($record, array $data): void {
                        try {
                            app(ReportCardWorkflow::class)->openRevision($record, auth()->user(), $data['reason']);
                            Notification::make()->title('Revisi dibuka dan tercatat')->success()->send();
                        } catch (DomainException $exception) {
                            Notification::make()->title($exception->getMessage())->danger()->send();
                        }
                    }),
                Action::make('revisionHistory')
                    ->label('Riwayat revisi')
                    ->icon('heroicon-o-clock')
                    ->visible(fn ($record): bool => $record->revisionLogs()->exists())
                    ->modalHeading('Riwayat revisi rapor')
                    ->modalContent(fn ($record) => view('filament.resources.report-cards.revision-history', [
                        'logs' => $record->revisionLogs()->with('performer')->get(),
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup'),
                EditAction::make()->visible(fn ($record): bool => $record->status === 'draft'),
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
                    DeleteBulkAction::make()->authorizeIndividualRecords('delete'),
                ]),
            ]);
    }

    private static function canManageReportCards(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'kabag_diniyyah']) ?? false;
    }
}
