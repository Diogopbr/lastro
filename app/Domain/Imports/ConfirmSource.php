<?php

namespace App\Domain\Imports;

use App\Domain\Access\CurrentWorkspace;
use App\Domain\Access\WorkspaceTransaction;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ConfirmSource
{
    public function __construct(private WorkspaceTransaction $transaction, private CsvImportValidator $validator) {}

    public function execute(CurrentWorkspace $scope, string $conferenceId, SourceKind $kind, string $bytes, string $expectedHash, int $expectedVersion, string $filename): SourceProjection
    {
        return $this->transaction->run($scope, function () use ($scope, $conferenceId, $kind, $bytes, $expectedHash, $expectedVersion, $filename): SourceProjection {
            $conference = DB::table('conferences')->where('workspace_id', $scope->id)->where('id', $conferenceId)->lockForUpdate()->first();
            if ($conference === null) {
                throw new NotFoundHttpException;
            }
            $preview = $this->validator->validate($kind, $bytes, new DateTimeImmutable($conference->start_date), new DateTimeImmutable($conference->end_date));
            if (! $preview->valid) {
                throw ValidationException::withMessages(['file' => array_column($preview->errors, 'message')]);
            }
            if (! hash_equals($preview->normalizedHash, $expectedHash)) {
                throw new HttpException(409, 'O arquivo mudou após a prévia. Calcule uma nova prévia.');
            }
            $existing = DB::table('sources')->where('workspace_id', $scope->id)->where('conference_id', $conferenceId)->where('kind', $kind->value)->first();
            if ($existing !== null) {
                if (! hash_equals($existing->normalized_hash, $preview->normalizedHash)) {
                    throw new HttpException(409, 'Esta fonte já foi confirmada com outro conteúdo.');
                }

                return new SourceProjection($existing);
            }
            if ($conference->version !== $expectedVersion) {
                throw new HttpException(409, 'A conferência mudou. Atualize os dados e refaça a prévia.');
            }
            $sourceId = (string) Str::uuid();
            DB::table('sources')->insert([
                'id' => $sourceId, 'workspace_id' => $scope->id, 'conference_id' => $conferenceId,
                'kind' => $kind->value, 'normalized_hash' => $preview->normalizedHash,
                'display_filename' => self::filename($filename), 'row_count' => $preview->rowCount,
                'gross_total_cents' => $preview->grossTotalCents, 'confirmed_at' => DB::raw('clock_timestamp()'),
            ]);
            DB::table('source_rows')->insert(array_map(fn (ImportRow $row): array => [
                'id' => (string) Str::uuid(), 'workspace_id' => $scope->id, 'conference_id' => $conferenceId,
                'source_id' => $sourceId, 'kind' => $kind->value, 'line' => $row->line,
                'order_id' => $row->orderId, 'payout_id' => $row->payoutId, 'business_date' => $row->businessDate,
                'gross_cents' => $row->grossCents, 'fee_cents' => $row->feeCents, 'net_cents' => $row->netCents,
            ], $preview->rows));
            DB::table('source_events')->insert([
                'id' => (string) Str::uuid(), 'workspace_id' => $scope->id, 'source_id' => $sourceId,
                'actor_id' => $scope->userId, 'event_type' => 'confirmed', 'created_at' => DB::raw('clock_timestamp()'),
            ]);
            DB::table('conferences')->where('id', $conferenceId)->update([
                'period_locked' => true, 'version' => $conference->version + 1, 'updated_at' => DB::raw('clock_timestamp()'),
            ]);

            return new SourceProjection(DB::table('sources')->where('id', $sourceId)->first(), true);
        });
    }

    private static function filename(string $filename): string
    {
        $basename = basename(str_replace('\\', '/', $filename));
        $basename = preg_replace('/[\p{Cc}\p{Cf}\p{Zl}\p{Zp}]/u', '', $basename) ?? '';

        return mb_substr(trim($basename), 0, 200) ?: 'fonte.csv';
    }
}
