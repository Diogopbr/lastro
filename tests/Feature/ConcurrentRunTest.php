<?php

namespace Tests\Feature;

use App\Domain\Access\DemoWorkspace;
use App\Domain\Reconciliation\ReconcileConference;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\ReconciliationDatabase;

class ConcurrentRunTest extends ReconciliationDatabase
{
    public function test_two_processes_return_one_snapshot_and_event_after_observed_lock_wait(): void
    {
        $this->importPair();
        DB::beginTransaction();
        $first = app(ReconcileConference::class)->execute($this->scope, $this->conferenceId);
        $worker = $this->worker('run', [$this->conferenceId]);
        DB::commit();
        $result = $this->workerResult($worker);
        $this->assertSame(200, $result['status']);
        $this->assertSame($first->toArray()['id'], $result['run']['id']);
        $this->assertSame(1, DB::table('runs')->count());
        $this->assertSame(1, DB::table('run_events')->count());
        $this->assertSame(4, DB::table('results')->count());
    }

    public function test_reset_beats_waiting_reconciliation_of_old_scope(): void
    {
        $this->importPair();
        DB::beginTransaction();
        app(DemoWorkspace::class)->reset($this->scope, $this->token, '127.0.0.81');
        $worker = $this->worker('run', [$this->conferenceId]);
        DB::commit();
        $this->assertSame(404, $this->workerResult($worker)['status']);
        $this->assertSame(0, DB::table('runs')->count());
    }
}
