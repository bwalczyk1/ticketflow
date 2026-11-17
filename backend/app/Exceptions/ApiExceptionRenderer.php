<?php

namespace App\Exceptions;

use App\Exceptions\Domain\DomainException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
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

    /**
     * Laravel runs this after prepareException(), which has already turned authorization, missing
     * model and CSRF errors into HTTP exceptions, so http() only maps their status to a code.
     */
    public function __invoke(Throwable $e, Request $request): ?JsonResponse
    {
        if (! self::isApiRequest($request)) {
            return null;
        }

        return match (true) {
            $e instanceof DomainException => $this->domain($e),
            $e instanceof ValidationException => $this->validation($e),
            $e instanceof AuthenticationException => $this->error(401, 'UNAUTHENTICATED', 'Authentication required'),
            $e instanceof QueryException => $this->database($e),
            $e instanceof HttpExceptionInterface => $this->http($e),
            default => $this->error(500, 'INTERNAL_ERROR', 'Internal server error'),
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

    private function domain(DomainException $e): JsonResponse
    {
        $details = array_map(
            fn (array $detail) => ['field' => $detail['field'], 'message' => __($detail['message'])],
            $e->details,
        );

        return $this->error($e->status, $e->errorCode, $e->getMessage(), $details);
    }

    private function validation(ValidationException $e): JsonResponse
    {
        $details = [];

        foreach ($e->errors() as $field => $messages) {
            foreach ($messages as $message) {
                $details[] = ['field' => $field, 'message' => $message];
            }
        }

        return $this->error(400, 'VALIDATION_ERROR', 'Invalid request data', $details);
    }

    private function database(QueryException $e): JsonResponse
    {
        return match (self::driverCode($e)) {
            self::MYSQL_DUPLICATE => $this->error(409, 'UNIQUE_VIOLATION', 'Resource already exists'),
            self::MYSQL_ROW_IS_REFERENCED,
            self::MYSQL_NO_REFERENCED_ROW => $this->error(409, 'FOREIGN_KEY_VIOLATION', 'Resource is referenced by other data'),
            self::MYSQL_CHECK_VIOLATION => $this->error(409, 'CHECK_VIOLATION', 'Operation violates a data constraint'),
            default => $this->error(500, 'INTERNAL_ERROR', 'Internal server error'),
        };
    }

    private function http(HttpExceptionInterface $e): JsonResponse
    {
        $status = $e->getStatusCode();

        return match ($status) {
            403 => $this->error(403, 'FORBIDDEN', 'Insufficient permissions'),
            404 => $this->error(404, 'NOT_FOUND', 'Resource not found'),
            405 => $this->error(405, 'METHOD_NOT_ALLOWED', 'Method not allowed'),
            419 => $this->error(419, 'CSRF_TOKEN_MISMATCH', 'CSRF token mismatch'),
            429 => $this->error(429, 'TOO_MANY_REQUESTS', 'Too many requests'),
            default => $this->error($status, 'HTTP_ERROR', 'Request failed'),
        };
    }

    /**
     * @param  list<array<string, mixed>>  $details
     */
    private function error(int $status, string $code, string $message, array $details = []): JsonResponse
    {
        $error = ['code' => $code, 'message' => $message];

        if ($details !== []) {
            $error['details'] = $details;
        }

        $error['requestId'] = Context::get('requestId');

        return response()->json(['error' => $error], $status);
    }
}
