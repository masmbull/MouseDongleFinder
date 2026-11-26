<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Dongle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DongleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Dongle::query();

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('dongle_id', 'like', "%{$search}%")
                    ->orWhere('brand', 'like', "%{$search}%")
                    ->orWhere('model', 'like', "%{$search}%")
                    ->orWhere('physical_description', 'like', "%{$search}%")
                    ->orWhere('vid', 'like', "%{$search}%")
                    ->orWhere('pid', 'like', "%{$search}%");
            });
        }

        if ($request->has('brand')) {
            $query->where('brand', $request->brand);
        }

        if ($request->filled('status')) {
            $status = $request->string('status')->toString();

            if ($status === 'unmatched') {
                $query->whereDoesntHave('match', fn ($matchQuery) => $matchQuery->whereIn('status', ['matched', 'suspected']));
            } elseif (in_array($status, ['matched', 'suspected', 'unknown'], true)) {
                $query->whereHas('match', fn ($matchQuery) => $matchQuery->where('status', $status));
            }
        }

        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);
        $dongles = $query->with(['match', 'identification'])
            ->withCount('referencePhotos')
            ->orderByDesc('reference_photos_count')
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json($dongles);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'brand' => 'nullable|string|max:255',
            'model' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:50',
            'physical_description' => 'nullable|string',
            'receiver_type' => 'nullable|string|max:100',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'notes' => 'nullable|string',
            'vid' => 'nullable|string|max:4',
            'pid' => 'nullable|string|max:4',
            'hardware_id' => 'nullable|string',
            'serial_number' => 'nullable|string',
        ]);

        $dongle = Dongle::create([
            'dongle_id' => 'DG-'.strtoupper(Str::random(8)),
            'brand' => $validated['brand'] ?? null,
            'model' => $validated['model'] ?? null,
            'color' => $validated['color'] ?? null,
            'physical_description' => $validated['physical_description'] ?? null,
            'receiver_type' => $validated['receiver_type'] ?? null,
            'vid' => $this->normalizeUsbId($validated['vid'] ?? null),
            'pid' => $this->normalizeUsbId($validated['pid'] ?? null),
            'hardware_id' => $validated['hardware_id'] ?? null,
            'serial_number' => $validated['serial_number'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        if ($request->hasFile('photo')) {
            $photo = $request->file('photo');
            $path = $photo->store('dongles', 'public');
            $dongle->update(['photo_path' => $path]);
        }

        return response()->json($dongle->load(['match', 'identification']), 201);
    }

    public function show(Dongle $dongle): JsonResponse
    {
        return response()->json($dongle->load([
            'match',
            'matches' => fn ($query) => $query->with('mouse')->latest(),
            'identification',
            'downloads',
        ]));
    }

    public function update(Request $request, Dongle $dongle): JsonResponse
    {
        $validated = $request->validate([
            'brand' => 'nullable|string|max:255',
            'model' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:50',
            'physical_description' => 'nullable|string',
            'receiver_type' => 'nullable|string|max:100',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'remove_photo' => 'nullable|boolean',
            'notes' => 'nullable|string',
            'vid' => 'nullable|string|max:4',
            'pid' => 'nullable|string|max:4',
            'hardware_id' => 'nullable|string',
            'serial_number' => 'nullable|string',
        ]);

        $dongle->update([
            'brand' => $validated['brand'] ?? null,
            'model' => $validated['model'] ?? null,
            'color' => $validated['color'] ?? null,
            'physical_description' => $validated['physical_description'] ?? null,
            'receiver_type' => $validated['receiver_type'] ?? null,
            'vid' => $this->normalizeUsbId($validated['vid'] ?? null),
            'pid' => $this->normalizeUsbId($validated['pid'] ?? null),
            'hardware_id' => $validated['hardware_id'] ?? null,
            'serial_number' => $validated['serial_number'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        if ($request->hasFile('photo')) {
            if ($dongle->photo_path) {
                Storage::disk('public')->delete($dongle->photo_path);
            }
            $photo = $request->file('photo');
            $path = $photo->store('dongles', 'public');
            $dongle->update(['photo_path' => $path]);
        } elseif ($request->boolean('remove_photo')) {
            if ($dongle->photo_path) {
                Storage::disk('public')->delete($dongle->photo_path);
            }
            $dongle->update(['photo_path' => null]);
        }

        return response()->json($dongle->load(['match', 'identification']));
    }

    public function destroy(Dongle $dongle): JsonResponse
    {
        if ($dongle->photo_path) {
            Storage::disk('public')->delete($dongle->photo_path);
        }
        $dongle->delete();

        return response()->json(['message' => 'Dongle deleted successfully']);
    }

    private function normalizeUsbId(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return strtoupper(trim($value));
    }
}
