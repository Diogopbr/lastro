<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VercelIngress
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $headers = Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO;
        Request::setTrustedProxies([], $headers);
        if (! config('lastro.vercel_ingress')) {
            return $next($request);
        }
        abort_unless(config('lastro.vercel_runtime'), 503, 'Configuração da hospedagem indisponível.');
        if ($request->isMethod('GET') && $request->is('health/ready', 'up')) {
            return $next($request);
        }
        $clientIp = $request->header('X-Vercel-Forwarded-For', '');
        abort_unless(filter_var($clientIp, FILTER_VALIDATE_IP) !== false, 400, 'Identidade de origem indisponível.');
        abort_unless($request->header('X-Forwarded-Proto') === 'https', 400, 'Protocolo de origem indisponível.');
        // This mode is only valid behind Vercel's isolated gateway, which overwrites this header.
        $request->headers->set('X-Forwarded-For', $clientIp);
        Request::setTrustedProxies([$request->server->get('REMOTE_ADDR')], $headers);

        return $next($request);
    }
}
