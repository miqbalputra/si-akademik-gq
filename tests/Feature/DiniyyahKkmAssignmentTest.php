<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\ClassroomTerm;
use App\Models\ClassSession;
use App\Models\DiniyyahClassSubject;
use App\Models\DiniyyahKkmScope;
use App\Models\DiniyyahLedgerSnapshot;
use App\Models\DiniyyahSubject;
use App\Models\DiniyyahTeacherAssignment;
use App\Models\DiniyyahTeachingSchedule;
use App\Models\PanelNotification;
use App\Models\School;
use App\Models\Teacher;
use App\Models\User;
use App\Services\DiniyyahKkmAssignmentRoster;
use App\Services\DiniyyahKkmBulkAssignment;
use App\Services\DiniyyahKkmWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DiniyyahKkmAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private function context(): array
    {
        foreach (['admin', 'kabag_diniyyah', 'guru'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $kabag = User::factory()->create();
        $kabag->assignRole('kabag_diniyyah');
        $school = School::create(['name' => 'Sekolah KKM Massal']);
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2026/2027']);
        $term = AcademicTerm::create(['academic_year_id' => $year->id, 'name' => 'Ganjil', 'semester' => 'ganjil', 'starts_at' => '2026-07-01', 'ends_at' => '2026-12-31', 'is_active' => true, 'kkm_workflow_enabled' => true]);
        $subject = DiniyyahSubject::create(['code' => 'akidah_massal', 'name' => 'Akidah Akhlak', 'default_assessment_method' => 'direct_final']);
        $female = $this->teacher('female', 'Ustadzah Penyusun');
        $male = $this->teacher('male', 'Ustadz Pembaca');
        $akhwat = $this->classSubject($term, $subject, 'M1 Akhwat A', 'Mustawa 1');
        $ikhwan = $this->classSubject($term, $subject, 'M1 Ikhwan A', 'M1');
        DiniyyahTeacherAssignment::create(['diniyyah_class_subject_id' => $akhwat->id, 'teacher_id' => $female->id, 'assignment_role' => 'primary']);
        DiniyyahTeacherAssignment::create(['diniyyah_class_subject_id' => $ikhwan->id, 'teacher_id' => $male->id, 'assignment_role' => 'primary']);

        return compact('admin', 'kabag', 'term', 'subject', 'female', 'male', 'akhwat', 'ikhwan');
    }

    private function teacher(string $gender, string $name): Teacher
    {
        $user = User::factory()->create(['name' => $name]);
        $user->assignRole('guru');

        return Teacher::create(['name' => $name, 'gender' => $gender, 'status' => 'active', 'user_id' => $user->id]);
    }

    private function classSubject(AcademicTerm $term, DiniyyahSubject $subject, string $name, ?string $level): DiniyyahClassSubject
    {
        $classroom = Classroom::create(['name' => $name, 'level_name' => $level, 'gender_group' => 'male', 'is_active' => true]);
        $ct = ClassroomTerm::create(['academic_term_id' => $term->id, 'classroom_id' => $classroom->id, 'name' => $name, 'status' => 'active']);

        return DiniyyahClassSubject::create(['classroom_term_id' => $ct->id, 'subject_id' => $subject->id, 'assessment_method' => 'direct_final', 'is_active' => true]);
    }

    private function payload(array $c, ?array $rows = null): array
    {
        $rows ??= app(DiniyyahKkmAssignmentRoster::class)->forTerm($c['term'])['rows'];
        $id = $c['akhwat']->id;

        return [
            'academic_term_id' => $c['term']->id,
            'preview_token' => app(DiniyyahKkmBulkAssignment::class)->previewToken($c['term'], $rows, $c['admin']),
            'selected' => [$id], 'teachers' => [$id => $c['female']->id], 'genders' => [$id => 'female'],
        ];
    }

    public function test_roster_groups_parallel_classes_and_deduplicates_teachers_without_writes(): void
    {
        $c = $this->context();
        DiniyyahTeacherAssignment::create(['diniyyah_class_subject_id' => $c['ikhwan']->id, 'teacher_id' => $c['female']->id, 'assignment_role' => 'assistant']);
        $this->classSubject($c['term'], $c['subject'], 'Kelas tingkat belum diisi', null);
        $response = $this->actingAs($c['admin'], 'admin')->get(route('admin.kkm.index'))->assertOk()
            ->assertSee('Tetapkan Massal')->assertSee('Belum dijadwalkan')->assertSee('Data jenjang perlu dilengkapi');
        $rows = $response->viewData('roster')['rows'];
        $this->assertCount(1, $rows);
        $this->assertSame(['M1 Akhwat A', 'M1 Ikhwan A'], $rows[0]['classes']);
        $this->assertCount(2, $rows[0]['teachers']);
        $female = collect($rows[0]['teachers'])->firstWhere('id', $c['female']->id);
        $this->assertCount(2, $female['classes']);
        $this->assertSame('Pendamping', $female['classes'][1]['role']);
        $this->get(route('admin.kkm.bulk.preview'))->assertOk()->assertSee('Terapkan Penugasan');
        $this->assertDatabaseCount('diniyyah_kkm_scopes', 0);
        $this->assertSame(0, PanelNotification::where('notification_type', 'kkm_assigned')->count());
        $this->qa('assignment-roster.html', $response->getContent());
        $this->qa('assignment-bulk.html', $this->get(route('admin.kkm.bulk.preview'))->getContent());
    }

    public function test_schedules_respect_semester_assignment_range_and_visible_versions(): void
    {
        $c = $this->context();
        $assignment = $c['akhwat']->teacherAssignments()->first();
        $assignment->update(['starts_at' => '2026-08-01', 'ends_at' => '2026-11-30']);
        $session = ClassSession::create(['session_name' => 'JP1', 'starts_at' => '08:00', 'ends_at' => '08:40', 'is_break' => false]);
        foreach ([['legacy', null, null, 1], ['active', '2026-09-01', '2026-10-31', 2], ['superseded', null, null, 3], ['active', '2027-01-01', null, 4], ['active', null, '2026-07-31', 5]] as [$status, $start, $end, $day]) {
            DiniyyahTeachingSchedule::withoutEvents(fn () => DiniyyahTeachingSchedule::create(['diniyyah_teacher_assignment_id' => $assignment->id, 'class_session_id' => $session->id, 'day_of_week' => $day, 'version_status' => $status, 'effective_from' => $start, 'effective_until' => $end]));
        }
        $teacher = collect(app(DiniyyahKkmAssignmentRoster::class)->forTerm($c['term'])['rows'][0]['teachers'])->firstWhere('id', $c['female']->id);
        $this->assertCount(2, $teacher['classes'][0]['schedules']);
        $this->assertStringContainsString('Senin 08:00', $teacher['classes'][0]['schedules'][0]);
        $this->assertStringContainsString('Selasa 08:00', $teacher['classes'][0]['schedules'][1]);
    }

    public function test_candidates_exclude_inactive_missing_account_out_of_term_and_other_scope(): void
    {
        $c = $this->context();
        $inactive = $this->teacher('female', 'Tidak aktif');
        $inactive->update(['status' => 'inactive']);
        $noAccount = Teacher::create(['name' => 'Tanpa akun', 'gender' => 'female', 'status' => 'active']);
        $expired = $this->teacher('female', 'Penugasan lampau');
        foreach ([$inactive, $noAccount, $expired] as $teacher) {
            DiniyyahTeacherAssignment::create(['diniyyah_class_subject_id' => $c['akhwat']->id, 'teacher_id' => $teacher->id, 'ends_at' => $teacher->is($expired) ? '2026-06-30' : null]);
        }
        $m2 = $this->classSubject($c['term'], $c['subject'], 'M2 Akhwat', 'M2');
        DiniyyahTeacherAssignment::create(['diniyyah_class_subject_id' => $m2->id, 'teacher_id' => $expired->id]);
        $roster = app(DiniyyahKkmAssignmentRoster::class)->forTerm($c['term']);
        $this->assertCount(2, $roster['rows']);
        $this->assertEqualsCanonicalizing([$c['female']->id, $c['male']->id], array_column($roster['rows'][0]['teachers'], 'id'));
        $this->assertSame([$expired->id], array_column($roster['rows'][1]['teachers'], 'id'));
    }

    public function test_bulk_assigns_only_selected_gender_and_retry_does_not_duplicate_notifications(): void
    {
        $c = $this->context();
        $payload = $this->payload($c);
        $this->actingAs($c['admin'], 'admin')->post(route('admin.kkm.bulk.apply'), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('diniyyah_kkm_scopes', ['subject_id' => $c['subject']->id, 'assigned_teacher_id' => $c['female']->id]);
        $this->assertDatabaseHas('activity_log', ['log_name' => 'kkm', 'event' => 'assigned']);
        $notification = PanelNotification::where('notification_type', 'kkm_assigned')->sole();
        $this->assertSame($c['female']->user_id, $notification->user_id);
        $this->assertSame(1, $notification->batch_count);
        $this->post(route('admin.kkm.bulk.apply'), $payload)->assertSessionHasNoErrors();
        $this->assertSame(1, $notification->fresh()->batch_count);
        $this->assertDatabaseCount('diniyyah_kkm_scopes', 1);
    }

    public function test_multiple_candidates_require_choice_and_gender_can_differ_per_row(): void
    {
        $c = $this->context();
        $second = $this->teacher('female', 'Ustadzah Kedua');
        DiniyyahTeacherAssignment::create(['diniyyah_class_subject_id' => $c['akhwat']->id, 'teacher_id' => $second->id]);
        $this->qa('assignment-multiple.html', $this->actingAs($c['admin'], 'admin')->get(route('admin.kkm.bulk.preview'))->assertOk()->getContent());
        $payload = $this->payload($c);
        $payload['teachers'][$c['akhwat']->id] = '';
        $this->actingAs($c['admin'], 'admin')->post(route('admin.kkm.bulk.apply'), $payload)->assertSessionHasErrors('kkm');
        $payload['teachers'][$c['akhwat']->id] = $c['male']->id;
        $payload['genders'][$c['akhwat']->id] = 'male';
        $this->post(route('admin.kkm.bulk.apply'), $payload)->assertSessionHasNoErrors();
        $this->assertSame($c['male']->id, DiniyyahKkmScope::sole()->assigned_teacher_id);
    }

    public function test_per_row_gender_choices_and_excluded_rows_are_respected(): void
    {
        $c = $this->context();
        $m2 = $this->classSubject($c['term'], $c['subject'], 'M2 Ikhwan', 'M2');
        $m3 = $this->classSubject($c['term'], $c['subject'], 'M3 Ikhwan', 'M3');
        foreach ([$m2, $m3] as $subject) {
            DiniyyahTeacherAssignment::create(['diniyyah_class_subject_id' => $subject->id, 'teacher_id' => $c['male']->id]);
        }
        $payload = $this->payload($c);
        $payload['selected'][] = $m2->id;
        $payload['teachers'][$m2->id] = $c['male']->id;
        $payload['genders'][$m2->id] = 'male';
        $payload['teachers'][$m3->id] = '';
        $payload['genders'][$m3->id] = 'female';
        $this->actingAs($c['admin'], 'admin')->post(route('admin.kkm.bulk.apply'), $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('diniyyah_kkm_scopes', ['mustawa' => 1, 'assigned_teacher_id' => $c['female']->id]);
        $this->assertDatabaseHas('diniyyah_kkm_scopes', ['mustawa' => 2, 'assigned_teacher_id' => $c['male']->id]);
        $this->assertDatabaseMissing('diniyyah_kkm_scopes', ['mustawa' => 3]);
    }

    public function test_gender_mismatch_forged_token_wrong_actor_and_outside_preview_are_rejected(): void
    {
        $c = $this->context();
        $payload = $this->payload($c);
        $this->actingAs($c['admin'], 'admin');
        $wrongGender = $payload;
        $wrongGender['genders'][$c['akhwat']->id] = 'male';
        $this->post(route('admin.kkm.bulk.apply'), $wrongGender)->assertSessionHasErrors('kkm');
        $forged = $payload;
        $forged['preview_token'] .= 'x';
        $this->post(route('admin.kkm.bulk.apply'), $forged)->assertSessionHasErrors('kkm');
        $outside = $payload;
        $outside['selected'] = [$c['ikhwan']->id];
        $this->post(route('admin.kkm.bulk.apply'), $outside)->assertSessionHasErrors('kkm');
        $this->actingAs($c['kabag'], 'web')->post(route('kabag.kkm.bulk.apply'), $payload)->assertSessionHasErrors('kkm');
        $this->assertDatabaseCount('diniyyah_kkm_scopes', 0);
    }

    public function test_stale_candidates_roll_back_entire_batch_including_audit_and_notifications(): void
    {
        $c = $this->context();
        $m2 = $this->classSubject($c['term'], $c['subject'], 'M2 Akhwat', 'M2');
        $second = $this->teacher('female', 'Pengajar M2');
        DiniyyahTeacherAssignment::create(['diniyyah_class_subject_id' => $m2->id, 'teacher_id' => $second->id]);
        $payload = $this->payload($c);
        $payload['selected'][] = $m2->id;
        $payload['teachers'][$m2->id] = $second->id;
        $payload['genders'][$m2->id] = 'female';
        $second->update(['status' => 'inactive']);
        $this->actingAs($c['admin'], 'admin')->post(route('admin.kkm.bulk.apply'), $payload)->assertSessionHasErrors('kkm');
        $this->assertDatabaseCount('diniyyah_kkm_scopes', 0);
        $this->assertSame(0, PanelNotification::where('notification_type', 'kkm_assigned')->count());
        $this->assertDatabaseMissing('activity_log', ['log_name' => 'kkm', 'event' => 'assigned']);
    }

    public function test_existing_author_is_never_overwritten_by_a_stale_preview(): void
    {
        $c = $this->context();
        $payload = $this->payload($c);
        app(DiniyyahKkmWorkflow::class)->assign($c['ikhwan'], $c['male']->id, $c['admin']);
        $this->actingAs($c['admin'], 'admin')->post(route('admin.kkm.bulk.apply'), $payload)->assertSessionHasErrors('kkm');
        $this->assertSame($c['male']->id, DiniyyahKkmScope::sole()->assigned_teacher_id);
        $response = $this->get(route('admin.kkm.bulk.preview'))->assertOk()->assertSee('Sudah ditetapkan:');
        $this->assertStringNotContainsString('name="selected[]"', $response->getContent());
    }

    public function test_legacy_term_and_locked_documents_prevent_writes(): void
    {
        $c = $this->context();
        $payload = $this->payload($c);
        DiniyyahLedgerSnapshot::create(['academic_term_id' => $c['term']->id, 'classroom_term_id' => $c['ikhwan']->classroom_term_id, 'title' => 'Leger terkunci', 'status' => 'locked']);
        $this->actingAs($c['admin'], 'admin')->post(route('admin.kkm.bulk.apply'), $payload)->assertSessionHasErrors('kkm');
        $this->assertDatabaseCount('diniyyah_kkm_scopes', 0);
        $c['term']->update(['kkm_workflow_enabled' => false]);
        $this->post(route('admin.kkm.bulk.apply'), $payload)->assertSessionHasErrors('kkm');
        $this->assertFalse($c['term']->fresh()->kkm_workflow_enabled);
        $this->assertDatabaseHas('diniyyah_ledger_snapshots', ['title' => 'Leger terkunci', 'status' => 'locked']);
    }

    public function test_kabag_portal_works_and_teacher_cannot_access_bulk_actions(): void
    {
        $c = $this->context();
        $response = $this->actingAs($c['kabag'], 'web')->get(route('kabag.kkm.bulk.preview'))->assertOk();
        $payload = $this->payload($c);
        $payload['preview_token'] = $response->viewData('previewToken');
        $this->post(route('kabag.kkm.bulk.apply'), $payload)->assertSessionHasNoErrors();
        $this->get(route('admin.kkm.bulk.preview'))->assertRedirect();
        $this->actingAs($c['male']->user, 'web')->get(route('kabag.kkm.bulk.preview'))->assertForbidden();
        $this->post(route('kabag.kkm.bulk.apply'), $payload)->assertForbidden();
        $this->get(route('guru.kkm.index'))->assertOk()->assertDontSee('Tetapkan Massal');
    }

    public function test_archived_parallel_class_still_blocks_assignment_when_its_ledger_is_locked(): void
    {
        $c = $this->context();
        DiniyyahLedgerSnapshot::create(['academic_term_id' => $c['term']->id, 'classroom_term_id' => $c['ikhwan']->classroom_term_id, 'title' => 'Leger arsip', 'status' => 'locked']);
        $c['ikhwan']->delete();
        $c['ikhwan']->classroomTerm->classroom->delete();
        $row = app(DiniyyahKkmAssignmentRoster::class)->forTerm($c['term'])['rows'][0];
        $this->assertStringContainsString('Leger arsip', $row['blocked']);
        $this->actingAs($c['admin'], 'admin')->get(route('admin.kkm.bulk.preview'))->assertOk()->assertSee('Leger arsip');
    }

    public function test_teacher_filters_only_show_assigned_levels_even_before_kkm_exists(): void
    {
        $c = $this->context();
        $m2 = $this->classSubject($c['term'], $c['subject'], 'M2 Guru Lain', 'M2');
        DiniyyahTeacherAssignment::create(['diniyyah_class_subject_id' => $m2->id, 'teacher_id' => $c['male']->id]);
        $response = $this->actingAs($c['female']->user, 'web')->get(route('guru.kkm.index'))->assertOk();
        $this->assertSame([1], $response->viewData('mustawaOptions'));
        $this->assertSame([$c['term']->id], $response->viewData('terms')->pluck('id')->all());
        $this->assertSame(['1'], $this->filterOptionValues($response->getContent(), 'mustawa'));
        $this->assertDatabaseCount('diniyyah_kkm_scopes', 0);
        $this->qa('teacher-kkm-filters.html', $response->getContent());
    }

    public function test_teacher_levels_follow_selected_semester_without_showing_other_teachers_terms(): void
    {
        $c = $this->context();
        $nextTerm = AcademicTerm::create(['academic_year_id' => $c['term']->academic_year_id, 'name' => 'Genap', 'semester' => 'genap', 'starts_at' => '2027-01-01', 'ends_at' => '2027-06-30', 'kkm_workflow_enabled' => true]);
        $otherYear = AcademicYear::create(['school_id' => $c['term']->academicYear->school_id, 'name' => '2027/2028']);
        $unassignedTerm = AcademicTerm::create(['academic_year_id' => $otherYear->id, 'name' => 'Semester guru lain', 'semester' => 'genap']);
        $m3 = $this->classSubject($nextTerm, $c['subject'], 'Mustawa 3 Guru', 'Mustawa 3');
        $m4 = $this->classSubject($unassignedTerm, $c['subject'], 'Mustawa 4 Guru Lain', 'M4');
        DiniyyahTeacherAssignment::create(['diniyyah_class_subject_id' => $m3->id, 'teacher_id' => $c['female']->id, 'assignment_role' => 'assistant']);
        DiniyyahTeacherAssignment::create(['diniyyah_class_subject_id' => $m4->id, 'teacher_id' => $c['male']->id]);
        $this->actingAs($c['female']->user, 'web');
        $all = $this->get(route('guru.kkm.index'))->assertOk()->assertDontSee('Semester guru lain');
        $this->assertSame([1, 3], $all->viewData('mustawaOptions'));
        $this->assertSame([(string) $nextTerm->id, (string) $c['term']->id], $this->filterOptionValues($all->getContent(), 'academic_term_id'));
        $this->assertSame([1], $this->get(route('guru.kkm.index', ['academic_term_id' => $c['term']->id]))->assertOk()->viewData('mustawaOptions'));
        $next = $this->get(route('guru.kkm.index', ['academic_term_id' => $nextTerm->id]))->assertOk();
        $this->assertSame(['3'], $this->filterOptionValues($next->getContent(), 'mustawa'));
    }

    public function test_teacher_filter_ignores_expired_inactive_deleted_and_unknown_level_assignments(): void
    {
        $c = $this->context();
        foreach ([2, 3, 4, 5] as $level) {
            $subject = $this->classSubject($c['term'], $c['subject'], 'Kelas tidak memenuhi syarat '.$level, $level === 5 ? 'Jenjang belum dikenal' : 'M'.$level);
            DiniyyahTeacherAssignment::create(['diniyyah_class_subject_id' => $subject->id, 'teacher_id' => $c['female']->id, 'ends_at' => $level === 2 ? '2026-06-30' : null]);
            if ($level === 3) {
                $subject->update(['is_active' => false]);
            } elseif ($level === 4) {
                $subject->delete();
            }
        }
        $response = $this->actingAs($c['female']->user, 'web')->get(route('guru.kkm.index'))->assertOk();
        $this->assertSame(['1'], $this->filterOptionValues($response->getContent(), 'mustawa'));
        $c['female']->update(['status' => 'inactive']);
        $this->actingAs($c['female']->user->fresh(), 'web');
        $this->assertSame([], $this->get(route('guru.kkm.index'))->assertOk()->viewData('mustawaOptions'));
    }

    public function test_teacher_without_teaching_assignment_has_no_extra_filter_options(): void
    {
        $this->context();
        $teacher = $this->teacher('female', 'Guru belum ditugaskan');
        $response = $this->actingAs($teacher->user, 'web')->get(route('guru.kkm.index'))->assertOk();
        $this->assertSame([], $this->filterOptionValues($response->getContent(), 'mustawa'));
        $this->assertSame([], $this->filterOptionValues($response->getContent(), 'academic_term_id'));
    }

    public function test_teacher_portal_filters_stay_personal_for_users_with_management_roles(): void
    {
        $c = $this->context();
        $c['female']->user->assignRole('admin');
        $this->actingAs($c['female']->user, 'web')->get(route('guru.kkm.index'))->assertOk()->assertDontSee('Tetapkan Massal')->assertViewHas('mustawaOptions', [1]);
        $this->actingAs($c['female']->user, 'admin')->get(route('admin.kkm.index'))->assertOk()->assertViewHas('mustawaOptions', range(1, 6));
        $this->actingAs($c['kabag'], 'web')->get(route('kabag.kkm.index'))->assertOk()->assertViewHas('mustawaOptions', range(1, 6));
    }

    public function test_changing_teacher_filter_url_does_not_expose_other_teaching_scopes(): void
    {
        $c = $this->context();
        $m2 = $this->classSubject($c['term'], $c['subject'], 'M2 Guru Lain', 'M2');
        DiniyyahTeacherAssignment::create(['diniyyah_class_subject_id' => $m2->id, 'teacher_id' => $c['male']->id]);
        $scope = app(DiniyyahKkmWorkflow::class)->assign($m2, $c['male']->id, $c['admin']);
        // The teacher portal keeps its teaching boundary even for a user who also has an admin role.
        $c['female']->user->assignRole('admin');
        $this->actingAs($c['female']->user, 'web');
        $response = $this->get(route('guru.kkm.index', ['mustawa' => 2]))->assertOk();
        $this->assertSame([], $response->viewData('scopes')->items());
        $this->assertSame(['1'], $this->filterOptionValues($response->getContent(), 'mustawa'));
        $c['female']->user->removeRole('admin');
        $this->get(route('guru.kkm.show', $scope))->assertForbidden();
    }

    private function filterOptionValues(string $html, string $name): array
    {
        $document = new \DOMDocument;
        @$document->loadHTML($html);
        $options = (new \DOMXPath($document))->query('//select[@name="'.$name.'"]/option[@value!=""]');

        return array_map(fn ($option) => $option->getAttribute('value'), iterator_to_array($options));
    }

    private function qa(string $name, string $content): void
    {
        if (getenv('KKM_QA') === '1') {
            $path = storage_path('app/kkm-qa');
            if (! is_dir($path)) {
                mkdir($path, 0755, true);
            }
            file_put_contents($path.'/'.$name, $content);
        }
    }
}
