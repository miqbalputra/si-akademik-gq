<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * One-off identity correction, deliberately not a generic subject merger.
 * Never deletes rows, changes journal assignment IDs, recalculates grades,
 * changes KKM approvals, or sends notifications.
 */
class FiqihSubjectCorrection
{
    private const EDITABLE = ['diniyyah_subjects', 'diniyyah_class_subjects', 'diniyyah_kkm_scopes'];

    private const VOLATILE = ['cache', 'cache_locks', 'sessions', 'jobs', 'job_batches', 'failed_jobs', 'activity_log'];

    public function __construct(private readonly FiqihCorrectionBackup $backup) {}

    public function preview(): array
    {
        return $this->inspect()['preview'];
    }

    public function apply(string $fingerprint, ?array $coolifyBackup = null): array
    {
        if (app()->environment('production') && ! app()->isDownForMaintenance()) {
            throw new RuntimeException('Aktifkan maintenance mode sebelum koreksi production.');
        }

        return DB::transaction(function () use ($fingerprint, $coolifyBackup): array {
            // FK writers also wait on these locks in PostgreSQL. SQLite obtains
            // its write reservation before reading the snapshot.
            if (DB::getDriverName() === 'sqlite') {
                DB::update('UPDATE diniyyah_subjects SET id = id WHERE 1 = 0');
            }
            DB::table('diniyyah_subjects')->orderBy('id')->lockForUpdate()->get();
            DB::table('diniyyah_class_subjects')->orderBy('id')->lockForUpdate()->get();
            DB::table('diniyyah_kkm_scopes')->orderBy('id')->lockForUpdate()->get();

            $state = $this->inspect();
            $preview = $state['preview'];
            if ($preview['blockers']) {
                throw new RuntimeException('Ada konflik. Tidak ada data diubah. Periksa hasil pratinjau.');
            }
            if ($preview['already_corrected']) {
                return ['changed' => false, 'message' => 'Koreksi pada cakupan ini sudah diterapkan.'];
            }
            if (! preg_match('/^[a-f0-9]{64}$/', $fingerprint) || ! hash_equals($preview['fingerprint'], $fingerprint)) {
                throw new RuntimeException('Data berbeda dari pratinjau. Jalankan pratinjau ulang; tidak ada data diubah.');
            }

            // The snapshot includes every old column in the only editable
            // tables and hashes of all other persistent tables.
            $backup = $this->backup->save([
                'operation' => 'fiqih_ibadah_to_fiqih_v1',
                'created_at' => now('Asia/Jakarta')->toIso8601String(),
                'preview' => $preview,
                'before' => $state['editable'],
                'protected' => $state['protected'],
            ], $coolifyBackup);
            $now = now()->toDateTimeString();
            $expected = $state['editable'];
            $classIds = $preview['class_subject_ids'];
            $scopeIds = $preview['kkm_scope_ids'];
            DB::table('diniyyah_class_subjects')->whereIn('id', $classIds)->update(['subject_id' => $preview['target']['id'], 'updated_at' => $now]);
            DB::table('diniyyah_kkm_scopes')->whereIn('id', $scopeIds)->update(['subject_id' => $preview['target']['id'], 'updated_at' => $now]);

            // Keep the old master for provenance and historical name lookups.
            $remaining = DB::table('diniyyah_class_subjects')->where('subject_id', $preview['source']['id'])->exists()
                || DB::table('diniyyah_kkm_scopes')->where('subject_id', $preview['source']['id'])->exists();
            if (! $remaining) {
                DB::table('diniyyah_subjects')->where('id', $preview['source']['id'])->update(['is_active' => false, 'updated_at' => $now]);
            }
            foreach ($expected as $table => &$rows) {
                foreach ($rows as &$row) {
                    if (($table === 'diniyyah_class_subjects' && in_array($row['id'], $classIds, true))
                        || ($table === 'diniyyah_kkm_scopes' && in_array($row['id'], $scopeIds, true))) {
                        $row['subject_id'] = $preview['target']['id'];
                        $row['updated_at'] = $now;
                    }
                    if ($table === 'diniyyah_subjects' && $row['id'] === $preview['source']['id'] && ! $remaining) {
                        $row['is_active'] = false;
                        $row['updated_at'] = $now;
                    }
                }
                unset($row);
            }
            unset($rows);

            foreach ($this->editableRows() as $table => $rows) {
                // Normalize DB boolean representations without relaxing other
                // columns or values in the row comparison.
                if ($this->encodeRows($table, $rows) !== $this->encodeRows($table, $expected[$table])) {
                    throw new RuntimeException('Verifikasi perubahan mapel gagal; transaksi dibatalkan.');
                }
            }
            if ($state['protected'] !== $this->protectedHashes()) {
                throw new RuntimeException('Data jurnal/presensi/nilai atau data terlindungi berubah; transaksi dibatalkan.');
            }
            activity('data-correction')->event('fiqih_unified')->withProperties([
                'source_id' => $preview['source']['id'], 'target_id' => $preview['target']['id'],
                'fingerprint' => $fingerprint, 'backup' => $backup,
                'class_subject_ids' => $classIds, 'kkm_scope_ids' => $scopeIds,
            ])->log('Koreksi satu kali Fiqih Ibadah menjadi Fiqih');

            return ['changed' => true, 'backup' => $backup, 'class_subjects_updated' => count($classIds),
                'kkm_scopes_updated' => count($scopeIds), 'protected_tables_unchanged' => count($state['protected']),
                'message' => 'Identitas mapel disatukan. Angka KKM, ACC, jurnal, presensi, dan nilai lama tidak diubah.'];
        });
    }

    private function inspect(): array
    {
        if (! in_array(DB::getDriverName(), ['sqlite', 'pgsql'], true)) {
            throw new RuntimeException('Koreksi hanya mendukung PostgreSQL dan SQLite.');
        }
        $source = $this->master('fiqih ibadah');
        $target = $this->master('fiqih');
        if ($source['id'] === $target['id'] || ! $target['is_active']) {
            throw new RuntimeException('Mapel tujuan Fiqih harus aktif dan berbeda dari sumber.');
        }
        $editable = $this->editableRows();
        $classes = collect($editable['diniyyah_class_subjects']);
        $sourceClasses = $classes->where('subject_id', $source['id']);
        $targetClasses = $classes->where('subject_id', $target['id']);
        $scopes = collect($editable['diniyyah_kkm_scopes']);
        $sourceScopes = $scopes->where('subject_id', $source['id']);
        $targetScopes = $scopes->where('subject_id', $target['id']);
        $blockers = [];
        foreach ($sourceClasses as $row) {
            if ($targetClasses->contains('classroom_term_id', $row['classroom_term_id'])) {
                $blockers[] = 'Kelas #'.$row['classroom_term_id'].' memiliki kedua mapel, termasuk arsip. Relasi tidak digabung otomatis.';
            }
        }
        foreach ($sourceScopes as $row) {
            if ($targetScopes->contains(fn ($other) => $other['academic_term_id'] === $row['academic_term_id'] && $other['mustawa'] === $row['mustawa'])) {
                $blockers[] = 'KKM semester #'.$row['academic_term_id'].' Mustawa '.$row['mustawa'].' sudah ada pada kedua mapel. Riwayat/penyusun tidak ditimpa.';
            }
        }
        $contexts = DB::table('classroom_terms as ct')->join('classrooms as c', 'c.id', '=', 'ct.classroom_id')
            ->get(['ct.id', 'ct.academic_term_id', 'c.level_name'])->keyBy('id');
        $groupKey = function (array $row) use ($contexts): ?string {
            $context = $contexts->get($row['classroom_term_id']);
            $level = DiniyyahKkmResolver::mustawa($context?->level_name);

            return $context && $level ? $context->academic_term_id.':'.$level : null;
        };
        $sourceKeys = $sourceScopes->map(fn ($row) => $row['academic_term_id'].':'.$row['mustawa'])->all();
        foreach ($sourceClasses as $row) {
            $key = $groupKey($row);
            if ($key === null) {
                $blockers[] = 'Jenjang kelas #'.$row['classroom_term_id'].' belum dikenali untuk KKM. Lengkapi Mustawa sebelum koreksi.';
            } else {
                $sourceKeys[] = $key;
            }
        }
        $affectedClasses = $sourceClasses->concat($targetClasses->filter(fn ($row) => in_array($groupKey($row), $sourceKeys, true)));
        $kkmValues = [];
        foreach ($affectedClasses as $row) {
            if ($row['kkm'] !== null && ($key = $groupKey($row)) !== null) {
                $kkmValues[$key][] = (float) $row['kkm'];
            }
        }
        $activeIds = $scopes->whereIn('subject_id', [$source['id'], $target['id']])->pluck('active_revision_id')->filter()->all();
        $activeRevisions = DB::table('diniyyah_kkm_revisions')->whereIn('id', $activeIds)->get()->keyBy('id');
        foreach ($scopes->whereIn('subject_id', [$source['id'], $target['id']]) as $row) {
            $key = $row['academic_term_id'].':'.$row['mustawa'];
            $revision = $activeRevisions->get($row['active_revision_id']);
            if (in_array($key, $sourceKeys, true) && $revision?->final_kkm !== null) {
                $kkmValues[$key][] = (float) $revision->final_kkm;
            }
        }
        foreach ($kkmValues as $key => $values) {
            if (count(array_unique($values)) > 1) {
                $blockers[] = 'Angka KKM berbeda pada semester:Mustawa '.$key.'. Tidak memilih atau menghitung ulang KKM secara otomatis.';
            }
        }
        $affectedClassIds = $affectedClasses->pluck('classroom_term_id')->unique()->all();
        foreach (['diniyyah_ledger_snapshots', 'report_cards'] as $table) {
            $query = DB::table($table)->whereIn('classroom_term_id', $affectedClassIds)->whereIn('status', ['locked', 'published']);
            if ($table === 'report_cards') {
                $query->where('report_type', 'diniyyah');
            }
            foreach ($query->pluck('id') as $id) {
                $blockers[] = $table.' #'.$id.' terkunci/terbit. Dokumen dan semester historis tidak diubah otomatis.';
            }
        }
        $protected = $this->protectedHashes();
        $assignmentIds = DB::table('diniyyah_teacher_assignments')->whereIn('diniyyah_class_subject_id', $sourceClasses->pluck('id'))->pluck('id');
        $journalIds = DB::table('diniyyah_class_journals')->whereIn('diniyyah_teacher_assignment_id', $assignmentIds)->pluck('id');
        $preview = [
            'source' => $source, 'target' => $target, 'scope' => 'all_terms_including_archived',
            'class_subject_ids' => $sourceClasses->pluck('id')->values()->all(),
            'kkm_scope_ids' => $sourceScopes->pluck('id')->values()->all(),
            'preserved_source_data' => [
                'teacher_assignments' => $assignmentIds->count(), 'journals_including_archives' => $journalIds->count(),
                'journal_absences' => DB::table('diniyyah_class_journal_absences')->whereIn('diniyyah_class_journal_id', $journalIds)->count(),
                'assessment_sets_including_archives' => DB::table('diniyyah_assessment_sets')->whereIn('diniyyah_class_subject_id', $sourceClasses->pluck('id'))->count(),
                'kkm_revisions' => DB::table('diniyyah_kkm_revisions')->whereIn('diniyyah_kkm_scope_id', $sourceScopes->pluck('id'))->count(),
            ],
            'blockers' => $blockers,
            'already_corrected' => ! $source['is_active'] && $sourceClasses->isEmpty() && $sourceScopes->isEmpty(),
            'note' => 'Tidak mengganti angka/ACC KKM otomatis. Tinjau penugasan dan lakukan revisi KKM melalui alur KKM setelah identitas disatukan.',
        ];
        $preview['fingerprint'] = hash('sha256', json_encode([
            DB::getDriverName(), DB::connection()->getDatabaseName(), $preview, $editable, $protected,
        ], JSON_THROW_ON_ERROR));

        return compact('preview', 'editable', 'protected');
    }

    private function master(string $name): array
    {
        $rows = DB::table('diniyyah_subjects')->whereRaw('LOWER(TRIM(name)) = ?', [$name])->get();
        if ($rows->count() !== 1 || $rows->first()->deleted_at !== null) {
            throw new RuntimeException('Diperlukan tepat satu master tidak terarsip untuk "'.$name.'". Data belum diubah.');
        }

        return (array) $rows->first();
    }

    private function editableRows(): array
    {
        $rows = [];
        foreach (self::EDITABLE as $table) {
            $rows[$table] = DB::table($table)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
        }

        return $rows;
    }

    private function encodeRows(string $table, array $rows): string
    {
        if ($table === 'diniyyah_subjects') {
            foreach ($rows as &$row) {
                $row['is_active'] = (bool) $row['is_active'];
            }
            unset($row);
        }

        return json_encode($rows, JSON_THROW_ON_ERROR);
    }

    private function protectedHashes(): array
    {
        $result = [];
        foreach (Schema::getTables() as $table) {
            $name = $table['name'];
            if (in_array($name, [...self::EDITABLE, ...self::VOLATILE], true)) {
                continue;
            }
            $qualified = $table['schema_qualified_name'];
            $primary = collect(Schema::getIndexes($qualified))->firstWhere('primary', true);
            $query = DB::table($qualified);
            foreach ($primary['columns'] ?? [] as $column) {
                $query->orderBy($column);
            }
            if (! $primary) {
                // Rare legacy tables without a primary key: sort serialized
                // rows, rather than relying on unspecified database row order.
                $rows = $query->get()->map(fn ($row) => json_encode($row, JSON_THROW_ON_ERROR))->sort()->values()->all();
                $result[$qualified] = ['count' => count($rows), 'sha256' => hash('sha256', implode("\n", $rows))];

                continue;
            }
            $context = hash_init('sha256');
            $count = 0;
            foreach ($query->cursor() as $row) {
                hash_update($context, json_encode($row, JSON_THROW_ON_ERROR)."\n");
                $count++;
            }
            $result[$qualified] = ['count' => $count, 'sha256' => hash_final($context)];
        }
        ksort($result);

        return $result;
    }
}
