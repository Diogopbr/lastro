<?php

use App\Domain\Access\CurrentWorkspace;
use App\Domain\Exports\ExportRun;
use App\Domain\Reconciliation\ReconcileConference;
use App\Domain\Reviews\ReviewResult;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
[$script, $schema, $workspaceId, $userId, $applicationName, $mode] = $argv;
config(['database.connections.pgsql.search_path' => $schema]);
DB::purge('pgsql');
Cache::purge('database');
DB::selectOne("SELECT set_config('application_name', ?, false)", [$applicationName]);
$scope = new CurrentWorkspace($workspaceId, null, 'demo', $userId);
try {
    if ($mode === 'review') {
        $result = app(ReviewResult::class)->execute($scope, $argv[6], (int) $argv[7], $argv[8], base64_decode($argv[9], true));
        echo json_encode(['status' => 200, 'result' => $result->toArray()], JSON_THROW_ON_ERROR);
    } elseif ($mode === 'export') {
        $response = app(ExportRun::class)->execute($scope, $argv[6]);
        echo json_encode(['status' => 200, 'csv' => base64_encode($response->getContent())], JSON_THROW_ON_ERROR);
    } else {
        $run = app(ReconcileConference::class)->execute($scope, $argv[6]);
        echo json_encode(['status' => $run->created ? 201 : 200, 'run' => $run->toArray()], JSON_THROW_ON_ERROR);
    }
} catch (ValidationException $exception) {
    echo json_encode(['status' => 422], JSON_THROW_ON_ERROR);
} catch (HttpException $exception) {
    echo json_encode(['status' => $exception->getStatusCode()], JSON_THROW_ON_ERROR);
}
