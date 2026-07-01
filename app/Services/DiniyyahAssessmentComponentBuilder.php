<?php

namespace App\Services;

use App\Models\DiniyyahAssessmentSet;
use App\Models\DiniyyahScoreComponent;

class DiniyyahAssessmentComponentBuilder
{
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
            ['code' => 'ulangan_harian_1', 'name' => 'Ulangan Harian 1', 'component_group' => 'daily', 'sort_order' => 20],
            ['code' => 'ulangan_harian_2', 'name' => 'Ulangan Harian 2', 'component_group' => 'daily', 'sort_order' => 30],
            ['code' => 'nilai_tugas', 'name' => 'Nilai Tugas', 'component_group' => 'daily', 'sort_order' => 40],
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
