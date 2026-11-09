<?php

namespace App\Http\Controllers\Foundation;

use App\Http\Controllers\Controller;
use App\Services\Foundation\HealthCheck;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    public function __invoke(HealthCheck $health): JsonResponse
    {
        $checks = $health->run();
        $healthy = ! in_array(false, $checks, true);

        return response()->json(
            ['status' => $healthy ? 'ok' : 'degraded', 'checks' => $checks],
            $healthy ? 200 : 503,
        );
    }
}
