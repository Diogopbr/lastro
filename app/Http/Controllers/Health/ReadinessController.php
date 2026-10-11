<?php

namespace App\Http\Controllers\Health;

use App\Http\Controllers\Controller;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ReadinessController extends Controller
{
    public function __invoke(Migrator $migrator): JsonResponse
    {
        try {
            $repository = $migrator->getRepository();
            DB::select('SELECT 1');
            if (! $repository->repositoryExists()) {
                return response()->json(['status' => 'unavailable'], 503);
            }

            $pending = array_diff(array_keys($migrator->getMigrationFiles(database_path('migrations'))), $repository->getRan());
            foreach (['users', 'sessions', 'cache', 'cache_locks'] as $table) {
                if (! Schema::hasTable($table)) {
                    return response()->json(['status' => 'unavailable'], 503);
                }
            }
            if ($pending !== []) {
                return response()->json(['status' => 'unavailable'], 503);
            }

            return response()->json(['status' => 'ready']);
        } catch (Throwable) {
            return response()->json(['status' => 'unavailable'], 503);
        }
    }
}
