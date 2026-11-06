<?php

namespace App\Docs;

use App\Exceptions\Domain\DomainException;
use Dedoc\Scramble\Extensions\ExceptionToResponseExtension;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;

/**
 * Every domain exception has default constructor arguments, so its status and code can be read
 * from a fresh instance and the documentation cannot drift from the class.
 */
class DomainExceptionToResponseExtension extends ExceptionToResponseExtension
{
    public function shouldHandle(Type $type): bool
    {
        return $type instanceof ObjectType
            && $type->isInstanceOf(DomainException::class)
            && $type->name !== DomainException::class;
    }

    public function toResponse(Type $type): Response
    {
        $exception = new $type->name;

        return ErrorResponse::make($exception->status, $exception->errorCode, $exception->getMessage());
    }
}
