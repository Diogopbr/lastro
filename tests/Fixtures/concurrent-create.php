<?php

use App\Domain\Access\CleanupWorkspaces;
use App\Domain\Access\CurrentWorkspace;
use App\Domain\Access\DemoWorkspace;
use App\Domain\Conferences\CreateConference;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
[$script, $schema, $workspaceId, $userId, $applicationName, $mode] = $argv;
config(['database.connections.pgsql.search_path' => $schema]);
DB::purge('pgsql');
Cache::purge('database');
DB::selectOne("SELECT set_config('application_name', ?, false)", [$applicationName]);

try {
    $deleted = null;
    if ($mode === 'cleanup') {
        $deleted = app(CleanupWorkspaces::class)->batch();
    } elseif ($mode === 'reset') {
        app(DemoWorkspace::class)->reset(new CurrentWorkspace($workspaceId, null, 'demo', $userId), $argv[6], $argv[7]);
    } elseif ($mode === 'unsafe') {
        // Negative control: quota is read before the workspace lock, so both actors see four.
        DB::transaction(function () use ($workspaceId): void {
            if (DB::table('conferences')->where('workspace_id', $workspaceId)->count() >= 5) {
                throw new HttpException(409);
            }
            DB::table('conferences')->insert([
                'id' => (string) Str::uuid(), 'workspace_id' => $workspaceId, 'code' => 'LT-UNSAFE',
                'name' => 'Sem proteção', 'start_date' => '2026-05-01', 'end_date' => '2026-05-31',
                'created_at' => DB::raw('clock_timestamp()'), 'updated_at' => DB::raw('clock_timestamp()'),
            ]);
        });
    } else {
        $scope = new CurrentWorkspace($workspaceId, null, 'demo', $userId);
        app(CreateConference::class)->execute($scope, 'Concorrente', '2026-05-01', '2026-05-31');
    }
    echo json_encode(['status' => 201, 'deleted' => $deleted], JSON_THROW_ON_ERROR);
} catch (HttpException $exception) {
    echo json_encode(['status' => $exception->getStatusCode(), 'diagnostic' => [
        'schema' => DB::selectOne('SELECT current_schema() AS schema')->schema,
        'workspaceCount' => DB::table('workspaces')->count(),
        'targetExists' => DB::table('workspaces')->where('id', $workspaceId)->exists(),
    ]], JSON_THROW_ON_ERROR);
}
