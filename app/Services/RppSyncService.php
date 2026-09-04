<?php

namespace App\Services;

use App\Models\ClassroomTerm;
use App\Models\DiniyyahClassSubject;
use App\Models\DiniyyahSubject;
use App\Models\DiniyyahTeacherAssignment;
use App\Models\Rpp;
use App\Models\RppFile;
use App\Models\RppPromes;
use App\Models\RppSyncConflict;
use App\Models\RppSyncMapping;
use App\Models\RppSyncState;
use App\Models\Teacher;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/** Pulls canonical data from Project RPP. It never creates school master data. */
class RppSyncService
{
    private const SOURCE = 'rpp-next';

    public function syncEntity(string $entity, string $id): bool
    {
        if (! config('rpp_sync.enabled')) return true;
        try {
            match ($entity) {
                'rpp' => $this->syncRpp($this->detail('rpp', $id)),
                'promes' => $this->syncPromes($this->detail('promes', $id)),
                default => throw new RuntimeException('Entitas sinkronisasi RPP tidak dikenal.'),
            };
        } catch (RppSyncMappingException $exception) {
            $this->conflict($entity, $id, $exception->getMessage(), $exception->details);
            return false;
        }
        return true;
    }

    /** @return array{processed:int,conflicts:int} */
    public function reconcile(): array
    {
        if (! config('rpp_sync.enabled')) return ['processed' => 0, 'conflicts' => 0];
        $state = RppSyncState::firstOrCreate(['source' => self::SOURCE]);
        $cursor = $state->cursor;
        $processed = $conflicts = 0;

        do {
            $response = $this->client()->get('/api/integrations/v1/changes', array_filter(['cursor' => $cursor, 'limit' => 100]))->throw()->json();
            foreach ($response['changes'] ?? [] as $change) {
                try {
                    if ($this->syncEntity((string) $change['entity'], (string) $change['id'])) $processed++; else $conflicts++;
                } catch (RppSyncMappingException $exception) {
                    $this->conflict((string) $change['entity'], (string) $change['id'], $exception->getMessage(), $exception->details);
                    $conflicts++;
                }
            }
            $cursor = $response['nextCursor'] ?? $cursor;
            $state->update(['cursor' => $cursor, 'last_synced_at' => now(), 'last_error' => null]);
        } while (($response['hasMore'] ?? false) === true);

        return compact('processed', 'conflicts');
    }

    private function syncRpp(array $data): void
    {
        $sourceId = (string) $data['id'];
        $existing = Rpp::withTrashed()->where('legacy_source_id', $sourceId)->first();
        if ($data['deletedAt'] ?? null) {
            if ($existing && ! $existing->trashed()) $existing->delete();
            $this->resolveConflict('rpp', $sourceId);
            return;
        }

        $hash = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
        $sourceUpdatedAt = Carbon::parse((string) $data['updatedAt']);
        $sourceFileId = data_get($data, 'file.id');
        $hasCopiedFile = ! $sourceFileId || $existing?->files()->where('kind', 'source-upload')->exists();
        if ($existing && $existing->source_updated_at?->gte($sourceUpdatedAt) && $existing->source_payload_hash === $hash && $hasCopiedFile) return;

        $mapping = $this->rppMapping($data);
        $rpp = DB::transaction(function () use ($data, $existing, $mapping, $sourceUpdatedAt, $hash, $sourceId): Rpp {
            $rpp = $existing ?? new Rpp(['legacy_source_id' => $sourceId]);
            $rpp->fill([
                'diniyyah_class_subject_id' => $mapping['classSubject']->id,
                'diniyyah_teacher_assignment_id' => $mapping['assignment']->id,
                'teacher_id' => $mapping['teacher']->id,
                'created_by' => $mapping['creator']?->id,
                'no_rpp' => $data['noRpp'] ?: null,
                'materi' => $data['materi'] ?: 'RPP tanpa materi',
                'alokasi_waktu' => $data['alokasiWaktu'] ?: null,
                'tujuan_pembelajaran' => $data['tujuanPembelajaran'] ?: null,
                'tanggal_pengesahan' => $data['tanggalPengesahan'] ? Carbon::parse($data['tanggalPengesahan'])->toDateString() : null,
                'input_method' => $this->inputMethod((string) $data['metodeInput']),
                'ai_assisted' => (bool) $data['dibuatDenganAI'],
                'legacy_status' => $data['status'] ?? null,
                'legacy_metadata' => ['source' => self::SOURCE, 'source_url' => $this->sourceUrl('/guru/rpp/'.$sourceId), 'source_file_id' => data_get($data, 'file.id')],
                'source_updated_at' => $sourceUpdatedAt,
                'source_payload_hash' => $hash,
                'source_synced_at' => now(),
            ]);
            $rpp->save();
            if ($rpp->trashed()) $rpp->restore();
            $rpp->meetings()->delete();
            foreach ($data['pertemuan'] ?? [] as $meeting) {
                $rpp->meetings()->create(['urutan' => $meeting['urutan'], 'isi_kegiatan' => $meeting['isiKegiatan'], 'tanggal_kbm' => $meeting['tanggal'] ? Carbon::parse($meeting['tanggal'])->toDateString() : null]);
            }
            $assessment = $data['penilaian'] ?? null;
            if ($assessment) $rpp->assessment()->updateOrCreate([], ['pengetahuan' => $assessment['pengetahuan'], 'keterampilan' => $assessment['keterampilan'], 'sikap' => $assessment['sikap']]);
            return $rpp;
        });

        if ($sourceFileId && ! $rpp->files()->where('kind', 'source-upload')->where('path', 'like', '%/'.$sourceFileId.'.pdf')->exists()) {
            try {
                $this->copySourcePdf($rpp, $sourceId, (string) $sourceFileId, $data['file']);
            } catch (\Throwable $exception) {
                $this->conflict('rpp', $sourceId, 'PDF sumber gagal disalin: '.$exception->getMessage(), ['file_id' => $sourceFileId]);
                return;
            }
        }
        $this->resolveConflict('rpp', $sourceId);
    }

    private function syncPromes(array $data): void
    {
        $sourceId = (string) $data['id'];
        $existing = RppPromes::withTrashed()->where('legacy_source_id', $sourceId)->first();
        if ($data['deletedAt'] ?? null) {
            if ($existing && ! $existing->trashed()) $existing->delete();
            $this->resolveConflict('promes', $sourceId);
            return;
        }
        $classSubject = $this->classSubjectFor($data['mapel'], $data['kelas']);
        if (! $classSubject) throw new RppSyncMappingException('Mapel/kelas Promes belum dipetakan pada aplikasi sekolah.', ['mapel' => $data['mapel'], 'kelas' => $data['kelas']]);
        $promes = $existing ?? RppPromes::withTrashed()->where('diniyyah_class_subject_id', $classSubject->id)->first() ?? new RppPromes();
        $promes->fill(['diniyyah_class_subject_id' => $classSubject->id, 'url' => $data['url'], 'legacy_source_id' => $sourceId, 'source_updated_at' => Carbon::parse($data['updatedAt'])]);
        $promes->save();
        if ($promes->trashed()) $promes->restore();
        $this->resolveConflict('promes', $sourceId);
    }

    private function copySourcePdf(Rpp $rpp, string $sourceId, string $sourceFileId, array $file): void
    {
        $response = $this->client()->get('/api/integrations/v1/rpp/'.$sourceId.'/file')->throw();
        $contents = $response->body();
        if (! str_starts_with($contents, '%PDF-')) throw new RuntimeException('Berkas sumber bukan PDF valid.');
        $path = 'source/'.$rpp->id.'/'.$sourceFileId.'.pdf';
        if (! Storage::disk('rpp')->put($path, $contents)) throw new RuntimeException('Penyimpanan RPP tidak menerima berkas.');
        $rpp->files()->where('kind', 'source-upload')->delete();
        $rpp->files()->create(['kind' => 'source-upload', 'disk' => 'rpp', 'path' => $path, 'nama_file' => $file['namaFile'] ?? 'rpp.pdf', 'mime_type' => $file['mimeType'] ?? 'application/pdf', 'ukuran_byte' => strlen($contents), 'checksum' => hash('sha256', $contents)]);
    }

    /** @return array{teacher:Teacher,classSubject:DiniyyahClassSubject,assignment:DiniyyahTeacherAssignment,creator:?User} */
    private function rppMapping(array $data): array
    {
        $teacher = $this->teacherFor($data['guru']);
        if (! $teacher) throw new RppSyncMappingException('Guru sumber belum dipetakan pada aplikasi sekolah.', ['guru' => $data['guru']]);
        $classSubject = $this->classSubjectFor($data['mapel'], $data['kelas']);
        if (! $classSubject) throw new RppSyncMappingException('Mapel atau kelas sumber belum dipetakan pada aplikasi sekolah.', ['mapel' => $data['mapel'], 'kelas' => $data['kelas']]);
        $assignment = DiniyyahTeacherAssignment::query()->where('teacher_id', $teacher->id)->where('diniyyah_class_subject_id', $classSubject->id)->first();
        if (! $assignment) throw new RppSyncMappingException('Penugasan guru untuk mapel dan kelas sumber belum ada.', ['teacher_id' => $teacher->id, 'class_subject_id' => $classSubject->id]);
        $creator = $this->userFor(data_get($data, 'guru.user'));
        return compact('teacher', 'classSubject', 'assignment', 'creator');
    }

    private function teacherFor(array $guru): ?Teacher
    {
        if ($mappedId = $this->mappedId('teacher', (string) ($guru['id'] ?? ''))) return Teacher::find($mappedId);
        return $this->userFor($guru['user'] ?? null)?->teacher
            ?? Teacher::query()->whereRaw('lower(name) = ?', [$this->normal((string) ($guru['namaTampil'] ?? ''))])->first();
    }

    private function userFor(?array $sourceUser): ?User
    {
        if (! $sourceUser) return null;
        return User::query()->where('username', $sourceUser['username'] ?? null)->first()
            ?? User::query()->whereNotNull('email')->where('email', $sourceUser['email'] ?? null)->first();
    }

    private function classSubjectFor(array $mapel, array $kelas): ?DiniyyahClassSubject
    {
        if ($mappedId = $this->mappedId('class_subject', (string) ($mapel['id'] ?? '').'|'.(string) ($kelas['id'] ?? ''))) return DiniyyahClassSubject::find($mappedId);
        $subject = DiniyyahSubject::query()->whereRaw('lower(name) = ?', [$this->normal((string) ($mapel['namaMapel'] ?? ''))])->first();
        if (! $subject) return null;
        $term = ClassroomTerm::query()->with('academicTerm.academicYear')->whereRaw('lower(name) = ?', [$this->normal((string) ($kelas['namaKelas'] ?? ''))])->get()->first(function (ClassroomTerm $term) use ($kelas): bool {
            return (empty($kelas['semester']) || strcasecmp((string) $term->academicTerm?->semester, (string) $kelas['semester']) === 0)
                && (empty($kelas['tahunAjaran']) || strcasecmp((string) $term->academicTerm?->academicYear?->name, (string) $kelas['tahunAjaran']) === 0);
        });
        return $term ? DiniyyahClassSubject::query()->where('subject_id', $subject->id)->where('classroom_term_id', $term->id)->first() : null;
    }

    private function detail(string $entity, string $id): array
    {
        $data = $this->client()->get('/api/integrations/v1/'.$entity.'/'.$id)->throw()->json('data');
        if (! is_array($data)) throw new RuntimeException('Respons sumber RPP tidak valid.');
        return $data;
    }

    private function client(): PendingRequest
    {
        $source = config('rpp_sync.source');
        $token = config('rpp_sync.token');
        if (! $source || ! $token) throw new RuntimeException('Konfigurasi sumber RPP belum lengkap.');
        return Http::baseUrl($source)->withToken($token)->acceptJson()->timeout((int) config('rpp_sync.timeout'));
    }

    private function sourceUrl(string $path): string { return rtrim((string) config('rpp_sync.source'), '/').$path; }
    private function mappedId(string $type, string $sourceId): ?int { return RppSyncMapping::query()->where('mapping_type', $type)->where('source_id', $sourceId)->value('target_id'); }
    private function inputMethod(string $method): string { return in_array(strtolower($method), ['manual', 'ai', 'upload'], true) ? strtolower($method) : 'manual'; }
    private function normal(string $value): string { return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $value) ?? $value)); }
    private function conflict(string $type, string $id, string $reason, array $details = []): void { RppSyncConflict::updateOrCreate(['source' => self::SOURCE, 'source_type' => $type, 'source_id' => $id], ['reason' => $reason, 'details' => $details, 'resolved_at' => null]); }
    private function resolveConflict(string $type, string $id): void { RppSyncConflict::query()->where(['source' => self::SOURCE, 'source_type' => $type, 'source_id' => $id])->update(['resolved_at' => now()]); }
}

class RppSyncMappingException extends RuntimeException
{
    public function __construct(string $message, public readonly array $details = []) { parent::__construct($message); }
}
