<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\ClassEnrollment;
use App\Models\Classroom;
use App\Models\ClassroomTerm;
use App\Models\DiniyyahAssessmentSet;
use App\Models\DiniyyahClassSubject;
use App\Models\DiniyyahLedgerSnapshot;
use App\Models\DiniyyahScoreComponent;
use App\Models\DiniyyahSubject;
use App\Models\DiniyyahTeacherAssignment;
use App\Models\ReportCard;
use App\Models\School;
use App\Models\ScoreChangeLog;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Services\DiniyyahAssessmentComponentBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GuruDiniyyahScoreInputTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigned_teacher_can_open_score_input_page(): void
    {
        [$assessmentSet, $teacher] = $this->makeInputContext();

        $this->actingAs($teacher->user)
            ->get(route('guru.diniyyah-scores.edit', $assessmentSet))
            ->assertOk()
            ->assertSee('Fiqih')
            ->assertSee('Santri 1');
    }

    public function test_unassigned_teacher_cannot_open_score_input_page(): void
    {
        [$assessmentSet] = $this->makeInputContext();
        $otherTeacher = $this->makeTeacher('Guru Lain');

        $this->actingAs($otherTeacher->user)
            ->get(route('guru.diniyyah-scores.edit', $assessmentSet))
            ->assertForbidden();
    }

    public function test_assigned_teacher_can_store_scores(): void
    {
        [$assessmentSet, $teacher, $enrollment, $dailyComponent, $examComponent] = $this->makeInputContext();

        $this->actingAs($teacher->user)
            ->put(route('guru.diniyyah-scores.update', $assessmentSet), [
                'scores' => [
                    $enrollment->id => [
                        $dailyComponent->id => 80,
                        $examComponent->id => 90,
                    ],
                ],
            ])
            ->assertRedirect(route('guru.diniyyah-scores.edit', $assessmentSet));

        $this->assertDatabaseHas('diniyyah_scores', [
            'diniyyah_assessment_set_id' => $assessmentSet->id,
            'diniyyah_score_component_id' => $dailyComponent->id,
            'class_enrollment_id' => $enrollment->id,
            'score' => 80,
        ]);
        $this->assertDatabaseHas('diniyyah_assessment_results', [
            'diniyyah_assessment_set_id' => $assessmentSet->id,
            'class_enrollment_id' => $enrollment->id,
            'final_score' => 86,
            'is_complete' => true,
        ]);
    }

    public function test_assigned_teacher_can_submit_complete_scores(): void
    {
        [$assessmentSet, $teacher, $enrollment, $dailyComponent, $examComponent] = $this->makeInputContext();

        $this->actingAs($teacher->user)
            ->put(route('guru.diniyyah-scores.update', $assessmentSet), [
                'scores' => [
                    $enrollment->id => [
                        $dailyComponent->id => 80,
                        $examComponent->id => 90,
                    ],
                ],
            ]);

        $this->actingAs($teacher->user)
            ->post(route('guru.diniyyah-scores.submit', $assessmentSet))
            ->assertRedirect(route('guru.diniyyah-scores.index'));

        $this->assertSame('submitted', $assessmentSet->refresh()->status);
        $this->assertSame(2, $assessmentSet->scores()->where('status', 'submitted')->count());
    }

    public function test_assigned_teacher_cannot_submit_incomplete_scores(): void
    {
        [$assessmentSet, $teacher] = $this->makeInputContext();

        $this->actingAs($teacher->user)
            ->post(route('guru.diniyyah-scores.submit', $assessmentSet))
            ->assertRedirect(route('guru.diniyyah-scores.edit', $assessmentSet))
            ->assertSessionHasErrors('scores');

        $this->assertSame('active', $assessmentSet->refresh()->status);
    }

    public function test_assigned_teacher_cannot_update_submitted_scores(): void
    {
        [$assessmentSet, $teacher, $enrollment, $dailyComponent] = $this->makeInputContext();
        $assessmentSet->update(['status' => 'submitted']);

        $this->actingAs($teacher->user)
            ->put(route('guru.diniyyah-scores.update', $assessmentSet), [
                'scores' => [
                    $enrollment->id => [
                        $dailyComponent->id => 80,
                    ],
                ],
            ])
            ->assertForbidden();
    }

    public function test_extra_columns_are_optional_numbered_and_keep_all_original_components_and_scores(): void
    {
        [$set, $teacher, $enrollment, $daily, $exam] = $this->makeInputContext();
        app(DiniyyahAssessmentComponentBuilder::class)->createDefaults($set);
        $legacyTask = $set->components()->where('code', 'nilai_tugas')->sole();
        $legacyTask->update(['name' => 'Nilai Tugas', 'is_required' => true]);
        $this->actingAs($teacher->user)->put(route('guru.diniyyah-scores.update', $set), ['scores' => [$enrollment->id => [$daily->id => 80, $exam->id => 90, $legacyTask->id => 75]]]);
        $oldComponents = $set->components()->orderBy('id')->get()->toArray();
        $oldScores = $set->scores()->orderBy('id')->get()->toArray();

        foreach (['ulangan_harian_3', 'ulangan_harian_4', 'nilai_tugas_2', 'nilai_tugas_3'] as $code) {
            $type = str_starts_with($code, 'ulangan') ? 'ulangan' : 'tugas';
            $this->postJson(route('guru.diniyyah-scores.components.store', $set), ['type' => $type, 'expected_code' => $code])
                ->assertOk()->assertJsonPath('component.name', ($type === 'ulangan' ? 'Ulangan Harian ' : 'Nilai Tugas ').substr($code, -1));
            $this->assertDatabaseHas('diniyyah_score_components', ['diniyyah_assessment_set_id' => $set->id, 'code' => $code, 'is_required' => false, 'component_group' => 'daily']);
        }
        $this->postJson(route('guru.diniyyah-scores.components.store', $set), ['type' => 'ulangan', 'expected_code' => 'ulangan_harian_3'])->assertOk();
        $this->assertSame(count($oldComponents) + 4, $set->components()->count());
        $this->assertSame($oldComponents, $set->components()->whereIn('id', collect($oldComponents)->pluck('id'))->orderBy('id')->get()->toArray());
        $this->assertSame($oldScores, $set->scores()->orderBy('id')->get()->toArray());
        $this->get(route('guru.diniyyah-scores.edit', $set))->assertOk()
            ->assertSee('Nilai Tugas 1')->assertSee('Ulangan Harian 4')->assertSee('(bawaan)')->assertSee('(tambahan opsional)');
        $this->assertSame($oldComponents, $set->components()->whereIn('id', collect($oldComponents)->pluck('id'))->orderBy('id')->get()->toArray());
        $this->assertSame($oldScores, $set->scores()->orderBy('id')->get()->toArray());
    }

    public function test_autosave_changes_only_sent_cells_and_recalculates_using_optional_scores(): void
    {
        [$set, $teacher, $enrollment, $daily, $exam] = $this->makeInputContext();
        $this->actingAs($teacher->user)->put(route('guru.diniyyah-scores.update', $set), ['scores' => [$enrollment->id => [$daily->id => 80, $exam->id => 90]]]);
        $oldDaily = $set->scores()->where('diniyyah_score_component_id', $daily->id)->sole()->toArray();
        $id = $this->postJson(route('guru.diniyyah-scores.components.store', $set), ['type' => 'ulangan', 'expected_code' => 'ulangan_harian_3'])->assertOk()->json('component.id');
        // Empty extra columns never become zero scores or change the prior result.
        $this->postJson(route('guru.diniyyah-scores.components.store', $set), ['type' => 'tugas', 'expected_code' => 'nilai_tugas_2'])->assertOk();
        $this->assertSame('86.00', $set->results()->sole()->final_score);
        $changes = [['enrollment_id' => $enrollment->id, 'component_id' => $id, 'score' => 60, 'original' => null]];
        $this->putJson(route('guru.diniyyah-scores.autosave', $set), ['changes' => $changes])->assertOk()
            ->assertJsonPath('rows.0.final_score', '82.00')->assertJsonPath('progress.complete_students', 1);
        $this->assertSame($oldDaily, $set->scores()->where('diniyyah_score_component_id', $daily->id)->sole()->toArray());
        $logCount = ScoreChangeLog::count();
        $this->putJson(route('guru.diniyyah-scores.autosave', $set), ['changes' => $changes])->assertOk();
        $this->assertSame($logCount, ScoreChangeLog::count());
        $changes[0]['original'] = 60;
        $changes[0]['score'] = null;
        $this->putJson(route('guru.diniyyah-scores.autosave', $set), ['changes' => $changes])->assertOk()->assertJsonPath('rows.0.final_score', '86.00');
    }

    public function test_stale_autosave_rolls_back_the_whole_batch(): void
    {
        [$set, $teacher, $enrollment, $daily, $exam] = $this->makeInputContext();
        $this->actingAs($teacher->user)->put(route('guru.diniyyah-scores.update', $set), ['scores' => [$enrollment->id => [$daily->id => 80, $exam->id => 90]]]);
        $this->putJson(route('guru.diniyyah-scores.autosave', $set), ['changes' => [
            ['enrollment_id' => $enrollment->id, 'component_id' => $exam->id, 'score' => 95, 'original' => 90],
            ['enrollment_id' => $enrollment->id, 'component_id' => $daily->id, 'score' => 50, 'original' => null],
        ]])->assertConflict();
        $this->assertSame('90.00', $set->scores()->where('diniyyah_score_component_id', $exam->id)->sole()->score);
        $this->assertSame('80.00', $set->scores()->where('diniyyah_score_component_id', $daily->id)->sole()->score);
    }

    public function test_autosave_rejects_invalid_scores_and_foreign_cells_and_automatic_attendance(): void
    {
        [$set, $teacher, $enrollment, $daily] = $this->makeInputContext();
        $this->actingAs($teacher->user);
        foreach ([-1, 101, 'abc', 80.123] as $value) {
            $this->putJson(route('guru.diniyyah-scores.autosave', $set), ['changes' => [
                ['enrollment_id' => $enrollment->id, 'component_id' => $daily->id, 'score' => $value, 'original' => null],
            ]])->assertUnprocessable();
        }
        $attendance = $set->components()->create(['code' => 'keaktifan_presensi', 'name' => 'Presensi', 'component_group' => 'daily']);
        foreach ([[$enrollment->id, 999999], [999999, $daily->id], [$enrollment->id, $attendance->id]] as [$enrollmentId, $componentId]) {
            $this->putJson(route('guru.diniyyah-scores.autosave', $set), ['changes' => [
                ['enrollment_id' => $enrollmentId, 'component_id' => $componentId, 'score' => 80, 'original' => null],
            ]])->assertUnprocessable();
        }
        $this->assertSame(0, $set->scores()->count());
        foreach ([0, 100, 80.25] as $value) {
            $original = $set->scores()->where('diniyyah_score_component_id', $daily->id)->first()?->score;
            $this->putJson(route('guru.diniyyah-scores.autosave', $set), ['changes' => [
                ['enrollment_id' => $enrollment->id, 'component_id' => $daily->id, 'score' => $value, 'original' => $original],
            ]])->assertOk();
        }
    }

    public function test_new_endpoints_enforce_assignment_status_and_frozen_documents(): void
    {
        [$set, $teacher, $enrollment, $daily] = $this->makeInputContext();
        $changes = ['changes' => [['enrollment_id' => $enrollment->id, 'component_id' => $daily->id, 'score' => 80, 'original' => null]]];
        $component = ['type' => 'ulangan', 'expected_code' => 'ulangan_harian_3'];
        $this->actingAs($this->makeTeacher('Guru lain')->user);
        $this->putJson(route('guru.diniyyah-scores.autosave', $set), $changes)->assertForbidden();
        $this->postJson(route('guru.diniyyah-scores.components.store', $set), $component)->assertForbidden();
        $this->actingAs($teacher->user);
        foreach (['submitted', 'validated'] as $status) {
            $set->update(['status' => $status]);
            $this->putJson(route('guru.diniyyah-scores.autosave', $set), $changes)->assertForbidden();
            $this->postJson(route('guru.diniyyah-scores.components.store', $set), $component)->assertForbidden();
        }
        $set->update(['status' => 'active']);
        $snapshot = DiniyyahLedgerSnapshot::create(['academic_term_id' => $enrollment->academic_term_id, 'classroom_term_id' => $enrollment->classroom_term_id, 'title' => 'Leger final', 'status' => 'locked']);
        $this->putJson(route('guru.diniyyah-scores.autosave', $set), $changes)->assertForbidden();
        $this->postJson(route('guru.diniyyah-scores.components.store', $set), $component)->assertForbidden();
        $this->get(route('guru.diniyyah-scores.edit', $set))->assertOk()->assertSee('Nilai hanya dapat dibaca.')->assertDontSee('data-add-component=', false);
        $snapshot->update(['status' => 'draft']);
        ReportCard::withoutEvents(fn () => ReportCard::create(['academic_term_id' => $enrollment->academic_term_id, 'classroom_term_id' => $enrollment->classroom_term_id, 'class_enrollment_id' => $enrollment->id, 'student_id' => $enrollment->student_id, 'report_type' => 'diniyyah', 'status' => 'published']));
        $this->putJson(route('guru.diniyyah-scores.autosave', $set), $changes)->assertForbidden();
        $this->postJson(route('guru.diniyyah-scores.components.store', $set), $component)->assertForbidden();
    }

    private function makeTeacher(string $name): Teacher
    {
        Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web']);

        $user = User::factory()->create(['name' => $name]);
        $user->assignRole('guru');

        return Teacher::create(['user_id' => $user->id, 'name' => $name]);
    }

    /** @return array{DiniyyahAssessmentSet, Teacher, ClassEnrollment, DiniyyahScoreComponent, DiniyyahScoreComponent} */
    private function makeInputContext(): array
    {
        $teacher = $this->makeTeacher('Guru Fiqih');
        $school = School::create(['name' => 'Griya Quran']);
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2025/2026']);
        $term = AcademicTerm::create(['academic_year_id' => $year->id, 'name' => 'Semester Genap', 'semester' => 'genap']);
        $classroom = Classroom::create(['name' => 'Mustawa 1 Ikhwan']);
        $classroomTerm = ClassroomTerm::create([
            'academic_term_id' => $term->id,
            'classroom_id' => $classroom->id,
            'name' => 'Mustawa 1 Ikhwan',
        ]);
        $student = Student::create(['name' => 'Santri 1', 'gender' => 'male', 'nis' => '001']);
        $enrollment = ClassEnrollment::create([
            'academic_term_id' => $term->id,
            'classroom_term_id' => $classroomTerm->id,
            'student_id' => $student->id,
        ]);
        $subject = DiniyyahSubject::create([
            'code' => 'fiqih',
            'name' => 'Fiqih',
            'default_assessment_method' => 'weighted',
        ]);
        $classSubject = DiniyyahClassSubject::create([
            'classroom_term_id' => $classroomTerm->id,
            'subject_id' => $subject->id,
            'assessment_method' => 'weighted',
            'kkm' => 70,
            'daily_weight' => 40,
            'exam_weight' => 60,
        ]);
        DiniyyahTeacherAssignment::create([
            'diniyyah_class_subject_id' => $classSubject->id,
            'teacher_id' => $teacher->id,
            'assignment_role' => 'primary',
        ]);
        $assessmentSet = DiniyyahAssessmentSet::create([
            'diniyyah_class_subject_id' => $classSubject->id,
            'title' => 'Fiqih',
            'assessment_method' => 'weighted',
            'kkm' => 70,
            'daily_weight' => 40,
            'exam_weight' => 60,
            'status' => 'active',
        ]);
        $dailyComponent = DiniyyahScoreComponent::create([
            'diniyyah_assessment_set_id' => $assessmentSet->id,
            'code' => 'harian',
            'name' => 'Harian',
            'component_group' => 'daily',
            'is_required' => true,
        ]);
        $examComponent = DiniyyahScoreComponent::create([
            'diniyyah_assessment_set_id' => $assessmentSet->id,
            'code' => 'ujian',
            'name' => 'Ujian',
            'component_group' => 'exam',
            'is_required' => true,
        ]);

        return [$assessmentSet, $teacher, $enrollment, $dailyComponent, $examComponent];
    }
}
