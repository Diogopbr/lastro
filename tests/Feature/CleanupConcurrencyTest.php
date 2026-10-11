<?php

namespace Tests\Feature;

use App\Domain\Access\CleanupWorkspaces;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class CleanupConcurrencyTest extends TestCase
{
    public function test_cleanup_skips_a_locked_workspace_and_collects_it_after_release(): void
    {
        $original = config('database.connections.pgsql');
        $schema = 'cleanup_race_'.bin2hex(random_bytes(6));
        $process = null;
        DB::statement('CREATE SCHEMA "'.$schema.'"');
        try {
            config(['database.connections.pgsql.search_path' => $schema]);
            DB::purge('pgsql');
            Cache::purge('database');
            $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
            $ids = [];
            for ($index = 0; $index < 3; $index++) {
                $ids[] = $id = (string) Str::uuid();
                DB::table('workspaces')->insert(['id' => $id, 'kind' => 'demo', 'expires_at' => DB::raw("clock_timestamp() - interval '1 hour'")]);
            }
            DB::beginTransaction();
            DB::table('workspaces')->where('id', $ids[0])->lockForUpdate()->first();
            $process = new Process([PHP_BINARY, base_path('tests/Fixtures/concurrent-create.php'), $schema, $ids[0], (string) Str::uuid(), 'lastro-cleanup-test', 'cleanup'], base_path(), ['APP_ENV' => 'testing']);
            $process->setTimeout(5);
            $process->run();
            $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
            $this->assertSame(2, json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR)['deleted']);
            $this->assertSame([$ids[0]], DB::table('workspaces')->pluck('id')->all());
            DB::commit();
            $this->assertSame(1, app(CleanupWorkspaces::class)->batch());
            $this->assertSame(0, DB::table('workspaces')->count());
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
