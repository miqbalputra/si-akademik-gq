<?php

namespace App\Filament\Resources\DiniyyahTeacherAssignments\Tables;

use App\Models\DiniyyahTeacherAssignment;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class DiniyyahTeacherAssignmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('classSubject.classroomTerm.name')->label('Kelas')->searchable(),
                TextColumn::make('classSubject.subject.name')->label('Mapel')->searchable(),
                TextColumn::make('teacher.name')->label('Guru')->searchable()->sortable(),
                TextColumn::make('assignment_role')->label('Peran Tugas')->badge(),
                TextColumn::make('starts_at')->label('Dimulai Pada')->date()->sortable(),
                TextColumn::make('ends_at')->label('Selesai Pada')->date()->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->action(function (Collection $records): void {
                            // Blokir penghapusan massal bila ada penugasan terpilih
                            // yang masih memiliki jurnal — cascadeOnDelete akan
                            // menghapus jurnal permanen. Batalkan seluruh batch
                            // (jangan hapus sebagian) supaya tidak ada data hilang
                            // tanpa kesadaran admin.
                            $blocked = $records->filter(
                                fn (DiniyyahTeacherAssignment $r): bool => ! $r->isDeletable(),
                            );

                            if ($blocked->isNotEmpty()) {
                                Notification::make()
                                    ->title('Penghapusan dibatalkan')
                                    ->body($blocked->count().' penugasan masih memiliki jurnal kelas dan tidak dapat dihapus. Jurnal yang sudah terisi harus tetap tersimpan.')
                                    ->status('danger')
                                    ->send();
                                $this->halt();

                                return;
                            }

                            $records->each(fn (DiniyyahTeacherAssignment $r) => $r->delete());
                        }),
                ]),
            ]);
    }
}