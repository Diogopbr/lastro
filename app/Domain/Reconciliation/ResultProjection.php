<?php

namespace App\Domain\Reconciliation;

use App\Domain\Imports\ImportRow;
use App\Domain\Imports\Money;
use JsonSerializable;

final readonly class ResultProjection implements JsonSerializable
{
    public function __construct(
        public object $row,
        private ?object $order,
        private ?object $payout,
        private array $reviewEvents = [],
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->row->id, 'orderId' => $this->row->order_id, 'classification' => $this->row->classification,
            'difference' => $this->row->difference_cents === null ? null : Money::format((int) $this->row->difference_cents),
            'reviewStatus' => $this->row->review_status, 'version' => $this->row->version,
            'order' => $this->source($this->order), 'payout' => $this->source($this->payout), 'reviewEvents' => $this->reviewEvents,
        ];
    }

    private function source(?object $row): ?array
    {
        if ($row === null) {
            return null;
        }
        $import = new ImportRow($row->line, $row->order_id, $row->payout_id, $row->business_date, (int) $row->gross_cents,
            $row->fee_cents === null ? null : (int) $row->fee_cents, $row->net_cents === null ? null : (int) $row->net_cents);

        return [...$import->toArray(), 'sourceId' => $row->source_id, 'normalizedHash' => $row->normalized_hash, 'displayFilename' => $row->display_filename];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
