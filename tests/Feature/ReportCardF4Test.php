<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\ClassEnrollment;
use App\Models\Classroom;
use App\Models\ClassroomTerm;
use App\Models\DiniyyahLedgerSnapshot;
use App\Models\HomeroomAssignment;
use App\Models\ReportCard;
use App\Models\School;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Services\DiniyyahCombinedLedger;
use App\Services\DiniyyahLedgerWorkflow;
use App\Services\ReportCardAutomation;
use App\Services\ReportCardPrintOptions;
use App\Services\ReportCardPrintService;
use App\Services\ReportCardTemplateData;
use App\Services\ReportCardTemplateRenderer;
use App\Services\ReportCardWorkflow;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReportCardF4Test extends TestCase
{
    use RefreshDatabase;

    public function test_locking_combined_ledger_automatically_creates_a_complete_frozen_report(): void
    {
        [$snapshot, $user] = $this->context();
        $snapshot->update(['status' => 'draft']);
        $this->mock(DiniyyahCombinedLedger::class)->shouldReceive('ensureCurrent')->once();
        app(DiniyyahLedgerWorkflow::class)->lock($snapshot, $user);
        $this->assertSame('locked', $snapshot->fresh()->status);
        $card = ReportCard::firstOrFail();
        $data = app(ReportCardPrintService::class)->data($card);
        $this->assertSame('590.25', $card->total_score);
        $this->assertSame(2, $card->rank_in_class);
        $this->assertCount(2, $data['subjects']);
        $this->assertCount(1, $data['practice']);
        $this->assertSame(85.13, $data['subject_average']);
        $this->assertSame(70, (int) $data['subjects'][0]['kkm']);
        $this->assertSame(80.3, $data['subjects'][0]['class_average']);
        $this->assertSame('00123', $data['student_nis']);
        $this->assertSame(0, $data['attendance']['permission_count']);
        $this->assertSame([], app(ReportCardTemplateData::class)->missing($data));

        // Mutating source data after capture cannot affect printable data.
        $snapshot->rows()->first()->cells()->where('column_key', 'assessment_1001')->update(['value_numeric' => 1]);
        $this->assertSame($data, app(ReportCardPrintService::class)->data($card->fresh()));
        $this->actingAs($user)->get(route('report-cards.classroom', $snapshot))->assertOk()->assertSee('Siap cetak F4');
        $this->actingAs($user)->get(route('report-cards.show', $card))->assertOk()->assertSee('Pelengkap rapor');
        $this->actingAs($user)->get(route('report-cards.print', $card))->assertOk()->assertSee('CETAK SMT1')->assertSee('215.9mm 330.2mm', false);
    }

    public function test_automatic_retry_preserves_existing_draft_locked_and_published_reports(): void
    {
        [$snapshot, $user] = $this->context();
        $automation = app(ReportCardAutomation::class);
        $this->assertSame(1, $automation->createMissing($snapshot, $user->id));
        $card = ReportCard::firstOrFail();
        foreach (['draft', 'locked', 'published'] as $status) {
            if ($status === 'locked') {
                app(ReportCardWorkflow::class)->lock($card, $user);
            }
            if ($status === 'published') {
                app(ReportCardWorkflow::class)->publish($card, $user);
            }
            $before = $card->fresh(['lines', 'attendance', 'snapshots'])->toArray();
            $this->assertSame(0, $automation->createMissing($snapshot->fresh(), $user->id));
            $this->assertSame($before, $card->fresh(['lines', 'attendance', 'snapshots'])->toArray());
        }
    }

    public function test_wali_kelas_can_only_access_the_assigned_class_and_cannot_edit_scores(): void
    {
        [$snapshot, $user] = $this->context();
        app(ReportCardAutomation::class)->createMissing($snapshot, $user->id);
        $card = ReportCard::firstOrFail();
        $other = User::factory()->create();
        $other->assignRole('guru');
        Teacher::create(['name' => 'Guru lain', 'user_id' => $other->id, 'status' => 'active']);
        $this->actingAs($other)->get(route('report-cards.classroom', $snapshot))->assertForbidden();
        $this->actingAs($other)->get(route('report-cards.show', $card))->assertForbidden();
        $this->actingAs($other)->get(route('report-cards.download-pdf', $card))->assertForbidden();
        $this->actingAs($other)->get(route('report-cards.classroom.pdf', $snapshot))->assertForbidden();
        $this->actingAs($other)->put(route('report-cards.print-options', $card), [])->assertForbidden();
        $this->actingAs($other)->post(route('report-cards.generate', $snapshot))->assertForbidden();

        $oldSnapshot = $card->snapshots()->first()->toArray();
        $data = app(ReportCardPrintService::class)->data($card);
        $options = array_replace($data['options'], ['issue_date' => '2026-12-20', 'note' => 'Semangat belajar.', 'total_score' => 1]);
        $this->actingAs($user)->put(route('report-cards.print-options', $card), $options)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('590.25', $card->fresh()->total_score);
        $this->assertSame($oldSnapshot, $card->snapshots()->oldest('id')->first()->toArray());
        $this->assertSame('Semangat belajar.', app(ReportCardPrintService::class)->data($card)['options']['note']);
        $this->assertSame(2, $card->snapshots()->count());

        HomeroomAssignment::query()->update(['ends_at' => today()->subDay()]);
        $this->actingAs($user)->get(route('report-cards.show', $card))->assertForbidden();
    }

    public function test_genap_requires_a_decision_and_uses_the_correct_sheet(): void
    {
        [$snapshot, $user] = $this->context('genap');
        app(ReportCardAutomation::class)->createMissing($snapshot, $user->id);
        $card = ReportCard::firstOrFail();
        $data = app(ReportCardPrintService::class)->data($card);
        $this->assertContains('Keputusan kenaikan kelas', app(ReportCardTemplateData::class)->missing($data));
        $this->actingAs($user)->from(route('report-cards.show', $card))->get(route('report-cards.download-pdf', $card))->assertRedirect()->assertSessionHasErrors('print');
        $this->actingAs($user)->put(route('report-cards.print-options', $card), array_replace($data['options'], ['promotion_status' => 'promoted', 'next_class' => null]))->assertSessionHasNoErrors();
        $this->assertContains('Kelas tujuan', app(ReportCardPrintService::class)->readiness($card));
        $beforeLock = $card->fresh()->getAttributes();
        try {
            app(ReportCardWorkflow::class)->lock($card, $user);
            $this->fail('Rapor dengan kelas tujuan kosong seharusnya tidak bisa dikunci.');
        } catch (\DomainException $exception) {
            $this->assertStringContainsString('Kelas tujuan', $exception->getMessage());
        }
        $this->assertSame($beforeLock, $card->fresh()->getAttributes());
        app(ReportCardPrintOptions::class)->save($card, array_replace($data['options'], ['promotion_status' => 'promoted', 'next_class' => 'Mustawa 6']), $user);
        $page = app(ReportCardPrintService::class)->page($card);
        $this->assertSame('CETAK SMT2', $page['sheet']);
        $this->assertSame([], $page['missing']);
        $this->assertSame('Mustawa 6', collect($page['cells'])->firstWhere('address', 'N42')['text']);
        app(ReportCardWorkflow::class)->lock($card, $user);
        $this->actingAs($user)->put(route('report-cards.print-options', $card), $data['options'])->assertForbidden();
    }

    public function test_preexisting_locked_incomplete_f4_can_only_be_reopened_with_an_audited_revision(): void
    {
        [$snapshot, $homeroom] = $this->context('genap');
        app(ReportCardAutomation::class)->createMissing($snapshot, $homeroom->id);
        $card = ReportCard::firstOrFail();
        $card->update(['status' => 'locked']);
        $original = $card->snapshots()->latest('id')->firstOrFail();
        $data = $original->snapshot_data;
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']));
        $workflow = app(ReportCardWorkflow::class);

        try {
            $workflow->publish($card, $admin);
            $this->fail('Rapor terkunci yang belum lengkap seharusnya tidak terbit.');
        } catch (\DomainException $exception) {
            $this->assertStringContainsString('Keputusan kenaikan kelas', $exception->getMessage());
        }
        $this->assertSame('locked', $card->fresh()->status);
        $workflow->openRevision($card, $admin, 'Melengkapi keputusan kenaikan kelas.');
        $this->assertSame('draft', $card->fresh()->status);
        $this->assertSame($data, $original->fresh()->snapshot_data);
        $this->assertSame('locked', $card->revisionLogs()->sole()->before_data['status']);
    }

    public function test_failed_automatic_generation_rolls_back_all_cards_and_does_not_change_the_ledger(): void
    {
        [$snapshot, $user] = $this->context();
        $snapshot->update(['status' => 'draft']);
        $snapshot->rows()->first()->cells()->where('column_key', 'attendance_permission')->delete();
        $this->mock(DiniyyahCombinedLedger::class)->shouldReceive('ensureCurrent')->once();
        try {
            app(DiniyyahLedgerWorkflow::class)->lock($snapshot, $user);
            $this->fail('Missing ledger attendance must block generation.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('100%', $e->getMessage());
        }
        $this->assertSame('draft', $snapshot->fresh()->status);
        $this->assertDatabaseCount('report_cards', 0);
        $this->assertDatabaseCount('report_card_lines', 0);
    }

    public function test_the_template_expands_for_additional_assigned_subjects_and_practice_rows(): void
    {
        [$snapshot, $user] = $this->context();
        app(ReportCardAutomation::class)->createMissing($snapshot, $user->id);
        $data = app(ReportCardPrintService::class)->data(ReportCard::firstOrFail());
        $data['subjects'] = array_fill(0, 10, $data['subjects'][0]);
        $data['subjects'][9]['name'] = 'Mapel tambahan';
        $data['practice'] = array_fill(0, 3, $data['practice'][0]);
        $data['practice'][2]['material'] = 'Praktik ketiga';
        $page = app(ReportCardTemplateRenderer::class)->page($data);
        $this->assertTrue(collect($page['cells'])->contains('text', 'Mapel tambahan'));
        $this->assertTrue(collect($page['cells'])->contains('text', 'Praktik ketiga'));
        $this->assertLessThanOrEqual(($page['paper']['height_mm'] - 9) * 96 / 25.4, $page['height'] * $page['scale']);
        $this->assertSame('Nol', app(ReportCardTemplateData::class)->words(0));
        $this->assertSame('Seratus', app(ReportCardTemplateData::class)->words(99.96));
        $this->assertSame('Delapan Puluh Lima Koma Tujuh', app(ReportCardTemplateData::class)->words(85.65));
    }

    public function test_pdf_contains_one_f4_page_per_student_without_blank_pages(): void
    {
        [$snapshot, $user] = $this->context();
        app(ReportCardAutomation::class)->createMissing($snapshot, $user->id);
        $card = ReportCard::firstOrFail();
        $pdf = app(ReportCardPrintService::class)->pdf([$card, $card]);
        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertMatchesRegularExpression('/\/Count 2\b/', $pdf);
        $this->assertMatchesRegularExpression('/\/MediaBox\s*\[0 0 612(?:\.0+)? 936(?:\.0+)?\]/', $pdf);
    }

    public function test_revision_refresh_requires_an_audited_revision_and_keeps_manual_options_and_old_snapshots(): void
    {
        [$snapshot, $user] = $this->context();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user->assignRole('admin');
        $automation = app(ReportCardAutomation::class);
        $automation->createMissing($snapshot, $user->id);
        $card = ReportCard::firstOrFail();
        try {
            $automation->refreshRevision($card, $user);
            $this->fail('A normal draft must not be overwritten.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('alasan tercatat', $exception->getMessage());
        }
        $options = app(ReportCardPrintService::class)->data($card)['options'];
        $options['note'] = 'Catatan yang harus tetap tersimpan.';
        app(ReportCardPrintOptions::class)->save($card, $options, $user);
        $old = $card->snapshots()->orderBy('id')->get()->toArray();
        $workflow = app(ReportCardWorkflow::class);
        $workflow->lock($card, $user);
        $workflow->publish($card, $user);
        $workflow->openRevision($card, $user, 'Koreksi berdasarkan leger final yang diverifikasi');
        $automation->refreshRevision($card->fresh(), $user);
        $this->assertSame($options['note'], app(ReportCardPrintService::class)->data($card)['options']['note']);
        $this->assertSame($old, $card->snapshots()->orderBy('id')->limit(2)->get()->toArray());
        $this->assertSame(3, $card->snapshots()->count());
    }

    public function test_class_pdf_refuses_to_silently_omit_a_student_without_a_report(): void
    {
        [$snapshot, $user] = $this->context();
        app(ReportCardAutomation::class)->createMissing($snapshot, $user->id);
        $student = Student::create(['name' => 'Santri Kedua', 'nis' => '00234', 'gender' => 'female', 'status' => 'active']);
        $enrollment = ClassEnrollment::create(['academic_term_id' => $snapshot->academic_term_id, 'classroom_term_id' => $snapshot->classroom_term_id, 'student_id' => $student->id, 'status' => 'active']);
        $row = $snapshot->rows()->first()->replicate();
        $row->class_enrollment_id = $enrollment->id;
        $row->save();
        $this->actingAs($user)->from(route('report-cards.classroom', $snapshot))->get(route('report-cards.classroom.pdf', $snapshot))->assertRedirect()->assertSessionHasErrors('print');
        $this->actingAs($user)->get(route('report-cards.classroom', $snapshot))->assertOk()->assertSee('1 santri belum memiliki rapor');
    }

    public function test_new_notes_cover_top_three_in_both_semesters_and_period_format_is_automatic(): void
    {
        [$snapshot, $user] = $this->context();
        $snapshot->academicTerm->academicYear->update(['name' => '2026-2027']);
        app(ReportCardAutomation::class)->createMissing($snapshot, $user->id);
        $data = app(ReportCardPrintService::class)->data(ReportCard::firstOrFail());
        $this->assertSame('2026/2027', $data['academic_year']);
        foreach (['ganjil' => '1 (Satu)', 'genap' => '2 (Dua)'] as $semester => $label) {
            foreach ([1, 2, 3, 4] as $rank) {
                $data['semester'] = $semester;
                $data['rank'] = $rank;
                $page = app(ReportCardTemplateRenderer::class)->page($data);
                $note = collect($page['cells'])->firstWhere('address', 'A36')['text'];
                $this->assertStringContainsString($rank <= 3 ? 'mendapat peringkat ke '.$rank : 'telah menyelesaikan KBM', $note);
                $this->assertSame($label, collect($page['cells'])->firstWhere('address', 'Q8')['text']);
                $this->assertSame('2026/2027', collect($page['cells'])->firstWhere('address', 'Q9')['text']);
            }
        }
        // Historical payloads retain the original semester-1 top-two rule.
        unset($data['note_policy']);
        $data['semester'] = 'ganjil';
        $data['rank'] = 3;
        $page = app(ReportCardTemplateRenderer::class)->page($data);
        $this->assertStringContainsString('telah menyelesaikan KBM', collect($page['cells'])->firstWhere('address', 'A36')['text']);
    }

    public function test_principal_name_is_fixed_in_form_and_cannot_be_changed_by_submissions(): void
    {
        [$snapshot, $user] = $this->context();
        app(ReportCardAutomation::class)->createMissing($snapshot, $user->id);
        $card = ReportCard::firstOrFail();
        $original = $card->snapshots()->first()->toArray();
        $options = app(ReportCardPrintService::class)->data($card)['options'];
        $this->assertSame('Ustadz Ahmad Nurul Huda', $options['principal_name']);
        $this->actingAs($user)->get(route('report-cards.show', $card))
            ->assertOk()->assertSee('Nama kepala sekolah (tetap)')
            ->assertDontSee('name="principal_name"', false);
        unset($options['principal_name']);
        $this->put(route('report-cards.print-options', $card), $options)->assertSessionHasNoErrors()->assertRedirect();
        $options['principal_name'] = 'Nama yang tidak diizinkan';
        $this->put(route('report-cards.print-options', $card), $options)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('Ustadz Ahmad Nurul Huda', $card->snapshots()->latest('id')->first()->snapshot_data['print']['options']['principal_name']);
        $this->assertSame($original, $card->snapshots()->oldest('id')->first()->toArray());
        $this->assertSame('590.25', $card->fresh()->total_score);
    }

    public function test_supplied_signature_fits_between_title_and_name_in_both_semesters_and_expanded_layouts(): void
    {
        [$snapshot, $user] = $this->context();
        app(ReportCardAutomation::class)->createMissing($snapshot, $user->id);
        $data = app(ReportCardPrintService::class)->data(ReportCard::firstOrFail());
        $asset = resource_path('report-cards/'.config('report-cards.principal_signature_file'));
        $this->assertSame('bd472c6bed34293dd00d331921608a3273829f39d1acfea3cf9e2aaeffe82257', hash_file('sha256', $asset));
        [$width, $height] = getimagesize($asset);
        foreach (['ganjil' => ['H52', 'H56'], 'genap' => ['H54', 'H58']] as $semester => [$titleAddress, $nameAddress]) {
            foreach ([false, true] as $expanded) {
                $renderData = $data;
                $renderData['semester'] = $semester;
                if ($expanded) {
                    $renderData['subjects'] = array_fill(0, 8, $data['subjects'][0]);
                    $renderData['practice'] = array_fill(0, 2, $data['practice'][0]);
                }
                $page = app(ReportCardTemplateRenderer::class)->page($renderData);
                $signature = collect($page['images'])->firstWhere('signature', true);
                $title = collect($page['cells'])->firstWhere('address', $titleAddress);
                $name = collect($page['cells'])->firstWhere('address', $nameAddress);
                $this->assertSame('Ustadz Ahmad Nurul Huda', $name['text']);
                $this->assertSame(file_get_contents($asset), base64_decode(explode(',', $signature['src'], 2)[1]));
                $this->assertEqualsWithDelta($width / $height, $signature['width'] / $signature['height'], .001);
                $this->assertEqualsWithDelta($name['x'] + $name['width'] / 2, $signature['x'] + $signature['width'] / 2, .001);
                $titleTextBottom = $title['y'] + ($title['height'] + $title['size'] * 4 / 3 * 1.12) / 2;
                $nameTextTop = $name['y'] + ($name['height'] - $name['size'] * 4 / 3 * 1.12) / 2;
                $this->assertGreaterThan(56, $signature['height']);
                $this->assertGreaterThanOrEqual($titleTextBottom + 1, $signature['y']);
                $this->assertLessThanOrEqual($nameTextTop - 1, $signature['y'] + $signature['height']);
            }
        }
    }

    public function test_viewing_existing_reports_never_rewrites_snapshots_or_signs_for_a_different_archived_principal(): void
    {
        [$snapshot, $user] = $this->context();
        app(ReportCardAutomation::class)->createMissing($snapshot, $user->id);
        $card = ReportCard::firstOrFail();
        $stored = $card->snapshots()->first();
        $payload = $stored->snapshot_data;
        $payload['print']['options']['principal_name'] = 'Kepala pada arsip lama';
        $stored->update(['snapshot_data' => $payload]);
        foreach (['draft', 'locked', 'published'] as $status) {
            $card->update(['status' => $status]);
            $before = $card->fresh(['snapshots', 'lines', 'attendance'])->toArray();
            $print = app(ReportCardPrintService::class)->data($card);
            $page = app(ReportCardPrintService::class)->page($card);
            $this->assertSame($status === 'draft' ? 'Ustadz Ahmad Nurul Huda' : 'Kepala pada arsip lama', $print['options']['principal_name']);
            $this->assertSame($status === 'draft', collect($page['images'])->contains('signature', true));
            $this->assertSame($before, $card->fresh(['snapshots', 'lines', 'attendance'])->toArray());
        }
    }

    private function context(string $semester = 'ganjil'): array
    {
        Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('guru');
        $teacher = Teacher::create(['user_id' => $user->id, 'name' => 'Ustadzah Test', 'status' => 'active']);
        $school = School::create(['name' => 'Griya Quran']);
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2026/2027']);
        $term = AcademicTerm::create(['academic_year_id' => $year->id, 'name' => ucfirst($semester), 'semester' => $semester, 'ends_at' => '2026-12-20', 'report_issue_date' => '2026-12-20']);
        $class = Classroom::create(['name' => 'Mustawa 5 Akhwat']);
        $classroom = ClassroomTerm::create(['academic_term_id' => $term->id, 'classroom_id' => $class->id, 'name' => $class->name]);
        HomeroomAssignment::create(['classroom_term_id' => $classroom->id, 'teacher_id' => $teacher->id]);
        $student = Student::create(['name' => 'Santri Test', 'gender' => 'female', 'nis' => '00123', 'status' => 'active']);
        $enrollment = ClassEnrollment::create(['academic_term_id' => $term->id, 'classroom_term_id' => $classroom->id, 'student_id' => $student->id, 'status' => 'active']);
        $columns = [];
        foreach ([1001 => ['Fiqih', 'weighted'], 1002 => ['Suluk', 'direct'], 1003 => ['Praktik Ibadah', 'practical']] as $id => [$name, $method]) {
            $columns[] = ['key' => 'assessment_'.$id, 'label' => $name, 'subject_name' => $name, 'subject_arabic_name' => $id === 1001 ? 'الفقه' : null, 'assessment_set_id' => $id,
                'source_type' => 'diniyyah_assessment_set', 'assessment_method' => $method, 'kkm' => 70, 'tested_material' => $id === 1003 ? 'Shalat Jenazah' : null, 'appears_on_report' => true];
        }
        $snapshot = DiniyyahLedgerSnapshot::create(['academic_term_id' => $term->id, 'classroom_term_id' => $classroom->id, 'title' => 'Leger Test', 'status' => 'locked',
            'snapshot_data' => ['layout_version' => 'combined-v1', 'columns' => $columns, 'summary' => ['blocking_issues' => 0]]]);
        $row = $snapshot->rows()->create(['class_enrollment_id' => $enrollment->id, 'row_number' => 1, 'student_name' => $student->name, 'student_nis' => $student->nis, 'total_diniyyah_score' => 270.25, 'average_diniyyah_score' => 90.08, 'rank_in_class' => 1]);
        foreach ([1001 => 80.25, 1002 => 90, 1003 => 100] as $id => $score) {
            $row->cells()->create(['column_key' => 'assessment_'.$id, 'source_id' => $id, 'source_type' => 'diniyyah_assessment_set', 'label' => $columns[$id - 1001]['label'], 'value_numeric' => $score, 'sort_order' => $id]);
        }
        foreach (['tahfidz_uas' => 80, 'tahfidz_sabaq' => 80, 'tahfidz_sabqi' => 80, 'tahfidz_manzil' => 80, 'combined_total' => 590.25, 'combined_average' => 84.32, 'combined_rank' => 2, 'attendance_sick' => 2, 'attendance_permission' => 0, 'attendance_absent' => 1] as $key => $value) {
            $row->cells()->create(['column_key' => $key, 'source_type' => str_starts_with($key, 'tahfidz') ? 'tahfidz_snapshot' : 'combined_summary', 'label' => $key, 'value_numeric' => $value]);
        }
        $row->cells()->create(['column_key' => 'tahfidz_juz', 'source_type' => 'tahfidz_snapshot', 'label' => 'Juz UAS', 'value_text' => 'Juz 30']);

        return [$snapshot, $user];
    }
}
