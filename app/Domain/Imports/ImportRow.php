<?php

namespace App\Domain\Imports;

use JsonSerializable;

final readonly class ImportRow implements JsonSerializable
{
    public function __construct(
        public int $line,
        public string $orderId,
        public ?string $payoutId,
        public string $businessDate,
        public int $grossCents,
        public ?int $feeCents,
        public ?int $netCents,
    ) {}

    /** @return array{line:int,orderId:string,payoutId:?string,businessDate:string,gross:string,fee:?string,net:?string} */
    public function toArray(): array
    {
        return [
            'line' => $this->line,
            'orderId' => $this->orderId,
            'payoutId' => $this->payoutId,
            'businessDate' => $this->businessDate,
            'gross' => Money::format($this->grossCents),
            'fee' => $this->feeCents === null ? null : Money::format($this->feeCents),
            'net' => $this->netCents === null ? null : Money::format($this->netCents),
        ];
    }

    /** @return array{orderId:string,payoutId:?string,businessDate:string,grossCents:int,feeCents:?int,netCents:?int,currency:string} */
    public function canonical(): array
    {
        return [
            'orderId' => $this->orderId,
            'payoutId' => $this->payoutId,
            'businessDate' => $this->businessDate,
            'grossCents' => $this->grossCents,
            'feeCents' => $this->feeCents,
            'netCents' => $this->netCents,
            'currency' => 'BRL',
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
