<?php

namespace App\Http\Controllers\Reviews;

use App\Domain\Access\WorkspaceTransaction;
use App\Domain\Reconciliation\RunQuery;
use App\Domain\Reviews\ReviewResult;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ReviewController extends Controller
{
    public function show(Request $request, string $id, WorkspaceTransaction $transaction, RunQuery $query): Response
    {
        $scope = $request->attributes->get('workspace');
        $result = $transaction->run($scope, function () use ($scope, $id, $query): array {
            $row = DB::table('results')->where('workspace_id', $scope->id)->where('id', $id)->first();
            abort_if($row === null, 404);

            return $query->project([$row])[0]->toArray();
        });

        return response()->json($result);
    }

    public function store(Request $request, string $id, ReviewResult $review): Response
    {
        $input = $request->validate(['expectedVersion' => 'required|integer|min:1', 'action' => 'required|in:reviewed,reopened', 'note' => 'required|string']);
        $result = $review->execute($request->attributes->get('workspace'), $id, (int) $input['expectedVersion'], $input['action'], $input['note']);

        return $request->expectsJson() ? response()->json($result->toArray())
            : redirect()->route('runs.show', ['id' => $result->row->run_id], 303);
    }
}
