<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceMatch;
use App\Models\Dongle;
use App\Models\Mouse;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        $stats = [
            'total_mice' => Mouse::count(),
            'total_dongles' => Dongle::count(),
            'matched_pairs' => DeviceMatch::where('status', 'matched')->count(),
            'suspected_pairs' => DeviceMatch::where('status', 'suspected')->count(),
            'unmatched_dongles' => Dongle::whereDoesntHave('match')->count(),
            'unmatched_mice' => Mouse::whereDoesntHave('match')->count(),
            'unknown_devices' => Dongle::whereHas('match', function ($q) {
                $q->where('status', 'unknown');
            })->count(),
        ];

        $recentMice = Mouse::with('match')
            ->latest()
            ->take(5)
            ->get();

        $recentDongles = Dongle::with(['match', 'identification'])
            ->latest()
            ->take(5)
            ->get();

        $recentDeviceMatches = DeviceMatch::with(['mouse', 'dongle'])
            ->latest('matched_at')
            ->take(5)
            ->get();

        $statusOverview = DeviceMatch::selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        return response()->json([
            'stats' => $stats,
            'recent_mice' => $recentMice,
            'recent_dongles' => $recentDongles,
            'recent_matches' => $recentDeviceMatches,
            'status_overview' => (object) $statusOverview->all(),
        ]);
    }
}
