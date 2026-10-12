<?php

namespace App\Domain\Reconciliation;

use App\Domain\Imports\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use JsonSerializable;

final readonly class RunProjection implements JsonSerializable
{
    private array $projection;

    public function __construct(public object $row, public bool $created = false)
    {
        $counts = ['matched' => 0, 'amountMismatch' => 0, 'missingPayout' => 0, 'missingOrder' => 0];
        $reviewed = 0;
        foreach (DB::table('results')->where('workspace_id', $this->row->workspace_id)->where('run_id', $this->row->id)->get(['classification', 'review_status']) as $result) {
            $counts[$result->classification]++;
            if ($result->review_status === 'reviewed') {
                $reviewed++;
            }
        }
        $orderGross = (int) DB::table('source_rows')->where('source_id', $this->row->orders_source_id)->sum('gross_cents');
        $payouts = DB::table('source_rows')->where('source_id', $this->row->payouts_source_id)
            ->selectRaw('SUM(gross_cents) AS gross, SUM(fee_cents) AS fees, SUM(net_cents) AS net')->first();

        $this->projection = [
            'id' => $this->row->id, 'conferenceId' => $this->row->conference_id,
            'createdAt' => CarbonImmutable::parse($this->row->created_at)->utc()->toIso8601String(),
            'summary' => [
                'counts' => $counts, 'orderGross' => Money::format($orderGross),
                'payoutGross' => Money::format((int) $payouts->gross), 'fees' => Money::format((int) $payouts->fees),
                'net' => Money::format((int) $payouts->net),
                'divergences' => array_sum($counts) - $counts['matched'], 'reviewed' => $reviewed,
            ],
        ];
    }

    public function toArray(): array
    {
        return $this->projection;
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
