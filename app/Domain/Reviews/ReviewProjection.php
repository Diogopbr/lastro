<?php

namespace App\Domain\Reviews;

use Carbon\CarbonImmutable;

final class ReviewProjection
{
    public static function fromRow(object $row): array
    {
        return [
            'id' => $row->id, 'action' => $row->action, 'note' => $row->note, 'actorName' => $row->actor_name,
            'createdAt' => CarbonImmutable::parse($row->created_at)->utc()->toIso8601String(),
        ];
    }
}
