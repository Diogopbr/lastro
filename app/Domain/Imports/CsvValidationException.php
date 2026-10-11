<?php

namespace App\Domain\Imports;

use InvalidArgumentException;

final class CsvValidationException extends InvalidArgumentException
{
    public function __construct(
        public readonly int $recordLine,
        public readonly string $field,
        public readonly string $errorCode,
        string $message,
    ) {
        parent::__construct($message);
    }

    /** @return array{line:int,field:string,code:string,message:string} */
    public function toArray(): array
    {
        return ['line' => $this->recordLine, 'field' => $this->field, 'code' => $this->errorCode, 'message' => $this->getMessage()];
    }
}
