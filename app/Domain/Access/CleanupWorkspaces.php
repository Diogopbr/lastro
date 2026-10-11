<?php

namespace App\Domain\Access;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class CleanupWorkspaces
{
    public function batch(): int
    {
        return DB::transaction(function (): int {
            $ids = DB::table('workspaces')->where('kind', 'demo')
                ->where(function (Builder $query): void {
                    $query->whereNotNull('revoked_at')->orWhereRaw('expires_at <= clock_timestamp()');
                })->orderBy('expires_at')->limit(10)->lock('FOR UPDATE SKIP LOCKED')->pluck('id');
            if ($ids->isEmpty()) {
                return 0;
            }
            DB::table('sessions')->whereIn('user_id', DB::table('users')->whereIn('workspace_id', $ids)->select('id'))->delete();

            return DB::table('workspaces')->whereIn('id', $ids)->delete();
        });
    }

    public function opportunistic(): int
    {
        $cache = Cache::store('database');
        $result = $cache->lock('lastro:cleanup:mutex', 15)->get(function () use ($cache): int {
            $now = (float) DB::selectOne('SELECT EXTRACT(EPOCH FROM clock_timestamp()) AS now')->now;
            if ((float) $cache->get('lastro:cleanup:due', 0) > $now) {
                return 0;
            }
            $total = 0;
            $count = 0;
            for ($batch = 0; $batch < 3; $batch++) {
                $count = $this->batch();
                $total += $count;
                if ($count < 10) {
                    break;
                }
            }
            $cache->put('lastro:cleanup:due', $now + ($count === 10 ? 1 : 60), 120);

            return $total;
        });

        return is_int($result) ? $result : 0;
    }
}
