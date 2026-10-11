<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('access', fn (Request $request): Limit => Limit::perMinute(10)->by('access:'.$request->ip()));
        RateLimiter::for('imports', function (Request $request): Limit {
            $scope = $request->attributes->get('workspace');
            $visitor = DB::table('workspaces')->where('id', $scope->id)->value('visitor_id');

            return Limit::perMinute(20)->by('imports:'.($visitor ?? $scope->userId));
        });
    }
}
