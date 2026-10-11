<?php

namespace App\Domain\Demo;

use App\Domain\Imports\SourceKind;

final class ConferenceExamples
{
    public static function template(SourceKind $kind): string
    {
        return implode(',', $kind->headers())."\n";
    }

    public static function csv(SourceKind $kind, string $scenario = 'marketplace'): string
    {
        $rows = match ($scenario) {
            'marketplace' => $kind === SourceKind::Orders
                ? "0001,2026-05-01,100.00,BRL\n0002,2026-05-02,200.00,BRL\n0003,2026-05-03,300.00,BRL\n"
                : "0001,RP-001,2026-05-04,100.00,5.00,95.00,BRL\n0002,RP-001,2026-05-04,190.00,5.00,185.00,BRL\n0004,RP-002,2026-05-05,400.00,10.00,390.00,BRL\n",
            'store' => $kind === SourceKind::Orders
                ? "LOJA-01,2026-05-06,149.90,BRL\nLOJA-02,2026-05-07,89.50,BRL\n"
                : "LOJA-01,RP-LOJA,2026-05-08,149.90,4.90,145.00,BRL\nLOJA-02,RP-LOJA,2026-05-08,89.50,2.50,87.00,BRL\n",
            'loose' => $kind === SourceKind::Orders
                ? "AV-01,2026-05-11,75.00,BRL\n"
                : "AV-02,RP-AV,2026-05-12,45.00,0.00,45.00,BRL\n",
            default => throw new \InvalidArgumentException('Exemplo desconhecido.'),
        };

        return self::template($kind).$rows;
    }
}
