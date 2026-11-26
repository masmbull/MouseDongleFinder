<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceMatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DeviceMatchController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = DeviceMatch::query()->with(['mouse', 'dongle']);

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('mouse', function ($sub) use ($search) {
                    $sub->where('mouse_id', 'like', "%{$search}%")
                        ->orWhere('brand', 'like', "%{$search}%")
                        ->orWhere('model', 'like', "%{$search}%");
                })->orWhereHas('dongle', function ($sub) use ($search) {
                    $sub->where('dongle_id', 'like', "%{$search}%")
                        ->orWhere('brand', 'like', "%{$search}%")
                        ->orWhere('model', 'like', "%{$search}%");
                });
            });
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);
        $matches = $query->latest()->paginate($perPage);

        return response()->json($matches);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'mouse_id' => ['required', 'exists:mice,id'],
            'dongle_id' => ['required', 'exists:dongles,id'],
            'status' => ['required', Rule::in(['matched', 'suspected', 'unmatched', 'unknown'])],
            'notes' => 'nullable|string',
        ]);

        // Both identifiers must be inside the same nested closure so the
        // active-status condition applies to each of them.
        $existingDeviceMatch = $this->findActiveMatch(
            $validated['mouse_id'],
            $validated['dongle_id'],
        );

        if ($existingDeviceMatch) {
            return response()->json([
                'error' => 'This mouse or dongle already has an active match.',
                'existing_match' => $existingDeviceMatch->load(['mouse', 'dongle']),
            ], 422);
        }

        $match = DeviceMatch::create([
            'mouse_id' => $validated['mouse_id'],
            'dongle_id' => $validated['dongle_id'],
            'status' => $validated['status'],
            'notes' => $validated['notes'] ?? null,
            'matched_at' => $validated['status'] === 'matched' ? now() : null,
        ]);

        return response()->json($match->load(['mouse', 'dongle']), 201);
    }

    public function show(DeviceMatch $match): JsonResponse
    {
        return response()->json($match->load(['mouse', 'dongle']));
    }

    public function update(Request $request, DeviceMatch $match): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['sometimes', Rule::in(['matched', 'suspected', 'unmatched', 'unknown'])],
            'notes' => 'nullable|string',
        ]);

        if (isset($validated['status']) && in_array($validated['status'], ['matched', 'suspected'], true)) {
            $existingDeviceMatch = $this->findActiveMatch(
                $match->mouse_id,
                $match->dongle_id,
                $match->id,
            );

            if ($existingDeviceMatch) {
                return response()->json([
                    'error' => 'This mouse or dongle already has an active match.',
                    'existing_match' => $existingDeviceMatch->load(['mouse', 'dongle']),
                ], 422);
            }
        }

        $updateData = [];

        if (isset($validated['status'])) {
            $updateData['status'] = $validated['status'];
            if ($validated['status'] === 'matched' && ! $match->matched_at) {
                $updateData['matched_at'] = now();
            } elseif ($validated['status'] !== 'matched') {
                $updateData['matched_at'] = null;
            }
        }

        if (isset($validated['notes'])) {
            $updateData['notes'] = $validated['notes'];
        }

        $match->update($updateData);

        return response()->json($match->load(['mouse', 'dongle']));
    }

    public function destroy(DeviceMatch $match): JsonResponse
    {
        $match->delete();

        return response()->json(['message' => 'DeviceMatch removed successfully']);
    }

    private function findActiveMatch(int $mouseId, int $dongleId, ?int $excludeId = null): ?DeviceMatch
    {
        return DeviceMatch::query()
            ->where(function ($query) use ($mouseId, $dongleId) {
                $query->where('mouse_id', $mouseId)
                    ->orWhere('dongle_id', $dongleId);
            })
            ->whereIn('status', ['matched', 'suspected'])
            ->when($excludeId !== null, fn ($query) => $query->where('id', '!=', $excludeId))
            ->first();
    }
}
