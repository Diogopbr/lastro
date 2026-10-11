<?php

namespace App\Domain\Conferences;

use App\Domain\Access\CurrentWorkspace;
use App\Domain\Access\WorkspaceTransaction;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class UpdateConference
{
    public function __construct(private WorkspaceTransaction $transaction) {}

    /** @return array<string, mixed> */
    public function execute(CurrentWorkspace $scope, string $id, string $name, string $startDate, string $endDate, int $expectedVersion): array
    {
        $input = ConferenceInput::validate($name, $startDate, $endDate);

        return $this->transaction->run($scope, function () use ($scope, $id, $input, $expectedVersion): array {
            $row = DB::table('conferences')->where('workspace_id', $scope->id)->where('id', $id)->lockForUpdate()->first();
            if ($row === null) {
                throw new NotFoundHttpException;
            }
            if ($row->version !== $expectedVersion) {
                throw new HttpException(409, 'A conferência mudou. Atualize os dados antes de salvar.');
            }
            if ($row->period_locked && ($row->start_date !== $input['startDate'] || $row->end_date !== $input['endDate'])) {
                throw new HttpException(409, 'O período está fechado após a primeira importação.');
            }
            DB::table('conferences')->where('id', $id)->update([
                'name' => $input['name'], 'start_date' => $input['startDate'], 'end_date' => $input['endDate'],
                'version' => $row->version + 1, 'updated_at' => DB::raw('clock_timestamp()'),
            ]);

            return ConferenceProjection::fromRow(DB::table('conferences')->where('id', $id)->first());
        });
    }
}
