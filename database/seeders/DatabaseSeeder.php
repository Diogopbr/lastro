<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (config('lastro.demo_enabled')) {
            return;
        }
        $email = config('lastro.staff_email');
        $password = config('lastro.staff_password');
        if (! is_string($email) || filter_var($email, FILTER_VALIDATE_EMAIL) === false || ! is_string($password) || strlen($password) < 12) {
            throw new \RuntimeException('Configure e-mail e senha de equipe com pelo menos 12 caracteres antes de instalar.');
        }
        DB::transaction(function () use ($email, $password): void {
            DB::selectOne("SELECT pg_advisory_xact_lock(hashtext('lastro:staff-install'))");
            if (User::where('email', $email)->exists()) {
                return;
            }
            $workspace = DB::table('workspaces')->where('kind', 'normal')->whereNull('revoked_at')->first();
            $id = $workspace?->id ?? (string) Str::uuid();
            if ($workspace === null) {
                DB::table('workspaces')->insert(['id' => $id, 'kind' => 'normal']);
            }
            User::create(['workspace_id' => $id, 'name' => config('lastro.staff_name', 'Equipe'), 'email' => $email, 'password' => $password]);
        });
    }
}
