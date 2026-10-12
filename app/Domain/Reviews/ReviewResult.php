<?php

namespace App\Domain\Reviews;

use App\Domain\Access\CurrentWorkspace;
use App\Domain\Access\WorkspaceTransaction;
use App\Domain\Reconciliation\ResultProjection;
use App\Domain\Reconciliation\RunQuery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ReviewResult
{
    public function __construct(private WorkspaceTransaction $transaction, private RunQuery $query) {}

    public function execute(CurrentWorkspace $scope, string $resultId, int $expectedVersion, string $action, string $note): ResultProjection
    {
        return $this->transaction->run($scope, function () use ($scope, $resultId, $expectedVersion, $action, $note): ResultProjection {
            $result = DB::table('results')->where('workspace_id', $scope->id)->where('id', $resultId)->lockForUpdate()->first();
            if ($result === null) {
                throw new NotFoundHttpException;
            }
            if ($result->version !== $expectedVersion) {
                throw new HttpException(409, 'O resultado mudou. Atualize os dados antes de salvar a revisão.');
            }
            if ($result->classification === 'matched') {
                throw ValidationException::withMessages(['action' => 'Resultados conciliados não possuem revisão.']);
            }
            if (! in_array($action, ['reviewed', 'reopened'], true)) {
                throw ValidationException::withMessages(['action' => 'Escolha revisar ou reabrir o resultado.']);
            }
            if (($action === 'reviewed' && $result->review_status !== 'pending') || ($action === 'reopened' && $result->review_status !== 'reviewed')) {
                throw ValidationException::withMessages(['action' => 'Esta ação não é válida para o estado atual da revisão.']);
            }
            $note = trim($note, ' ');
            if (! mb_check_encoding($note, 'UTF-8') || trim($note) === '' || mb_strlen($note, 'UTF-8') < 3 || mb_strlen($note, 'UTF-8') > 1000) {
                throw ValidationException::withMessages(['note' => 'Informe uma justificativa válida com 3 a 1000 caracteres.']);
            }
            $actor = DB::table('users')->where('workspace_id', $scope->id)->where('id', $scope->userId)->first();
            if ($actor === null) {
                throw new NotFoundHttpException;
            }
            DB::table('results')->where('id', $resultId)->update(['version' => $expectedVersion + 1, 'review_status' => $action === 'reviewed' ? 'reviewed' : 'pending']);
            DB::table('review_events')->insert([
                'id' => (string) Str::uuid(), 'workspace_id' => $scope->id, 'result_id' => $resultId,
                'actor_id' => $scope->userId, 'actor_name' => $actor->name, 'action' => $action, 'note' => $note,
                'result_version' => $expectedVersion + 1, 'created_at' => DB::raw('clock_timestamp()'),
            ]);

            return $this->query->project([DB::table('results')->where('id', $resultId)->first()])[0];
        });
    }
}
