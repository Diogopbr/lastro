<?php

namespace Tests\Feature;

use App\Domain\Access\CleanupWorkspaces;
use App\Domain\Access\CurrentWorkspace;
use App\Domain\Access\DemoWorkspace;
use App\Domain\Conferences\ConferenceProjection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DemoExamplesTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seeds_three_real_snapshots_and_repeated_start_is_idempotent(): void
    {
        config(['lastro.demo_examples' => true]);
        $demo = app(DemoWorkspace::class);
        $token = str_repeat('f', 64);
        $user = $demo->start($token);
        $this->assertSame(6, DB::table('sources')->count());
        $this->assertSame(3, DB::table('runs')->count());
        $this->assertSame(8, DB::table('results')->count());
        $this->assertSame(6, DB::table('source_events')->count());
        $this->assertSame(3, DB::table('run_events')->count());
        $conferences = DB::table('conferences')->orderBy('code')->get();
        $this->assertSame(3, ConferenceProjection::fromRow($conferences[0])['progress']['divergences']);
        $this->assertSame(0, ConferenceProjection::fromRow($conferences[1])['progress']['divergences']);
        $this->assertSame(2, ConferenceProjection::fromRow($conferences[2])['progress']['divergences']);
        $this->assertSame($user->id, $demo->start($token)->id);
        $this->assertSame(3, DB::table('runs')->count());
    }

    public function test_full_examples_remain_isolated_after_reset_and_collection(): void
    {
        config(['lastro.demo_examples' => true]);
        $demo = app(DemoWorkspace::class);
        $token = str_repeat('a', 64);
        $old = $demo->start($token);
        $other = $demo->start(str_repeat('b', 64));
        $new = $demo->reset(new CurrentWorkspace($old->workspace_id, null, 'demo', $old->id), $token, '127.0.0.29');
        $this->assertSame(18, DB::table('sources')->count());
        $this->assertSame(9, DB::table('runs')->count());
        $this->assertNotSame($old->workspace_id, $new->workspace_id);
        $this->assertSame(1, app(CleanupWorkspaces::class)->batch());
        $this->assertSame(12, DB::table('sources')->count());
        $this->assertSame(6, DB::table('runs')->count());
        $this->assertSame(16, DB::table('results')->count());
        foreach ([$new, $other] as $user) {
            $this->assertSame(3, DB::table('runs')->where('workspace_id', $user->workspace_id)->count());
            $this->assertSame(6, DB::table('sources')->where('workspace_id', $user->workspace_id)->count());
        }
    }
}
