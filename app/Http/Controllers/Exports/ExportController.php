<?php

namespace App\Http\Controllers\Exports;

use App\Domain\Exports\ExportRun;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ExportController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, string $id, ExportRun $export): Response
    {
        return $export->execute($request->attributes->get('workspace'), $id);
    }
}
