<?php

namespace App\Filament\Resources\DiniyyahLedgerSnapshots\Tables;

use App\Services\DiniyyahLedgerGenerator;
use App\Services\DiniyyahLedgerWorkflow;
use App\Services\ReportCardBulkWorkflow;
use App\Services\ReportCardGenerator;
use DomainException;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DiniyyahLedgerSnapshotsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->searchable()->sortable(),
                TextColumn::make('classroomTerm.name')->label('Kelas')->searchable(),
                TextColumn::make('academicTerm.name')->label('Periode')->searchable(),
                TextColumn::make('status')->badge(),
                TextColumn::make('generated_at')->dateTime()->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                Action::make('preview')
                    ->label('Preview')
                    ->url(fn ($record): string => route('diniyyah.ledger.show', $record))
                    ->openUrlInNewTab(),
                Action::make('regenerate')
                    ->label('Generate Ulang')
                    ->authorize(fn (): bool => self::canManageDiniyyah())
                    ->requiresConfirmation()
                    ->visible(fn ($record): bool => self::canManageDiniyyah() && ! in_array($record->status, ['locked', 'published'], true))
                    ->action(function ($record): void {
                        try {
                            app(DiniyyahLedgerGenerator::class)->generate($record->classroomTerm, auth()->id());

                            Notification::make()
                                ->title('Leger berhasil digenerate ulang')
                                ->success()
                                ->send();
                        } catch (DomainException $exception) {
                            Notification::make()
                                ->title($exception->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Action::make('validate')
                    ->label('Validasi')
                    ->authorize(fn (): bool => self::canManageDiniyyah())
                    ->requiresConfirmation()
                    ->visible(fn ($record): bool => self::canManageDiniyyah() && $record->status === 'draft')
                    ->action(function ($record): void {
                        try {
                            app(DiniyyahLedgerWorkflow::class)->validate($record, auth()->user());

                            Notification::make()
                                ->title('Leger berhasil divalidasi')
                                ->success()
                                ->send();
                        } catch (DomainException $exception) {
                            Notification::make()
                                ->title($exception->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Action::make('lock')
                    ->label('Lock')
                    ->authorize(fn (): bool => self::canManageDiniyyah())
                    ->requiresConfirmation()
                    ->visible(fn ($record): bool => self::canManageDiniyyah() && in_array($record->status, ['draft', 'validated'], true))
                    ->action(function ($record): void {
                        try {
                            app(DiniyyahLedgerWorkflow::class)->lock($record, auth()->user());

                            Notification::make()
                                ->title('Leger berhasil dikunci')
                                ->success()
                                ->send();
                        } catch (DomainException $exception) {
                            Notification::make()
                                ->title($exception->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Action::make('generateReportCards')
                    ->label('Generate Rapor')
                    ->authorize(fn (): bool => self::canManageDiniyyah())
                    ->requiresConfirmation()
                    ->visible(fn ($record): bool => self::canManageDiniyyah() && in_array($record->status, ['locked', 'published'], true))
                    ->action(function ($record): void {
                        try {
                            $count = app(ReportCardGenerator::class)->generateFromLedgerSnapshot($record, auth()->id());

                            Notification::make()
                                ->title("{$count} rapor berhasil dibuat")
                                ->success()
                                ->send();
                        } catch (DomainException $exception) {
                            Notification::make()
                                ->title($exception->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Action::make('lockReportCards')
                    ->label('Lock Rapor')
                    ->authorize(fn (): bool => self::canManageDiniyyah())
                    ->requiresConfirmation()
                    ->visible(fn ($record): bool => self::canManageDiniyyah() && in_array($record->status, ['locked', 'published'], true))
                    ->action(function ($record): void {
                        try {
                            $result = app(ReportCardBulkWorkflow::class)->lockForSnapshot($record, auth()->user());

                            Notification::make()
                                ->title("{$result['locked']} rapor dikunci, {$result['skipped']} dilewati")
                                ->success()
                                ->send();
                        } catch (DomainException $exception) {
                            Notification::make()
                                ->title($exception->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Action::make('publishReportCards')
                    ->label('Publish Rapor')
                    ->authorize(fn (): bool => self::canManageDiniyyah())
                    ->requiresConfirmation()
                    ->visible(fn ($record): bool => self::canManageDiniyyah() && in_array($record->status, ['locked', 'published'], true))
                    ->action(function ($record): void {
                        try {
                            $result = app(ReportCardBulkWorkflow::class)->publishForSnapshot($record, auth()->user());

                            Notification::make()
                                ->title("{$result['published']} rapor dipublish, {$result['skipped']} dilewati")
                                ->success()
                                ->send();
                        } catch (DomainException $exception) {
                            Notification::make()
                                ->title($exception->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    private static function canManageDiniyyah(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'kabag_diniyyah']) ?? false;
    }
}
