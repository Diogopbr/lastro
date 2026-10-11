<?php

namespace Tests\Feature;

use App\Domain\Access\CurrentWorkspace;
use App\Domain\Conferences\CreateConference;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class QuotaConcurrencyTest extends TestCase
{
    public function test_two_processes_starting_from_four_accept_only_one_creation(): void
    {
        $this->race('guarded', 409, 5);
    }

    public function test_negative_control_without_lock_before_quota_exceeds_limit(): void
    {
        $this->race('unsafe', 201, 6);
    }

    private function race(string $mode, int $expectedStatus, int $expectedCount): void
    {
        $original = config('database.connections.pgsql');
        $schema = 'quota_race_'.bin2hex(random_bytes(6));
        $process = null;
        DB::statement('CREATE SCHEMA "'.$schema.'"');
        try {
            config(['database.connections.pgsql.search_path' => $schema]);
            DB::purge('pgsql');
            $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
            $id = (string) Str::uuid();
            $scope = new CurrentWorkspace($id, null, 'demo', (string) Str::uuid());
            DB::table('workspaces')->insert(['id' => $id, 'kind' => 'demo', 'expires_at' => DB::raw("clock_timestamp() + interval '1 hour'")]);
            for ($index = 1; $index <= 4; $index++) {
                app(CreateConference::class)->execute($scope, 'Inicial '.$index, '2026-05-01', '2026-05-31');
            }
            DB::beginTransaction();
            app(CreateConference::class)->execute($scope, 'Quinta', '2026-05-01', '2026-05-31');
            $parentPid = DB::selectOne('SELECT pg_backend_pid() AS pid')->pid;
            $applicationName = 'lastro-quota-'.bin2hex(random_bytes(6));
            $process = new Process([PHP_BINARY, base_path('tests/Fixtures/concurrent-create.php'), $schema, $id, $scope->userId, $applicationName, $mode], base_path(), ['APP_ENV' => 'testing']);
            $process->setTimeout(20);
            $process->start();
            $blocked = false;
            $deadline = microtime(true) + 10;
            while ($process->isRunning() && microtime(true) < $deadline) {
                DB::selectOne('SELECT pg_stat_clear_snapshot()');
                $blocked = DB::selectOne('SELECT pid FROM pg_stat_activity WHERE application_name = ? AND ? = ANY(pg_blocking_pids(pid))', [$applicationName, $parentPid]) !== null;
                if ($blocked) {
                    break;
                }
                usleep(10000);
            }
            $this->assertTrue($blocked, 'No independent PostgreSQL worker was observed blocked by the parent. '.$process->getErrorOutput().$process->getOutput());
            DB::commit();
            $process->wait();
            $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
            $this->assertSame($expectedStatus, json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR)['status']);
            $this->assertSame($expectedCount, DB::table('conferences')->where('workspace_id', $id)->count());
        } finally {
            while (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            $process?->stop(1);
            config(['database.connections.pgsql' => $original]);
            DB::purge('pgsql');
            DB::statement('DROP SCHEMA "'.$schema.'" CASCADE');
        }
    }
}
