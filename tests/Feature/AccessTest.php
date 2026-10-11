<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_absent_session_returns_json_401_or_login_redirect(): void
    {
        $this->getJson('/conferencias')->assertUnauthorized()->assertJsonPath('code', 'unauthenticated');
        $this->get('/conferencias')->assertRedirect('/entrar');
    }

    public function test_mutation_requires_csrf_even_before_authentication(): void
    {
        $environment = $this->app['env'];
        try {
            $this->app['env'] = 'local';
            $this->postJson('/conferencias', ['name' => 'Maio', 'startDate' => '2026-05-01', 'endDate' => '2026-05-31'])->assertStatus(419)->assertJsonPath('code', 'csrf_rejected');
        } finally {
            $this->app['env'] = $environment;
        }
    }

    public function test_csrf_endpoint_returns_current_session_token(): void
    {
        $this->withSession(['_token' => 'current-token'])->getJson('/csrf')->assertOk()->assertExactJson(['token' => 'current-token']);
    }

    public function test_normal_staff_can_create_but_cannot_read_foreign_conference(): void
    {
        $workspace = (string) Str::uuid();
        DB::table('workspaces')->insert(['id' => $workspace, 'kind' => 'normal']);
        $user = User::factory()->create(['workspace_id' => $workspace]);
        $conference = $this->actingAs($user)->postJson('/conferencias', ['name' => 'Maio', 'startDate' => '2026-05-01', 'endDate' => '2026-05-31'])
            ->assertCreated()->assertJsonPath('name', 'Maio')->json();
        $this->getJson('/conferencias/'.$conference['id'])->assertOk()->assertJsonPath('id', $conference['id']);
        $foreign = (string) Str::uuid();
        DB::table('workspaces')->insert(['id' => $foreign, 'kind' => 'normal']);
        $otherUser = User::factory()->create(['workspace_id' => $foreign]);
        $this->actingAs($otherUser)->getJson('/conferencias/'.$conference['id'])->assertNotFound();
    }

    public function test_expired_demo_user_cannot_read_or_create(): void
    {
        $workspace = (string) Str::uuid();
        DB::table('workspaces')->insert(['id' => $workspace, 'kind' => 'demo', 'expires_at' => DB::raw("clock_timestamp() - interval '1 second'")]);
        $user = User::factory()->create(['workspace_id' => $workspace]);
        $this->actingAs($user)->getJson('/conferencias')->assertNotFound();
        $this->postJson('/conferencias', ['name' => 'Maio', 'startDate' => '2026-05-01', 'endDate' => '2026-05-31'])->assertNotFound();
        $this->assertSame(0, DB::table('conferences')->count());
    }
}
