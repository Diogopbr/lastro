<?php

namespace App\Domain\Exports;

use InvalidArgumentException;

final class SafeCsvCell
{
    public static function text(string $value): string
    {
        if (! mb_check_encoding($value, 'UTF-8')) {
            throw new InvalidArgumentException('A célula textual deve usar UTF-8 válido.');
        }

        return preg_match('/\A[\p{Cc}\p{Cf}\p{Z}]*[=+\-@]/u', $value) === 1 ? "'".$value : $value;
    }
}
