<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AutoDiscoverService;
use Illuminate\Http\JsonResponse;
use Throwable;

class AutoDiscoverController extends Controller
{
    public function __construct(private AutoDiscoverService $autoDiscover) {}

    public function store(): JsonResponse
    {
        try {
            return response()->json($this->autoDiscover->discover());
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'scanned_at' => now()->toIso8601String(),
                'error' => 'Auto-discovery failed: '.$exception->getMessage(),
                'message' => 'Auto-discovery is unavailable.',
                'discovered' => [],
                'unattributed_devices' => [],
            ]);
        }
    }
}
