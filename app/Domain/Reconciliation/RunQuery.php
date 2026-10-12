<?php

namespace App\Domain\Reconciliation;

use App\Domain\Access\CurrentWorkspace;
use App\Domain\Access\WorkspaceTransaction;
use App\Domain\Conferences\ConferenceProjection;
use App\Domain\Reviews\ReviewProjection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class RunQuery
{
    public function __construct(private WorkspaceTransaction $transaction) {}

    public function show(CurrentWorkspace $scope, string $runId, array $filters = []): array
    {
        return $this->transaction->run($scope, function () use ($scope, $runId, $filters): array {
            $run = $this->run($scope, $runId);
            $projection = (new RunProjection($run))->toArray();
            $conference = ConferenceProjection::fromRow(DB::table('conferences')->where('workspace_id', $scope->id)->where('id', $run->conference_id)->first());
            $conference['run'] = ['id' => $runId, 'createdAt' => $projection['createdAt']];
            $conference['progress'] = ['divergences' => $projection['summary']['divergences'], 'reviewed' => $projection['summary']['reviewed']];
            $input = $this->filters($filters);

            return ['conference' => $conference, 'run' => $projection, 'results' => $this->page($scope, $runId, $input), 'filters' => [
                'classification' => $input['classification'], 'reviewStatus' => $input['reviewStatus'], 'search' => $input['search'],
                'page' => $input['page'], 'pageSize' => $input['pageSize'],
            ]];
        });
    }

    public function results(CurrentWorkspace $scope, string $runId, array $filters = []): array
    {
        return $this->transaction->run($scope, function () use ($scope, $runId, $filters): array {
            $this->run($scope, $runId);

            return $this->page($scope, $runId, $this->filters($filters));
        });
    }

    public function run(CurrentWorkspace $scope, string $id): object
    {
        $run = DB::table('runs')->where('workspace_id', $scope->id)->where('id', $id)->first();
        if ($run === null) {
            throw new NotFoundHttpException;
        }

        return $run;
    }

    /** @param list<object> $rows
     * @return list<ResultProjection>
     */
    public function project(array $rows): array
    {
        $ids = [];
        foreach ($rows as $row) {
            array_push($ids, $row->order_row_id, $row->payout_row_id);
        }
        $sources = DB::table('source_rows as sr')->join('sources as s', 's.id', '=', 'sr.source_id')
            ->whereIn('sr.id', array_filter($ids))->select('sr.*', 's.normalized_hash', 's.display_filename')->get()->keyBy('id');
        $events = DB::table('review_events')->whereIn('result_id', array_map(fn (object $row): string => $row->id, $rows))
            ->orderByDesc('result_version')->get()->groupBy('result_id');

        return array_map(fn (object $row): ResultProjection => new ResultProjection(
            $row, $sources[$row->order_row_id] ?? null, $sources[$row->payout_row_id] ?? null,
            isset($events[$row->id]) ? $events[$row->id]->map(fn (object $event): array => ReviewProjection::fromRow($event))->all() : [],
        ), $rows);
    }

    private function filters(array $filters): array
    {
        $input = Validator::make($filters, [
            'classification' => 'nullable|in:matched,amountMismatch,missingPayout,missingOrder',
            'reviewStatus' => 'nullable|in:pending,reviewed', 'search' => 'nullable|string|max:100',
            'page' => 'sometimes|integer|min:1|max:2147483647', 'pageSize' => 'sometimes|integer|min:1|max:20',
        ])->validate();

        return [
            'classification' => $input['classification'] ?? null, 'reviewStatus' => $input['reviewStatus'] ?? null,
            'search' => $input['search'] ?? '', 'page' => (int) ($input['page'] ?? 1), 'pageSize' => (int) ($input['pageSize'] ?? 20),
        ];
    }

    private function page(CurrentWorkspace $scope, string $runId, array $filters): array
    {
        $query = DB::table('results')->where('workspace_id', $scope->id)->where('run_id', $runId);
        foreach (['classification' => 'classification', 'reviewStatus' => 'review_status'] as $filter => $column) {
            if ($filters[$filter] !== null) {
                $query->where($column, $filters[$filter]);
            }
        }
        if ($filters['search'] !== '') {
            $query->whereRaw('strpos(order_id, ?) > 0', [$filters['search']]);
        }
        $total = $query->count();
        $rows = $query->orderBy('order_id')->orderBy('id')->offset(($filters['page'] - 1) * $filters['pageSize'])->limit($filters['pageSize'])->get()->all();

        return ['items' => array_map(fn (ResultProjection $row): array => $row->toArray(), $this->project($rows)), 'total' => $total, 'page' => $filters['page'], 'pageSize' => $filters['pageSize']];
    }
}
