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
use App\Services\RppSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RppSourceSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_source_rpp_is_created_then_archived_when_source_soft_deletes_it(): void
    {
        $context = $this->context();
        config()->set('rpp_sync.enabled', true);
        config()->set('rpp_sync.source', 'https://rpp.test');
        config()->set('rpp_sync.token', 'source-token');
        $active = $this->payload($context, null);
        $deleted = $this->payload($context, now()->addSecond()->toIso8601String());
        Http::fake(['rpp.test/api/integrations/v1/rpp/source-rpp-1' => Http::sequence()->push(['data' => $active])->push(['data' => $deleted])]);

        app(RppSyncService::class)->syncEntity('rpp', 'source-rpp-1');
        $rpp = Rpp::where('legacy_source_id', 'source-rpp-1')->firstOrFail();
        $this->assertSame('Fiqih Sinkron', $rpp->materi);
        $this->assertDatabaseCount('rpp_meetings', 1);

        app(RppSyncService::class)->syncEntity('rpp', 'source-rpp-1');
        $this->assertSoftDeleted('rpps', ['id' => $rpp->id]);
    }

    private function payload(array $context, ?string $deletedAt): array
    {
        return [
            'id' => 'source-rpp-1', 'noRpp' => 'SRC-01', 'materi' => 'Fiqih Sinkron', 'alokasiWaktu' => '2 JP', 'tujuanPembelajaran' => 'Tujuan sinkron.',
            'status' => 'DRAFT', 'tanggalPengesahan' => '2026-09-04T00:00:00.000Z', 'dibuatDenganAI' => false, 'metodeInput' => 'MANUAL',
            'createdAt' => '2026-09-04T00:00:00.000Z', 'updatedAt' => now()->toIso8601String(), 'deletedAt' => $deletedAt,
            'guru' => ['id' => 'source-teacher-1', 'namaTampil' => $context['teacher']->name, 'user' => ['username' => $context['user']->username, 'email' => $context['user']->email]],
            'mapel' => ['id' => 'source-subject-1', 'namaMapel' => $context['subject']->name],
            'kelas' => ['id' => 'source-class-1', 'namaKelas' => $context['termClass']->name, 'semester' => 'ganjil', 'tahunAjaran' => '2026/2027'],
            'pertemuan' => [['urutan' => 1, 'isiKegiatan' => 'Kegiatan sinkron.', 'tanggal' => '2026-09-04T00:00:00.000Z']],
            'penilaian' => ['pengetahuan' => 'Tes', 'keterampilan' => 'Praktik', 'sikap' => 'Observasi'], 'file' => null,
        ];
    }

    private function context(): array
    {
        Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web']);
        $user = User::factory()->create(['username' => 'guru-sync']);
        $user->assignRole('guru');
        $teacher = Teacher::create(['user_id' => $user->id, 'name' => 'Guru Sinkron']);
        $school = School::firstOrCreate(['name' => 'Sekolah Uji']);
        $year = AcademicYear::firstOrCreate(['school_id' => $school->id, 'name' => '2026/2027']);
        $term = AcademicTerm::firstOrCreate(['academic_year_id' => $year->id, 'name' => 'Ganjil'], ['semester' => 'ganjil']);
        $classroom = Classroom::create(['name' => 'Kelas Sinkron']);
        $termClass = ClassroomTerm::create(['academic_term_id' => $term->id, 'classroom_id' => $classroom->id, 'name' => 'Kelas Sinkron']);
        $subject = DiniyyahSubject::create(['code' => 'fiqih-sync', 'name' => 'Fiqih Sinkron', 'default_assessment_method' => 'weighted']);
        $classSubject = DiniyyahClassSubject::create(['classroom_term_id' => $termClass->id, 'subject_id' => $subject->id, 'assessment_method' => 'weighted', 'kkm' => 70, 'daily_weight' => 40, 'exam_weight' => 60, 'is_active' => true]);
        DiniyyahTeacherAssignment::create(['diniyyah_class_subject_id' => $classSubject->id, 'teacher_id' => $teacher->id, 'assignment_role' => 'primary']);
        return compact('user', 'teacher', 'termClass', 'subject');
    }
}
