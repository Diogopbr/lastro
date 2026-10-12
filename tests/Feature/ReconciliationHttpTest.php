<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\Fixtures\ReconciliationDatabase;

class ReconciliationHttpTest extends ReconciliationDatabase
{
    public function test_production_routes_reconcile_review_export_and_refresh_progress(): void
    {
        $this->importPair();
        $run = $this->postJson('/conferencias/'.$this->conferenceId.'/conciliar')->assertCreated()->assertJsonPath('summary.orderGross', '600.00')->assertJsonPath('summary.payoutGross', '690.00')->json();
        $this->postJson('/conferencias/'.$this->conferenceId.'/conciliar')->assertOk()->assertJsonPath('id', $run['id']);
        $show = $this->getJson('/execucoes/'.$run['id'])->assertOk()->assertJsonPath('results.total', 4)->json();
        $result = collect($show['results']['items'])->firstWhere('classification', 'amountMismatch');
        $this->postJson('/resultados/'.$result['id'].'/revisoes', ['expectedVersion' => 1, 'action' => 'reviewed', 'note' => 'Diferença registrada na origem.'])->assertOk()->assertJsonPath('classification', 'amountMismatch')->assertJsonPath('reviewStatus', 'reviewed')->assertJsonPath('version', 2);
        $this->getJson('/conferencias/'.$this->conferenceId)->assertOk()->assertJsonPath('run.id', $run['id'])->assertJsonPath('progress.divergences', 3)->assertJsonPath('progress.reviewed', 1);
        $this->getJson('/conferencias')->assertOk()->assertJsonFragment(['progress' => ['divergences' => 3, 'reviewed' => 1]]);
        $this->getJson('/execucoes/'.$run['id'].'/resultados?classification=amountMismatch')->assertOk()->assertJsonPath('total', 1);
        $this->get('/execucoes/'.$run['id'].'/exportar?classification=matched')->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertSame(4, DB::table('results')->count());
    }
}
