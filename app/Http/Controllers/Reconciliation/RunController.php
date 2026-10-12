<?php

namespace App\Http\Controllers\Reconciliation;

use App\Domain\Reconciliation\ReconcileConference;
use App\Domain\Reconciliation\RunQuery;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class RunController extends Controller
{
    public function store(Request $request, string $id, ReconcileConference $reconcile): Response
    {
        $run = $reconcile->execute($request->attributes->get('workspace'), $id);

        return $request->expectsJson() ? response()->json($run->toArray(), $run->created ? 201 : 200)
            : redirect()->route('runs.show', ['id' => $run->row->id], 303);
    }

    public function show(Request $request, string $id, RunQuery $query): Response
    {
        $props = $query->show($request->attributes->get('workspace'), $id, $request->query());

        return $request->expectsJson() ? response()->json($props) : Inertia::render('Runs/Show', $props)->toResponse($request);
    }

    public function results(Request $request, string $id, RunQuery $query): Response
    {
        return response()->json($query->results($request->attributes->get('workspace'), $id, $request->query()));
    }
}
