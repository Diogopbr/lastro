<?php

namespace Tests\Feature;

use App\Domain\Access\CurrentWorkspace;
use App\Domain\Access\DemoWorkspace;
use App\Domain\Conferences\UpdateConference;
use App\Domain\Imports\ConfirmSource;
use App\Domain\Imports\CsvImportValidator;
use App\Domain\Imports\SourceKind;
use DateTimeImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class SourceConcurrencyTest extends TestCase
{
    private const CSV = "order_id,ordered_at,gross_amount,currency\n0001,2026-05-01,100.00,BRL\n";

    public function test_same_content_waits_and_returns_one_source_and_event(): void
    {
        $this->race('same', 200);
    }

    public function test_different_content_waits_and_conflicts_without_replacement(): void
    {
        $this->race('different', 409);
    }

    public function test_confirmation_revalidates_period_after_waiting_for_update(): void
    {
        $this->race('period', 422);
    }

    public function test_reset_revokes_a_waiting_confirmation_of_old_generation(): void
    {
        $this->race('reset', 404);
    }

    private function race(string $mode, int $status): void
    {
        $original = config('database.connections.pgsql');
        $schema = 'source_race_'.bin2hex(random_bytes(6));
        $process = null;
        DB::statement('CREATE SCHEMA "'.$schema.'"');
        try {
            config(['database.connections.pgsql.search_path' => $schema]);
            DB::purge('pgsql');
            Cache::purge('database');
            $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
            $token = str_repeat('a', 64);
            $user = app(DemoWorkspace::class)->start($token);
            $scope = new CurrentWorkspace($user->workspace_id, null, 'demo', $user->id);
            $conference = DB::table('conferences')->where('workspace_id', $scope->id)->orderBy('code')->first();
            $bytes = $mode === 'different' ? str_replace('100.00', '110.00', self::CSV) : self::CSV;
            $hash = app(CsvImportValidator::class)->validate(SourceKind::Orders, $bytes, new DateTimeImmutable('2026-05-01'), new DateTimeImmutable('2026-05-31'))->normalizedHash;
            $originalHash = app(CsvImportValidator::class)->validate(SourceKind::Orders, self::CSV, new DateTimeImmutable('2026-05-01'), new DateTimeImmutable('2026-05-31'))->normalizedHash;
            DB::beginTransaction();
            $source = null;
            if ($mode === 'period') {
                app(UpdateConference::class)->execute($scope, $conference->id, $conference->name, '2026-05-02', '2026-05-31', 1);
            } elseif ($mode === 'reset') {
                app(DemoWorkspace::class)->reset($scope, $token, '127.0.0.23');
            } else {
                $source = app(ConfirmSource::class)->execute($scope, $conference->id, SourceKind::Orders, self::CSV, $originalHash, 1, 'parent.csv');
            }
            $this->assertSame(1, DB::transactionLevel());
            $parentPid = DB::selectOne('SELECT pg_backend_pid() AS pid')->pid;
            $applicationName = 'lastro-source-'.bin2hex(random_bytes(6));
            $process = new Process([PHP_BINARY, base_path('tests/Fixtures/concurrent-create.php'), $schema, $scope->id, $scope->userId, $applicationName, 'confirm', $conference->id, base64_encode($bytes), $hash, '1'], base_path(), ['APP_ENV' => 'testing']);
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
            $this->assertTrue($blocked, 'Worker was not observed waiting for the parent lock. '.$process->getErrorOutput().$process->getOutput());
            DB::commit();
            $process->wait();
            $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
            $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame($status, $result['status']);
            $expectedCount = in_array($mode, ['same', 'different'], true) ? 1 : 0;
            $this->assertSame($expectedCount, DB::table('sources')->count());
            $this->assertSame($expectedCount, DB::table('source_rows')->count());
            $this->assertSame($expectedCount, DB::table('source_events')->count());
            if ($mode === 'same') {
                $this->assertSame($source->row->id, $result['sourceId']);
                $this->assertSame('parent.csv', DB::table('sources')->value('display_filename'));
            }
        } finally {
            while (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            $process?->stop(1);
            config(['database.connections.pgsql' => $original]);
            DB::purge('pgsql');
            Cache::purge('database');
            DB::statement('DROP SCHEMA "'.$schema.'" CASCADE');
        }
    }
}
