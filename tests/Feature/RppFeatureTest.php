<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\ClassroomTerm;
use App\Models\DiniyyahClassSubject;
use App\Models\DiniyyahSubject;
use App\Models\DiniyyahTeacherAssignment;
use App\Models\Rpp;
use App\Models\School;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RppFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    public function test_guru_can_create_structured_rpp_only_for_active_assignment(): void
    {
        $ctx = $this->context();
        $response = $this->actingAs($ctx['user'])->post(route('guru.rpp.store'), $this->payload($ctx['classSubject']->id));

        $response->assertRedirect();
        $this->assertDatabaseHas('rpps', ['teacher_id' => $ctx['teacher']->id, 'materi' => 'Fiqih Thaharah', 'input_method' => 'manual']);
        $this->assertDatabaseCount('rpp_meetings', 1);
        $this->assertDatabaseCount('rpp_assessments', 1);
    }

    public function test_rpp_portal_pages_render_for_assigned_teacher(): void
    {
        $ctx = $this->context();
        $this->actingAs($ctx['user'])->get(route('guru.rpp.index'))->assertOk()->assertSee('RPP Saya');
        $this->actingAs($ctx['user'])->get(route('guru.rpp.create'))->assertOk()->assertSee('Buat RPP Baru');
        $this->actingAs($ctx['user'])->get(route('guru.rpp.references'))->assertOk()->assertSee('Referensi RPP');
        $this->actingAs($ctx['user'])->get(route('guru.rpp.promes'))->assertOk()->assertSee('Program Semester');
    }

    public function test_guru_cannot_create_rpp_for_other_teacher_assignment(): void
    {
        $ctx = $this->context();
        $other = $this->context('Guru Lain', 'lain');
        $response = $this->actingAs($ctx['user'])->post(route('guru.rpp.store'), $this->payload($other['classSubject']->id));

        $response->assertForbidden();
        $this->assertDatabaseCount('rpps', 0);
    }

    public function test_other_teacher_cannot_view_or_duplicate_unrelated_rpp(): void
    {
        $ctx = $this->context();
        $rpp = $this->makeRpp($ctx);
        $other = $this->context('Guru Lain', 'lain');

        $this->actingAs($other['user'])->get(route('guru.rpp.show', $rpp))->assertForbidden();
        $this->actingAs($other['user'])->post(route('guru.rpp.duplicate', $rpp))->assertForbidden();
    }

    public function test_pdf_export_and_signed_share_download_are_available(): void
    {
        Storage::fake('rpp');
        $ctx = $this->context();
        $rpp = $this->makeRpp($ctx);

        $this->actingAs($ctx['user'])->get(route('guru.rpp.export', [$rpp, 'pdf']))->assertOk();
        $export = $rpp->exports()->where('type', 'pdf')->firstOrFail();
        Storage::disk('rpp')->assertExists($export->path);

        $url = URL::temporarySignedRoute('rpp.shared-download', now()->addMinutes(5), ['export' => $export->id]);
        $this->get($url)->assertOk();
    }

    public function test_soft_deleted_rpp_can_be_restored_by_owner(): void
    {
        $ctx = $this->context();
        $rpp = $this->makeRpp($ctx);
        $this->actingAs($ctx['user'])->delete(route('guru.rpp.destroy', $rpp))->assertRedirect(route('guru.rpp.index'));
        $this->assertSoftDeleted('rpps', ['id' => $rpp->id]);
        $this->actingAs($ctx['user'])->post(route('guru.rpp.restore', $rpp->id))->assertRedirect(route('guru.rpp.trash'));
        $this->assertDatabaseHas('rpps', ['id' => $rpp->id, 'deleted_at' => null]);
    }

    private function payload(int $classSubjectId): array
    {
        return [
            'input_method' => 'manual', 'diniyyah_class_subject_id' => $classSubjectId, 'no_rpp' => 'RPP-001',
            'materi' => 'Fiqih Thaharah', 'alokasi_waktu' => '2 x 35 menit', 'tujuan_pembelajaran' => 'Santri memahami thaharah.',
            'tanggal_pengesahan' => '2026-09-03', 'meetings' => [['isi_kegiatan' => 'Pembukaan, materi, latihan.', 'tanggal_kbm' => '2026-09-04']],
            'pengetahuan' => 'Tes tulis', 'keterampilan' => 'Praktik', 'sikap' => 'Observasi',
        ];
    }

    private function makeRpp(array $ctx): Rpp
    {
        return Rpp::create([
            'diniyyah_class_subject_id' => $ctx['classSubject']->id, 'diniyyah_teacher_assignment_id' => $ctx['assignment']->id,
            'teacher_id' => $ctx['teacher']->id, 'created_by' => $ctx['user']->id, 'materi' => 'RPP Referensi', 'alokasi_waktu' => '2 JP',
            'tujuan_pembelajaran' => 'Tujuan', 'tanggal_pengesahan' => '2026-09-03',
        ]);
    }

    private function context(string $name = 'Guru Pemilik', string $suffix = 'pemilik'): array
    {
        Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web']);
        $user = User::factory()->create(['name' => $name, 'username' => "guru-{$suffix}"]);
        $user->assignRole('guru');
        $teacher = Teacher::create(['user_id' => $user->id, 'name' => $name]);
        $school = School::firstOrCreate(['name' => 'Sekolah Uji']);
        $year = AcademicYear::firstOrCreate(['school_id' => $school->id, 'name' => '2026/2027']);
        $term = AcademicTerm::firstOrCreate(['academic_year_id' => $year->id, 'name' => 'Ganjil'], ['semester' => 'ganjil']);
        $classroom = Classroom::create(['name' => "Kelas {$suffix}"]);
        $termClass = ClassroomTerm::create(['academic_term_id' => $term->id, 'classroom_id' => $classroom->id, 'name' => "Kelas {$suffix}"]);
        $subject = DiniyyahSubject::create(['code' => "fiqih-{$suffix}", 'name' => "Fiqih {$suffix}", 'default_assessment_method' => 'weighted']);
        $classSubject = DiniyyahClassSubject::create(['classroom_term_id' => $termClass->id, 'subject_id' => $subject->id, 'assessment_method' => 'weighted', 'kkm' => 70, 'daily_weight' => 40, 'exam_weight' => 60, 'is_active' => true]);
        $assignment = DiniyyahTeacherAssignment::create(['diniyyah_class_subject_id' => $classSubject->id, 'teacher_id' => $teacher->id, 'assignment_role' => 'primary']);
        return compact('user', 'teacher', 'classSubject', 'assignment');
    }
}
