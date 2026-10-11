<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class StaffLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_normal_login_authenticates_and_logout_invalidates_session(): void
    {
        config(['lastro.demo_enabled' => false]);
        $workspace = (string) Str::uuid();
        DB::table('workspaces')->insert(['id' => $workspace, 'kind' => 'normal']);
        $user = User::factory()->create(['workspace_id' => $workspace, 'password' => 'correct-password']);
        $this->withSession(['_token' => 'before-login']);
        $oldSessionId = session()->getId();
        $this->postJson('/login', ['email' => $user->email, 'password' => 'correct-password'])->assertOk();
        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($oldSessionId, session()->getId());
        $this->postJson('/logout')->assertOk();
        $this->assertGuest();
        $this->getJson('/conferencias')->assertUnauthorized();
    }

    public function test_demo_mode_refuses_shared_staff_credentials(): void
    {
        config(['lastro.demo_enabled' => true]);
        $this->postJson('/login', ['email' => 'staff@example.invalid', 'password' => 'anything'])->assertStatus(409);
        $this->assertGuest();
    }

    public function test_wrong_password_does_not_authenticate(): void
    {
        config(['lastro.demo_enabled' => false]);
        $this->postJson('/login', ['email' => 'staff@example.invalid', 'password' => 'wrong'])->assertUnprocessable();
        $this->assertGuest();
    }

    public function test_a_demo_identity_cannot_authenticate_as_normal_staff(): void
    {
        config(['lastro.demo_enabled' => false]);
        $workspace = (string) Str::uuid();
        DB::table('workspaces')->insert(['id' => $workspace, 'kind' => 'demo', 'expires_at' => DB::raw("clock_timestamp() + interval '24 hours'")]);
        $user = User::factory()->create(['workspace_id' => $workspace, 'password' => 'correct-password']);
        $this->postJson('/login', ['email' => $user->email, 'password' => 'correct-password'])->assertUnprocessable();
        $this->assertGuest();
    }
}
