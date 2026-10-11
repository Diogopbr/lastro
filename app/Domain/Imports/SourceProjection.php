<?php

namespace App\Domain\Imports;

use Carbon\CarbonImmutable;
use JsonSerializable;

final readonly class SourceProjection implements JsonSerializable
{
    public function __construct(public object $row, public bool $created = false) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->row->id, 'kind' => $this->row->kind,
            'normalizedHash' => $this->row->normalized_hash, 'displayFilename' => $this->row->display_filename,
            'rowCount' => $this->row->row_count, 'grossTotal' => Money::format((int) $this->row->gross_total_cents),
            'confirmedAt' => CarbonImmutable::parse($this->row->confirmed_at)->utc()->toIso8601String(),
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
