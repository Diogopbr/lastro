<?php

namespace Tests\Fixtures;

use App\Domain\Access\CurrentWorkspace;
use App\Domain\Access\DemoWorkspace;
use App\Domain\Imports\ConfirmSource;
use App\Domain\Imports\CsvImportValidator;
use App\Domain\Imports\SourceKind;
use DateTimeImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

abstract class ReconciliationDatabase extends TestCase
{
    protected string $schema;

    protected array $originalConnection;

    protected CurrentWorkspace $scope;

    protected string $conferenceId;

    protected string $token;

    /** @var list<Process> */
    protected array $workers = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalConnection = config('database.connections.pgsql');
        $this->schema = 'reconciliation_'.bin2hex(random_bytes(6));
        DB::statement('CREATE SCHEMA "'.$this->schema.'"');
        config(['database.connections.pgsql.search_path' => $this->schema]);
        DB::purge('pgsql');
        Cache::purge('database');
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
        $this->token = bin2hex(random_bytes(32));
        $user = app(DemoWorkspace::class)->start($this->token);
        $this->scope = new CurrentWorkspace($user->workspace_id, null, 'demo', $user->id);
        $this->actingAs($user);
        $this->conferenceId = DB::table('conferences')->where('workspace_id', $this->scope->id)->orderBy('code')->value('id');
    }

    protected function tearDown(): void
    {
        foreach ($this->workers as $worker) {
            $worker->stop(1);
        }
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        config(['database.connections.pgsql' => $this->originalConnection]);
        DB::purge('pgsql');
        Cache::purge('database');
        DB::statement('DROP SCHEMA "'.$this->schema.'" CASCADE');
        parent::tearDown();
    }

    protected function import(SourceKind $kind, string $bytes): void
    {
        $preview = app(CsvImportValidator::class)->validate($kind, $bytes, new DateTimeImmutable('2026-05-01'), new DateTimeImmutable('2026-05-31'));
        $this->assertTrue($preview->valid, json_encode($preview->errors, JSON_THROW_ON_ERROR));
        $version = DB::table('conferences')->where('id', $this->conferenceId)->value('version');
        app(ConfirmSource::class)->execute($this->scope, $this->conferenceId, $kind, $bytes, $preview->normalizedHash, $version, $kind->value.'.csv');
    }

    protected function importPair(): void
    {
        $this->import(SourceKind::Orders, file_get_contents(__DIR__.DIRECTORY_SEPARATOR.'csv'.DIRECTORY_SEPARATOR.'reconciliation-orders.csv'));
        $this->import(SourceKind::Payouts, file_get_contents(__DIR__.DIRECTORY_SEPARATOR.'csv'.DIRECTORY_SEPARATOR.'reconciliation-payouts.csv'));
    }

    protected function worker(string $mode, array $arguments): Process
    {
        $name = 'lastro-domain-'.bin2hex(random_bytes(6));
        $worker = new Process([PHP_BINARY, __DIR__.DIRECTORY_SEPARATOR.'concurrent-reconciliation.php', $this->schema, $this->scope->id, $this->scope->userId, $name, $mode, ...$arguments], base_path(), ['APP_ENV' => 'testing']);
        $worker->setTimeout(25);
        $this->workers[] = $worker;
        $worker->start();
        $parentPid = DB::selectOne('SELECT pg_backend_pid() AS pid')->pid;
        $blocked = false;
        $deadline = microtime(true) + 10;
        while ($worker->isRunning() && microtime(true) < $deadline) {
            DB::selectOne('SELECT pg_stat_clear_snapshot()');
            $blocked = DB::selectOne('SELECT pid FROM pg_stat_activity WHERE application_name = ? AND ? = ANY(pg_blocking_pids(pid))', [$name, $parentPid]) !== null;
            if ($blocked) {
                break;
            }
            usleep(10000);
        }
        $this->assertTrue($blocked, 'Worker was not observed blocked. '.$worker->getErrorOutput().$worker->getOutput());

        return $worker;
    }

    protected function workerResult(Process $worker): array
    {
        $worker->wait();
        $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput());

        return json_decode($worker->getOutput(), true, flags: JSON_THROW_ON_ERROR);
    }
}
