<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use DomainException;

/** Positions, type, merges, rules and pictures come from the actual Excel sheets. */
class ReportCardTemplateRenderer
{
    public function page(array $data): array
    {
        $semester = $data['semester'] === 'genap' ? 2 : 1;
        $layout = json_decode(file_get_contents(resource_path('report-cards/layout-v1.json')), true, flags: JSON_THROW_ON_ERROR)[$semester];
        $adviceOpeningAddress = $semester === 1 ? 'A43' : 'A45';
        $adviceBodyAddress = $semester === 1 ? 'A44' : 'A46';
        $adviceBody = collect($layout['cells'])->firstWhere('address', $adviceBodyAddress);
        $extraSubjects = max(0, count($data['subjects']) - 6);
        $extraPractice = max(0, count($data['practice']) - 1);
        $subjectHeight = $layout['rows'][22] - $layout['rows'][21];
        $practiceHeight = $layout['rows'][34] - $layout['rows'][33];
        $shift = fn ($row) => ($row > 21 ? $extraSubjects * $subjectHeight : 0) + ($row > 33 ? $extraPractice * $practiceHeight : 0);
        $cells = $layout['cells'];
        foreach ($cells as $index => &$cell) {
            if ($cell['address'] === $adviceBodyAddress) {
                unset($cells[$index]);

                continue;
            }
            if ($cell['address'] === $adviceOpeningAddress) {
                $cell['text'] = rtrim($cell['text'])."\n".$adviceBody['text'];
                $cell['height'] = $adviceBody['y'] + $adviceBody['height'] - $cell['y'];
                $cell['borders']['bottom'] = $adviceBody['borders']['bottom'];
                $cell['wrap'] = true;
                $cell['is_advice'] = true;
            }
            $cell['y'] += $shift($cell['row']);
        }
        unset($cell);
        for ($i = 0; $i < $extraSubjects; $i++) {
            foreach ($layout['cells'] as $cell) {
                if ($cell['row'] === 21) {
                    $cell['y'] += ($i + 1) * $subjectHeight;
                    $cell['subject_index'] = 6 + $i;
                    $cells[] = $cell;
                }
            }
        }
        for ($i = 0; $i < $extraPractice; $i++) {
            foreach ($layout['cells'] as $cell) {
                if ($cell['row'] === 33) {
                    $cell['y'] += $extraSubjects * $subjectHeight + ($i + 1) * $practiceHeight;
                    $cell['practice_index'] = 1 + $i;
                    $cells[] = $cell;
                }
            }
        }
        $options = $data['options'];
        $rank = (int) $data['rank'];
        $note = $options['note'] ?: 'Alhamdulillah Ananda '.$data['student_name']
            .($rank >= 1 && $rank <= (($data['note_policy'] ?? '') === 'top-three-v2' ? 3 : ($semester === 1 ? 2 : 3)) ? ' mendapat peringkat ke '.$rank : ' telah menyelesaikan KBM pada semester ini')
            .'. Semoga apa yang didapatkan menjadi ilmu yang bermanfaat.';
        $date = empty($options['issue_date']) ? 'Tanggal belum diisi' : CarbonImmutable::parse($options['issue_date'])->locale('id')->translatedFormat('d F Y');
        $dateAddress = $semester === 1 ? 'N47' : 'N49';
        $values = [
            $dateAddress => $options['issue_place'].', '.$date,
            'D8' => $data['student_name'], 'D9' => $data['student_nis'], 'D10' => $data['classroom_name'],
            'Q8' => $semester === 1 ? '1 (Satu)' : '2 (Dua)', 'Q9' => $data['academic_year'],
            'I22' => $this->number($data['subject_average'], 2),
            'A26' => $data['tahfidz']['tahfidz_juz'] ?? '—',
            'J26' => $this->number($data['tahfidz']['tahfidz_uas']),
            'J28' => $this->number($data['tahfidz']['tahfidz_sabaq']),
            'J29' => $this->number($data['tahfidz']['tahfidz_sabqi']),
            'J30' => $this->number($data['tahfidz']['tahfidz_manzil']),
            'J35' => $this->number($data['total']), 'A36' => $note,
            'F39' => $data['attendance']['sick_count'], 'F40' => $data['attendance']['permission_count'], 'F41' => $data['attendance']['absent_count'],
        ];
        if ($semester === 1) {
            $values += ['N53' => $options['homeroom_name'] ?? '—', 'H52' => $options['principal_title'], 'H56' => $options['principal_name']];
        } else {
            $promotion = $options['promotion_status'];
            $values += ['N55' => $options['homeroom_name'] ?? '—', 'H54' => $options['principal_title'], 'H58' => $options['principal_name'],
                'N42' => $promotion === 'promoted' ? $options['next_class'] : ($promotion === 'graduated' ? 'Lulus' : '—'),
                'N43' => $promotion === 'retained' ? $data['classroom_name'] : '—'];
        }
        $words = app(ReportCardTemplateData::class);
        foreach ($cells as &$cell) {
            $cell['text'] = $values[$cell['address']] ?? $cell['text'];
            if ($cell['address'] === $dateAddress) {
                $cell['x'] = $adviceBody['x'];
                $cell['width'] = $adviceBody['width'];
                $cell['align'] = 'right';
                $cell['is_issue_date'] = true;
            }
            if ($cell['row'] >= 16 && $cell['row'] <= 21) {
                $i = $cell['subject_index'] ?? ($cell['row'] - 16);
                $line = $data['subjects'][$i] ?? null;
                $cell['text'] = $line ? match ($cell['col']) {
                    1 => $i + 1, 2 => $line['name'], 5 => $line['arabic_name'] ?? '',
                    8 => $this->number($line['kkm']), 9 => $this->number($line['score']),
                    10 => $words->words($line['score']), 17 => $this->number($line['class_average']), default => '',
                } : '';
            }
            if ($cell['row'] === 33) {
                $line = $data['practice'][$cell['practice_index'] ?? 0] ?? null;
                $cell['text'] = match ($cell['col']) {
                    1 => $line ? ($line['material'] ?: $line['name']) : '—', 10 => $this->number($line['score'] ?? null), default => ''
                };
            }
            if (in_array($cell['address'], ['D8', 'D9', 'D10'])) {
                $cell['width'] = $this->x($layout, 'N8') - $cell['x'];
            }
            if (in_array($cell['address'], ['A8', 'A9', 'A10'])) {
                $cell['width'] = $this->x($layout, 'C8') - $cell['x'];
            }
            if (in_array($cell['address'], ['N8', 'N9'])) {
                $cell['width'] = $this->x($layout, 'P8') - $cell['x'];
            }
        }
        unset($cell);
        // Long assigned subject names, tested materials and teacher notes need
        // taller rows. Keep every following block and signature aligned.
        $growth = [];
        foreach ($cells as $cell) {
            $dynamicText = ($cell['row'] >= 16 && $cell['row'] <= 21 && $cell['col'] === 2)
                || in_array($cell['address'], ['A26', 'A33', 'A36'], true);
            if (! $dynamicText || $cell['text'] === '') {
                continue;
            }
            $capacity = max(1, (int) floor(($cell['width'] - 4) / ($cell['size'] * 4 / 3 * .48)));
            $lineCount = 1;
            $length = 0;
            foreach (preg_split('/\s+/u', (string) $cell['text']) as $word) {
                $wordLength = mb_strlen($word);
                if ($length && $length + 1 + $wordLength > $capacity) {
                    $lineCount++;
                    $length = 0;
                }
                $lineCount += max(0, (int) ceil($wordLength / $capacity) - 1);
                $length += ($length ? 1 : 0) + $wordLength;
            }
            $required = $lineCount * $cell['size'] * 4 / 3 * 1.12 + 2;
            $key = (string) $cell['y'];
            $growth[$key] = max($growth[$key] ?? 0, max(0, $required - $cell['height']));
        }
        $offset = fn ($y) => array_sum(array_filter($growth, fn ($key) => (float) $key < $y, ARRAY_FILTER_USE_KEY));
        foreach ($cells as &$cell) {
            $y = $cell['y'];
            $cell['y'] += $offset($y);
            $cell['height'] += $growth[(string) $y] ?? 0;
        }
        unset($cell);
        $height = $layout['height'] + $extraSubjects * $subjectHeight + $extraPractice * $practiceHeight + array_sum($growth);
        $paper = config('report-cards.paper');
        $widthMm = $paper['width_mm'] - 2 * $paper['margin_mm'];
        $heightMm = $paper['height_mm'] - $paper['top_mm'] - $paper['bottom_mm'];
        $baseScale = $semester === 1 ? 1.06 : 1.03;
        $scale = min($baseScale, $widthMm * 96 / 25.4 / $layout['width'], $heightMm * 96 / 25.4 / $height);
        if ($scale < $baseScale * .8) {
            throw new DomainException('Jumlah mapel melebihi kapasitas satu lembar F4. Siapkan versi template lebih panjang sebelum mencetak.');
        }
        $images = [];
        $principalTitle = collect($cells)->firstWhere('address', $semester === 1 ? 'H52' : 'H54');
        $principalName = collect($cells)->firstWhere('address', $semester === 1 ? 'H56' : 'H58');
        foreach ($layout['images'] as $img) {
            if ($img['signature'] && $options['principal_name'] !== config('report-cards.principal_name')) {
                continue;
            }
            $file = $img['signature'] ? config('report-cards.principal_signature_file') : config('report-cards.logo_file');
            if ($img['signature']) {
                // Fit the original transparent asset between the title and name.
                // Use the final cell positions so extra subject rows stay aligned.
                [$sourceWidth, $sourceHeight] = getimagesize(resource_path('report-cards/'.$file));
                // Use the blank padding around the centered text as well. This
                // enlarges the signature about 10% without moving either label.
                $titleLineHeight = $principalTitle['size'] * 4 / 3 * 1.12;
                $nameLineHeight = $principalName['size'] * 4 / 3 * 1.12;
                $top = $principalTitle['y'] + ($principalTitle['height'] + $titleLineHeight) / 2 + 1;
                $bottom = $principalName['y'] + ($principalName['height'] - $nameLineHeight) / 2 - 1;
                $availableHeight = max(1, $bottom - $top);
                $imageScale = min(120 / $sourceWidth, $availableHeight / $sourceHeight);
                $img['width'] = $sourceWidth * $imageScale;
                $img['height'] = $sourceHeight * $imageScale;
                $img['x'] = $principalName['x'] + ($principalName['width'] - $img['width']) / 2;
                $img['y'] = $top + ($availableHeight - $img['height']) / 2;
            } else {
                $img['y'] += $shift($img['row']);
                $img['y'] += $offset($img['y']);
            }
            $img['src'] = 'data:image/png;base64,'.base64_encode(file_get_contents(resource_path('report-cards/'.$file)));
            $images[] = $img;
        }

        return ['sheet' => $layout['sheet'], 'cells' => $cells, 'images' => $images, 'scale' => $scale, 'width' => $layout['width'], 'height' => $height,
            'paper' => $paper, 'student_name' => $data['student_name'], 'missing' => $words->missing($data)];
    }

    private function x(array $layout, string $address): float
    {
        return collect($layout['cells'])->firstWhere('address', $address)['x'];
    }

    private function number(mixed $value, int $precision = 1): string
    {
        return $value === null ? '—' : rtrim(rtrim(number_format((float) $value, $precision, ',', ''), '0'), ',');
    }
}
