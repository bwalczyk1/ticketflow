<?php

namespace App\Docs;

use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;

class ErrorResponse
{
    /**
     * The body built by ApiExceptionRenderer: { error: { code, message, details?, requestId } }.
     */
    public static function make(int $status, string $code, string $message): Response
    {
        $error = (new ObjectType)
            ->addProperty('code', (new StringType)->enum([$code]))
            ->addProperty('message', (new StringType)->example($message))
            ->addProperty('details', (new ArrayType)->setItems(new ObjectType))
            ->addProperty('requestId', new StringType)
            ->setRequired(['code', 'message', 'requestId']);

        $body = (new ObjectType)
            ->addProperty('error', $error)
            ->setRequired(['error']);

        return Response::make($status)
            ->setDescription("`{$code}`: {$message}")
            ->setContent('application/json', Schema::fromType($body));
    }
}
