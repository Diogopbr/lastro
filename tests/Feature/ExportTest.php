<?php

namespace Tests\Feature;

use App\Domain\Access\CurrentWorkspace;
use App\Domain\Access\DemoWorkspace;
use App\Domain\Exports\ExportRun;
use App\Domain\Imports\SourceKind;
use App\Domain\Reconciliation\ReconcileConference;
use App\Domain\Reviews\ReviewResult;
use App\Http\Controllers\Exports\ExportController;
use App\Http\Middleware\ResolveWorkspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use League\Csv\Reader;
use LogicException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\Fixtures\ReconciliationDatabase;

class ExportTest extends ReconciliationDatabase
{
    public function test_export_has_fixed_header_all_rows_latest_note_and_blank_absences(): void
    {
        $this->importPair();
        $run = app(ReconcileConference::class)->execute($this->scope, $this->conferenceId);
        $id = DB::table('results')->where('classification', 'amountMismatch')->value('id');
        app(ReviewResult::class)->execute($this->scope, $id, 1, 'reviewed', 'Nota anterior.');
        app(ReviewResult::class)->execute($this->scope, $id, 2, 'reopened', "\t=HYPERLINK(\"x\")\r\nNova análise.");
        DB::table('conferences')->where('id', $this->conferenceId)->update(['name' => "Nome\r\nX-Injected: yes"]);
        $response = app(ExportRun::class)->execute($this->scope, $run->row->id);
        $this->assertSame(0, DB::transactionLevel());
        $this->assertSame('text/csv; charset=UTF-8', $response->headers->get('Content-Type'));
        $code = DB::table('conferences')->where('id', $this->conferenceId)->value('code');
        $this->assertSame('attachment; filename="lastro-'.$code.'-'.$run->row->id.'.csv"', $response->headers->get('Content-Disposition'));
        $reader = Reader::fromString($response->getContent())->setEscape('')->setHeaderOffset(0);
        $this->assertSame(['conference_code', 'run_id', 'order_id', 'classification', 'order_gross', 'payout_gross', 'fee', 'net', 'difference', 'review_status', 'review_note', 'reviewed_at'], $reader->getHeader());
        $rows = iterator_to_array($reader->getRecords(), false);
        $this->assertCount(4, $rows);
        $byId = array_column($rows, null, 'order_id');
        $this->assertSame('-10.00', $byId['0002']['difference']);
        $this->assertSame("'\t=HYPERLINK(\"x\")\r\nNova análise.", $byId['0002']['review_note']);
        $this->assertSame('pending', $byId['0002']['review_status']);
        $this->assertNotSame('', $byId['0002']['reviewed_at']);
        $this->assertSame('', $byId['0003']['payout_gross']);
        $this->assertSame('', $byId['0003']['fee']);
        $this->assertSame('', $byId['0003']['difference']);
        $this->assertSame('', $byId['0004']['order_gross']);
        $this->assertSame('', $byId['0001']['review_status']);
        $this->assertSame('', $byId['0001']['review_note']);
    }

    public function test_formula_identifiers_are_guarded_but_money_is_not(): void
    {
        $this->import(SourceKind::Orders, "order_id,ordered_at,gross_amount,currency\n=1+1,2026-05-01,10.00,BRL\n\"a,\"\"b\"\"\",2026-05-01,1.00,BRL");
        $this->import(SourceKind::Payouts, "order_id,payout_id,payout_date,gross_amount,fee_amount,net_amount,currency\n=1+1,p,2026-05-01,9.00,0.00,9.00,BRL");
        $run = app(ReconcileConference::class)->execute($this->scope, $this->conferenceId);
        $rows = iterator_to_array(Reader::fromString(app(ExportRun::class)->execute($this->scope, $run->row->id)->getContent())->setEscape('')->setHeaderOffset(0)->getRecords(), false);
        $this->assertSame("'=1+1", $rows[0]['order_id']);
        $this->assertSame('-1.00', $rows[0]['difference']);
        $this->assertSame('a,"b"', $rows[1]['order_id']);
    }

    public function test_foreign_run_and_new_export_after_revocation_are_not_found(): void
    {
        $this->importPair();
        $run = app(ReconcileConference::class)->execute($this->scope, $this->conferenceId);
        $other = app(DemoWorkspace::class)->start(bin2hex(random_bytes(32)));
        try {
            app(ExportRun::class)->execute(new CurrentWorkspace($other->workspace_id, null, 'demo', $other->id), $run->row->id);
            $this->fail('Foreign export accepted.');
        } catch (NotFoundHttpException) {
            $this->assertTrue(true);
        }
        $materialized = app(ExportRun::class)->execute($this->scope, $run->row->id)->getContent();
        app(DemoWorkspace::class)->reset($this->scope, $this->token, '127.0.0.83');
        $this->assertStringContainsString('0002', $materialized);
        $this->expectException(NotFoundHttpException::class);
        app(ExportRun::class)->execute($this->scope, $run->row->id);
    }

    public function test_export_rejects_ambient_transaction_instead_of_changing_isolation_after_a_query(): void
    {
        $this->importPair();
        $run = app(ReconcileConference::class)->execute($this->scope, $this->conferenceId);
        DB::beginTransaction();
        DB::selectOne('SELECT 1');
        $this->expectException(LogicException::class);
        app(ExportRun::class)->execute($this->scope, $run->row->id);
    }

    public function test_controller_exports_all_rows_even_with_screen_filters(): void
    {
        Route::get('/test/execucoes/{id}/exportar', ExportController::class)->middleware(['web', ResolveWorkspace::class]);
        $this->importPair();
        $run = app(ReconcileConference::class)->execute($this->scope, $this->conferenceId);
        $response = $this->get('/test/execucoes/'.$run->row->id.'/exportar?classification=matched&pageSize=1')->assertOk();
        $rows = iterator_to_array(Reader::fromString($response->getContent())->setEscape('')->setHeaderOffset(0)->getRecords(), false);
        $this->assertCount(4, $rows);
    }
}
