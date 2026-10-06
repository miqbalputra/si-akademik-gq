<?php

namespace App\Filament\Resources\StudentDepartures\Schemas;

use App\Models\DiniyyahClassJournalAbsence;
use App\Models\DiniyyahAssessmentResult;
use App\Models\DiniyyahScore;
use App\Models\ReportCard;
use App\Models\StudentAttendance;
use App\Models\StudentDeparture;
use App\Models\TasmiRecord;
use App\Models\TahfidzHalaqahMember;
use App\Models\TahfidzMonthlyRecap;
use App\Models\TahfidzUasResult;
use App\Models\TahfidzUasScore;
use App\Models\TahfidzWeeklyScore;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class StudentDepartureInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Profil Santri')
                ->columns(2)
                ->schema([
                    TextEntry::make('student.name')->label('Nama'),
                    TextEntry::make('student.nis')->label('NIS'),
                    TextEntry::make('student.nik')->label('NIK')->placeholder('—'),
                    TextEntry::make('student.gender')->label('Jenis Kelamin')
                        ->formatStateUsing(fn (?string $state): string => \App\Support\UiLabel::genderLabel($state)),
                    TextEntry::make('student.status')->label('Status Profil')->badge(),
                    TextEntry::make('student.deleted_at')->label('Diarsipkan Pada')->dateTime('d M Y H:i')->placeholder('Profil sudah dipulihkan'),
                ]),
            Section::make('Informasi Pengeluaran')
                ->columns(2)
                ->schema([
                    TextEntry::make('type')->label('Jenis')
                        ->formatStateUsing(fn (string $state): string => $state === StudentDeparture::TYPE_TRANSFER ? 'Pindah' : 'Keluar'),
                    TextEntry::make('effective_date')->label('Tanggal Keluar')->date('d M Y'),
                    TextEntry::make('destination_school')->label('Sekolah Tujuan')->placeholder('—'),
                    TextEntry::make('exitedBy.name')->label('Diproses Oleh')->placeholder('Akun tidak tersedia'),
                    TextEntry::make('reason')->label('Alasan')->columnSpanFull(),
                    TextEntry::make('restored_at')->label('Profil Dipulihkan Pada')->dateTime('d M Y H:i')->placeholder('Belum dipulihkan'),
                    TextEntry::make('restoredBy.name')->label('Dipulihkan Oleh')->placeholder('—'),
                ]),
            Section::make('Data Terkait yang Tetap Tersimpan')
                ->schema([
                    TextEntry::make('relatedData')
                        ->label('Ringkasan data santri')
                        ->state(fn (StudentDeparture $record): string => self::relatedDataSummary($record))
                        ->columnSpanFull(),
                    TextEntry::make('preservedData')
                        ->label('Jumlah catatan tersimpan')
                        ->state(fn (StudentDeparture $record): string => self::preservedDataSummary($record))
                        ->columnSpanFull(),
                ]),
        ]);
    }

    private static function preservedDataSummary(StudentDeparture $record): string
    {
        $student = $record->student;
        if (! $student) {
            return 'Profil siswa tidak ditemukan.';
        }

        $enrollmentIds = $student->enrollments()->pluck('id');
        $data = [
            'Wali santri' => $student->guardians()->count(),
            'Riwayat kelas' => $enrollmentIds->count(),
            'Presensi' => StudentAttendance::query()->where('student_id', $student->id)->count(),
            'Nilai Diniyyah' => DiniyyahScore::query()->whereIn('class_enrollment_id', $enrollmentIds)->count(),
            'Hasil penilaian Diniyyah' => DiniyyahAssessmentResult::query()->whereIn('class_enrollment_id', $enrollmentIds)->count(),
            'Catatan absensi jurnal Diniyyah' => DiniyyahClassJournalAbsence::query()->whereIn('class_enrollment_id', $enrollmentIds)->count(),
            'Rapor' => ReportCard::query()->where('student_id', $student->id)->count(),
            'Keanggotaan halaqah' => TahfidzHalaqahMember::query()->where('student_id', $student->id)->count(),
            'Nilai pekanan Tahfidz' => TahfidzWeeklyScore::query()->where('student_id', $student->id)->count(),
            'Rekap bulanan Tahfidz' => TahfidzMonthlyRecap::query()->where('student_id', $student->id)->count(),
            'Nilai UAS Tahfidz' => TahfidzUasScore::query()->where('student_id', $student->id)->count(),
            'Hasil UAS Tahfidz' => TahfidzUasResult::query()->where('student_id', $student->id)->count(),
            'Record Tasmi\'' => TasmiRecord::withTrashed()->where('student_id', $student->id)->count(),
        ];

        return collect($data)
            ->map(fn (int $count, string $label): string => "{$label}: {$count}")
            ->implode("\n");
    }

    private static function relatedDataSummary(StudentDeparture $record): string
    {
        $student = $record->student;
        if (! $student) {
            return 'Profil siswa tidak ditemukan.';
        }

        $guardians = $student->guardians()
            ->orderBy('guardians.name')
            ->get(['guardians.name', 'guardians.phone', 'guardians.email'])
            ->map(fn ($guardian): string => collect([
                $guardian->name,
                $guardian->phone,
                $guardian->email,
            ])->filter()->join(' · '));
        $enrollments = $student->enrollments()
            ->with(['classroomTerm', 'academicTerm'])
            ->orderByDesc('id')
            ->get()
            ->map(fn ($enrollment): string => collect([
                $enrollment->classroomTerm?->name,
                $enrollment->academicTerm?->name,
                $enrollment->status === 'active' ? 'Aktif' : 'Nonaktif',
            ])->filter()->join(' · '));
        $attendance = StudentAttendance::query()
            ->where('student_id', $student->id)
            ->latest('attendance_date')
            ->take(10)
            ->get()
            ->map(fn (StudentAttendance $row): string => $row->attendance_date->format('d M Y').' · '.$row->status);
        $reportCards = ReportCard::query()
            ->with('academicTerm')
            ->where('student_id', $student->id)
            ->latest('id')
            ->get()
            ->map(fn (ReportCard $card): string => collect([
                $card->academicTerm?->name,
                'Rapor '.($card->report_type ?? 'Diniyyah'),
                'Status '.$card->status,
                $card->total_score === null ? null : 'Nilai '.$card->total_score,
            ])->filter()->join(' · '));

        return collect([
            'Wali santri' => $guardians->isEmpty() ? '—' : $guardians->implode("\n"),
            'Riwayat kelas' => $enrollments->isEmpty() ? '—' : $enrollments->implode("\n"),
            'Presensi terbaru' => $attendance->isEmpty() ? '—' : $attendance->implode("\n"),
            'Rapor' => $reportCards->isEmpty() ? '—' : $reportCards->implode("\n"),
        ])->map(fn (string $value, string $label): string => "{$label}:\n{$value}")
            ->implode("\n\n");
    }
}
