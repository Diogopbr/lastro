<?php

namespace App\Http\Controllers\Reviews;

use App\Domain\Reviews\ReviewResult;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ReviewController extends Controller
{
    public function store(Request $request, string $id, ReviewResult $review): Response
    {
        $input = $request->validate(['expectedVersion' => 'required|integer|min:1', 'action' => 'required|in:reviewed,reopened', 'note' => 'required|string']);
        $result = $review->execute($request->attributes->get('workspace'), $id, (int) $input['expectedVersion'], $input['action'], $input['note']);

        return $request->expectsJson() ? response()->json($result->toArray())
            : redirect()->route('runs.show', ['id' => $result->row->run_id], 303);
    }
}
