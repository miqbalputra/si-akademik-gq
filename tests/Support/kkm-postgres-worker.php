<?php

use App\Models\DiniyyahAssessmentSet;
use App\Models\DiniyyahClassSubject;
use App\Models\DiniyyahKkmRevision;
use App\Models\User;
use App\Services\DiniyyahKkmBulkAssignment;
use App\Services\DiniyyahKkmWorkflow;
use App\Services\DiniyyahSchoolKkm;
use App\Services\DiniyyahScoreCalculator;
use App\Services\DiniyyahTeacherScoreEditor;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

require_once dirname(__DIR__, 2).'/vendor/autoload.php';

/** Only an explicitly created, disposable database on the local test cluster is allowed. */
function kkmPostgresApplication(array $settings): Application
{
    if (! preg_match('/^kkm_assignment_test_[a-f0-9]{12}$/', $settings['database'] ?? '') || ! is_int($settings['port'] ?? null)) {
        throw new RuntimeException('Unsafe PostgreSQL test configuration.');
    }
    $environment = [
        'APP_ENV' => 'testing', 'APP_KEY' => 'base64:'.base64_encode(str_repeat('k', 32)),
        'DB_CONNECTION' => 'pgsql', 'DB_URL' => '', 'DB_HOST' => '127.0.0.1', 'DB_PORT' => (string) $settings['port'],
        'DB_DATABASE' => $settings['database'], 'DB_USERNAME' => 'kkm_test', 'DB_PASSWORD' => '',
        'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync', 'BCRYPT_ROUNDS' => '4',
        'TELESCOPE_ENABLED' => 'false', 'PULSE_ENABLED' => 'false', 'NIGHTWATCH_ENABLED' => 'false',
    ];
    foreach ($environment as $key => $value) {
        putenv($key.'='.$value);
        $_ENV[$key] = $_SERVER[$key] = $value;
    }
    Facade::clearResolvedInstances();
    $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    if (config('database.default') !== 'pgsql' || config('database.connections.pgsql.database') !== $settings['database'] || config('database.connections.pgsql.host') !== '127.0.0.1') {
        throw new RuntimeException('Refusing to test against an unexpected database.');
    }
    DB::statement("SET lock_timeout = '15s'");
    DB::statement("SET statement_timeout = '25s'");

    return $app;
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    $input = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
    kkmPostgresApplication($input['settings']);
    DB::select("SELECT set_config('application_name', ?, false)", [$input['name']]);
    try {
        $result = match ($input['action'] ?? 'bulk') {
            'score_autosave' => app(DiniyyahTeacherScoreEditor::class)->save(DiniyyahAssessmentSet::findOrFail($input['assessment_set']), User::findOrFail($input['actor']), $input['changes']),
            'score_component' => app(DiniyyahTeacherScoreEditor::class)->add(DiniyyahAssessmentSet::findOrFail($input['assessment_set']), User::findOrFail($input['actor']), 'ulangan', 'ulangan_harian_3'),
            'score_recalculate' => app(DiniyyahScoreCalculator::class)->calculateAssessmentSet(DiniyyahAssessmentSet::findOrFail($input['assessment_set'])),
            'school_initialize' => ['revision_id' => app(DiniyyahSchoolKkm::class)->ensure(DiniyyahClassSubject::findOrFail($input['subject']))?->id],
            'approve' => app(DiniyyahKkmWorkflow::class)->review(DiniyyahKkmRevision::findOrFail($input['revision']), User::findOrFail($input['actor']), $input['lock_version'], true),
            'bulk' => app(DiniyyahKkmBulkAssignment::class)->apply($input['term'], $input['entries'], $input['token'], User::findOrFail($input['actor'])),
        };
        echo json_encode(['ok' => true, 'result' => $result], JSON_THROW_ON_ERROR);
    } catch (ValidationException|DomainException $exception) {
        echo json_encode(['ok' => false, 'errors' => $exception instanceof ValidationException ? $exception->errors() : ['ledger' => [$exception->getMessage()]]], JSON_THROW_ON_ERROR);
    } catch (HttpExceptionInterface $exception) {
        echo json_encode(['ok' => false, 'status' => $exception->getStatusCode(), 'message' => $exception->getMessage()], JSON_THROW_ON_ERROR);
    }
}
