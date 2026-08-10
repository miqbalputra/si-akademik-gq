<?php

namespace App\Filament\Resources\DiniyyahClassJournals\Schemas;

use App\Models\ClassSession;
use App\Models\DiniyyahTeacherAssignment;
use App\Support\SessionTimetable;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;

/**
 * Form jurnal KBM diniyyah untuk admin/kabag. Sengaja TIDAK memeriksa
 * kecocokan jadwal — dipakai untuk mencatat jurnal terlewat (jadwal lama)
 * setelah perubahan jadwal, di mana slot lama tak lagi cocok jadwal saat ini.
 * Admin memverifikasi jadwal & guru yang berlaku pada tanggal itu via
 * "Riwayat Perubahan Jadwal" sebelum mengisi.
 */
class DiniyyahClassJournalForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Callout::make('Panduan: Jurnal Terlewat / Jadwal Lama')
                    ->description('Form ini TIDAK memeriksa kecocokan jadwal — dipakai admin mencatat jurnal yang terlewat di jadwal lama. Sebelum mengisi, buka **Riwayat Perubahan Jadwal** untuk memastikan tugas mengajar, tanggal, dan sesi yang benar-benar berlangsung pada hari itu. Untuk kasus tukar guru, isi "Guru Pengganti" dengan guru yang sebenarnya mengajar agar JP/gaji tercatat ke dia.')
                    ->icon('heroicon-o-information-circle')
                    ->color('warning')
                    ->columnSpanFull(),

                Select::make('diniyyah_teacher_assignment_id')
                    ->label('Tugas Mengajar (Mapel & Guru)')
                    ->relationship('teacherAssignment', 'id')
                    ->getOptionLabelFromRecordUsing(fn (DiniyyahTeacherAssignment $record) => "{$record->classSubject->classroomTerm->name} - {$record->classSubject->subject->name} ({$record->teacher->name})")
                    ->getSearchResultsUsing(fn (string $search): array => DiniyyahTeacherAssignment::searchOptions($search))
                    ->searchable()
                    ->preload()
                    ->optionsLimit(200)
                    ->required()
                    ->live()
                    ->helperText('Pilih tugas mengajar (kelas + mapel + guru pemilik assignment) yang berlaku pada tanggal jurnal.'),

                DatePicker::make('date')
                    ->label('Tanggal Mengajar')
                    ->required()
                    ->live()
                    ->helperText('Tanggal jurnal dilaksanakan — boleh tanggal lalu (untuk backfill jurnal terlewat).'),

                Select::make('session_hour')
                    ->label('Sesi / Jam Ke')
                    ->required()
                    ->helperText('Opsi sesi diambil dari matrix jam kelas pada hari tanggal yang dipilih.')
                    ->options(function (Get $get): array {
                        $assignmentId = $get('diniyyah_teacher_assignment_id');
                        $date = $get('date');
                        if (! $assignmentId || ! $date) {
                            return [];
                        }

                        $assignment = DiniyyahTeacherAssignment::with('classSubject.classroomTerm.classroom')->find($assignmentId);
                        $classroomId = $assignment?->classSubject?->classroomTerm?->classroom_id;

                        // Fallback ke daftar sesi global bila classroom tak ter-resolve
                        // (mis. classroom non-Mustawa tanpa matrix) atau tak ada slot di
                        // hari itu — supaya admin tetap bisa memilih sesi.
                        if (! $classroomId) {
                            return self::globalSessionOptions();
                        }

                        $slots = SessionTimetable::slotsFor($classroomId, SessionTimetable::dayOfWeekIso($date));
                        if ($slots->isEmpty()) {
                            return self::globalSessionOptions();
                        }

                        return $slots->mapWithKeys(fn ($slot) => [
                            $slot->session_name => self::sessionLabel($slot->session_name, $slot->starts_at, $slot->ends_at),
                        ])->all();
                    })
                    ->unique(
                        table: 'diniyyah_class_journals',
                        column: 'session_hour',
                        ignoreRecord: true,
                        modifyRuleUsing: function (Unique $rule, Get $get): Unique {
                            // whereDate (bukan where) karena kolom date di-cast model
                            // 'date' lalu disimpan sebagai 'Y-m-d H:i:s' (mis.
                            // '2026-08-04 00:00:00'). where('date','2026-08-04') tidak
                            // cocok di SQLite (string compare) → cek unique lolos →
                            // menabrak unique index DB. whereDate membandingkan tanggal
                            // saja, konsisten lintas DB. Lihat jebakan date-cast SQLite.
                            $date = $get('date');
                            $dateStr = is_string($date) ? $date : (string) \Illuminate\Support\Carbon::parse($date)->format('Y-m-d');
                            $assignmentId = $get('diniyyah_teacher_assignment_id');

                            return $rule->where(function ($query) use ($dateStr, $assignmentId): void {
                                $query->whereDate('date', $dateStr)
                                    ->where('diniyyah_teacher_assignment_id', $assignmentId);
                            });
                        },
                    )
                    ->validationMessages([
                        'unique' => 'Jurnal untuk tugas mengajar, tanggal, dan sesi ini sudah ada.',
                    ]),

                Select::make('substitute_teacher_id')
                    ->label('Guru Pengganti (yang benar-benar mengajar)')
                    ->relationship('substituteTeacher', 'name')
                    ->nullable()
                    ->searchable()
                    ->preload()
                    ->helperText('Kosongkan jika guru pemilik assignment yang mengajar. Untuk kasus tukar guru: isi guru yang benar-benar mengajar tanggal itu (guru lama) agar JP/gaji tercatat ke dia.'),

                Textarea::make('material')
                    ->label('Materi')
                    ->required()
                    ->maxLength(65535)
                    ->columnSpanFull(),

                TextInput::make('jp_count')
                    ->label('Jumlah JP')
                    ->required()
                    ->numeric()
                    ->default(1)
                    ->minValue(1),
            ]);
    }

    /**
     * Label sesi "Sesi N (HH:MM-HH:MM)" dengan jam dari matrix.
     */
    private static function sessionLabel(string $sessionName, mixed $startsAt, mixed $endsAt): string
    {
        $label = SessionTimetable::label($sessionName);
        $start = substr((string) $startsAt, 0, 5) ?: null;
        $end = substr((string) $endsAt, 0, 5) ?: null;
        if ($start === null && $end === null) {
            return $label;
        }

        return $label.' ('.($start ?? '–').'-'.($end ?? '–').')';
    }

    /**
     * Opsi sesi global (ClassSession) dipakai sebagai fallback bila matrix per-
     * classroom tak tersedia untuk (kelas, hari) yang dipilih.
     */
    private static function globalSessionOptions(): array
    {
        return ClassSession::query()
            ->orderBy('starts_at')
            ->get()
            ->mapWithKeys(fn (ClassSession $s) => [
                $s->session_name => self::sessionLabel($s->session_name, $s->starts_at, $s->ends_at),
            ])
            ->all();
    }
}