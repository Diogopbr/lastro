<?php

namespace App\Http\Controllers\Conferences;

use App\Domain\Access\CurrentWorkspace;
use App\Domain\Access\WorkspaceTransaction;
use App\Domain\Conferences\ConferenceProjection;
use App\Domain\Conferences\CreateConference;
use App\Domain\Conferences\UpdateConference;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class ConferenceController extends Controller
{
    public function index(Request $request, WorkspaceTransaction $transaction): Response
    {
        $scope = $this->scope($request);
        $props = $transaction->run($scope, function () use ($scope): array {
            $rows = DB::table('conferences')->where('workspace_id', $scope->id)->orderByDesc('created_at')->get();

            return ['conferences' => $rows->map(fn (object $row): array => ConferenceProjection::fromRow($row))->all(),
                'quota' => ['used' => $rows->count(), 'limit' => $scope->kind === 'demo' ? 5 : null]];
        });

        return $request->expectsJson() ? response()->json($props) : Inertia::render('Conferences/Index', $props)->toResponse($request);
    }

    public function show(Request $request, string $id, WorkspaceTransaction $transaction): Response
    {
        $scope = $this->scope($request);
        $projection = $transaction->run($scope, function () use ($scope, $id): array {
            $row = DB::table('conferences')->where('workspace_id', $scope->id)->where('id', $id)->first();
            abort_if($row === null, 404);

            return ConferenceProjection::fromRow($row);
        });

        return $request->expectsJson() ? response()->json($projection) : Inertia::render('Conferences/Show', ['conference' => $projection])->toResponse($request);
    }

    public function store(Request $request, CreateConference $create): Response
    {
        $input = $request->validate(['name' => 'required|string', 'startDate' => 'required|string', 'endDate' => 'required|string']);
        $projection = $create->execute($this->scope($request), $input['name'], $input['startDate'], $input['endDate']);

        return $request->expectsJson() ? response()->json($projection, 201) : redirect()->route('conferences.show', ['id' => $projection['id']], 303);
    }

    public function update(Request $request, string $id, UpdateConference $update): Response
    {
        $input = $request->validate(['name' => 'required|string', 'startDate' => 'required|string', 'endDate' => 'required|string', 'expectedVersion' => 'required|integer|min:1']);
        $projection = $update->execute($this->scope($request), $id, $input['name'], $input['startDate'], $input['endDate'], (int) $input['expectedVersion']);

        return $request->expectsJson() ? response()->json($projection) : redirect()->route('conferences.show', ['id' => $id], 303);
    }

    private function scope(Request $request): CurrentWorkspace
    {
        return $request->attributes->get('workspace');
    }
}
