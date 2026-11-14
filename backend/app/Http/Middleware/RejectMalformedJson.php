<?php

namespace App\Http\Middleware;

use App\Exceptions\Domain\InvalidJson;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Laravel reads a body it cannot parse as an empty one, so a typo in the JSON would come back as
 * "field required". This names the real problem instead.
 */
class RejectMalformedJson
{
    public function handle(Request $request, Closure $next): Response
    {
        $body = $request->getContent();

        if ($request->isJson() && $body !== '' && ! json_validate($body)) {
            throw new InvalidJson;
        }

        return $next($request);
    }
}
