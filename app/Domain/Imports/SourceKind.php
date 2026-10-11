<?php

namespace App\Domain\Imports;

enum SourceKind: string
{
    case Orders = 'orders';
    case Payouts = 'payouts';

    /** @return list<string> */
    public function headers(): array
    {
        return match ($this) {
            self::Orders => ['order_id', 'ordered_at', 'gross_amount', 'currency'],
            self::Payouts => ['order_id', 'payout_id', 'payout_date', 'gross_amount', 'fee_amount', 'net_amount', 'currency'],
        };
    }
}
