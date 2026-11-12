<?php

namespace App\Services;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class DiniyyahKkmCalculator
{
    public const POLICY = 'bands_65_70_75_v1';

    public const CRITERIA = ['complexity' => 'Kompleksitas', 'support' => 'Daya Dukung', 'intake' => 'Intake Peserta Didik'];

    public function calculate(array $input, bool $complete = false): array
    {
        $rules = [];
        foreach (self::CRITERIA as $key => $label) {
            $rules["{$key}.score"] = [$complete ? 'required' : 'nullable', 'integer', 'between:60,100'];
            $rules["{$key}.reason"] = [$complete ? 'required' : 'nullable', 'string', 'max:4000'];
        }
        $values = Validator::make($input, $rules)->validate();
        $criteria = [];
        $scores = [];
        foreach (self::CRITERIA as $key => $label) {
            $score = $values[$key]['score'] ?? null;
            $score = $score === null || $score === '' ? null : (int) $score;
            $reason = trim($values[$key]['reason'] ?? '');
            if ($complete && $reason === '') {
                throw ValidationException::withMessages(["{$key}.reason" => "Alasan {$label} wajib diisi."]);
            }
            $criteria[$key] = ['score' => $score, 'category' => $score === null ? null : $this->category($key, $score), 'reason' => $reason];
            if ($score !== null) {
                $scores[] = $score;
            }
        }
        $average = count($scores) === 3 ? array_sum($scores) / 3 : null;
        $criteria['calculation_policy'] = self::POLICY;

        return ['criteria' => $criteria, 'average' => $average, 'final_kkm' => $average === null ? null : ($average < 70 ? 65 : ($average < 75 ? 70 : 75))];
    }

    public function category(string $criterion, int $score): string
    {
        return match ($criterion) {
            'complexity' => $score <= 65 ? 'Tinggi' : ($score <= 79 ? 'Sedang' : 'Rendah'),
            'support' => $score <= 65 ? 'Kurang Mendukung' : ($score <= 79 ? 'Cukup Mendukung' : 'Sangat Mendukung'),
            'intake' => $score <= 65 ? 'Rendah' : ($score <= 79 ? 'Sedang' : 'Tinggi'),
        };
    }
}
