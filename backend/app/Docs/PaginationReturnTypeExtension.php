<?php

namespace App\Docs;

use App\Support\Pagination;
use Dedoc\Scramble\Infer\Extensions\Event\StaticMethodCallEvent;
use Dedoc\Scramble\Infer\Extensions\StaticMethodReturnTypeExtension;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\ArrayType;
use Dedoc\Scramble\Support\Type\GenericClassStringType;
use Dedoc\Scramble\Support\Type\IntegerType;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\Type;

/**
 * Scramble reads the resource class from the call, because it cannot follow $resource::collection()
 * inside Pagination::response(). Controllers then need no @return for their lists.
 */
class PaginationReturnTypeExtension implements StaticMethodReturnTypeExtension
{
    public function shouldHandle(string $name): bool
    {
        return $name === Pagination::class;
    }

    public function getStaticMethodReturnType(StaticMethodCallEvent $event): ?Type
    {
        $resource = $event->getArg('resource', 1);

        if ($event->name !== 'response' || ! $resource instanceof GenericClassStringType) {
            return null;
        }

        return new KeyedArrayType([
            new ArrayItemType_('data', new ArrayType($resource->type)),
            new ArrayItemType_('meta', new KeyedArrayType([
                new ArrayItemType_('page', new IntegerType),
                new ArrayItemType_('limit', new IntegerType),
                new ArrayItemType_('total', new IntegerType),
                new ArrayItemType_('totalPages', new IntegerType),
            ])),
        ]);
    }
}
