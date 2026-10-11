<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => function () use ($request): array {
                $user = $request->user();
                $workspace = $request->attributes->get('workspace');

                return [
                    'user' => $user === null ? null : ['id' => $user->id, 'displayName' => $user->name],
                    'workspace' => $workspace === null ? null : ['id' => $workspace->id, 'kind' => $workspace->kind, 'expiresAt' => $workspace->expiresAt],
                    'demoEnabled' => config('lastro.demo_enabled'),
                ];
            },
            'flash' => ['success' => fn (): ?string => $request->session()->get('success')],
        ];
    }
}
