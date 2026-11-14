<?php

namespace App\Exceptions\Domain;

use RuntimeException;

class DomainException extends RuntimeException
{
    /**
     * The message of a detail is a translation key, so the renderer can answer in the language
     * of the request while the exception itself stays free of the framework.
     *
     * @param  list<array{field: string, message: string}>  $details
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
