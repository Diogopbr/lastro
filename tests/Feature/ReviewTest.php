<?php

namespace Tests\Feature;

use App\Domain\Access\CleanupWorkspaces;
use App\Domain\Access\CurrentWorkspace;
use App\Domain\Access\DemoWorkspace;
use App\Domain\Reconciliation\ReconcileConference;
use App\Domain\Reconciliation\RunQuery;
use App\Domain\Reviews\ReviewResult;
use App\Http\Controllers\Reviews\ReviewController;
use App\Http\Middleware\ResolveWorkspace;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\Fixtures\ReconciliationDatabase;

class ReviewTest extends ReconciliationDatabase
{
    private function createResult(string $classification = 'amountMismatch'): object
    {
        $this->importPair();
        app(ReconcileConference::class)->execute($this->scope, $this->conferenceId);

        return DB::table('results')->where('workspace_id', $this->scope->id)->where('classification', $classification)->first();
    }

    public function test_review_reopen_preserve_snapshot_and_full_event_history(): void
    {
        $result = $this->createResult();
        $review = app(ReviewResult::class);
        $first = $review->execute($this->scope, $result->id, 1, 'reviewed', 'Conferido com a origem.')->toArray();
        $this->assertSame('reviewed', $first['reviewStatus']);
        $this->assertSame(2, $first['version']);
        $this->assertSame('amountMismatch', $first['classification']);
        $this->assertSame('-10.00', $first['difference']);
        $this->assertSame('Conferido com a origem.', $first['reviewEvents'][0]['note']);
        $this->assertSame('Visitante da demonstração', $first['reviewEvents'][0]['actorName']);
        $this->assertStringEndsWith('+00:00', $first['reviewEvents'][0]['createdAt']);
        $run = app(RunQuery::class)->show($this->scope, $result->run_id);
        $this->assertSame(1, $run['run']['summary']['reviewed']);
        $second = $review->execute($this->scope, $result->id, 2, 'reopened', 'Nova investigação necessária.')->toArray();
        $this->assertSame('pending', $second['reviewStatus']);
        $this->assertSame(3, $second['version']);
        $this->assertSame(['reopened', 'reviewed'], array_column($second['reviewEvents'], 'action'));
        $this->assertSame($first['order'], $second['order']);
        $this->assertSame($first['payout'], $second['payout']);
        $this->assertSame(0, app(RunQuery::class)->show($this->scope, $result->run_id)['run']['summary']['reviewed']);
        $this->assertSame(2, DB::table('review_events')->count());
        $this->assertSame(1, app(RunQuery::class)->results($this->scope, $result->run_id, ['reviewStatus' => 'pending', 'classification' => 'amountMismatch'])['total']);
    }

    #[DataProvider('invalidNotes')]
    public function test_invalid_notes_are_422_without_event_or_version_change(string $note): void
    {
        $result = $this->createResult();
        try {
            app(ReviewResult::class)->execute($this->scope, $result->id, 1, 'reviewed', $note);
            $this->fail('Invalid note accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('note', $exception->errors());
        }
        $this->assertSame(1, DB::table('results')->where('id', $result->id)->value('version'));
        $this->assertSame(0, DB::table('review_events')->count());
    }

    public static function invalidNotes(): array
    {
        return [[''], ['ab'], ['   '], [str_repeat('é', 1001)], ["bad\xFF"], ["abc\0x"], [str_repeat("\u{00A0}", 3)], [str_repeat("\u{200B}", 3)]];
    }

    public function test_accepts_three_and_one_thousand_unicode_characters(): void
    {
        $result = $this->createResult();
        app(ReviewResult::class)->execute($this->scope, $result->id, 1, 'reviewed', 'ééé');
        $reopen = app(ReviewResult::class)->execute($this->scope, $result->id, 2, 'reopened', str_repeat('é', 1000))->toArray();
        $this->assertSame(1000, mb_strlen($reopen['reviewEvents'][0]['note']));
    }

    public function test_stale_review_always_conflicts_without_overwrite(): void
    {
        $result = $this->createResult();
        app(ReviewResult::class)->execute($this->scope, $result->id, 1, 'reviewed', 'Vencedora.');
        try {
            app(ReviewResult::class)->execute($this->scope, $result->id, 1, 'reviewed', 'Vencedora.');
            $this->fail('Stale command accepted.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }
        $this->assertSame(1, DB::table('review_events')->count());
        $this->assertSame('Vencedora.', DB::table('review_events')->value('note'));
    }

    public function test_matched_cannot_be_reviewed_and_reopen_requires_reviewed_state(): void
    {
        $matched = $this->createResult('matched');
        foreach ([[$matched->id, 'reviewed'], [DB::table('results')->where('classification', 'amountMismatch')->value('id'), 'reopened']] as [$id, $action]) {
            try {
                app(ReviewResult::class)->execute($this->scope, $id, 1, $action, 'Motivo válido.');
                $this->fail('Invalid transition accepted.');
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
            }
        }
        $this->assertSame(0, DB::table('review_events')->count());
    }

    public function test_foreign_result_is_not_found(): void
    {
        $result = $this->createResult();
        $other = app(DemoWorkspace::class)->start(bin2hex(random_bytes(32)));
        $this->expectException(NotFoundHttpException::class);
        app(ReviewResult::class)->execute(new CurrentWorkspace($other->workspace_id, null, 'demo', $other->id), $result->id, 1, 'reviewed', 'Motivo válido.');
    }

    public function test_cleanup_collects_entire_generation_with_run_review_and_deferred_actor_fks(): void
    {
        $result = $this->createResult();
        app(ReviewResult::class)->execute($this->scope, $result->id, 1, 'reviewed', 'Motivo válido.');
        $constraints = DB::select("SELECT conname, condeferrable, condeferred FROM pg_constraint WHERE conrelid IN ('run_events'::regclass, 'review_events'::regclass) AND confrelid = 'users'::regclass");
        $this->assertCount(2, $constraints);
        foreach ($constraints as $constraint) {
            $this->assertTrue($constraint->condeferrable);
            $this->assertTrue($constraint->condeferred);
        }
        DB::table('workspaces')->where('id', $this->scope->id)->update(['expires_at' => DB::raw("clock_timestamp() - interval '1 second'")]);
        $this->assertSame(1, app(CleanupWorkspaces::class)->batch());
        foreach (['runs', 'results', 'run_events', 'review_events', 'sources', 'source_rows', 'source_events', 'users', 'conferences'] as $table) {
            $this->assertSame(0, DB::table($table)->count(), $table);
        }
    }

    public function test_review_events_cannot_be_updated_or_deleted_directly(): void
    {
        $result = $this->createResult();
        app(ReviewResult::class)->execute($this->scope, $result->id, 1, 'reviewed', 'Imutável.');
        foreach ([fn () => DB::table('review_events')->update(['note' => 'Editada.']), fn () => DB::table('review_events')->delete()] as $mutation) {
            try {
                DB::transaction($mutation);
                $this->fail('Event changed.');
            } catch (QueryException $exception) {
                $this->assertSame('23514', $exception->errorInfo[0]);
            }
        }
        $this->assertSame('Imutável.', DB::table('review_events')->value('note'));
    }

    public function test_controller_validates_input_and_returns_projection(): void
    {
        Route::post('/test/resultados/{id}/revisoes', [ReviewController::class, 'store'])->middleware(['web', ResolveWorkspace::class]);
        $result = $this->createResult();
        $this->postJson('/test/resultados/'.$result->id.'/revisoes', ['expectedVersion' => 1, 'action' => 'invalid', 'note' => 'Motivo.'])->assertUnprocessable();
        $this->postJson('/test/resultados/'.$result->id.'/revisoes', ['expectedVersion' => 1, 'action' => 'reviewed', 'note' => 'Motivo.'])->assertOk()->assertJsonPath('reviewStatus', 'reviewed')->assertJsonPath('version', 2);
        $this->postJson('/test/resultados/'.$result->id.'/revisoes', ['expectedVersion' => 1, 'action' => 'reviewed', 'note' => 'Motivo.'])->assertConflict();
    }

    public function test_empty_multiline_note_is_rejected(): void
    {
        $result = $this->createResult();
        try {
            app(ReviewResult::class)->execute($this->scope, $result->id, 1, 'reviewed', "\r\n\t");
            $this->fail('Whitespace note accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('note', $exception->errors());
        }
        $this->assertSame(0, DB::table('review_events')->count());
    }

    public function test_expiry_during_review_rolls_back_result_and_event(): void
    {
        $result = $this->createResult();
        DB::unprepared(<<<'SQL'
            CREATE FUNCTION expire_during_review() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                UPDATE workspaces SET expires_at = clock_timestamp() - interval '1 second' WHERE id = NEW.workspace_id;
                RETURN NEW;
            END $$;
            CREATE TRIGGER expire_during_review AFTER INSERT ON review_events FOR EACH ROW EXECUTE FUNCTION expire_during_review();
            SQL);
        try {
            app(ReviewResult::class)->execute($this->scope, $result->id, 1, 'reviewed', 'Deve reverter.');
            $this->fail('Expired write committed.');
        } catch (NotFoundHttpException) {
            $this->assertTrue(true);
        }
        $this->assertSame(1, DB::table('results')->where('id', $result->id)->value('version'));
        $this->assertSame(0, DB::table('review_events')->count());
    }

    public function test_collecting_revoked_generation_preserves_replacement_and_its_reviews(): void
    {
        $result = $this->createResult();
        app(ReviewResult::class)->execute($this->scope, $result->id, 1, 'reviewed', 'Geração anterior.');
        $replacement = app(DemoWorkspace::class)->reset($this->scope, $this->token, '127.0.0.85');
        $this->scope = new CurrentWorkspace($replacement->workspace_id, null, 'demo', $replacement->id);
        $this->conferenceId = DB::table('conferences')->where('workspace_id', $this->scope->id)->orderBy('code')->value('id');
        $newResult = $this->createResult();
        app(ReviewResult::class)->execute($this->scope, $newResult->id, 1, 'reviewed', 'Geração vigente.');
        $this->assertSame(1, app(CleanupWorkspaces::class)->batch());
        $this->assertSame(1, DB::table('runs')->count());
        $this->assertSame(4, DB::table('results')->count());
        $this->assertSame('Geração vigente.', DB::table('review_events')->value('note'));
        $this->assertSame($this->scope->id, DB::table('runs')->value('workspace_id'));
    }

    public function test_new_migrations_roll_back_and_reapply_without_touching_foundation(): void
    {
        $this->artisan('migrate:rollback', ['--step' => 2, '--force' => true])->assertExitCode(0);
        $this->assertFalse(Schema::hasTable('runs'));
        $this->assertFalse(Schema::hasTable('review_events'));
        $this->assertTrue(Schema::hasTable('sources'));
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
        $result = $this->createResult();
        $this->assertSame(2, app(ReviewResult::class)->execute($this->scope, $result->id, 1, 'reviewed', 'Após reaplicar.')->toArray()['version']);
    }

    public function test_review_form_redirects_only_after_persisted_update(): void
    {
        $result = $this->createResult();
        $this->post('/resultados/'.$result->id.'/revisoes', ['expectedVersion' => 1, 'action' => 'reviewed', 'note' => 'Formulário revisado.'])
            ->assertStatus(303)->assertRedirect('/execucoes/'.$result->run_id);
        $this->assertSame('reviewed', DB::table('results')->where('id', $result->id)->value('review_status'));
        $this->assertSame(1, DB::table('review_events')->count());
    }
}
