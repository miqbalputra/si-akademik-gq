<?php

namespace Tests\Feature;

use App\Models\DiniyyahLedgerCell;
use App\Models\DiniyyahLedgerRow;
use App\Models\DiniyyahLedgerSnapshot;
use App\Services\DiniyyahLedgerPresentation;
use Illuminate\Database\Eloquent\Collection;
use PHPUnit\Framework\TestCase;

class DiniyyahLedgerPresentationTest extends TestCase
{
    public function test_grouping_keeps_saved_kkm_scores_and_totals_without_recalculation_or_model_changes(): void
    {
        $snapshot = new DiniyyahLedgerSnapshot(['status' => 'locked', 'snapshot_data' => ['layout_version' => 'combined-v1', 'columns' => [
            ['key' => 'attendance_sick', 'label' => 'Sakit', 'source_type' => 'student_attendance_recap'],
            ['key' => 'assessment_2', 'label' => 'Penilaian Praktik Ibadah 2', 'subject_name' => 'Praktek Ibadah', 'tested_material' => 'Shalat Jenazah', 'source_type' => 'diniyyah_assessment_set', 'kkm' => 70],
            ['key' => 'assessment_1', 'label' => 'Penilaian Fiqih', 'source_type' => 'diniyyah_assessment_set', 'kkm' => 75],
            ['key' => 'suluk', 'label' => 'Suluk', 'source_type' => 'diniyyah_assessment_set', 'kkm' => 70],
            ['key' => 'tahfidz_juz', 'label' => 'Juz UAS Tahfidz', 'source_type' => 'tahfidz_snapshot', 'numeric' => false],
        ]]]);
        $row = new DiniyyahLedgerRow(['row_number' => 1, 'student_name' => '=Nama Santri', 'student_nis' => '00123']);
        $cells = new Collection;
        foreach (['assessment_1' => 74.5, 'assessment_2' => 82.25, 'suluk' => null, 'attendance_sick' => 0, 'combined_total' => 999.75, 'combined_average' => 99.98, 'combined_rank' => 1] as $key => $value) {
            $cells->push(new DiniyyahLedgerCell(['column_key' => $key, 'value_numeric' => $value]));
        }
        $cells->push(new DiniyyahLedgerCell(['column_key' => 'tahfidz_juz', 'value_text' => 'Juz 28–30']));
        $row->setRelation('cells', $cells);
        $snapshot->setRelation('rows', new Collection([$row]));
        $before = $snapshot->toArray();
        $view = (new DiniyyahLedgerPresentation)->build($snapshot);
        $this->assertSame(['subjects', 'suluk', 'practice', 'tahfidz_exam', 'attendance', 'summary'], $view['groups']->pluck('id')->all());
        $this->assertSame(75, $view['columns']->firstWhere('key', 'assessment_1')['kkm']);
        $values = $view['rows']->first()['values'];
        $this->assertSame('74.5', $values['assessment_1']['display']);
        $this->assertTrue($values['assessment_1']['below']);
        $this->assertSame(['subjects'], $view['rows']->first()['below_groups']);
        $this->assertSame('82.25', $values['assessment_2']['display']);
        $this->assertSame('—', $values['suluk']['display']);
        $this->assertSame('0', $values['attendance_sick']['display']);
        $this->assertFalse($values['attendance_sick']['missing']);
        $this->assertSame('999.75', $values['combined_total']['display']);
        $this->assertSame('1', $values['combined_rank']['display']);
        $this->assertSame('Juz 28–30', $values['tahfidz_juz']['display']);
        $this->assertSame('00123', $view['rows']->first()['nis']);
        $this->assertSame($before, $snapshot->toArray());
    }
}
