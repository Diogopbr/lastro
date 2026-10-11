<?php

namespace App\Domain\Imports;

use InvalidArgumentException;

final class Money
{
    public const MAX_CENTS = 100000000;

    public static function parse(string $text): int
    {
        if (preg_match('/\A[0-9]+\.[0-9]{2}\z/', $text) !== 1) {
            throw new InvalidArgumentException('Informe dinheiro com ponto e exatamente duas casas decimais, sem sinal.');
        }

        [$whole, $fraction] = explode('.', $text);
        $whole = ltrim($whole, '0');
        if (strlen($whole) > 7) {
            throw new InvalidArgumentException('O valor excede R$ 1.000.000,00.');
        }

        $cents = (int) $whole * 100 + (int) $fraction;
        if ($cents > self::MAX_CENTS) {
            throw new InvalidArgumentException('O valor excede R$ 1.000.000,00.');
        }

        return $cents;
    }

    public static function format(int $cents): string
    {
        $digits = (string) $cents;
        $negative = str_starts_with($digits, '-');
        $digits = str_pad($negative ? substr($digits, 1) : $digits, 3, '0', STR_PAD_LEFT);

        return ($negative ? '-' : '').substr($digits, 0, -2).'.'.substr($digits, -2);
    }
}
