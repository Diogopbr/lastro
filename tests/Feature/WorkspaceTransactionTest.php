<?php

namespace Tests\Feature;

use App\Domain\Access\CurrentWorkspace;
use App\Domain\Access\WorkspaceTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class WorkspaceTransactionTest extends TestCase
{
    private array $originalConnection;

    private string $schema;

    private CurrentWorkspace $scope;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalConnection = config('database.connections.pgsql');
        $this->schema = 'workspace_test_'.bin2hex(random_bytes(6));
        DB::statement('CREATE SCHEMA "'.$this->schema.'"');
        config(['database.connections.pgsql.search_path' => $this->schema]);
        DB::purge('pgsql');
        DB::statement('CREATE TABLE workspaces (id uuid PRIMARY KEY, kind text NOT NULL, expires_at timestamptz, revoked_at timestamptz, marker integer NOT NULL DEFAULT 0)');
        $this->scope = new CurrentWorkspace((string) Str::uuid(), null, 'demo', (string) Str::uuid());
        DB::table('workspaces')->insert(['id' => $this->scope->id, 'kind' => 'demo', 'expires_at' => DB::raw("clock_timestamp() + interval '24 hours'")]);
    }

    protected function tearDown(): void
    {
        config(['database.connections.pgsql' => $this->originalConnection]);
        DB::purge('pgsql');
        DB::statement('DROP SCHEMA "'.$this->schema.'" CASCADE');
        parent::tearDown();
    }

    public function test_commits_work_for_a_current_workspace(): void
    {
        $result = app(WorkspaceTransaction::class)->run($this->scope, function (): string {
            DB::table('workspaces')->where('id', $this->scope->id)->update(['marker' => 7]);

            return 'saved';
        });
        $this->assertSame('saved', $result);
        $this->assertSame(7, DB::table('workspaces')->value('marker'));
    }

    public function test_rejects_expired_workspace_before_work(): void
    {
        DB::table('workspaces')->update(['expires_at' => DB::raw("clock_timestamp() - interval '1 second'")]);
        $this->expectException(NotFoundHttpException::class);
        app(WorkspaceTransaction::class)->run($this->scope, fn () => $this->fail('Expired workspace reached callback'));
    }

    public function test_rolls_back_if_workspace_expires_during_work(): void
    {
        try {
            app(WorkspaceTransaction::class)->run($this->scope, function (): void {
                DB::table('workspaces')->update(['marker' => 9, 'expires_at' => DB::raw("clock_timestamp() - interval '1 second'")]);
            });
            $this->fail('Expired workspace committed');
        } catch (NotFoundHttpException) {
            $this->assertSame(0, DB::table('workspaces')->value('marker'));
            $this->assertTrue((bool) DB::selectOne('SELECT expires_at > clock_timestamp() AS valid FROM workspaces')->valid);
        }
    }

    public function test_rejects_a_revoked_generation(): void
    {
        DB::table('workspaces')->update(['revoked_at' => DB::raw('clock_timestamp()')]);
        $this->expectException(NotFoundHttpException::class);
        app(WorkspaceTransaction::class)->run($this->scope, fn () => $this->fail('Revoked generation reached callback'));
    }

    public function test_does_not_substitute_an_unknown_workspace(): void
    {
        $unknown = new CurrentWorkspace((string) Str::uuid(), null, 'demo', $this->scope->userId);
        $this->expectException(NotFoundHttpException::class);
        app(WorkspaceTransaction::class)->run($unknown, fn () => $this->fail('Unknown workspace reached callback'));
    }
}
