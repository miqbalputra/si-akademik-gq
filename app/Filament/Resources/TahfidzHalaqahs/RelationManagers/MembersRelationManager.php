<?php

namespace App\Filament\Resources\TahfidzHalaqahs\RelationManagers;

use App\Models\ClassEnrollment;
use App\Models\TahfidzHalaqahMember;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rules\Unique;

class MembersRelationManager extends RelationManager
{
    protected static string $relationship = 'members';

    protected static ?string $title = 'Santri Halaqah';

    public function form(Schema $schema): Schema
    {
        $owner = $this->getOwnerRecord();

        return $schema
            ->components([
                Select::make('student_id')
                    ->label('Santri')
                    ->relationship('student', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    // Cegah santri di-add dua kali ke halaqah yang sama (DB sudah punya
                    // unique(tahfidz_halaqah_id, student_id)); beri pesan ramah, dan abaikan
                    // baris yang sedang diedit.
                    ->unique(
                        table: 'tahfidz_halaqah_members',
                        column: 'student_id',
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule) => $rule->where('tahfidz_halaqah_id', $owner?->id ?? 0),
                    )
                    ->validationMessages([
                        'unique' => 'Santri sudah terdaftar di halaqah ini. Jika statusnya Pindah/Tidak Aktif, edit baris tersebut untuk mengaktifkan kembali.',
                    ])
                    // Cegah satu santri aktif di lebih dari satu halaqah pada periode yang sama.
                    ->rule(function () use ($owner) {
                        return new class($owner) implements ValidationRule
                        {
                            public function __construct(private $owner) {}

                            public function validate(string $attribute, mixed $value, \Closure $fail): void
                            {
                                if (! $value || ! $this->owner) {
                                    return;
                                }

                                $exists = TahfidzHalaqahMember::query()
                                    ->where('student_id', $value)
                                    ->where('status', 'active')
                                    ->where('tahfidz_halaqah_id', '!=', $this->owner->id)
                                    ->whereHas('halaqah', fn ($q) => $q->where('academic_term_id', $this->owner->academic_term_id))
                                    ->exists();

                                if ($exists) {
                                    $fail('Santri ini masih aktif di halaqah lain pada periode yang sama. Keluarkan/pindahkan dari halaqah lamanya dulu.');
                                }
                            }
                        };
                    }),
                Select::make('class_enrollment_id')
                    ->label('Penempatan Kelas')
                    ->options(function () use ($owner) {
                        return ClassEnrollment::query()
                            ->with('classroomTerm.classroom', 'student')
                            ->where('academic_term_id', $owner?->academic_term_id)
                            ->get()
                            ->mapWithKeys(fn (ClassEnrollment $e) => [$e->id => "{$e->student?->name} — {$e->classroomTerm?->name}"])
                            ->toArray();
                    })
                    ->getOptionLabelUsing(function ($value) {
                        $e = ClassEnrollment::with('classroomTerm.classroom', 'student')->find($value);

                        return $e ? "{$e->student?->name} — {$e->classroomTerm?->name}" : null;
                    })
                    ->searchable()
                    ->helperText('Opsional: hubungkan ke penempatan kelas periode ini.'),
                Select::make('status')
                    ->label('Status')
                    ->options([
                        'active' => 'Aktif',
                        'moved' => 'Pindah',
                        'inactive' => 'Tidak Aktif',
                    ])
                    ->required()
                    ->default('active'),
                TextInput::make('sort_order')
                    ->label('Urutan')
                    ->numeric()
                    ->default(0),
                TextInput::make('joined_at')
                    ->label('Tanggal Bergabung')
                    ->type('date'),
                TextInput::make('left_at')
                    ->label('Tanggal Keluar')
                    ->type('date')
                    ->helperText('Terisi otomatis saat santri dikeluarkan, atau isi manual jika pindah halaqah.'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('sort_order')
                    ->label('Urutan')
                    ->width('80px'),
                TextColumn::make('student.name')
                    ->label('Nama Santri')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('student.nis')
                    ->label('NIS'),
                TextColumn::make('student.gender')
                    ->label('L/P')
                    ->formatStateUsing(fn ($state) => $state === 'male' ? 'L' : 'P')
                    ->width('50px'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'active' => 'success',
                        'moved' => 'warning',
                        'inactive' => 'gray',
                        default => 'gray',
                    }),
                TextColumn::make('joined_at')
                    ->date('d M Y')
                    ->label('Bergabung')
                    ->toggleable(),
                TextColumn::make('left_at')
                    ->date('d M Y')
                    ->label('Keluar Pada')
                    ->toggleable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Tambah Santri'),
            ])
            ->recordActions([
                EditAction::make(),
                // "Keluarkan" = pindah (soft-move): isi left_at & status=moved, BUKAN hapus,
                // agar riwayat keanggotaan tetap utuh. Untuk mengaktifkan kembali, Edit baris.
                Action::make('keluarkan')
                    ->label('Keluarkan')
                    ->icon('heroicon-o-arrow-right-start-on-rectangle')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (TahfidzHalaqahMember $record): bool => $record->status === 'active')
                    ->action(function (TahfidzHalaqahMember $record): void {
                        $record->update([
                            'left_at' => $record->left_at ?? now()->toDateString(),
                            'status' => 'moved',
                        ]);
                    }),
            ]);
    }
}