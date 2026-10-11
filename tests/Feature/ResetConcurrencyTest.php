<?php

namespace Tests\Feature;

use App\Domain\Access\CurrentWorkspace;
use App\Domain\Access\DemoWorkspace;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ResetConcurrencyTest extends TestCase
{
    public function test_two_resets_are_serialized_and_second_is_rate_limited(): void
    {
        $this->race('reset', 429);
    }

    public function test_reset_invalidates_a_waiting_mutation_of_old_generation(): void
    {
        $this->race('guarded', 404);
    }

    private function race(string $mode, int $expectedStatus): void
    {
        $original = config('database.connections.pgsql');
        $schema = 'reset_race_'.bin2hex(random_bytes(6));
        $process = null;
        DB::statement('CREATE SCHEMA "'.$schema.'"');
        try {
            config(['database.connections.pgsql.search_path' => $schema]);
            DB::purge('pgsql');
            Cache::purge('database');
            $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
            $token = str_repeat('d', 64);
            $ip = '127.0.0.19';
            $user = app(DemoWorkspace::class)->start($token);
            $scope = new CurrentWorkspace($user->workspace_id, null, 'demo', $user->id);
            DB::beginTransaction();
            $replacement = app(DemoWorkspace::class)->reset($scope, $token, $ip);
            $this->assertSame(1, DB::transactionLevel());
            $parentPid = DB::selectOne('SELECT pg_backend_pid() AS pid')->pid;
            $applicationName = 'lastro-reset-'.bin2hex(random_bytes(6));
            $process = new Process([PHP_BINARY, base_path('tests/Fixtures/concurrent-create.php'), $schema, $scope->id, $scope->userId, $applicationName, $mode, $token, $ip], base_path(), ['APP_ENV' => 'testing']);
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
            $this->assertTrue($blocked, 'Independent worker was not observed blocked. Parent level='.DB::transactionLevel().' schema='.$schema.' '.$process->getErrorOutput().$process->getOutput());
            DB::commit();
            $process->wait();
            $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
            $this->assertSame($expectedStatus, json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR)['status']);
            $this->assertSame(2, DB::table('workspaces')->count());
            $this->assertSame(3, DB::table('conferences')->where('workspace_id', $replacement->workspace_id)->count());
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
