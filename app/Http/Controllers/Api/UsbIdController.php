<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\UsbIdDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UsbIdController extends Controller
{
    public function __construct(private UsbIdDatabase $usbIds) {}

    public function lookup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'vid' => 'nullable|string|size:4',
            'pid' => 'nullable|string|size:4',
        ]);

        return response()->json($this->usbIds->lookup(
            $validated['vid'] ?? null,
            $validated['pid'] ?? null,
        ));
    }

    public function refresh(): JsonResponse
    {
        return response()->json($this->usbIds->refresh());
    }
}
