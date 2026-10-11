<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StaffInstallationTest extends TestCase
{
    use RefreshDatabase;

    public function test_normal_installation_refuses_implicit_default_credentials(): void
    {
        config(['lastro.demo_enabled' => false, 'lastro.staff_email' => null, 'lastro.staff_password' => null]);
        $exception = null;
        try {
            $this->seed(DatabaseSeeder::class);
        } catch (\RuntimeException $caught) {
            $exception = $caught;
        }
        $this->assertNotNull($exception, 'Installation accepted missing credentials');
        $this->assertSame(0, DB::table('users')->count());
        $this->assertSame(0, DB::table('workspaces')->count());
    }

    public function test_explicit_staff_seed_is_idempotent_and_can_log_in(): void
    {
        config(['lastro.demo_enabled' => false, 'lastro.staff_email' => 'staff@example.invalid', 'lastro.staff_password' => 'installation-password', 'lastro.staff_name' => 'Equipe']);
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);
        $this->assertSame(1, DB::table('users')->count());
        $this->assertSame(1, DB::table('workspaces')->where('kind', 'normal')->count());
        $this->postJson('/login', ['email' => 'staff@example.invalid', 'password' => 'installation-password'])->assertOk();
    }

    public function test_demo_installation_does_not_create_a_shared_user(): void
    {
        config(['lastro.demo_enabled' => true]);
        $this->seed(DatabaseSeeder::class);
        $this->assertSame(0, DB::table('users')->count());
        $this->assertSame(0, DB::table('workspaces')->count());
    }
}
