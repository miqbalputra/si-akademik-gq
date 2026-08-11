<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\ClassEnrollment;
use App\Models\Classroom;
use App\Models\ClassroomTerm;
use App\Models\Guardian;
use App\Models\HomeroomAssignment;
use App\Models\PanelNotification;
use App\Models\School;
use App\Models\SchoolEvent;
use App\Models\Student;
use App\Models\TasmiExaminerAssignment;
use App\Models\TasmiRecord;
use App\Models\Teacher;
use App\Models\User;
use App\Services\NotificationDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class NotificationDispatchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['admin', 'kabag_diniyyah', 'kabag_tahfidz', 'kepala_sekolah', 'guru', 'wali_santri'] as $r) {
            Role::firstOrCreate(['name' => $r, 'guard_name' => 'web']);
        }
    }

    private function makeContext(): array
    {
        $school = School::create(['name' => 'GQ']);
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2026/2027']);
        $term = AcademicTerm::create([
            'academic_year_id' => $year->id, 'name' => 'Ganjil', 'semester' => 'ganjil',
            'starts_at' => '2026-07-13', 'ends_at' => '2026-12-31', 'is_active' => true,
        ]);
        $classroom = Classroom::create(['name' => 'M1 Ikhwan', 'gender_group' => 'male', 'level_name' => 'M1', 'sort_order' => 1, 'is_active' => true]);
        $ct = ClassroomTerm::create(['academic_term_id' => $term->id, 'classroom_id' => $classroom->id, 'name' => $classroom->name, 'status' => 'active']);

        $guruUser = User::factory()->create(['name' => 'Ustadz PJ']);
        $guruUser->assignRole('guru');
        $teacher = Teacher::create(['user_id' => $guruUser->id, 'name' => 'Ustadz PJ', 'gender' => 'male', 'niy' => 'N1', 'status' => 'active']);

        $waliUser = User::factory()->create(['name' => 'Wali Kelas']);
        $waliUser->assignRole('guru');
        $waliTeacher = Teacher::create(['user_id' => $waliUser->id, 'name' => 'Wali Kelas', 'gender' => 'male', 'niy' => 'N2', 'status' => 'active']);
        HomeroomAssignment::create(['classroom_term_id' => $ct->id, 'teacher_id' => $waliTeacher->id]);

        $student = Student::create(['name' => 'Santri A', 'gender' => 'male', 'nis' => '111', 'status' => 'active']);
        $enrollment = ClassEnrollment::create(['academic_term_id' => $term->id, 'classroom_term_id' => $ct->id, 'student_id' => $student->id, 'status' => 'active']);

        $waliSantriUser = User::factory()->create(['name' => 'Wali Santri']);
        $waliSantriUser->assignRole('wali_santri');
        $guardian = Guardian::create(['user_id' => $waliSantriUser->id, 'name' => 'Ayah Santri', 'gender' => 'male', 'status' => 'active']);
        DB::table('student_guardians')->insert([
            'student_id' => $student->id, 'guardian_id' => $guardian->id,
            'relationship' => 'father', 'is_primary' => true, 'can_login' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $kabagTahfidzUser = User::factory()->create(['name' => 'Kabag Tahfidz']);
        $kabagTahfidzUser->assignRole('kabag_tahfidz');

        return compact('school', 'year', 'term', 'classroom', 'ct', 'guruUser', 'teacher', 'waliUser', 'waliTeacher', 'student', 'enrollment', 'waliSantriUser', 'guardian', 'kabagTahfidzUser');
    }

    public function test_dispatcher_to_user_creates_notification(): void
    {
        $ctx = $this->makeContext();

        app(NotificationDispatcher::class)->dispatchToUser(
            $ctx['waliUser']->id, 'Test', 'Body test', 'tasmi_created', '/test',
        );

        $this->assertDatabaseHas('panel_notifications', [
            'user_id' => $ctx['waliUser']->id,
            'title' => 'Test',
            'notification_type' => 'tasmi_created',
            'status' => 'unread',
        ]);
    }

    public function test_dispatcher_to_role_creates_broadcast_notification(): void
    {
        $this->makeContext();

        app(NotificationDispatcher::class)->dispatchToRole(
            'kabag_tahfidz', 'Test role', 'Body role', 'tasmi_created', '/test',
        );

        $this->assertDatabaseHas('panel_notifications', [
            'user_id' => null,
            'audience_role' => 'kabag_tahfidz',
            'title' => 'Test role',
        ]);
    }

    public function test_dispatcher_batches_repeated_events_within_10_minutes(): void
    {
        $ctx = $this->makeContext();
        // Gunakan user fresh tanpa observer-triggered notif untuk isolasi batching.
        $freshUser = User::factory()->create();
        $freshUser->assignRole('guru');
        $dispatcher = app(NotificationDispatcher::class);

        // Kirim 5 event beruntin dengan type+link yang sama.
        for ($i = 0; $i < 5; $i++) {
            $dispatcher->dispatchToUser($freshUser->id, 'Batch', "Body {$i}", 'diniyyah_score_input', '/same-link');
        }

        // Harus hanya 1 baris notifikasi (bukan 5).
        $count = PanelNotification::where('user_id', $freshUser->id)
            ->where('notification_type', 'diniyyah_score_input')
            ->count();
        $this->assertEquals(1, $count, 'Batching should produce 1 notification, not 5');

        $notif = PanelNotification::where('user_id', $freshUser->id)->where('notification_type', 'diniyyah_score_input')->first();
        $this->assertEquals(5, $notif->batch_count);
    }

    public function test_dispatcher_to_homeroom_teacher_resolves_correct_user(): void
    {
        $ctx = $this->makeContext();

        $sent = app(NotificationDispatcher::class)->dispatchToHomeroomTeacher(
            $ctx['ct']->id, 'Test homeroom', 'Body', 'tasmi_created', '/test',
        );

        $this->assertEquals(1, $sent);
        $this->assertDatabaseHas('panel_notifications', [
            'user_id' => $ctx['waliUser']->id,
            'title' => 'Test homeroom',
        ]);
    }

    public function test_dispatcher_to_guardians_resolves_wali_santri(): void
    {
        $ctx = $this->makeContext();

        $sent = app(NotificationDispatcher::class)->dispatchToGuardiansOfStudent(
            $ctx['student']->id, 'Test guardian', 'Body', 'attendance_absent', '/test',
        );

        $this->assertEquals(1, $sent);
        $this->assertDatabaseHas('panel_notifications', [
            'user_id' => $ctx['waliSantriUser']->id,
            'title' => 'Test guardian',
        ]);
    }

    public function test_tasmi_record_created_notifies_wali_kelas_and_kabag_and_pj(): void
    {
        $ctx = $this->makeContext();
        TasmiExaminerAssignment::create([
            'academic_term_id' => $ctx['term']->id,
            'teacher_id' => $ctx['teacher']->id,
            'status' => 'active',
            'assigned_by' => $ctx['guruUser']->id,
        ]);

        // PJ Tasmi' input record baru.
        TasmiRecord::create([
            'academic_term_id' => $ctx['term']->id,
            'classroom_term_id' => $ctx['ct']->id,
            'class_enrollment_id' => $ctx['enrollment']->id,
            'student_id' => $ctx['student']->id,
            'examiner_teacher_id' => $ctx['teacher']->id,
            'exam_type' => '1_juz',
            'juz_start' => 30, 'juz_end' => 30,
            'exam_date' => '2026-08-15',
            'predicate' => 'mumtaz',
            'input_by' => $ctx['guruUser']->id,
            'input_at' => now(),
            'last_updated_by' => $ctx['guruUser']->id,
        ]);

        // Wali kelas dapat notif.
        $this->assertDatabaseHas('panel_notifications', [
            'user_id' => $ctx['waliUser']->id,
            'notification_type' => 'tasmi_created',
        ]);
        // PJ sendiri dapat receipt.
        $this->assertDatabaseHas('panel_notifications', [
            'user_id' => $ctx['guruUser']->id,
            'notification_type' => 'tasmi_created',
        ]);
        // Kabag tahfidz dapat broadcast.
        $this->assertDatabaseHas('panel_notifications', [
            'user_id' => null,
            'audience_role' => 'kabag_tahfidz',
            'notification_type' => 'tasmi_created',
        ]);
    }

    public function test_tasmi_examiner_assignment_notifies_guru(): void
    {
        $ctx = $this->makeContext();

        // Admin assign PJ Tasmi' → guru tsb dapat notif.
        TasmiExaminerAssignment::create([
            'academic_term_id' => $ctx['term']->id,
            'teacher_id' => $ctx['teacher']->id,
            'status' => 'active',
            'assigned_by' => $ctx['guruUser']->id,
        ]);

        $this->assertDatabaseHas('panel_notifications', [
            'user_id' => $ctx['guruUser']->id,
            'notification_type' => 'assignment_created',
        ]);
    }

    public function test_school_event_notifies_guru_and_wali_santri(): void
    {
        $ctx = $this->makeContext();

        SchoolEvent::create([
            'school_id' => $ctx['school']->id,
            'academic_term_id' => $ctx['term']->id,
            'title' => 'Outdoor Test',
            'event_type' => 'outdoor',
            'target_scope' => 'all',
            'starts_on' => '2026-09-01',
            'ends_on' => '2026-09-01',
            'show_to_teachers' => true,
            'show_to_guardians' => true,
        ]);

        // Guru dapat (broadcast by role).
        $this->assertDatabaseHas('panel_notifications', [
            'audience_role' => 'guru',
            'notification_type' => 'school_event_created',
        ]);
        // Wali santri dapat (broadcast by role).
        $this->assertDatabaseHas('panel_notifications', [
            'audience_role' => 'wali_santri',
            'notification_type' => 'school_event_created',
        ]);
    }

    public function test_notification_feed_endpoint_returns_json(): void
    {
        $ctx = $this->makeContext();
        // Gunakan user baru tanpa observer-triggered notif, agar count akurat.
        $freshUser = User::factory()->create();
        $freshUser->assignRole('guru');
        app(NotificationDispatcher::class)->dispatchToUser(
            $freshUser->id, 'Feed test', 'Body', 'tasmi_created', '/test',
        );

        $resp = $this->actingAs($freshUser)->get(route('notifications.feed'));

        $resp->assertOk();
        $resp->assertJsonStructure(['unread_count', 'notifications']);
        $resp->assertJsonPath('unread_count', 1);
    }

    public function test_mark_all_read_clears_unread_status(): void
    {
        $ctx = $this->makeContext();
        $freshUser = User::factory()->create();
        $freshUser->assignRole('guru');
        app(NotificationDispatcher::class)->dispatchToUser($freshUser->id, 'A', 'Body', 'tasmi_created', '/a');
        app(NotificationDispatcher::class)->dispatchToUser($freshUser->id, 'B', 'Body', 'tasmi_updated', '/b');

        $resp = $this->actingAs($freshUser)->postJson(route('notifications.read-all'));

        $resp->assertOk();
        $this->assertEquals(0, PanelNotification::where('user_id', $freshUser->id)->where('status', 'unread')->count());
    }

    public function test_archive_hides_notification(): void
    {
        $ctx = $this->makeContext();
        $freshUser = User::factory()->create();
        $freshUser->assignRole('guru');
        app(NotificationDispatcher::class)->dispatchToUser($freshUser->id, 'Archive me', 'Body', 'tasmi_created', '/test');
        $notif = PanelNotification::where('user_id', $freshUser->id)->first();

        $resp = $this->actingAs($freshUser)->deleteJson(route('notifications.archive', $notif));

        $resp->assertOk();
        $this->assertNotNull($notif->fresh()->archived_at);
    }

    public function test_notification_index_page_loads_for_guru(): void
    {
        $ctx = $this->makeContext();

        $resp = $this->actingAs($ctx['guruUser'])->get(route('notifications.index'));

        $resp->assertOk();
        $resp->assertSee('Notifikasi');
    }

    public function test_relevant_for_scope_includes_both_direct_and_role_broadcast(): void
    {
        $ctx = $this->makeContext();
        // Direct notif ke waliUser.
        app(NotificationDispatcher::class)->dispatchToUser($ctx['waliUser']->id, 'Direct', 'B', 'tasmi_created', '/x');
        // Broadcast ke role guru.
        app(NotificationDispatcher::class)->dispatchToRole('guru', 'Role', 'B', 'school_event_created', '/y');

        $resp = $this->actingAs($ctx['waliUser'])->get(route('notifications.feed'));
        $data = $resp->json();

        $titles = collect($data['notifications'])->pluck('title')->all();
        $this->assertContains('Direct', $titles);
        $this->assertContains('Role', $titles);
    }
}