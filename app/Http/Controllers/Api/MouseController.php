<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Mouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MouseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Mouse::query();

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('mouse_id', 'like', "%{$search}%")
                    ->orWhere('brand', 'like', "%{$search}%")
                    ->orWhere('model', 'like', "%{$search}%")
                    ->orWhere('physical_description', 'like', "%{$search}%");
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
        $mice = $query->with('match')
            ->withCount('referencePhotos')
            ->orderByDesc('reference_photos_count')
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json($mice);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'brand' => 'required|string|max:255',
            'model' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:50',
            'physical_description' => 'nullable|string',
            'connectivity_type' => 'nullable|string|max:100',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'notes' => 'nullable|string',
        ]);

        $mouse = Mouse::create([
            'mouse_id' => 'MS-'.strtoupper(Str::random(8)),
            'brand' => $validated['brand'],
            'model' => $validated['model'] ?? null,
            'color' => $validated['color'] ?? null,
            'physical_description' => $validated['physical_description'] ?? null,
            'connectivity_type' => $validated['connectivity_type'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        if ($request->hasFile('photo')) {
            $photo = $request->file('photo');
            $path = $photo->store('mice', 'public');
            $mouse->update(['photo_path' => $path]);
        }

        return response()->json($mouse->load('match'), 201);
    }

    public function show(Mouse $mouse): JsonResponse
    {
        return response()->json($mouse->load('match'));
    }

    public function update(Request $request, Mouse $mouse): JsonResponse
    {
        $validated = $request->validate([
            'brand' => 'required|string|max:255',
            'model' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:50',
            'physical_description' => 'nullable|string',
            'connectivity_type' => 'nullable|string|max:100',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'remove_photo' => 'nullable|boolean',
            'notes' => 'nullable|string',
        ]);

        $mouse->update([
            'brand' => $validated['brand'],
            'model' => $validated['model'] ?? null,
            'color' => $validated['color'] ?? null,
            'physical_description' => $validated['physical_description'] ?? null,
            'connectivity_type' => $validated['connectivity_type'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        if ($request->hasFile('photo')) {
            if ($mouse->photo_path) {
                Storage::disk('public')->delete($mouse->photo_path);
            }
            $photo = $request->file('photo');
            $path = $photo->store('mice', 'public');
            $mouse->update(['photo_path' => $path]);
        } elseif ($request->boolean('remove_photo')) {
            if ($mouse->photo_path) {
                Storage::disk('public')->delete($mouse->photo_path);
            }
            $mouse->update(['photo_path' => null]);
        }

        return response()->json($mouse->load('match'));
    }

    public function destroy(Mouse $mouse): JsonResponse
    {
        if ($mouse->photo_path) {
            Storage::disk('public')->delete($mouse->photo_path);
        }
        $mouse->delete();

        return response()->json(['message' => 'Mouse deleted successfully']);
    }
}
