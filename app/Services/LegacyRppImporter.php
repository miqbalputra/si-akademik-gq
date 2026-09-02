<?php

namespace App\Services;

use App\Models\ClassroomTerm;
use App\Models\DiniyyahClassSubject;
use App\Models\DiniyyahSubject;
use App\Models\DiniyyahTeacherAssignment;
use App\Models\Rpp;
use App\Models\RppImportRecord;
use App\Models\RppPromes;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LegacyRppImporter
{
    /** @return array{imported:int, skipped:int, conflicts:array<int, array<string, mixed>>, files:int, promes:int} */
    public function import(ConnectionInterface $source, bool $dryRun, ?string $filesRoot = null): array
    {
        foreach (['users', 'gurus', 'mapels', 'kelas', 'rpp'] as $table) {
            if (! $source->getSchemaBuilder()->hasTable($table)) {
                throw new \InvalidArgumentException("Tabel sumber '{$table}' tidak ditemukan. Pastikan backup aplikasi RPP lama sudah dipulihkan.");
            }
        }

        $report = ['imported' => 0, 'skipped' => 0, 'conflicts' => [], 'files' => 0, 'promes' => 0];
        $mapels = $source->table('mapels')->get()->keyBy('id');
        $kelas = $source->table('kelas')->get()->keyBy('id');
        $gurus = $source->table('gurus')->get()->keyBy('id');
        $users = $source->table('users')->get()->keyBy('id');
        $hasMeetings = $source->getSchemaBuilder()->hasTable('rpp_pertemuan');
        $hasAssessments = $source->getSchemaBuilder()->hasTable('rpp_penilaian');
        $hasFiles = $source->getSchemaBuilder()->hasTable('rpp_file');

        foreach ($source->table('rpp')->orderBy('createdAt')->cursor() as $legacy) {
            if (Rpp::withTrashed()->where('legacy_source_id', (string) $legacy->id)->exists()) {
                $report['skipped']++;
                continue;
            }

            $resolution = $this->resolve($legacy, $gurus->get($legacy->guruId), $users, $mapels->get($legacy->mapelId), $kelas->get($legacy->kelasId));
            if (isset($resolution['conflict'])) {
                $report['conflicts'][] = $resolution['conflict'];
                if (! $dryRun) {
                    RppImportRecord::updateOrCreate(
                        ['source' => 'rpp-next', 'source_type' => 'rpp', 'source_id' => (string) $legacy->id],
                        ['status' => 'conflict', 'details' => $resolution['conflict']],
                    );
                }
                continue;
            }

            $teacher = $resolution['teacher'];
            $classSubject = $resolution['class_subject'];
            $assignment = DiniyyahTeacherAssignment::query()->where('teacher_id', $teacher->id)->where('diniyyah_class_subject_id', $classSubject->id)->first();
            if (! $assignment) {
                $conflict = ['legacy_rpp_id' => (string) $legacy->id, 'reason' => 'Penugasan guru target tidak ditemukan.', 'teacher_id' => $teacher->id, 'class_subject_id' => $classSubject->id];
                $report['conflicts'][] = $conflict;
                if (! $dryRun) RppImportRecord::updateOrCreate(['source' => 'rpp-next', 'source_type' => 'rpp', 'source_id' => (string) $legacy->id], ['status' => 'conflict', 'details' => $conflict]);
                continue;
            }

            if ($dryRun) {
                $report['imported']++;
                continue;
            }

            $createdBy = $this->matchUser($users->get($legacy->dibuatOleh));
            $rpp = DB::transaction(function () use ($legacy, $teacher, $classSubject, $assignment, $createdBy, $source, $hasMeetings, $hasAssessments): Rpp {
                $rpp = Rpp::create([
                    'diniyyah_class_subject_id' => $classSubject->id,
                    'diniyyah_teacher_assignment_id' => $assignment->id,
                    'teacher_id' => $teacher->id,
                    'created_by' => $createdBy?->id,
                    'no_rpp' => $legacy->noRpp ?: null,
                    'materi' => $legacy->materi ?: 'RPP Legacy',
                    'alokasi_waktu' => $legacy->alokasiWaktu ?: null,
                    'tujuan_pembelajaran' => $legacy->tujuanPembelajaran ?: null,
                    'tanggal_pengesahan' => $legacy->tanggalPengesahan ?: null,
                    'input_method' => $this->legacyMethod($legacy),
                    'ai_assisted' => (bool) ($legacy->dibuatDenganAI ?? false),
                    'legacy_source_id' => (string) $legacy->id,
                    'legacy_status' => $legacy->status ?? null,
                    'legacy_metadata' => ['source' => 'rpp-next', 'created_at' => $legacy->createdAt ?? null, 'updated_at' => $legacy->updatedAt ?? null],
                ]);
                // Timestamp lama dicatat persis setelah create; atribut ini
                // sengaja tidak mass-assignable pada model RPP normal.
                $rpp->forceFill(['created_at' => $legacy->createdAt ?? now(), 'updated_at' => $legacy->updatedAt ?? now()])->saveQuietly();
                if ($hasMeetings) foreach ($source->table('rpp_pertemuan')->where('rppId', $legacy->id)->orderBy('urutan')->get() as $meeting) $rpp->meetings()->create(['urutan' => $meeting->urutan, 'isi_kegiatan' => $meeting->isiKegiatan, 'tanggal_kbm' => $meeting->tanggal ?? null]);
                if ($hasAssessments && ($assessment = $source->table('rpp_penilaian')->where('rppId', $legacy->id)->first())) $rpp->assessment()->create(['pengetahuan' => $assessment->pengetahuan, 'keterampilan' => $assessment->keterampilan, 'sikap' => $assessment->sikap]);
                return $rpp;
            });
            if ($hasFiles && ($file = $source->table('rpp_file')->where('rppId', $legacy->id)->first())) $report['files'] += $this->copyFile($rpp, $file, $filesRoot) ? 1 : 0;
            RppImportRecord::updateOrCreate(['source' => 'rpp-next', 'source_type' => 'rpp', 'source_id' => (string) $legacy->id], ['status' => 'imported', 'rpp_id' => $rpp->id, 'details' => ['legacy_status' => $legacy->status ?? null]]);
            $report['imported']++;
        }

        if ($source->getSchemaBuilder()->hasTable('promes') && ! $dryRun) {
            foreach ($source->table('promes')->cursor() as $legacyPromes) {
                $subject = $this->subjectFor($mapels->get($legacyPromes->mapelId));
                $term = $this->classroomTermFor($kelas->get($legacyPromes->kelasId));
                $classSubject = $subject && $term ? DiniyyahClassSubject::where('subject_id', $subject->id)->where('classroom_term_id', $term->id)->first() : null;
                if (! $classSubject) { $report['conflicts'][] = ['legacy_promes_id' => (string) $legacyPromes->id, 'reason' => 'Promes tidak dapat dipetakan ke mapel-kelas target.']; continue; }
                RppPromes::updateOrCreate(['diniyyah_class_subject_id' => $classSubject->id], ['url' => $legacyPromes->url]);
                $report['promes']++;
            }
        }

        return $report;
    }

    private function resolve(object $legacy, ?object $legacyTeacher, $users, ?object $legacySubject, ?object $legacyClass): array
    {
        $teacher = $this->teacherFor($legacyTeacher, $users);
        if (! $teacher) return ['conflict' => ['legacy_rpp_id' => (string) $legacy->id, 'reason' => 'Guru sumber tidak dapat dipetakan ke Teacher target.']];
        $subject = $this->subjectFor($legacySubject);
        if (! $subject) return ['conflict' => ['legacy_rpp_id' => (string) $legacy->id, 'reason' => 'Mapel sumber tidak cocok dengan DiniyyahSubject target.']];
        $term = $this->classroomTermFor($legacyClass);
        if (! $term) return ['conflict' => ['legacy_rpp_id' => (string) $legacy->id, 'reason' => 'Kelas/periode sumber tidak cocok dengan ClassroomTerm target.']];
        $classSubject = DiniyyahClassSubject::where('subject_id', $subject->id)->where('classroom_term_id', $term->id)->first();
        if (! $classSubject) return ['conflict' => ['legacy_rpp_id' => (string) $legacy->id, 'reason' => 'Pasangan mapel-kelas target belum dikonfigurasi.']];
        return ['teacher' => $teacher, 'class_subject' => $classSubject];
    }

    private function matchUser(?object $legacy): ?User
    {
        if (! $legacy) return null;
        return User::query()->where('username', $legacy->username ?? null)->first()
            ?? User::query()->whereNotNull('email')->where('email', $legacy->email ?? null)->first();
    }

    private function teacherFor(?object $legacyTeacher, $users): ?Teacher
    {
        if (! $legacyTeacher) return null;
        $user = $this->matchUser($users->get($legacyTeacher->userId));
        return $user?->teacher ?? Teacher::query()->whereRaw('lower(name) = ?', [$this->normal((string) $legacyTeacher->namaTampil)])->first();
    }

    private function subjectFor(?object $legacySubject): ?DiniyyahSubject
    {
        return $legacySubject ? DiniyyahSubject::query()->whereRaw('lower(name) = ?', [$this->normal((string) $legacySubject->namaMapel)])->first() : null;
    }

    private function classroomTermFor(?object $legacyClass): ?ClassroomTerm
    {
        if (! $legacyClass) return null;
        $query = ClassroomTerm::query()->with('academicTerm.academicYear')->whereRaw('lower(name) = ?', [$this->normal((string) $legacyClass->namaKelas)]);
        $candidate = $query->get()->first(function (ClassroomTerm $term) use ($legacyClass): bool {
            $semester = (string) ($term->academicTerm?->semester ?? '');
            $year = (string) ($term->academicTerm?->academicYear?->name ?? '');
            return ($legacyClass->semester === null || $semester === '' || strcasecmp($semester, (string) $legacyClass->semester) === 0)
                && ($legacyClass->tahunAjaran === null || $year === '' || strcasecmp($year, (string) $legacyClass->tahunAjaran) === 0);
        });
        return $candidate;
    }

    private function copyFile(Rpp $rpp, object $file, ?string $root): bool
    {
        if (! $root || ! $file->pathFile) return false;
        $base = realpath($root);
        $candidate = realpath($root.DIRECTORY_SEPARATOR.str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $file->pathFile));
        if (! $base || ! $candidate || ! str_starts_with($candidate, $base.DIRECTORY_SEPARATOR) || ! is_file($candidate)) return false;
        $content = file_get_contents($candidate);
        if ($content === false) return false;
        $path = "legacy/{$rpp->id}/".Str::uuid().'-'.basename($candidate);
        Storage::disk('rpp')->put($path, $content);
        $rpp->files()->create(['kind' => 'upload', 'disk' => 'rpp', 'path' => $path, 'nama_file' => $file->namaFile ?? basename($candidate), 'mime_type' => $file->mimeType ?? 'application/pdf', 'ukuran_byte' => strlen($content), 'checksum' => hash('sha256', $content)]);
        return true;
    }

    private function legacyMethod(object $legacy): string { return in_array($legacy->metodeInput ?? null, ['manual', 'ai', 'upload'], true) ? $legacy->metodeInput : strtolower((string) ($legacy->metodeInput ?? 'manual')); }
    private function normal(string $value): string { return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $value) ?? $value)); }
}
