<?php

namespace App\Http\Middleware;

use App\Domain\Access\CleanupWorkspaces;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CleanupExpiredWorkspaces
{
    public function __construct(private CleanupWorkspaces $cleanup) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        if (config('lastro.demo_enabled')) {
            $this->cleanup->opportunistic();
        }

        return $response;
    }
}
