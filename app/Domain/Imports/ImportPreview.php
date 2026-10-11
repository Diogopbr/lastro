<?php

namespace App\Domain\Imports;

use JsonSerializable;

final readonly class ImportPreview implements JsonSerializable
{
    public string $grossTotal;

    /**
     * @param  list<ImportRow>  $rows
     * @param  list<array{line:int,field:string,code:string,message:string}>  $errors
     */
    public function __construct(
        public bool $valid,
        public ?string $normalizedHash,
        public int $rowCount,
        public int $grossTotalCents,
        public array $rows,
        public array $errors,
    ) {
        $this->grossTotal = Money::format($grossTotalCents);
    }

    /** @return array{valid:bool,normalizedHash:?string,rowCount:int,grossTotal:string,rows:list<array{line:int,orderId:string,payoutId:?string,businessDate:string,gross:string,fee:?string,net:?string}>,errors:list<array{line:int,field:string,code:string,message:string}>} */
    public function toArray(): array
    {
        return [
            'valid' => $this->valid,
            'normalizedHash' => $this->normalizedHash,
            'rowCount' => $this->rowCount,
            'grossTotal' => $this->grossTotal,
            'rows' => array_map(fn (ImportRow $row): array => $row->toArray(), $this->rows),
            'errors' => $this->errors,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
