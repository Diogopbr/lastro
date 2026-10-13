<?php

namespace Tests\Feature;

use App\Domain\Access\CurrentWorkspace;
use App\Domain\Access\DemoWorkspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class DemoIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_visitors_receive_separate_workspaces_and_three_conferences(): void
    {
        $service = app(DemoWorkspace::class);
        $first = $service->start(str_repeat('a', 64));
        $second = $service->start(str_repeat('b', 64));
        $this->assertNotSame($first->workspace_id, $second->workspace_id);
        $this->assertSame(3, DB::table('conferences')->where('workspace_id', $first->workspace_id)->count());
        $this->assertSame(3, DB::table('conferences')->where('workspace_id', $second->workspace_id)->count());
        $this->assertSame(hash('sha256', str_repeat('a', 64)), DB::table('visitors')->orderBy('created_at')->value('token_hash'));
    }

    public function test_repeated_start_preserves_fixed_expiry_and_generation(): void
    {
        $service = app(DemoWorkspace::class);
        $first = $service->start(str_repeat('a', 64));
        $expiry = DB::table('workspaces')->value('expires_at');
        $again = $service->start(str_repeat('a', 64));
        $this->assertSame($first->id, $again->id);
        $this->assertSame($expiry, DB::table('workspaces')->value('expires_at'));
        $this->assertSame(1, DB::table('workspaces')->count());
    }

    public function test_old_page_cannot_create_in_the_replacement_generation_with_a_fresh_cookie(): void
    {
        $demo = app(DemoWorkspace::class);
        $token = str_repeat('c', 64);
        $old = $demo->start($token);
        $new = $demo->reset(new CurrentWorkspace($old->workspace_id, null, 'demo', $old->id), $token, '127.0.0.28');
        $this->actingAs($new);
        $input = ['name' => 'Rascunho de maio', 'startDate' => '2026-05-01', 'endDate' => '2026-05-31'];
        $this->postJson('/conferencias', $input, ['X-Lastro-Workspace' => $old->workspace_id])->assertNotFound();
        $this->assertSame(3, DB::table('conferences')->where('workspace_id', $new->workspace_id)->count());
        $this->postJson('/conferencias', $input, ['X-Lastro-Workspace' => $new->workspace_id])->assertCreated();
        $this->assertSame(4, DB::table('conferences')->where('workspace_id', $new->workspace_id)->count());
    }

    public function test_reset_revokes_old_generation_and_limits_repeated_reset(): void
    {
        $service = app(DemoWorkspace::class);
        $token = str_repeat('a', 64);
        $first = $service->start($token);
        DB::table('sessions')->insert(['id' => 'old-session', 'user_id' => $first->id, 'payload' => 'old', 'last_activity' => time()]);
        $scope = new CurrentWorkspace($first->workspace_id, null, 'demo', $first->id);
        $second = $service->reset($scope, $token, '127.0.0.9');
        $this->assertNotSame($first->workspace_id, $second->workspace_id);
        $this->assertNotNull(DB::table('workspaces')->where('id', $first->workspace_id)->value('revoked_at'));
        $this->assertSame(0, DB::table('sessions')->where('id', 'old-session')->count());
        $this->assertSame(1, DB::table('visitors')->count());
        try {
            $service->reset(new CurrentWorkspace($second->workspace_id, null, 'demo', $second->id), $token, '127.0.0.9');
            $this->fail('Repeated reset accepted');
        } catch (HttpException $exception) {
            $this->assertSame(429, $exception->getStatusCode());
            $this->assertArrayHasKey('Retry-After', $exception->getHeaders());
            $this->assertSame(2, DB::table('workspaces')->count());
        }
    }

    public function test_foreign_visitor_cannot_reset_a_workspace(): void
    {
        $service = app(DemoWorkspace::class);
        $first = $service->start(str_repeat('a', 64));
        try {
            $service->reset(new CurrentWorkspace($first->workspace_id, null, 'demo', $first->id), str_repeat('b', 64), '127.0.0.9');
            $this->fail('Foreign visitor reset accepted');
        } catch (HttpException $exception) {
            $this->assertSame(404, $exception->getStatusCode());
            $this->assertNull(DB::table('workspaces')->value('revoked_at'));
        }
    }

    public function test_demo_get_does_not_create_data_and_start_is_explicit(): void
    {
        config(['lastro.demo_enabled' => true]);
        $this->get('/demo')->assertRedirect('/entrar');
        $this->assertSame(0, DB::table('workspaces')->count());
        $this->postJson('/demo/start')->assertCreated()->assertCookie('lastro_visitor');
        $this->assertAuthenticated();
        $this->getJson('/conferencias')->assertOk()->assertJsonCount(3, 'conferences');
    }

    public function test_reset_limit_expires_after_sixty_seconds_without_extending_workspace(): void
    {
        $this->travelTo(now()->startOfSecond());
        $service = app(DemoWorkspace::class);
        $token = str_repeat('e', 64);
        $user = $service->start($token);
        $user = $service->reset(new CurrentWorkspace($user->workspace_id, null, 'demo', $user->id), $token, '127.0.0.28');
        $expiry = DB::table('workspaces')->where('id', $user->workspace_id)->value('expires_at');
        $this->travel(59)->seconds();
        try {
            $service->reset(new CurrentWorkspace($user->workspace_id, null, 'demo', $user->id), $token, '127.0.0.28');
            $this->fail('Reset accepted inside its minute window');
        } catch (HttpException $exception) {
            $this->assertSame(429, $exception->getStatusCode());
        }
        $this->travel(1)->seconds();
        $replacement = $service->reset(new CurrentWorkspace($user->workspace_id, null, 'demo', $user->id), $token, '127.0.0.28');
        $this->assertNotSame($user->workspace_id, $replacement->workspace_id);
        $this->assertSame($expiry, DB::table('workspaces')->where('id', $user->workspace_id)->value('expires_at'));
        $this->travelBack();
    }
}
