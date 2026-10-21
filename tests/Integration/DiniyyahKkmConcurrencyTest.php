<?php

namespace Tests\Integration;

use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\ClassroomTerm;
use App\Models\DiniyyahAssessmentSet;
use App\Models\DiniyyahClassSubject;
use App\Models\DiniyyahKkmScope;
use App\Models\DiniyyahLedgerSnapshot;
use App\Models\DiniyyahSubject;
use App\Models\DiniyyahTeacherAssignment;
use App\Models\PanelNotification;
use App\Models\School;
use App\Models\Teacher;
use App\Models\User;
use App\Services\DiniyyahKkmAssignmentRoster;
use App\Services\DiniyyahKkmBulkAssignment;
use App\Services\DiniyyahKkmCalculator;
use App\Services\DiniyyahKkmResolver;
use App\Services\DiniyyahLedgerWorkflow;
use Illuminate\Foundation\Bootstrap\HandleExceptions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PDO;
use PHPUnit\Framework\TestCase;
use Spatie\Permission\Models\Role;
use Symfony\Component\Process\Process;

require_once dirname(__DIR__).'/Support/kkm-postgres-worker.php';

/** Run explicitly: KKM_PG_TEST_PORT=<local port> php vendor/bin/phpunit --no-configuration --bootstrap vendor/autoload.php tests/Integration */
class DiniyyahKkmConcurrencyTest extends TestCase
{
    private ?PDO $cluster = null;

    private ?array $settings = null;

    private array $processes = [];

    protected function setUp(): void
    {
        if (! getenv('KKM_PG_TEST_PORT')) {
            $this->markTestSkipped('Requires an explicitly configured disposable local PostgreSQL cluster (user kkm_test).');
        }
        $port = (int) getenv('KKM_PG_TEST_PORT');
        $this->settings = ['port' => $port, 'database' => 'kkm_assignment_test_'.bin2hex(random_bytes(6))];
        $this->cluster = new PDO('pgsql:host=127.0.0.1;port='.$port.';dbname=postgres', 'kkm_test', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->cluster->exec('CREATE DATABASE '.$this->settings['database']);
        kkmPostgresApplication($this->settings);
        Artisan::call('migrate', ['--force' => true]);
    }

    protected function tearDown(): void
    {
        foreach ($this->processes as $process) {
            if ($process->isRunning()) {
                $process->stop();
            }
        }
        if ($this->cluster && $this->settings) {
            while (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            DB::disconnect();
            $this->cluster->exec('DROP DATABASE '.$this->settings['database'].' WITH (FORCE)');
            HandleExceptions::flushState($this);
        }
        parent::tearDown();
    }

    private function context(): array
    {
        foreach (['admin', 'guru'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $school = School::create(['name' => 'Isolated PG concurrency']);
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2026/2027']);
        $term = AcademicTerm::create(['academic_year_id' => $year->id, 'name' => 'Ganjil', 'semester' => 'ganjil', 'kkm_workflow_enabled' => true]);
        $subject = DiniyyahSubject::create(['code' => 'pg_kkm', 'name' => 'Akidah Akhlak']);
        $classroom = Classroom::create(['name' => 'M1', 'level_name' => 'M1']);
        $ct = ClassroomTerm::create(['academic_term_id' => $term->id, 'classroom_id' => $classroom->id, 'name' => 'M1']);
        $cs = DiniyyahClassSubject::create(['classroom_term_id' => $ct->id, 'subject_id' => $subject->id, 'is_active' => true, 'appears_on_ledger' => true, 'assessment_method' => 'direct_final']);
        $teachers = [];
        foreach (['female', 'male'] as $gender) {
            $user = User::factory()->create();
            $user->assignRole('guru');
            $teacher = Teacher::create(['name' => $gender, 'gender' => $gender, 'status' => 'active', 'user_id' => $user->id]);
            DiniyyahTeacherAssignment::create(['teacher_id' => $teacher->id, 'diniyyah_class_subject_id' => $cs->id]);
            $teachers[$gender] = $teacher;
        }
        $scope = app(DiniyyahKkmResolver::class)->scopeFor($cs, true);

        return compact('admin', 'term', 'cs', 'ct', 'scope', 'teachers');
    }

    private function worker(array $c, string $gender, string $name): Process
    {
        $token = app(DiniyyahKkmBulkAssignment::class)->previewToken($c['term'], app(DiniyyahKkmAssignmentRoster::class)->forTerm($c['term'])['rows'], $c['admin']);
        $process = new Process([PHP_BINARY, dirname(__DIR__).'/Support/kkm-postgres-worker.php'], dirname(__DIR__, 2));
        $process->setInput(json_encode([
            'settings' => $this->settings, 'name' => $name, 'term' => $c['term']->id, 'actor' => $c['admin']->id, 'token' => $token,
            'entries' => [['class_subject_id' => $c['cs']->id, 'teacher_id' => $c['teachers'][$gender]->id, 'gender' => $gender]],
        ], JSON_THROW_ON_ERROR));
        $process->setTimeout(60);
        $process->start();
        $this->processes[] = $process;

        return $process;
    }

    private function awaitBlocked(int $count): void
    {
        $until = microtime(true) + 12;
        do {
            $waiting = $this->cluster->query("SELECT count(*) FROM pg_stat_activity WHERE application_name LIKE 'kkm_concurrent_%' AND wait_event_type = 'Lock'")->fetchColumn();
            if ((int) $waiting >= $count) {
                $this->assertGreaterThanOrEqual($count, (int) $waiting);

                return;
            }
            usleep(25000);
        } while (microtime(true) < $until);
        $this->fail('Workers did not reach the expected PostgreSQL row locks. '.implode(' ', array_map(fn ($p) => $p->getOutput().$p->getErrorOutput(), $this->processes)));
    }

    private function processResult(Process $process): array
    {
        $process->wait();
        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput());

        return json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
    }

    public function test_two_managers_cannot_overwrite_each_other_or_duplicate_notifications(): void
    {
        $c = $this->context();
        $c['scope']->delete();
        DB::beginTransaction();
        AcademicTerm::whereKey($c['term']->id)->lockForUpdate()->firstOrFail();
        $first = $this->worker($c, 'female', 'kkm_concurrent_first');
        $second = $this->worker($c, 'male', 'kkm_concurrent_second');
        $this->awaitBlocked(2);
        DB::commit();
        $results = [$this->processResult($first), $this->processResult($second)];
        $this->assertCount(1, array_filter($results, fn ($r) => $r['ok']));
        $this->assertContains(DiniyyahKkmScope::sole()->assigned_teacher_id, collect($c['teachers'])->pluck('id')->all());
        $this->assertSame(1, PanelNotification::where('notification_type', 'kkm_assigned')->count());
        $this->assertSame(1, DB::table('activity_log')->where('log_name', 'kkm')->where('event', 'assigned')->count());
    }

    public function test_batch_waits_for_ledger_lock_then_rejects_assignment(): void
    {
        $c = $this->context();
        $criteria = array_fill_keys(array_keys(DiniyyahKkmCalculator::CRITERIA), ['score' => 75, 'reason' => 'Kondisi kelas']);
        $revision = $c['scope']->revisions()->create(['version' => 1, 'status' => 'approved'] + app(DiniyyahKkmCalculator::class)->calculate($criteria, true));
        $c['scope']->update(['active_revision_id' => $revision->id]);
        $set = DiniyyahAssessmentSet::create(['diniyyah_class_subject_id' => $c['cs']->id, 'title' => 'Akidah', 'assessment_method' => 'direct_final', 'status' => 'validated', 'appears_on_ledger' => true]);
        $snapshot = DiniyyahLedgerSnapshot::create(['academic_term_id' => $c['term']->id, 'classroom_term_id' => $c['ct']->id, 'title' => 'Leger PG', 'status' => 'validated', 'snapshot_data' => ['columns' => [['source_type' => 'diniyyah_assessment_set', 'assessment_set_id' => $set->id, 'kkm_revision_id' => $revision->id]], 'summary' => ['blocking_issues' => 0]]]);
        // Preview before finalization; hold the actual workflow's transaction open while the worker tries to assign.
        $token = app(DiniyyahKkmBulkAssignment::class)->previewToken($c['term'], app(DiniyyahKkmAssignmentRoster::class)->forTerm($c['term'])['rows'], $c['admin']);
        DB::beginTransaction();
        app(DiniyyahLedgerWorkflow::class)->lock($snapshot, $c['admin']);
        // worker() must use the pre-lock snapshot, not inspect this connection's uncommitted ledger.
        $process = new Process([PHP_BINARY, dirname(__DIR__).'/Support/kkm-postgres-worker.php'], dirname(__DIR__, 2));
        $process->setInput(json_encode(['settings' => $this->settings, 'name' => 'kkm_concurrent_ledger', 'term' => $c['term']->id, 'actor' => $c['admin']->id, 'token' => $token, 'entries' => [['class_subject_id' => $c['cs']->id, 'teacher_id' => $c['teachers']['female']->id, 'gender' => 'female']]], JSON_THROW_ON_ERROR));
        $process->setTimeout(60)->start();
        $this->processes[] = $process;
        $this->awaitBlocked(1);
        DB::commit();
        $this->assertFalse($this->processResult($process)['ok']);
        $this->assertNull($c['scope']->fresh()->assigned_teacher_id);
        $this->assertSame('locked', $snapshot->fresh()->status);
        $this->assertSame(0, PanelNotification::where('notification_type', 'kkm_assigned')->count());
    }
}
