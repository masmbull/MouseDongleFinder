<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Download;
use App\Services\DownloadService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use RuntimeException;

class DownloadController extends Controller
{
    private const EXECUTABLE_EXTENSIONS = ['exe', 'msi'];

    public function __construct(private DownloadService $downloadService) {}

    public function index(Request $request): JsonResponse
    {
        $query = Download::query();

        if ($request->filled('dongle_id')) {
            $query->where('dongle_id', $request->dongle_id);
        }

        if ($request->filled('source_type')) {
            $query->where('source_type', $request->source_type);
        }

        if ($request->filled('download_status')) {
            $query->where('download_status', $request->download_status);
        }

        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);

        return response()->json($query->with('dongle')->latest()->paginate($perPage));
    }

    public function store(Request $request): JsonResponse
    {
        return $this->fetch($request);
    }

    public function show(Download $download): JsonResponse
    {
        return response()->json($download->load(['dongle', 'identification']));
    }

    public function fetch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'dongle_id' => 'nullable|exists:dongles,id',
            'identification_id' => 'nullable|exists:identifications,id',
            'source_url' => ['required', 'url', function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_string($value) || strtolower((string) parse_url($value, PHP_URL_SCHEME)) !== 'https') {
                    $fail('Only HTTPS URLs can be downloaded. The provided URL is not secure.');
                }
            }],
            'confirmed' => ['sometimes', 'boolean'],
            'filename' => 'nullable|string',
            'source_type' => 'nullable|string|in:official,third-party,unknown',
            'manufacturer' => 'nullable|string|max:255',
            'model' => 'nullable|string|max:255',
            'software_type' => 'nullable|string|max:100',
        ]);

        $url = $validated['source_url'];
        $extension = strtolower(pathinfo((string) (parse_url($url, PHP_URL_PATH) ?? ''), PATHINFO_EXTENSION));

        if (in_array($extension, self::EXECUTABLE_EXTENSIONS, true) && ! $request->boolean('confirmed')) {
            return response()->json([
                'error' => 'This is an executable file. Set confirmed=true only after you trust the source.',
                'requires_confirmation' => true,
            ], 422);
        }

        try {
            $metadata = $this->downloadService->fetch($validated + ['confirmed' => $request->boolean('confirmed')]);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            return response()->json(['error' => $exception->getMessage()], 422);
        }

        $download = Download::create([
            'dongle_id' => $validated['dongle_id'] ?? null,
            'identification_id' => $validated['identification_id'] ?? null,
            'filename' => $metadata['filename'],
            'file_path' => $metadata['file_path'],
            'source_url' => $metadata['source_url'],
            'source_type' => $metadata['source_type'],
            'manufacturer' => $metadata['manufacturer'] !== 'Unknown' ? $metadata['manufacturer'] : ($validated['manufacturer'] ?? null),
            'model' => $metadata['model'] !== 'Unknown' ? $metadata['model'] : ($validated['model'] ?? null),
            'software_type' => $metadata['software_type'] ?? ($validated['software_type'] ?? null),
            'file_size' => $metadata['file_size'],
            'sha256_hash' => $metadata['sha256_hash'],
            'download_status' => 'completed',
            'downloaded_at' => now(),
        ]);

        return response()->json([
            'download' => $download->load(['dongle', 'identification']),
            'message' => 'Download completed. Nothing was executed.',
        ], 201);
    }

    public function verify(Download $download): JsonResponse
    {
        $result = $this->downloadService->verify($download);

        if (isset($result['error'])) {
            return response()->json($result, 404);
        }

        return response()->json($result);
    }

    public function open(Download $download): JsonResponse
    {
        return response()->json($this->downloadService->open($download));
    }

    public function destroy(Download $download): JsonResponse
    {
        $this->downloadService->deleteFile($download);
        $download->delete();

        return response()->json(['message' => 'Download record deleted']);
    }
}
