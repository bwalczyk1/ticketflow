<?php

namespace App\Docs;

use Dedoc\Scramble\Extensions\OperationExtension;
use Dedoc\Scramble\Support\Generator\Combined\AnyOf;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Support\Str;

/**
 * Scramble reads the errors from the controller code, so it cannot see the ones answered by
 * middleware: 403 from the role check and 400 INVALID_JSON for a body that is not valid JSON.
 */
class MiddlewareErrorResponseExtension extends OperationExtension
{
    private const BODY_METHODS = ['post', 'put', 'patch'];

    public function handle(Operation $operation, RouteInfo $routeInfo): void
    {
        $checksRole = collect($routeInfo->route->gatherMiddleware())
            ->contains(fn (mixed $middleware) => is_string($middleware) && Str::startsWith($middleware, 'role:'));

        if ($checksRole) {
            $this->addResponse($operation, ErrorResponse::make(403, 'FORBIDDEN', 'Insufficient permissions'));
        }

        if (in_array($operation->method, self::BODY_METHODS, true)) {
            $this->addResponse($operation, ErrorResponse::make(400, 'INVALID_JSON', 'Malformed JSON body.'));
        }
    }

    /**
     * Scramble has already grouped the responses by status, so a second error for the same
     * status joins the first one as anyOf, the way Scramble documents it itself.
     */
    private function addResponse(Operation $operation, Response $added): void
    {
        $existing = collect($operation->responses)
            ->first(fn (mixed $response) => $response instanceof Response && (int) $response->code === $added->code);

        if ($existing === null) {
            $operation->addResponse($added);

            return;
        }

        $current = $existing->getContent('application/json');
        $addedType = $added->getContent('application/json')->type;
        $types = $current->type instanceof AnyOf ? $current->type->items : [$current->type];

        foreach ($types as $type) {
            if (json_encode($type->toArray()) === json_encode($addedType->toArray())) {
                return;
            }
        }

        $existing->setContent('application/json', Schema::fromType((new AnyOf)->setItems([...$types, $addedType])));
        $existing->setDescription(trim("{$existing->description}\n\n{$added->description}"));
    }
}
