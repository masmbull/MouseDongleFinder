<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Identification;
use App\Services\IdentificationEngine;
use App\Services\ImageAnalysisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class IdentificationController extends Controller
{
    public function __construct(
        private IdentificationEngine $identificationEngine,
        private ImageAnalysisService $imageAnalysisService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Identification::query();

        if ($request->filled('dongle_id')) {
            $query->where('dongle_id', $request->dongle_id);
        }

        if ($request->filled('confidence')) {
            $query->where('confidence', $request->confidence);
        }

        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);

        return response()->json($query->with('dongle')->latest()->paginate($perPage));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'dongle_id' => 'nullable|exists:dongles,id',
            'vid' => 'nullable|string|size:4',
            'pid' => 'nullable|string|size:4',
            'hardware_id' => 'nullable|string',
            'probable_manufacturer' => 'nullable|string',
            'probable_model' => 'nullable|string',
            'device_category' => 'nullable|string',
            'wireless_technology' => 'nullable|string',
            'frequency' => 'nullable|string',
            'receiver_family' => 'nullable|string',
            'compatible_mice' => 'nullable|array',
            'pairing_utility' => 'nullable|string',
            'official_support_url' => 'nullable|url',
            'support_url' => 'nullable|url',
            'evidence' => 'nullable|array',
            'confidence' => 'nullable|string',
            'research_sources' => 'nullable|array',
            'notes' => 'nullable|string',
        ]);

        $identification = Identification::create([
            'dongle_id' => $validated['dongle_id'] ?? null,
            'vid' => $this->normalizeUsbId($validated['vid'] ?? null),
            'pid' => $this->normalizeUsbId($validated['pid'] ?? null),
            'hardware_id' => $validated['hardware_id'] ?? null,
            'probable_manufacturer' => $validated['probable_manufacturer'] ?? null,
            'probable_model' => $validated['probable_model'] ?? null,
            'device_category' => $validated['device_category'] ?? null,
            'wireless_technology' => $validated['wireless_technology'] ?? null,
            'frequency' => $validated['frequency'] ?? null,
            'receiver_family' => $validated['receiver_family'] ?? null,
            'compatible_mice' => $validated['compatible_mice'] ?? [],
            'pairing_utility' => $validated['pairing_utility'] ?? null,
            'official_support_url' => $validated['official_support_url'] ?? $validated['support_url'] ?? null,
            'evidence' => $validated['evidence'] ?? [],
            'confidence' => $validated['confidence'] ?? 'unknown',
            'research_sources' => $validated['research_sources'] ?? [],
            'notes' => $validated['notes'] ?? null,
        ]);

        return response()->json($identification->load('dongle'), 201);
    }

    public function show(Identification $identification): JsonResponse
    {
        return response()->json($identification->load('dongle'));
    }

    public function analyze(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'vid' => 'nullable|string|size:4',
            'pid' => 'nullable|string|size:4',
            'hardware_id' => 'nullable|string|max:255',
            'device_instance_id' => 'nullable|string|max:255',
            'dongle_id' => 'nullable|exists:dongles,id',
            'manufacturer' => 'nullable|string|max:255',
            'model' => 'nullable|string|max:255',
            'serial_number' => 'nullable|string|max:255',
            'name' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'vid_pid_source' => 'nullable|string|max:100',
            'is_receiver' => 'nullable|boolean',
            'online_results' => 'nullable|array',
        ]);

        return response()->json($this->identificationEngine->analyze($validated));
    }

    public function saveAnalysis(Request $request): JsonResponse
    {
        $analysis = $request->input('analysis');
        $payload = is_array($analysis) ? $analysis : $request->all();

        $validated = Validator::make($payload, [
            'dongle_id' => 'nullable|exists:dongles,id',
            'vid' => 'nullable|string|size:4',
            'pid' => 'nullable|string|size:4',
            'hardware_id' => 'nullable|string',
            'probable_manufacturer' => 'nullable|string',
            'probable_model' => 'nullable|string',
            'device_category' => 'nullable|string',
            'wireless_technology' => 'nullable|string',
            'frequency' => 'nullable|string',
            'receiver_family' => 'nullable|string',
            'compatible_mice' => 'nullable|array',
            'pairing_utility' => 'nullable|string',
            'support_url' => 'nullable|url',
            'official_support_url' => 'nullable|url',
            'evidence' => 'nullable|array',
            'confidence' => 'nullable|string|max:20',
            'research_sources' => 'nullable|array',
            'notes' => 'nullable|string',
        ])->validate();

        $identification = Identification::create([
            'dongle_id' => $validated['dongle_id'] ?? null,
            'vid' => $this->normalizeUsbId($validated['vid'] ?? null),
            'pid' => $this->normalizeUsbId($validated['pid'] ?? null),
            'hardware_id' => $validated['hardware_id'] ?? null,
            'probable_manufacturer' => $validated['probable_manufacturer'] ?? null,
            'probable_model' => $validated['probable_model'] ?? null,
            'device_category' => $validated['device_category'] ?? null,
            'wireless_technology' => $validated['wireless_technology'] ?? null,
            'frequency' => $validated['frequency'] ?? null,
            'receiver_family' => $validated['receiver_family'] ?? null,
            'compatible_mice' => $validated['compatible_mice'] ?? [],
            'pairing_utility' => $validated['pairing_utility'] ?? null,
            'official_support_url' => $validated['official_support_url'] ?? $validated['support_url'] ?? null,
            'evidence' => $validated['evidence'] ?? [],
            'confidence' => $validated['confidence'] ?? 'unknown',
            'research_sources' => $validated['research_sources'] ?? [],
            'notes' => $validated['notes'] ?? null,
        ]);

        return response()->json($identification->load('dongle'), 201);
    }

    public function ocr(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'photo' => ['required', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:5120'],
            'photo_type' => ['sometimes', 'string', Rule::in(['mouse', 'dongle', 'front', 'back', 'label', 'serial', 'receiver'])],
        ]);

        return response()->json($this->imageAnalysisService->analyze(
            $validated['photo'],
            $validated['photo_type'] ?? 'dongle',
        ));
    }

    private function normalizeUsbId(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return strtoupper(trim($value));
    }
}
