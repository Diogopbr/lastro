<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReadinessTest extends TestCase
{
    public function test_requires_database_and_applied_schema(): void
    {
        $original = config('database.connections.pgsql');
        $schema = 'readiness_'.bin2hex(random_bytes(6));
        $schemaCreated = false;

        try {
            config(['database.connections.pgsql.port' => 1]);
            DB::purge('pgsql');
            $this->getJson('/health/ready')->assertStatus(503)->assertExactJson(['status' => 'unavailable']);

            config(['database.connections.pgsql' => $original]);
            DB::purge('pgsql');
            DB::statement('CREATE SCHEMA "'.$schema.'"');
            $schemaCreated = true;
            config(['database.connections.pgsql.search_path' => $schema]);
            DB::purge('pgsql');
            $this->getJson('/health/ready')->assertStatus(503)->assertExactJson(['status' => 'unavailable']);

            $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
            $this->getJson('/health/ready')->assertOk()->assertExactJson(['status' => 'ready']);

            DB::table('migrations')->where('migration', '0001_01_01_000001_create_cache_table')->delete();
            $this->getJson('/health/ready')->assertStatus(503)->assertExactJson(['status' => 'unavailable']);
        } finally {
            config(['database.connections.pgsql' => $original]);
            DB::purge('pgsql');
            if ($schemaCreated) {
                DB::statement('DROP SCHEMA "'.$schema.'" CASCADE');
            }
        }
    }
}
