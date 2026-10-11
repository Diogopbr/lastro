<?php

namespace App\Domain\Conferences;

use App\Domain\Access\CurrentWorkspace;
use App\Domain\Access\WorkspaceTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class CreateConference
{
    public function __construct(private WorkspaceTransaction $transaction) {}

    /** @return array<string, mixed> */
    public function execute(CurrentWorkspace $scope, string $name, string $startDate, string $endDate): array
    {
        $input = ConferenceInput::validate($name, $startDate, $endDate);

        return $this->transaction->run($scope, function () use ($scope, $input): array {
            if ($scope->kind === 'demo' && DB::table('conferences')->where('workspace_id', $scope->id)->count() >= 5) {
                throw new HttpException(409, 'O limite de cinco conferências foi atingido.');
            }
            $number = DB::table('workspaces')->where('id', $scope->id)->value('next_conference_number');
            $id = (string) Str::uuid();
            DB::table('conferences')->insert([
                'id' => $id, 'workspace_id' => $scope->id,
                'code' => 'LT-'.str_pad((string) $number, 4, '0', STR_PAD_LEFT),
                'name' => $input['name'], 'start_date' => $input['startDate'], 'end_date' => $input['endDate'],
                'created_at' => DB::raw('clock_timestamp()'), 'updated_at' => DB::raw('clock_timestamp()'),
            ]);
            DB::table('workspaces')->where('id', $scope->id)->increment('next_conference_number');

            return ConferenceProjection::fromRow(DB::table('conferences')->where('id', $id)->first());
        });
    }
}
