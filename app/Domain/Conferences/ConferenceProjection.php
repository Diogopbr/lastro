<?php

namespace App\Domain\Conferences;

use App\Domain\Imports\SourceProjection;
use App\Domain\Reconciliation\RunProjection;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class ConferenceProjection
{
    /** @return array<string, mixed> */
    public static function fromRow(object $row): array
    {
        $sources = ['orders' => null, 'payouts' => null];
        foreach (DB::table('sources')->where('workspace_id', $row->workspace_id)->where('conference_id', $row->id)->get() as $source) {
            $sources[$source->kind] = (new SourceProjection($source))->toArray();
        }
        $run = DB::table('runs')->where('workspace_id', $row->workspace_id)->where('conference_id', $row->id)->first();
        $projection = $run === null ? null : (new RunProjection($run))->toArray();

        return [
            'id' => $row->id, 'code' => $row->code, 'name' => $row->name,
            'startDate' => $row->start_date, 'endDate' => $row->end_date, 'version' => $row->version,
            'createdAt' => CarbonImmutable::parse($row->created_at)->utc()->toIso8601String(),
            'updatedAt' => CarbonImmutable::parse($row->updated_at)->utc()->toIso8601String(),
            'sources' => $sources,
            'run' => $projection === null ? null : ['id' => $projection['id'], 'createdAt' => $projection['createdAt']],
            'progress' => $projection === null ? null : ['divergences' => $projection['summary']['divergences'], 'reviewed' => $projection['summary']['reviewed']],
        ];
    }
}
