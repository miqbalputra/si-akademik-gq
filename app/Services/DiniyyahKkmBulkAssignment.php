<?php

namespace App\Services;

use App\Models\AcademicTerm;
use App\Models\DiniyyahClassSubject;
use App\Models\DiniyyahKkmScope;
use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DiniyyahKkmBulkAssignment
{
    public function __construct(private DiniyyahKkmAssignmentRoster $roster, private DiniyyahKkmResolver $resolver, private DiniyyahKkmAccess $access, private DiniyyahKkmWorkflow $workflow) {}

    public function previewToken(AcademicTerm $term, array $rows, User $actor): string
    {
        abort_unless($this->access->manages($actor), 403);

        return Crypt::encryptString(json_encode([
            'term' => $term->id, 'actor' => $actor->id, 'expires' => now()->addHour()->timestamp,
            'rows' => collect($rows)->filter(fn ($r) => ! $r['assigned_teacher_id'] && ! $r['blocked'])->pluck('fingerprint', 'class_subject_id')->all(),
        ], JSON_THROW_ON_ERROR));
    }

    public function apply(int $termId, array $entries, string $token, User $actor): array
    {
        abort_unless($this->access->manages($actor), 403);
        try {
            $preview = json_decode(Crypt::decryptString($token), true, 512, JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException) {
            $this->invalid('Pratinjau tidak valid. Buka ulang Tetapkan Massal.');
        }
        if (($preview['term'] ?? null) !== $termId || ($preview['actor'] ?? null) !== $actor->id || ($preview['expires'] ?? 0) < now()->timestamp) {
            $this->invalid('Pratinjau kedaluwarsa atau tidak sesuai. Buka ulang Tetapkan Massal.');
        }
        if (! $entries || count($entries) > 500 || count(array_unique(array_column($entries, 'class_subject_id'))) !== count($entries)) {
            $this->invalid('Pilih setidaknya satu baris lengkap tanpa duplikasi.');
        }
        foreach ($entries as $entry) {
            if (! isset($preview['rows'][$entry['class_subject_id'] ?? '']) || ! in_array($entry['gender'] ?? null, ['male', 'female'], true) || empty($entry['teacher_id'])) {
                $this->invalid('Pilihan tidak lengkap atau berada di luar pratinjau. Pilih gender dan guru pada setiap baris terpilih.');
            }
        }
        $entries = collect($entries)->sortBy('class_subject_id')->values()->all();

        return DB::transaction(function () use ($termId, $entries, $preview, $actor) {
            $term = AcademicTerm::whereKey($termId)->lockForUpdate()->firstOrFail();
            if (! $term->kkm_workflow_enabled) {
                $this->invalid('Alur KKM semester ini belum aktif. Semester lama tidak diaktifkan melalui penetapan massal.');
            }
            $subjects = DiniyyahClassSubject::whereIn('id', array_column($entries, 'class_subject_id'))->orderBy('id')->get()->keyBy('id');
            $scopeIds = [];
            foreach ($entries as $entry) {
                $subject = $subjects->get($entry['class_subject_id']);
                if (! $subject || ! $subject->is_active || $subject->classroomTerm?->academic_term_id !== $term->id || ! ($scope = $this->resolver->scopeFor($subject, true))) {
                    $this->invalid('Data kelas/mapel telah berubah. Muat ulang pratinjau.');
                }
                $scopeIds[] = $scope->id;
            }
            // The same ID order is used by ledger/report finalization. Lock all before calling assign().
            DiniyyahKkmScope::whereIn('id', $scopeIds)->orderBy('id')->lockForUpdate()->get();
            $current = collect($this->roster->forTerm($term)['rows'])->keyBy('class_subject_id');
            $assigned = $unchanged = 0;
            foreach ($entries as $entry) {
                $row = $current->get($entry['class_subject_id']);
                $label = $row ? $row['subject'].' · Mustawa '.$row['mustawa'] : 'Kelas/mapel';
                if (! $row || $row['blocked']) {
                    $this->invalid($label.': '.($row['blocked'] ?? 'Data telah berubah. Muat ulang pratinjau.'));
                }
                if ($row['assigned_teacher_id']) {
                    if ((int) $row['assigned_teacher_id'] === (int) $entry['teacher_id']) {
                        $unchanged++;

                        continue; // Safe retry: never reset a draft or resend a notification.
                    }
                    $this->invalid($label.': penyusun sudah ditetapkan oleh pengelola lain. Tidak ada tugas yang ditimpa. Muat ulang pratinjau.');
                }
                if (! hash_equals($preview['rows'][$entry['class_subject_id']], $row['fingerprint'])) {
                    $this->invalid($label.': data pengajar/kelas/jadwal telah berubah. Muat ulang pratinjau.');
                }
                $teacher = collect($row['teachers'])->firstWhere('id', (int) $entry['teacher_id']);
                if (! $teacher || $teacher['gender'] !== $entry['gender']) {
                    $this->invalid($label.': pilih guru yang memenuhi penugasan dan gender pada baris tersebut.');
                }
                $this->workflow->assign($subjects->get($entry['class_subject_id']), (int) $entry['teacher_id'], $actor);
                $assigned++;
            }

            return compact('assigned', 'unchanged');
        }, 3);
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['kkm' => $message]);
    }
}
