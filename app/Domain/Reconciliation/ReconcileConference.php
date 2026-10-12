<?php

namespace App\Domain\Reconciliation;

use App\Domain\Access\CurrentWorkspace;
use App\Domain\Access\WorkspaceTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ReconcileConference
{
    public function __construct(private WorkspaceTransaction $transaction, private ReconciliationRules $rules) {}

    public function execute(CurrentWorkspace $scope, string $conferenceId): RunProjection
    {
        return $this->transaction->run($scope, function () use ($scope, $conferenceId): RunProjection {
            $conference = DB::table('conferences')->where('workspace_id', $scope->id)->where('id', $conferenceId)->lockForUpdate()->first();
            if ($conference === null) {
                throw new NotFoundHttpException;
            }
            $existing = DB::table('runs')->where('workspace_id', $scope->id)->where('conference_id', $conferenceId)->first();
            if ($existing !== null) {
                return new RunProjection($existing);
            }
            $sources = DB::table('sources')->where('workspace_id', $scope->id)->where('conference_id', $conferenceId)->get()->keyBy('kind');
            if (! isset($sources['orders'], $sources['payouts'])) {
                throw ValidationException::withMessages(['sources' => 'Confirme as fontes de pedidos e repasses antes de conciliar.']);
            }
            $runId = (string) Str::uuid();
            DB::table('runs')->insert([
                'id' => $runId, 'workspace_id' => $scope->id, 'conference_id' => $conferenceId,
                'orders_source_id' => $sources['orders']->id, 'payouts_source_id' => $sources['payouts']->id,
                'orders_kind' => 'orders', 'payouts_kind' => 'payouts', 'created_at' => DB::raw('clock_timestamp()'),
            ]);
            $orders = DB::table('source_rows')->where('source_id', $sources['orders']->id)->orderBy('line')->get()->all();
            $payouts = DB::table('source_rows')->where('source_id', $sources['payouts']->id)->orderBy('line')->get()->all();
            $results = $this->rules->match($orders, $payouts);
            DB::table('results')->insert(array_map(fn (array $result): array => [
                ...$result, 'id' => (string) Str::uuid(), 'workspace_id' => $scope->id, 'run_id' => $runId, 'version' => 1,
            ], $results));
            DB::table('run_events')->insert([
                'id' => (string) Str::uuid(), 'workspace_id' => $scope->id, 'run_id' => $runId,
                'actor_id' => $scope->userId, 'event_type' => 'reconciled', 'created_at' => DB::raw('clock_timestamp()'),
            ]);

            return new RunProjection(DB::table('runs')->where('id', $runId)->first(), true);
        });
    }
}
