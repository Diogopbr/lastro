<?php

namespace App\Domain\Access;

final readonly class CurrentWorkspace
{
    public function __construct(
        public string $id,
        public ?string $expiresAt,
        public string $kind,
        public string $userId,
    ) {}
}
