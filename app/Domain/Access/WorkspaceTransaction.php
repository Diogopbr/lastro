<?php

namespace App\Domain\Access;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class WorkspaceTransaction
{
    public function run(CurrentWorkspace $scope, callable $callback): mixed
    {
        return DB::transaction(function () use ($scope, $callback): mixed {
            $workspace = DB::table('workspaces')->where('id', $scope->id)->lockForUpdate()->first();
            if ($workspace === null || $workspace->kind !== $scope->kind) {
                throw new NotFoundHttpException;
            }

            $this->assertCurrent($scope);
            $result = $callback();
            $this->assertCurrent($scope);

            return $result;
        });
    }

    private function assertCurrent(CurrentWorkspace $scope): void
    {
        $current = DB::table('workspaces')
            ->where('id', $scope->id)
            ->whereNull('revoked_at')
            ->where(function (Builder $query): void {
                $query->whereNull('expires_at')->orWhereRaw('expires_at > clock_timestamp()');
            })
            ->exists();

        if (! $current) {
            throw new NotFoundHttpException;
        }
    }
}
