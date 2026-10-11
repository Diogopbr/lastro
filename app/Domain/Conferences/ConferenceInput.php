<?php

namespace App\Domain\Conferences;

use Illuminate\Support\Facades\Validator;

final class ConferenceInput
{
    /** @return array{name: string, startDate: string, endDate: string} */
    public static function validate(string $name, string $startDate, string $endDate): array
    {
        return Validator::make(['name' => trim($name), 'startDate' => $startDate, 'endDate' => $endDate], [
            'name' => ['required', 'string', 'min:3', 'max:120'],
            'startDate' => ['required', 'date_format:Y-m-d'],
            'endDate' => ['required', 'date_format:Y-m-d', 'after_or_equal:startDate'],
        ])->validate();
    }
}
