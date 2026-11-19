<?php

namespace App\Docs;

use Dedoc\Scramble\Support\Generator\Combined\AllOf;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Illuminate\Support\Str;

/**
 * The body built by ApiExceptionRenderer, { error: { code, message, details?, requestId } }, is
 * described once as ErrorResponse. Every error code gets one schema, such as EmailTakenError,
 * that adds its code to the envelope, and responses only reference that schema.
 */
class ErrorResponse
{
    public const SCHEMA = 'ErrorResponse';

    public const DETAIL_SCHEMA = 'ErrorDetail';

    /**
     * Codes documented while the operations were generated, with an example message.
     *
     * @var array<string, string>
     */
    private static array $codes = [];

    public static function make(int $status, string $code, string $message): Response
    {
        self::$codes[$code] ??= $message;

        return Response::make($status)
            ->setDescription("`{$code}`: {$message}")
            ->setContent('application/json', Schema::fromType(self::reference(self::schemaName($code))));
    }

    /**
     * Runs after all operations, so every code used by a response is known.
     */
    public static function register(OpenApi $openApi): void
    {
        $detail = (new ObjectType)
            ->addProperty('field', new StringType)
            ->addProperty('message', new StringType)
            ->setRequired(['field', 'message']);

        $error = (new ObjectType)
            ->addProperty('code', new StringType)
            ->addProperty('message', new StringType)
            ->addProperty('details', (new ArrayType)->setItems(self::reference(self::DETAIL_SCHEMA)))
            ->addProperty('requestId', new StringType)
            ->setRequired(['code', 'message', 'requestId']);

        $body = (new ObjectType)
            ->addProperty('error', $error)
            ->setRequired(['error']);

        $openApi->components->addSchema(self::DETAIL_SCHEMA, Schema::fromType($detail));
        $openApi->components->addSchema(self::SCHEMA, Schema::fromType($body));

        foreach (self::$codes as $code => $message) {
            $openApi->components->addSchema(self::schemaName($code), Schema::fromType(self::withCode($code, $message)));
        }
    }

    private static function withCode(string $code, string $message): AllOf
    {
        $error = (new ObjectType)
            ->addProperty('code', (new StringType)->enum([$code]))
            ->addProperty('message', (new StringType)->example($message));

        return (new AllOf)->setItems([
            self::reference(self::SCHEMA),
            (new ObjectType)->addProperty('error', $error),
        ]);
    }

    /**
     * EMAIL_TAKEN becomes EmailTakenError and INTERNAL_ERROR becomes InternalError.
     */
    private static function schemaName(string $code): string
    {
        return Str::studly(strtolower(Str::chopEnd($code, '_ERROR'))).'Error';
    }

    private static function reference(string $schema): Reference
    {
        return Reference::fromUri("#/components/schemas/{$schema}");
    }
}
