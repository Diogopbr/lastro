<?php

use App\Http\Controllers\Health\ReadinessController;
use App\Http\Middleware\CleanupExpiredWorkspaces;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ResolveWorkspace;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            Route::get('/health/ready', ReadinessController::class)->name('health.ready');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [HandleInertiaRequests::class, CleanupExpiredWorkspaces::class]);
        $middleware->prependToPriorityList(ThrottleRequests::class, ResolveWorkspace::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (ValidationException $exception, Request $request): ?JsonResponse {
            if ($request->expectsJson()) {
                return response()->json(['code' => 'validation_failed', 'message' => 'Revise os dados informados.', 'errors' => $exception->errors()], 422);
            }

            return null;
        });
        $exceptions->render(function (HttpExceptionInterface $exception, Request $request): ?JsonResponse {
            if (! $request->expectsJson()) {
                return null;
            }
            $status = $exception->getStatusCode();
            $codes = [401 => 'unauthenticated', 404 => 'not_found', 409 => 'conflict', 419 => 'csrf_rejected', 429 => 'rate_limited'];
            $messages = [401 => 'Entre para continuar.', 404 => 'Este recurso não está disponível.', 419 => 'A sessão mudou. Atualize antes de tentar novamente.', 429 => 'Aguarde antes de tentar novamente.'];

            return response()->json(['code' => $codes[$status] ?? 'request_failed', 'message' => $messages[$status] ?? $exception->getMessage()], $status, $exception->getHeaders());
        });
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request): bool => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
