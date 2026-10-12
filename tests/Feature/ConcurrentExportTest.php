<?php

namespace Tests\Feature;

use App\Domain\Access\DemoWorkspace;
use App\Domain\Exports\ExportRun;
use App\Domain\Reconciliation\ReconcileConference;
use App\Domain\Reviews\ReviewResult;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use League\Csv\Reader;
use Tests\Fixtures\ReconciliationDatabase;

class ConcurrentExportTest extends ReconciliationDatabase
{
    public function test_export_materializes_one_repeatable_read_state_before_waiting_review_commits(): void
    {
        $this->importPair();
        $run = app(ReconcileConference::class)->execute($this->scope, $this->conferenceId);
        $id = DB::table('results')->where('classification', 'amountMismatch')->value('id');
        app(ReviewResult::class)->execute($this->scope, $id, 1, 'reviewed', 'Estado anterior.');
        $worker = null;
        $observedIsolation = null;
        $entered = false;
        DB::listen(function (QueryExecuted $query) use (&$worker, &$observedIsolation, &$entered, $id): void {
            if ($entered || DB::transactionLevel() === 0 || ! str_contains($query->sql, 'from "results"')) {
                return;
            }
            $entered = true;
            $observedIsolation = DB::selectOne('SHOW transaction_isolation')->transaction_isolation;
            $worker = $this->worker('review', [$id, '2', 'reopened', base64_encode('Estado posterior.')]);
        });
        $response = app(ExportRun::class)->execute($this->scope, $run->row->id);
        $this->assertTrue($entered);
        $this->assertSame('repeatable read', $observedIsolation);
        $this->assertSame(200, $this->workerResult($worker)['status']);
        $rows = iterator_to_array(Reader::fromString($response->getContent())->setEscape('')->setHeaderOffset(0)->getRecords(), false);
        $row = array_column($rows, null, 'order_id')['0002'];
        $this->assertSame('reviewed', $row['review_status']);
        $this->assertSame('Estado anterior.', $row['review_note']);
        $this->assertSame('pending', DB::table('results')->where('id', $id)->value('review_status'));
        $this->assertSame('Estado posterior.', DB::table('review_events')->orderByDesc('result_version')->value('note'));
    }

    public function test_export_waiting_behind_review_keeps_the_consistent_snapshot_from_transaction_start(): void
    {
        $this->importPair();
        $run = app(ReconcileConference::class)->execute($this->scope, $this->conferenceId);
        $id = DB::table('results')->where('classification', 'amountMismatch')->value('id');
        DB::beginTransaction();
        app(ReviewResult::class)->execute($this->scope, $id, 1, 'reviewed', 'Revisão antes do export.');
        $worker = $this->worker('export', [$run->row->id]);
        DB::commit();
        $result = $this->workerResult($worker);
        $this->assertSame(200, $result['status']);
        $rows = iterator_to_array(Reader::fromString(base64_decode($result['csv'], true))->setEscape('')->setHeaderOffset(0)->getRecords(), false);
        $row = array_column($rows, null, 'order_id')['0002'];
        $this->assertSame('pending', $row['review_status']);
        $this->assertSame('', $row['review_note']);
        $this->assertSame('', $row['reviewed_at']);
        $this->assertSame('reviewed', DB::table('results')->where('id', $id)->value('review_status'));
    }

    public function test_export_waiting_behind_reset_is_not_found_after_serialization_retry(): void
    {
        $this->importPair();
        $run = app(ReconcileConference::class)->execute($this->scope, $this->conferenceId);
        DB::beginTransaction();
        app(DemoWorkspace::class)->reset($this->scope, $this->token, '127.0.0.84');
        $worker = $this->worker('export', [$run->row->id]);
        DB::commit();
        $this->assertSame(404, $this->workerResult($worker)['status']);
    }
}
