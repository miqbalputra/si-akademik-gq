<?php

namespace Tests\Feature;

use App\Services\DatabaseDumpRunner;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class DatabaseBackupTest extends TestCase
{
    public function test_database_backup_command_uploads_a_dump_to_the_private_backup_disk(): void
    {
        config([
            'database.default' => 'pgsql',
            'database.connections.pgsql' => [
                'driver' => 'pgsql',
                'database' => 'edu_test',
                'host' => 'db.internal',
                'port' => 5432,
                'username' => 'backup-user',
                'password' => 'test-only',
            ],
            'backup.disk' => 'backup',
            'backup.prefix' => 'database-backups',
            'backup.retention_days' => 30,
        ]);
        Storage::fake('backup');

        $runner = Mockery::mock(DatabaseDumpRunner::class);
        $runner->shouldReceive('dumpTo')->once()->andReturnUsing(function (string $path): void {
            file_put_contents($path, 'test-postgres-archive');
        });
        $this->app->instance(DatabaseDumpRunner::class, $runner);

        $this->artisan('db:backup')->assertSuccessful();

        $files = Storage::disk('backup')->allFiles('database-backups');
        $this->assertCount(1, $files);
        $this->assertStringEndsWith('.dump', $files[0]);
        $this->assertSame('test-postgres-archive', Storage::disk('backup')->get($files[0]));
    }
}
