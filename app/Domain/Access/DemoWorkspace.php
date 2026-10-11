<?php

namespace App\Domain\Access;

use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Cache\RateLimiter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class DemoWorkspace
{
    public function __construct(private DemoSeeder $seeder, private WorkspaceTransaction $transaction) {}

    public function start(string $token): User
    {
        $hash = hash('sha256', $token);

        return DB::transaction(function () use ($hash): User {
            DB::table('visitors')->insertOrIgnore(['id' => (string) Str::uuid(), 'token_hash' => $hash]);
            $visitor = DB::table('visitors')->where('token_hash', $hash)->lockForUpdate()->first();
            $workspace = DB::table('workspaces')->where('visitor_id', $visitor->id)->whereNull('revoked_at')
                ->whereRaw('expires_at > clock_timestamp()')->lockForUpdate()->first();
            if ($workspace !== null) {
                return User::where('workspace_id', $workspace->id)->firstOrFail();
            }

            return $this->create($visitor->id);
        });
    }

    public function reset(CurrentWorkspace $scope, string $token, string $ip): User
    {
        $cache = Cache::store('database');
        $limiter = new RateLimiter($cache);
        $ipKey = 'lastro:reset:ip:'.hash('sha256', $ip);

        return $cache->lock($ipKey.':mutex', 15)->block(3, function () use ($scope, $token, $ipKey, $limiter): User {
            return DB::transaction(function () use ($scope, $token, $ipKey, $limiter): User {
                $visitor = DB::table('visitors')->where('token_hash', hash('sha256', $token))->lockForUpdate()->first();
                if ($visitor === null) {
                    throw new NotFoundHttpException;
                }
                $workspace = DB::table('workspaces')->where('id', $scope->id)->where('visitor_id', $visitor->id)->first();
                if ($workspace === null || $scope->kind !== 'demo' || ! User::where('id', $scope->userId)->where('workspace_id', $scope->id)->exists()) {
                    throw new NotFoundHttpException;
                }
                $visitorKey = 'lastro:reset:visitor:'.$visitor->id;
                foreach ([$visitorKey => 1, $ipKey => 10] as $key => $limit) {
                    if ($limiter->tooManyAttempts($key, $limit)) {
                        throw new HttpException(429, 'Aguarde antes de reiniciar novamente.', null, ['Retry-After' => (string) max(1, $limiter->availableIn($key))]);
                    }
                }
                $replacement = $this->transaction->run($scope, function () use ($scope, $visitor, $visitorKey, $ipKey, $limiter): User {
                    $replacement = $this->create($visitor->id);
                    $limiter->hit($visitorKey, 60);
                    $limiter->hit($ipKey, 3600);
                    DB::table('sessions')->whereIn('user_id', DB::table('users')->where('workspace_id', $scope->id)->select('id'))->delete();

                    return $replacement;
                });
                DB::table('workspaces')->where('id', $scope->id)->update(['revoked_at' => DB::raw('clock_timestamp()')]);

                return $replacement;
            });
        });
    }

    private function create(string $visitorId): User
    {
        $id = (string) Str::uuid();
        DB::table('workspaces')->insert(['id' => $id, 'visitor_id' => $visitorId, 'kind' => 'demo', 'expires_at' => DB::raw("clock_timestamp() + interval '24 hours'")]);
        $user = User::create(['workspace_id' => $id, 'name' => 'Visitante da demonstração', 'email' => Str::uuid().'@demo.invalid', 'password' => bin2hex(random_bytes(32))]);
        $scope = new CurrentWorkspace($id, null, 'demo', $user->id);
        $this->seeder->seed($scope);

        return $user;
    }
}
