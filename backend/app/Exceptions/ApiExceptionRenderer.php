<?php

namespace App\Exceptions;

use App\Exceptions\Domain\DomainException;
use App\Exceptions\Domain\EmailTaken;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class ApiExceptionRenderer
{
    private const MYSQL_DUPLICATE = 1062;

    private const MYSQL_ROW_IS_REFERENCED = 1451;

    private const MYSQL_NO_REFERENCED_ROW = 1452;

    private const MYSQL_CHECK_VIOLATION = 3819;

    private const MYSQL_CONSTRAINT_ERRORS = [
        self::MYSQL_DUPLICATE,
        self::MYSQL_ROW_IS_REFERENCED,
        self::MYSQL_NO_REFERENCED_ROW,
        self::MYSQL_CHECK_VIOLATION,
    ];

    /**
     * The message of a QueryException holds the bindings, so a client error must not reach the logs.
     * Outside the API nothing answers the client, so jobs and commands keep reporting these errors.
     */
    public static function isClientConstraintError(Throwable $e, Request $request): bool
    {
        return $e instanceof QueryException
            && self::isApiRequest($request)
            && in_array(self::driverCode($e), self::MYSQL_CONSTRAINT_ERRORS, true);
    }

    public function __invoke(Throwable $e, Request $request): ?JsonResponse
    {
        if (! self::isApiRequest($request)) {
            return null;
        }

        return match (true) {
            $e instanceof DomainException => $this->domain($request, $e),
            $e instanceof ValidationException => $this->validation($request, $e),
            $e instanceof AuthenticationException => $this->error($request, 401, 'UNAUTHENTICATED', 'Authentication required'),
            $e instanceof AuthorizationException,
            $e instanceof AccessDeniedHttpException => $this->error($request, 403, 'FORBIDDEN', 'Insufficient permissions'),
            $e instanceof ModelNotFoundException,
            $e instanceof NotFoundHttpException => $this->error($request, 404, 'NOT_FOUND', 'Resource not found'),
            $e instanceof TokenMismatchException => $this->error($request, 419, 'CSRF_TOKEN_MISMATCH', 'CSRF token mismatch'),
            $e instanceof QueryException => $this->database($request, $e),
            $e instanceof HttpExceptionInterface => $this->http($request, $e),
            default => $this->error($request, 500, 'INTERNAL_ERROR', 'Internal server error'),
        };
    }

    private static function isApiRequest(Request $request): bool
    {
        return $request->is('api/*', 'sanctum/*');
    }

    private static function driverCode(QueryException $e): int
    {
        return (int) ($e->errorInfo[1] ?? 0);
    }

    private function domain(Request $request, DomainException $e): JsonResponse
    {
        $details = array_map(
            fn (array $detail) => ['field' => $detail['field'], 'message' => __($detail['message'])],
            $e->details,
        );

        return $this->error($request, $e->status, $e->errorCode, $e->getMessage(), $details);
    }

    private function validation(Request $request, ValidationException $e): JsonResponse
    {
        $details = [];

        foreach ($e->errors() as $field => $messages) {
            foreach ($messages as $message) {
                $details[] = ['field' => $field, 'message' => $message];
            }
        }

        return $this->error($request, 400, 'VALIDATION_ERROR', 'Invalid request data', $details);
    }

    private function database(Request $request, QueryException $e): JsonResponse
    {
        return match (self::driverCode($e)) {
            self::MYSQL_DUPLICATE => str_contains($e->getMessage(), 'users_email_unique')
                ? $this->domain($request, new EmailTaken)
                : $this->error($request, 409, 'UNIQUE_VIOLATION', 'Resource already exists'),
            self::MYSQL_ROW_IS_REFERENCED,
            self::MYSQL_NO_REFERENCED_ROW => $this->error($request, 409, 'FOREIGN_KEY_VIOLATION', 'Resource is referenced by other data'),
            self::MYSQL_CHECK_VIOLATION => $this->error($request, 409, 'CHECK_VIOLATION', 'Operation violates a data constraint'),
            default => $this->error($request, 500, 'INTERNAL_ERROR', 'Internal server error'),
        };
    }

    private function http(Request $request, HttpExceptionInterface $e): JsonResponse
    {
        $status = $e->getStatusCode();

        if ($status === 419) {
            return $this->error($request, 419, 'CSRF_TOKEN_MISMATCH', 'CSRF token mismatch');
        }

        return match ($status) {
            405 => $this->error($request, 405, 'METHOD_NOT_ALLOWED', 'Method not allowed'),
            429 => $this->error($request, 429, 'TOO_MANY_REQUESTS', 'Too many requests'),
            default => $this->error($request, $status, 'HTTP_ERROR', 'Request failed'),
        };
    }

    /**
     * @param  list<array<string, mixed>>  $details
     */
    private function error(Request $request, int $status, string $code, string $message, array $details = []): JsonResponse
    {
        $error = ['code' => $code, 'message' => $message];

        if ($details !== []) {
            $error['details'] = $details;
        }

        $error['requestId'] = $request->attributes->get('requestId');

        return response()->json(['error' => $error], $status);
    }
}
