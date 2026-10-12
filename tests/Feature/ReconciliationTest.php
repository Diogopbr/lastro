<?php

namespace Tests\Feature;

use App\Domain\Access\CurrentWorkspace;
use App\Domain\Access\DemoWorkspace;
use App\Domain\Imports\SourceKind;
use App\Domain\Reconciliation\ReconcileConference;
use App\Domain\Reconciliation\RunQuery;
use App\Http\Controllers\Reconciliation\RunController;
use App\Http\Middleware\ResolveWorkspace;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;
use PDOException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\Fixtures\ReconciliationDatabase;

class ReconciliationTest extends ReconciliationDatabase
{
    public function test_snapshot_has_exact_totals_origin_and_idempotent_run_event(): void
    {
        $this->importPair();
        $run = app(ReconcileConference::class)->execute($this->scope, $this->conferenceId);
        $this->assertTrue($run->created);
        $projection = $run->toArray();
        $this->assertSame([
            'counts' => ['matched' => 1, 'amountMismatch' => 1, 'missingPayout' => 1, 'missingOrder' => 1],
            'orderGross' => '600.00', 'payoutGross' => '690.00', 'fees' => '20.00', 'net' => '670.00',
            'divergences' => 3, 'reviewed' => 0,
        ], $projection['summary']);
        $this->assertSame(['id', 'conferenceId', 'createdAt', 'summary'], array_keys($projection));
        $this->assertStringEndsWith('+00:00', $projection['createdAt']);
        $repeat = app(ReconcileConference::class)->execute($this->scope, $this->conferenceId);
        $this->assertFalse($repeat->created);
        $this->assertSame($projection, $repeat->toArray());
        $this->assertSame(1, DB::table('runs')->count());
        $this->assertSame(1, DB::table('run_events')->count());
        $page = app(RunQuery::class)->results($this->scope, $projection['id']);
        $this->assertSame(4, $page['total']);
        $byId = array_column($page['items'], null, 'orderId');
        $this->assertSame('-10.00', $byId['0002']['difference']);
        $this->assertNull($byId['0001']['reviewStatus']);
        $this->assertSame([], $byId['0002']['reviewEvents']);
        $this->assertNull($byId['0003']['payout']);
        $this->assertNull($byId['0004']['order']);
        $source = DB::table('sources')->where('kind', 'orders')->first();
        $this->assertSame($source->id, $byId['0002']['order']['sourceId']);
        $this->assertSame($source->normalized_hash, $byId['0002']['order']['normalizedHash']);
        $this->assertSame('orders.csv', $byId['0002']['order']['displayFilename']);
        $this->assertSame(3, $byId['0002']['order']['line']);
        $this->assertSame('200.00', $byId['0002']['order']['gross']);
        $this->assertSame('5.00', $byId['0002']['payout']['fee']);
        $this->assertSame($projection, json_decode(json_encode($run, JSON_THROW_ON_ERROR), true));
    }

    public function test_missing_sources_fail_without_partial_snapshot(): void
    {
        try {
            app(ReconcileConference::class)->execute($this->scope, $this->conferenceId);
            $this->fail('Missing sources accepted.');
        } catch (ValidationException $exception) {
            $this->assertNotEmpty($exception->errors());
        }
        $this->import(SourceKind::Orders, "order_id,ordered_at,gross_amount,currency\n0001,2026-05-01,1.00,BRL");
        $this->expectException(ValidationException::class);
        app(ReconcileConference::class)->execute($this->scope, $this->conferenceId);
    }

    public function test_foreign_conference_is_not_found(): void
    {
        $other = app(DemoWorkspace::class)->start(bin2hex(random_bytes(32)));
        $scope = new CurrentWorkspace($other->workspace_id, null, 'demo', $other->id);
        $this->expectException(NotFoundHttpException::class);
        app(ReconcileConference::class)->execute($scope, $this->conferenceId);
    }

    public function test_filters_and_pagination_never_change_global_totals(): void
    {
        $this->importPair();
        $run = app(ReconcileConference::class)->execute($this->scope, $this->conferenceId)->toArray();
        $query = app(RunQuery::class);
        $page = $query->results($this->scope, $run['id'], ['classification' => 'amountMismatch', 'reviewStatus' => 'pending', 'search' => '0002', 'pageSize' => 1]);
        $this->assertSame(1, $page['total']);
        $this->assertCount(1, $page['items']);
        $this->assertSame('0002', $page['items'][0]['orderId']);
        $this->assertSame($run['summary'], $query->show($this->scope, $run['id'])['run']['summary']);
        $this->assertSame([], $query->results($this->scope, $run['id'], ['page' => 2, 'pageSize' => 20])['items']);
        $this->expectException(ValidationException::class);
        $query->results($this->scope, $run['id'], ['pageSize' => 21]);
    }

    public function test_zero_padding_does_not_create_a_match(): void
    {
        $this->import(SourceKind::Orders, "order_id,ordered_at,gross_amount,currency\n0001,2026-05-01,1.00,BRL");
        $this->import(SourceKind::Payouts, "order_id,payout_id,payout_date,gross_amount,fee_amount,net_amount,currency\n1,p,2026-05-01,1.00,0.00,1.00,BRL");
        $run = app(ReconcileConference::class)->execute($this->scope, $this->conferenceId)->toArray();
        $this->assertSame(0, $run['summary']['counts']['matched']);
        $this->assertSame(1, $run['summary']['counts']['missingOrder']);
        $this->assertSame(1, $run['summary']['counts']['missingPayout']);
    }

    public function test_controller_json_created_repeat_and_query_contracts_with_test_only_routes(): void
    {
        Route::middleware(['web', ResolveWorkspace::class])->group(function (): void {
            Route::post('/test/conferencias/{id}/conciliar', [RunController::class, 'store']);
            Route::get('/test/execucoes/{id}', [RunController::class, 'show'])->name('runs.show');
            Route::get('/test/execucoes/{id}/resultados', [RunController::class, 'results']);
        });
        $this->importPair();
        $run = $this->postJson('/test/conferencias/'.$this->conferenceId.'/conciliar')->assertCreated()->json();
        $this->postJson('/test/conferencias/'.$this->conferenceId.'/conciliar')->assertOk()->assertJsonPath('id', $run['id']);
        $this->getJson('/test/execucoes/'.$run['id'])->assertOk()->assertJsonPath('run.summary.orderGross', '600.00')->assertJsonPath('conference.run.id', $run['id']);
        $this->getJson('/test/execucoes/'.$run['id'].'/resultados?classification=missingOrder')->assertOk()->assertJsonPath('total', 1);
        $this->getJson('/test/execucoes/'.$run['id'].'/resultados?pageSize=21')->assertUnprocessable();
    }

    public function test_up_to_two_hundred_results_are_paginated_without_losing_large_totals(): void
    {
        $orders = [];
        $payouts = [];
        foreach (range(1, 100) as $id) {
            $orders[] = "o$id,2026-05-01,1000000.00,BRL";
            $payouts[] = "p$id,p,2026-05-01,1000000.00,0.00,1000000.00,BRL";
        }
        $this->import(SourceKind::Orders, "order_id,ordered_at,gross_amount,currency\n".implode("\n", $orders));
        $this->import(SourceKind::Payouts, "order_id,payout_id,payout_date,gross_amount,fee_amount,net_amount,currency\n".implode("\n", $payouts));
        $run = app(ReconcileConference::class)->execute($this->scope, $this->conferenceId)->toArray();
        $this->assertSame('100000000.00', $run['summary']['orderGross']);
        $this->assertSame('100000000.00', $run['summary']['payoutGross']);
        $page = app(RunQuery::class)->results($this->scope, $run['id'], ['page' => 10]);
        $this->assertSame(200, $page['total']);
        $this->assertCount(20, $page['items']);
        $this->assertSame(200, $run['summary']['divergences']);
    }

    public function test_foreign_queries_and_expired_scope_are_not_found(): void
    {
        $this->importPair();
        $run = app(ReconcileConference::class)->execute($this->scope, $this->conferenceId);
        $other = app(DemoWorkspace::class)->start(bin2hex(random_bytes(32)));
        try {
            app(RunQuery::class)->show(new CurrentWorkspace($other->workspace_id, null, 'demo', $other->id), $run->row->id);
            $this->fail('Foreign run accepted.');
        } catch (NotFoundHttpException) {
            $this->assertTrue(true);
        }
        DB::table('workspaces')->where('id', $this->scope->id)->update(['expires_at' => DB::raw("clock_timestamp() - interval '1 second'")]);
        $this->expectException(NotFoundHttpException::class);
        app(ReconcileConference::class)->execute($this->scope, $this->conferenceId);
    }

    public function test_schema_rejects_snapshot_edits_and_wrong_origins(): void
    {
        $this->importPair();
        $run = app(ReconcileConference::class)->execute($this->scope, $this->conferenceId);
        $matched = DB::table('results')->where('classification', 'matched')->first();
        $checks = [
            fn () => DB::table('runs')->where('id', $run->row->id)->update(['created_at' => DB::raw('clock_timestamp()')]),
            fn () => DB::table('results')->where('id', $matched->id)->update(['difference_cents' => 1, 'version' => 2]),
            fn () => DB::table('run_events')->update(['event_type' => 'edited']),
            fn () => DB::table('run_events')->delete(),
            fn () => DB::table('runs')->delete(),
            fn () => DB::table('results')->delete(),
            fn () => DB::table('results')->insert([...get_object_vars($matched), 'id' => (string) Str::uuid(), 'order_id' => 'wrong']),
            fn () => DB::table('results')->insert([...get_object_vars($matched), 'id' => (string) Str::uuid(), 'order_id' => 'bad', 'difference_cents' => null]),
        ];
        foreach ($checks as $check) {
            try {
                DB::transaction($check);
                $this->fail('Invalid snapshot mutation accepted.');
            } catch (QueryException $exception) {
                $this->assertContains($exception->errorInfo[0], ['23503', '23514']);
            }
        }
        $this->assertSame(4, DB::table('results')->count());
    }

    public function test_run_and_event_rollback_together_when_actor_is_foreign(): void
    {
        $this->importPair();
        $other = app(DemoWorkspace::class)->start(bin2hex(random_bytes(32)));
        try {
            app(ReconcileConference::class)->execute(new CurrentWorkspace($this->scope->id, null, 'demo', $other->id), $this->conferenceId);
            $this->fail('Foreign actor accepted.');
        } catch (PDOException $exception) {
            $this->assertSame('23503', $exception->errorInfo[0]);
        }
        $this->assertSame(0, DB::table('runs')->count());
        $this->assertSame(0, DB::table('run_events')->count());
        $this->assertSame(0, DB::table('results')->count());
    }

    public function test_inertia_show_and_form_reconcile_use_contract_props_and_303(): void
    {
        $this->withoutVite();
        $this->importPair();
        $response = $this->post('/conferencias/'.$this->conferenceId.'/conciliar')->assertStatus(303);
        $runId = DB::table('runs')->value('id');
        $response->assertRedirect('/execucoes/'.$runId);
        $this->get('/execucoes/'.$runId)->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Runs/Show', false)->where('run.summary.orderGross', '600.00')
            ->where('conference.progress.divergences', 3)->has('results.items', 4)->has('filters'));
    }
}
