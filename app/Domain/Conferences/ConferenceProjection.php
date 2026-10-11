<?php

namespace App\Domain\Conferences;

use Carbon\CarbonImmutable;

final class ConferenceProjection
{
    /** @return array<string, mixed> */
    public static function fromRow(object $row): array
    {
        return [
            'id' => $row->id, 'code' => $row->code, 'name' => $row->name,
            'startDate' => $row->start_date, 'endDate' => $row->end_date, 'version' => $row->version,
            'createdAt' => CarbonImmutable::parse($row->created_at)->utc()->toIso8601String(),
            'updatedAt' => CarbonImmutable::parse($row->updated_at)->utc()->toIso8601String(),
            'sources' => ['orders' => null, 'payouts' => null], 'run' => null, 'progress' => null,
        ];
    }
}
