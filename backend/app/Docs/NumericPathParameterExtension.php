<?php

namespace App\Docs;

use Dedoc\Scramble\Extensions\OperationExtension;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\RouteInfo;

/**
 * Controllers take ids as strings, so an id too long for an int ends as 404 and not as a TypeError.
 * The route constraint from whereNumber() says what the API really accepts.
 */
class NumericPathParameterExtension extends OperationExtension
{
    private const NUMBER_CONSTRAINT = '[0-9]+';

    public function handle(Operation $operation, RouteInfo $routeInfo): void
    {
        foreach ($operation->parameters as $parameter) {
            if (! $parameter instanceof Parameter || $parameter->in !== 'path') {
                continue;
            }

            if (($routeInfo->route->wheres[$parameter->name] ?? null) === self::NUMBER_CONSTRAINT) {
                $parameter->setSchema(Schema::fromType(new IntegerType));
            }
        }
    }
}
