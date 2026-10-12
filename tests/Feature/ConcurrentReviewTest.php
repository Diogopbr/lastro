<?php

namespace Tests\Feature;

use App\Domain\Access\DemoWorkspace;
use App\Domain\Reconciliation\ReconcileConference;
use App\Domain\Reconciliation\RunQuery;
use App\Domain\Reviews\ReviewResult;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Fixtures\ReconciliationDatabase;

class ConcurrentReviewTest extends ReconciliationDatabase
{
    #[DataProvider('races')]
    public function test_review_reopen_races_observe_lock_and_only_one_version_wins(string $first, string $second, bool $alreadyReviewed): void
    {
        $this->importPair();
        $run = app(ReconcileConference::class)->execute($this->scope, $this->conferenceId);
        $id = DB::table('results')->where('classification', 'amountMismatch')->value('id');
        $review = app(ReviewResult::class);
        $version = 1;
        if ($alreadyReviewed) {
            $review->execute($this->scope, $id, $version++, 'reviewed', 'Revisão original.');
        }
        DB::beginTransaction();
        $winner = $review->execute($this->scope, $id, $version, $first, 'Nota vencedora.');
        $worker = $this->worker('review', [$id, (string) $version, $second, base64_encode('Nota perdedora.')]);
        DB::commit();
        $this->assertSame(409, $this->workerResult($worker)['status']);
        $this->assertSame($version + 1, DB::table('results')->where('id', $id)->value('version'));
        $this->assertSame($alreadyReviewed ? 2 : 1, DB::table('review_events')->count());
        $this->assertSame('Nota vencedora.', $winner->toArray()['reviewEvents'][0]['note']);
        $this->assertSame($first === 'reviewed' ? 1 : 0, app(RunQuery::class)->show($this->scope, $run->row->id)['run']['summary']['reviewed']);
    }

    public static function races(): array
    {
        return [['reviewed', 'reviewed', false], ['reopened', 'reopened', true], ['reviewed', 'reopened', false], ['reopened', 'reviewed', true]];
    }

    public function test_reset_invalidates_waiting_review_of_old_generation(): void
    {
        $this->importPair();
        app(ReconcileConference::class)->execute($this->scope, $this->conferenceId);
        $id = DB::table('results')->where('classification', 'amountMismatch')->value('id');
        DB::beginTransaction();
        app(DemoWorkspace::class)->reset($this->scope, $this->token, '127.0.0.82');
        $worker = $this->worker('review', [$id, '1', 'reviewed', base64_encode('Nota antiga.')]);
        DB::commit();
        $this->assertSame(404, $this->workerResult($worker)['status']);
        $this->assertSame(0, DB::table('review_events')->count());
    }
}
