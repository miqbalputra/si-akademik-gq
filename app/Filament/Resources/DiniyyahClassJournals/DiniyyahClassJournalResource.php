<?php

namespace App\Filament\Resources\DiniyyahClassJournals;

use App\Filament\Resources\DiniyyahClassJournals\Pages;
use App\Models\DiniyyahClassJournal;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DiniyyahClassJournalResource extends Resource
{
    protected static ?string $model = DiniyyahClassJournal::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-book-open';
    
    protected static string|\UnitEnum|null $navigationGroup = 'Akademik Diniyyah';
    
    protected static ?string $navigationLabel = 'Jurnal KBM';
    
    protected static ?string $modelLabel = 'Jurnal KBM';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Select::make('diniyyah_teacher_assignment_id')
                    ->relationship('teacherAssignment', 'id')
                    ->required(),
                Forms\Components\DatePicker::make('date')
                    ->required(),
                Forms\Components\TextInput::make('session_hour')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Textarea::make('material')
                    ->maxLength(65535)
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('jp_count')
                    ->required()
                    ->numeric()
                    ->default(1),
            ]);
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
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
