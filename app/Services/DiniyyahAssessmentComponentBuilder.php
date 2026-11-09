<?php

namespace App\Services;

use App\Models\DiniyyahAssessmentSet;
use App\Models\DiniyyahScoreComponent;
use Illuminate\Support\Collection;

class DiniyyahAssessmentComponentBuilder
{
    /** Keep the original component IDs/codes, including the legacy first assignment. */
    public function ordered(DiniyyahAssessmentSet $set): Collection
    {
        return $set->components->sortBy(function ($component) {
            if ($component->code === 'keaktifan_presensi') {
                return [0, 0, $component->id];
            }
            if (preg_match('/^ulangan_harian_(\d+)$/', $component->code, $match)) {
                return [1, (int) $match[1], $component->id];
            }
            if ($component->code === 'nilai_tugas') {
                return [2, 1, $component->id];
            }
            if (preg_match('/^nilai_tugas_(\d+)$/', $component->code, $match)) {
                return [2, (int) $match[1], $component->id];
            }

            return [3, $component->sort_order, $component->id];
        })->values();
    }

    public function nextCode(DiniyyahAssessmentSet $set, string $type): string
    {
        $prefix = $type === 'ulangan' ? 'ulangan_harian' : 'nilai_tugas';
        $numbers = $set->components->map(function ($component) use ($prefix) {
            if ($prefix === 'nilai_tugas' && $component->code === 'nilai_tugas') {
                return 1;
            }

            return preg_match('/^'.preg_quote($prefix, '/').'_(\d+)$/', $component->code, $match) ? (int) $match[1] : 0;
        });

        return $prefix.'_'.(max($type === 'ulangan' ? 2 : 1, $numbers->max() ?? 0) + 1);
    }

    public function createDefaults(DiniyyahAssessmentSet $assessmentSet): void
    {
        $components = match ($assessmentSet->assessment_method) {
            'direct_final' => $this->directFinalComponents(),
            'practical', 'weighted' => $this->weightedComponents(),
            default => $this->weightedComponents(),
        };

        foreach ($components as $component) {
            DiniyyahScoreComponent::updateOrCreate(
                [
                    'diniyyah_assessment_set_id' => $assessmentSet->id,
                    'code' => $component['code'],
                ],
                $component + [
                    'diniyyah_assessment_set_id' => $assessmentSet->id,
                    'is_required' => true,
                ],
            );
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function weightedComponents(): array
    {
        return [
            ['code' => 'keaktifan_presensi', 'name' => 'Keaktifan/Presensi', 'component_group' => 'daily', 'sort_order' => 10],
            ['code' => 'ulangan_harian_1', 'name' => 'Ulangan Harian 1', 'component_group' => 'daily', 'sort_order' => 20, 'is_required' => false],
            ['code' => 'ulangan_harian_2', 'name' => 'Ulangan Harian 2', 'component_group' => 'daily', 'sort_order' => 30, 'is_required' => false],
            ['code' => 'nilai_tugas', 'name' => 'Nilai Tugas 1', 'component_group' => 'daily', 'sort_order' => 40, 'is_required' => false],
            ['code' => 'nilai_ujian_mentah', 'name' => 'Nilai Ujian', 'component_group' => 'exam', 'sort_order' => 50],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function directFinalComponents(): array
    {
        return [
            ['code' => 'nilai_akhir', 'name' => 'Nilai Akhir', 'component_group' => 'final', 'sort_order' => 10],
        ];
    }
}
