<?php

namespace App\Docs;

use Dedoc\Scramble\Extensions\ExceptionToResponseExtension;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\RecordsNotFoundException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Replaces the default Scramble responses, which describe plain Laravel errors
 * (for example 422), with what ApiExceptionRenderer really answers.
 */
class FrameworkExceptionToResponseExtension extends ExceptionToResponseExtension
{
    private const ERRORS = [
        ValidationException::class => [400, 'VALIDATION_ERROR', 'Invalid request data'],
        AuthenticationException::class => [401, 'UNAUTHENTICATED', 'Authentication required'],
        AuthorizationException::class => [403, 'FORBIDDEN', 'Insufficient permissions'],
        RecordsNotFoundException::class => [404, 'NOT_FOUND', 'Resource not found'],
        NotFoundHttpException::class => [404, 'NOT_FOUND', 'Resource not found'],
    ];

    public function shouldHandle(Type $type): bool
    {
        return $this->errorFor($type) !== null;
    }

    public function toResponse(Type $type): Response
    {
        return ErrorResponse::make(...$this->errorFor($type));
    }

    /**
     * @return array{0: int, 1: string, 2: string}|null
     */
    private function errorFor(Type $type): ?array
    {
        if (! $type instanceof ObjectType) {
            return null;
        }

        foreach (self::ERRORS as $exception => $error) {
            if ($type->isInstanceOf($exception)) {
                return $error;
            }
        }

        return null;
    }
}
