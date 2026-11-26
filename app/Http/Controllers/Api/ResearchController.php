<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WebResearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResearchController extends Controller
{
    public function __construct(private WebResearchService $research) {}

    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'queries' => ['required_without:query', 'array', 'min:1', 'max:10'],
            'queries.*' => ['string', 'max:255'],
            'query' => ['required_without:queries', 'string', 'max:255'],
            'vid' => 'nullable|string|size:4',
            'pid' => 'nullable|string|size:4',
            'manufacturer' => 'nullable|string|max:255',
            'model' => 'nullable|string|max:255',
        ]);

        $queries = $validated['queries'] ?? [$validated['query']];

        return response()->json($this->research->search($queries, $validated));
    }

    public function software(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'vid' => 'nullable|string|size:4',
            'pid' => 'nullable|string|size:4',
            'manufacturer' => 'nullable|string|max:255',
            'model' => 'nullable|string|max:255',
        ]);

        return response()->json($this->research->software($validated));
    }
}
