<?php

namespace App\Exceptions\Domain;

use RuntimeException;

class DomainException extends RuntimeException
{
    /**
     * @param  list<array<string, mixed>>  $details
     */
    public function __construct(
        string $message,
        public readonly string $errorCode,
        public readonly int $status = 409,
        public readonly array $details = [],
    ) {
        parent::__construct($message);
    }
}
