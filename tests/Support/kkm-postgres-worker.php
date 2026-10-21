<?php

use App\Models\User;
use App\Services\DiniyyahKkmBulkAssignment;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Illuminate\Validation\ValidationException;

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
        $result = app(DiniyyahKkmBulkAssignment::class)->apply($input['term'], $input['entries'], $input['token'], User::findOrFail($input['actor']));
        echo json_encode(['ok' => true, 'result' => $result], JSON_THROW_ON_ERROR);
    } catch (ValidationException $exception) {
        echo json_encode(['ok' => false, 'errors' => $exception->errors()], JSON_THROW_ON_ERROR);
    }
}
