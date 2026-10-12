<?php

namespace App\Domain\Exports;

use App\Domain\Access\CurrentWorkspace;
use App\Domain\Access\WorkspaceTransaction;
use App\Domain\Reconciliation\RunQuery;
use Illuminate\Support\Facades\DB;
use League\Csv\Writer;
use LogicException;
use PDOException;
use Symfony\Component\HttpFoundation\Response;

final class ExportRun
{
    public const HEADER = ['conference_code', 'run_id', 'order_id', 'classification', 'order_gross', 'payout_gross', 'fee', 'net', 'difference', 'review_status', 'review_note', 'reviewed_at'];

    public function __construct(private WorkspaceTransaction $transaction, private RunQuery $query) {}

    public function execute(CurrentWorkspace $scope, string $runId): Response
    {
        if (DB::transactionLevel() !== 0) {
            throw new LogicException('A exportação deve iniciar sua própria transação antes de qualquer consulta.');
        }

        return DB::transaction(function () use ($scope, $runId): Response {
            DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');

            try {
                return $this->transaction->run($scope, function () use ($scope, $runId): Response {
                    $run = $this->query->run($scope, $runId);
                    $conference = DB::table('conferences')->where('workspace_id', $scope->id)->where('id', $run->conference_id)->first();
                    if (preg_match('/\ALT-[0-9]+\z/', $conference->code) !== 1) {
                        throw new LogicException('O código persistido da conferência não corresponde ao formato de exportação.');
                    }
                    $results = DB::table('results')->where('workspace_id', $scope->id)->where('run_id', $runId)->orderBy('order_id')->orderBy('id')->get()->all();
                    $writer = Writer::fromString()->setEscape('')->setEndOfLine("\r\n");
                    $writer->insertOne(self::HEADER);
                    foreach ($this->query->project($results) as $result) {
                        $row = $result->toArray();
                        $event = $row['reviewEvents'][0] ?? null;
                        $writer->insertOne([
                            $conference->code, $runId, SafeCsvCell::text($row['orderId']), $row['classification'],
                            $row['order']['gross'] ?? '', $row['payout']['gross'] ?? '', $row['payout']['fee'] ?? '',
                            $row['payout']['net'] ?? '', $row['difference'] ?? '', $row['reviewStatus'] ?? '',
                            SafeCsvCell::text($event['note'] ?? ''), $event['createdAt'] ?? '',
                        ]);
                    }

                    return new Response($writer->toString(), 200, [
                        'Content-Type' => 'text/csv; charset=UTF-8',
                        'Content-Disposition' => 'attachment; filename="lastro-'.$conference->code.'-'.$runId.'.csv"',
                        'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
                    ]);
                });
            } catch (PDOException $exception) {
                $cause = $exception->getPrevious();
                if ($cause instanceof PDOException && ($cause->getCode() === '40001' || $cause->getCode() === 40001)) {
                    throw $cause;
                }

                throw $exception;
            }
        }, 3);
    }
}
