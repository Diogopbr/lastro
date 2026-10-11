<?php

namespace App\Http\Middleware;

use App\Domain\Access\CurrentWorkspace;
use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ResolveWorkspace
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user === null) {
            return $request->expectsJson()
                ? response()->json(['code' => 'unauthenticated', 'message' => 'Entre para continuar.'], 401)
                : redirect()->route('login');
        }
        $workspace = DB::table('workspaces')->where('id', $user->workspace_id)
            ->whereNull('revoked_at')
            ->where(function (Builder $query): void {
                $query->whereNull('expires_at')->orWhereRaw('expires_at > clock_timestamp()');
            })->first();
        if ($workspace === null) {
            abort(404);
        }
        $request->attributes->set('workspace', new CurrentWorkspace($workspace->id, $workspace->expires_at, $workspace->kind, $user->id));

        return $next($request);
    }
}
