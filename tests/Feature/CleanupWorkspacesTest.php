<?php

namespace Tests\Feature;

use App\Domain\Access\CleanupWorkspaces;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CleanupWorkspacesTest extends TestCase
{
    use RefreshDatabase;

    public function test_expired_graph_is_deleted_in_ten_workspace_batches(): void
    {
        for ($index = 0; $index < 12; $index++) {
            $id = (string) Str::uuid();
            DB::table('workspaces')->insert(['id' => $id, 'kind' => 'demo', 'expires_at' => DB::raw("clock_timestamp() - interval '1 minute'")]);
            $user = User::factory()->create(['workspace_id' => $id]);
            DB::table('sessions')->insert(['id' => 'expired-'.$index, 'user_id' => $user->id, 'payload' => 'old', 'last_activity' => time()]);
            DB::table('conferences')->insert(['id' => (string) Str::uuid(), 'workspace_id' => $id, 'code' => 'LT-0001', 'name' => 'Vencida', 'start_date' => '2026-05-01', 'end_date' => '2026-05-31']);
        }
        $active = (string) Str::uuid();
        DB::table('workspaces')->insert(['id' => $active, 'kind' => 'demo', 'expires_at' => DB::raw("clock_timestamp() + interval '1 day'")]);
        $normal = (string) Str::uuid();
        DB::table('workspaces')->insert(['id' => $normal, 'kind' => 'normal']);
        $cleanup = app(CleanupWorkspaces::class);
        $this->assertSame(10, $cleanup->batch());
        $this->assertSame(4, DB::table('workspaces')->count());
        $this->assertSame(2, DB::table('sessions')->count());
        $this->assertSame(2, DB::table('conferences')->count());
        $this->assertSame(2, $cleanup->batch());
        $this->assertSame(2, DB::table('workspaces')->count());
        $this->assertSame(0, DB::table('users')->count());
        $this->assertSame(0, DB::table('sessions')->count());
        $this->assertSame(0, DB::table('conferences')->count());
    }

    public function test_opportunistic_cleanup_has_a_three_batch_budget(): void
    {
        for ($index = 0; $index < 35; $index++) {
            DB::table('workspaces')->insert(['id' => (string) Str::uuid(), 'kind' => 'demo', 'expires_at' => DB::raw("clock_timestamp() - interval '1 minute'")]);
        }
        $cleanup = app(CleanupWorkspaces::class);
        $this->assertSame(30, $cleanup->opportunistic());
        $this->assertSame(5, DB::table('workspaces')->count());
        $this->assertSame(0, $cleanup->opportunistic());
    }

    public function test_administrative_command_drains_expired_and_revoked_graphs(): void
    {
        for ($index = 0; $index < 12; $index++) {
            DB::table('workspaces')->insert(['id' => (string) Str::uuid(), 'kind' => 'demo',
                'expires_at' => DB::raw("clock_timestamp() + interval '1 day'"), 'revoked_at' => DB::raw('clock_timestamp()')]);
        }
        $this->artisan('lastro:cleanup')->expectsOutput('Espaços removidos: 12')->assertExitCode(0);
        $this->assertSame(0, DB::table('workspaces')->count());
    }
}
