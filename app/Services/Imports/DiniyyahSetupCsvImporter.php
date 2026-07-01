<?php

namespace App\Services\Imports;

use App\Models\AcademicTerm;
use App\Models\ClassroomTerm;
use App\Models\DiniyyahAssessmentSet;
use App\Models\DiniyyahClassSubject;
use App\Models\DiniyyahScoreComponent;
use App\Models\DiniyyahSubject;
use App\Models\User;
use App\Services\DiniyyahAssessmentComponentBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DiniyyahSetupCsvImporter
{
    /** @var array<int, string> */
    private const REQUIRED_HEADERS = [
        'tahun_ajaran',
        'periode',
        'kelas_periode',
        'kode_mapel',
        'nama_mapel',
        'judul_set_nilai',
    ];

    public function __construct(
        private readonly DiniyyahAssessmentComponentBuilder $componentBuilder,
    ) {}

    public function import(string $path, ?User $user = null): DiniyyahSetupImportResult
    {
        $result = new DiniyyahSetupImportResult;

        if (! is_file($path) || ! is_readable($path)) {
            $result->errors[] = 'File import tidak bisa dibaca.';

            return $result;
        }

        $handle = fopen($path, 'r');

        if ($handle === false) {
            $result->errors[] = 'File import gagal dibuka.';

            return $result;
        }

        $headers = $this->readHeaders($handle);

        if ($headers === []) {
            $result->errors[] = 'Header CSV kosong.';
        }

        foreach (self::REQUIRED_HEADERS as $requiredHeader) {
            if (! in_array($requiredHeader, $headers, true)) {
                $result->errors[] = "Kolom wajib '{$requiredHeader}' belum ada.";
            }
        }

        if ($result->hasErrors()) {
            fclose($handle);

            return $result;
        }

        DB::transaction(function () use ($handle, $headers, $result, $user): void {
            $lineNumber = 1;

            while (($row = fgetcsv($handle)) !== false) {
                $lineNumber++;

                if ($this->isEmptyRow($row)) {
                    continue;
                }

                $data = $this->combineRow($headers, $row);
                $rowErrors = $this->validateRow($data, $lineNumber);

                if ($rowErrors !== []) {
                    array_push($result->errors, ...$rowErrors);

                    continue;
                }

                $this->importRow($data, $result, $user, $lineNumber);
                $result->processedRows++;
            }
        });

        fclose($handle);

        return $result;
    }

    /** @return array<int, string> */
    private function readHeaders(mixed $handle): array
    {
        $headers = fgetcsv($handle);

        if ($headers === false) {
            return [];
        }

        return array_map(fn (?string $header): string => Str::of((string) $header)
            ->replace("\xEF\xBB\xBF", '')
            ->trim()
            ->lower()
            ->snake()
            ->toString(), $headers);
    }

    /** @param array<int, string|null> $row */
    private function isEmptyRow(array $row): bool
    {
        return collect($row)->every(fn ($value): bool => trim((string) $value) === '');
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, string|null>  $row
     * @return array<string, string>
     */
    private function combineRow(array $headers, array $row): array
    {
        $data = [];

        foreach ($headers as $index => $header) {
            $data[$header] = trim((string) ($row[$index] ?? ''));
        }

        return $data;
    }

    /**
     * @param  array<string, string>  $data
     * @return array<int, string>
     */
    private function validateRow(array $data, int $lineNumber): array
    {
        $errors = [];

        foreach (self::REQUIRED_HEADERS as $header) {
            if (($data[$header] ?? '') === '') {
                $errors[] = "Baris {$lineNumber}: kolom '{$header}' wajib diisi.";
            }
        }

        if (($data['component_code'] ?? '') !== '' && (($data['component_name'] ?? '') === '' || ($data['component_group'] ?? '') === '')) {
            $errors[] = "Baris {$lineNumber}: component_name dan component_group wajib diisi jika component_code diisi.";
        }

        if (($data['component_group'] ?? '') !== '' && ! in_array($data['component_group'], ['daily', 'exam', 'final'], true)) {
            $errors[] = "Baris {$lineNumber}: component_group harus daily, exam, atau final.";
        }

        return $errors;
    }

    /** @param array<string, string> $data */
    private function importRow(array $data, DiniyyahSetupImportResult $result, ?User $user, int $lineNumber): void
    {
        $classroomTerm = $this->findClassroomTerm($data);

        if (! $classroomTerm) {
            $result->errors[] = "Baris {$lineNumber}: kelas_periode '{$data['kelas_periode']}' untuk {$data['tahun_ajaran']} {$data['periode']} tidak ditemukan.";

            return;
        }

        $subject = DiniyyahSubject::withTrashed()->where('code', $data['kode_mapel'])->first();
        $subjectPayload = [
            'name' => $data['nama_mapel'],
            'arabic_name' => ($data['nama_arab'] ?? '') ?: null,
            'default_assessment_method' => ($data['assessment_method'] ?? '') ?: 'weighted',
            'sort_order' => ($data['urutan_mapel'] ?? '') !== '' ? (int) $data['urutan_mapel'] : 0,
            'is_active' => $this->truthy($data['mapel_aktif'] ?? '1'),
        ];

        if ($subject) {
            $subject->restore();
            $subject->update($subjectPayload);
            $result->subjectsUpdated++;
        } else {
            $subject = DiniyyahSubject::create(['code' => $data['kode_mapel']] + $subjectPayload);
            $result->subjectsCreated++;
        }

        $classSubject = DiniyyahClassSubject::withTrashed()->firstOrCreate(
            [
                'classroom_term_id' => $classroomTerm->id,
                'subject_id' => $subject->id,
            ],
            [
                'assessment_method' => ($data['assessment_method'] ?? '') ?: $subject->default_assessment_method,
                'kkm' => ($data['kkm'] ?? '') !== '' ? (float) $data['kkm'] : null,
                'daily_weight' => ($data['daily_weight'] ?? '') !== '' ? (int) $data['daily_weight'] : 40,
                'exam_weight' => ($data['exam_weight'] ?? '') !== '' ? (int) $data['exam_weight'] : 60,
                'appears_on_ledger' => $this->truthy($data['masuk_leger'] ?? '1'),
                'appears_on_report' => $this->truthy($data['masuk_rapor'] ?? '1'),
                'sort_order' => ($data['urutan_mapel'] ?? '') !== '' ? (int) $data['urutan_mapel'] : 0,
                'is_active' => true,
                'created_by' => $user?->id,
                'updated_by' => $user?->id,
            ],
        );

        if ($classSubject->trashed()) {
            $classSubject->restore();
        }

        if ($classSubject->wasRecentlyCreated) {
            $result->classSubjectsCreated++;
        } else {
            $classSubject->update([
                'assessment_method' => ($data['assessment_method'] ?? '') ?: $classSubject->assessment_method,
                'kkm' => ($data['kkm'] ?? '') !== '' ? (float) $data['kkm'] : $classSubject->kkm,
                'daily_weight' => ($data['daily_weight'] ?? '') !== '' ? (int) $data['daily_weight'] : $classSubject->daily_weight,
                'exam_weight' => ($data['exam_weight'] ?? '') !== '' ? (int) $data['exam_weight'] : $classSubject->exam_weight,
                'appears_on_ledger' => $this->truthy($data['masuk_leger'] ?? ($classSubject->appears_on_ledger ? '1' : '0')),
                'appears_on_report' => $this->truthy($data['masuk_rapor'] ?? ($classSubject->appears_on_report ? '1' : '0')),
                'sort_order' => ($data['urutan_mapel'] ?? '') !== '' ? (int) $data['urutan_mapel'] : $classSubject->sort_order,
                'is_active' => $this->truthy($data['mapel_kelas_aktif'] ?? ($classSubject->is_active ? '1' : '0')),
                'updated_by' => $user?->id,
            ]);
            $result->classSubjectsUpdated++;
        }

        $assessmentSet = DiniyyahAssessmentSet::withTrashed()
            ->where('diniyyah_class_subject_id', $classSubject->id)
            ->where('title', $data['judul_set_nilai'])
            ->first();
        $assessmentPayload = [
            'tested_material' => ($data['materi'] ?? '') ?: null,
            'assessment_method' => ($data['assessment_method'] ?? '') ?: $classSubject->assessment_method,
            'kkm' => ($data['kkm'] ?? '') !== '' ? (float) $data['kkm'] : $classSubject->kkm,
            'daily_weight' => ($data['daily_weight'] ?? '') !== '' ? (int) $data['daily_weight'] : $classSubject->daily_weight,
            'exam_weight' => ($data['exam_weight'] ?? '') !== '' ? (int) $data['exam_weight'] : $classSubject->exam_weight,
            'appears_on_ledger' => $this->truthy($data['masuk_leger'] ?? '1'),
            'appears_on_report' => $this->truthy($data['masuk_rapor'] ?? '1'),
            'sort_order' => ($data['urutan_set'] ?? '') !== '' ? (int) $data['urutan_set'] : $classSubject->sort_order,
            'status' => ($data['status_set'] ?? '') ?: 'active',
            'updated_by' => $user?->id,
        ];

        if ($assessmentSet) {
            $assessmentSet->restore();
            $assessmentSet->update($assessmentPayload);
            $result->assessmentSetsUpdated++;
        } else {
            $assessmentSet = DiniyyahAssessmentSet::create([
                'diniyyah_class_subject_id' => $classSubject->id,
                'title' => $data['judul_set_nilai'],
                'created_by' => $user?->id,
            ] + $assessmentPayload);
            $result->assessmentSetsCreated++;
        }

        if (($data['component_code'] ?? '') !== '') {
            $component = DiniyyahScoreComponent::updateOrCreate(
                [
                    'diniyyah_assessment_set_id' => $assessmentSet->id,
                    'code' => $data['component_code'],
                ],
                [
                    'name' => $data['component_name'],
                    'component_group' => $data['component_group'],
                    'sort_order' => ($data['component_sort'] ?? '') !== '' ? (int) $data['component_sort'] : 0,
                    'is_required' => $this->truthy($data['component_required'] ?? '1'),
                ],
            );

            $component->wasRecentlyCreated ? $result->componentsCreated++ : $result->componentsUpdated++;

            return;
        }

        if ($this->truthy($data['buat_default_komponen'] ?? '1') && $assessmentSet->components()->count() === 0) {
            $before = $assessmentSet->components()->count();
            $this->componentBuilder->createDefaults($assessmentSet);
            $created = $assessmentSet->components()->count() - $before;

            $result->componentsCreated += $created;
            $result->defaultComponentSetsApplied++;
        }
    }

    /** @param array<string, string> $data */
    private function findClassroomTerm(array $data): ?ClassroomTerm
    {
        $semester = $this->normalizeSemester($data['periode']);

        $term = AcademicTerm::query()
            ->whereHas('academicYear', fn ($query) => $query->where('name', $data['tahun_ajaran']))
            ->where(function ($query) use ($data, $semester): void {
                $query->where('semester', $semester)
                    ->orWhere('name', $data['periode']);
            })
            ->first();

        if (! $term) {
            return null;
        }

        return ClassroomTerm::where('academic_term_id', $term->id)
            ->where('name', $data['kelas_periode'])
            ->first();
    }

    private function normalizeSemester(string $value): string
    {
        $normalized = Str::of($value)->lower()->trim()->toString();

        return match ($normalized) {
            'ganjil', 'ganil', 'gasal', '1', 'semester 1' => 'ganjil',
            'genap', '2', 'semester 2' => 'genap',
            default => $normalized,
        };
    }

    private function truthy(?string $value): bool
    {
        return in_array(Str::of((string) $value)->lower()->trim()->toString(), ['1', 'ya', 'yes', 'y', 'true', 'aktif'], true);
    }
}
