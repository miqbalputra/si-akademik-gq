<?php

namespace App\Filament\Resources\DiniyyahClassJournals;

use App\Filament\Resources\DiniyyahClassJournals\Pages;
use App\Filament\Resources\DiniyyahClassJournals\Schemas\DiniyyahClassJournalForm;
use App\Filament\Concerns\HasRoleBasedResourceAccess;
use App\Models\DiniyyahClassJournal;
use App\Models\DiniyyahTeacherAssignment;
use App\Support\SessionTimetable;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DiniyyahClassJournalResource extends Resource
{
    use HasRoleBasedResourceAccess;

    protected static ?string $model = DiniyyahClassJournal::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-book-open';

    protected static string|\UnitEnum|null $navigationGroup = 'Akademik Diniyyah';

    protected static ?string $navigationLabel = 'Jurnal KBM';

    protected static ?string $modelLabel = 'Jurnal KBM';

    protected const VIEW_ROLES = ['admin', 'kabag_diniyyah', 'kepala_sekolah'];

    protected const MANAGE_ROLES = ['admin', 'kabag_diniyyah'];

    public static function form(Schema $schema): Schema
    {
        return DiniyyahClassJournalForm::configure($schema);
    }

    /**
     * Resolve snapshot jam mulai/selesai sesi dari matrix (kelas + hari tanggal)
     * untuk data form jurnal. Mencerminkan logika portal guru
     * {@see \App\Http\Controllers\GuruDiniyyahJournalController::store} — null bila
     * matrix tak ada, tidak menolak penyimpanan. Dipakai hook Create/Edit page.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function resolveSessionTimes(array $data): array
    {
        $assignmentId = $data['diniyyah_teacher_assignment_id'] ?? null;
        $date = $data['date'] ?? null;
        $sessionHour = $data['session_hour'] ?? null;

        $time = null;
        if ($assignmentId && $date && $sessionHour) {
            $assignment = DiniyyahTeacherAssignment::with('classSubject.classroomTerm')->find($assignmentId);
            $classroomId = $assignment?->classSubject?->classroomTerm?->classroom_id;
            if ($classroomId) {
                $time = SessionTimetable::resolve($classroomId, SessionTimetable::dayOfWeekIso($date), (string) $sessionHour);
            }
        }

        $data['session_starts_at'] = $time['starts_at'] ?? null;
        $data['session_ends_at'] = $time['ends_at'] ?? null;

        return $data;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('date')
                    ->date()
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('session_hour')
                    ->label('Jam Ke')
                    ->searchable(),
                Tables\Columns\TextColumn::make('teacherAssignment.teacher.name')
                    ->label('Guru')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('substituteTeacher.name')
                    ->label('Pengganti')
                    ->placeholder('-')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('guru_mengajar')
                    ->label('Guru Mengajar (gaji)')
                    ->placeholder('-')
                    ->getStateUsing(fn (DiniyyahClassJournal $record): ?string => $record->effectiveTeacher()?->name)
                    ->sortable(false),
                Tables\Columns\TextColumn::make('teacherAssignment.classSubject.subject.name')
                    ->label('Mapel')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('teacherAssignment.classSubject.classroomTerm.name')
                    ->label('Kelas')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('jp_count')
                    ->label('JP')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('guru')
                    ->relationship('teacherAssignment.teacher', 'name')
                    ->label('Guru'),
                Tables\Filters\SelectFilter::make('tipe_jurnal')
                    ->label('Tipe Jurnal')
                    ->options([
                        'regular' => 'Reguler (guru asli)',
                        'substitute' => 'Pengganti',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'regular' => $query->whereNull('substitute_teacher_id'),
                            'substitute' => $query->whereNotNull('substitute_teacher_id'),
                            default => $query,
                        };
                    }),
                Tables\Filters\Filter::make('date')
                    ->form([
                        Forms\Components\DatePicker::make('date_from'),
                        Forms\Components\DatePicker::make('date_until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['date_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('date', '>=', $date),
                            )
                            ->when(
                                $data['date_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('date', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->headerActions([
                Action::make('exportAllJournalsExcel')
                    ->label('Export Semua Jurnal (.xls)')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->url(fn (): string => route('admin.diniyyah-journals.export', ['format' => 'excel']))
                    ->openUrlInNewTab(),
                Action::make('exportAllJournalsCsv')
                    ->label('Export CSV')
                    ->icon('heroicon-o-document-text')
                    ->color('gray')
                    ->url(fn (): string => route('admin.diniyyah-journals.export', ['format' => 'csv']))
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDiniyyahClassJournals::route('/'),
            'create' => Pages\CreateDiniyyahClassJournal::route('/create'),
            'view' => Pages\ViewDiniyyahClassJournal::route('/{record}'),
            'edit' => Pages\EditDiniyyahClassJournal::route('/{record}/edit'),
        ];
    }
}
