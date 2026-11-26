<?php

use App\Http\Controllers\Api\AutoDiscoverController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DeviceMatchController;
use App\Http\Controllers\Api\DongleController;
use App\Http\Controllers\Api\DownloadController;
use App\Http\Controllers\Api\IdentificationController;
use App\Http\Controllers\Api\MatchingSessionController;
use App\Http\Controllers\Api\MouseController;
use App\Http\Controllers\Api\ResearchController;
use App\Http\Controllers\Api\UsbIdController;
use App\Http\Controllers\Api\UsbScanController;
use Illuminate\Support\Facades\Route;

// Dashboard
Route::get('/dashboard', [DashboardController::class, 'index']);

// Mice
Route::apiResource('mice', MouseController::class);

// Dongles
Route::apiResource('dongles', DongleController::class);

// Matches
Route::apiResource('matches', DeviceMatchController::class);

// Dongle Lab - custom actions must precede the resource's {matching_session} binding.
Route::post('/matching-sessions/{matching_session}', [MatchingSessionController::class, 'observe']);
Route::post('/matching-sessions/{matching_session}/observe', [MatchingSessionController::class, 'observe']);
Route::post('/matching-sessions/{matching_session}/label', [MatchingSessionController::class, 'label']);
Route::post('/matching-sessions/{matching_session}/complete', [MatchingSessionController::class, 'complete']);
Route::apiResource('matching-sessions', MatchingSessionController::class)->except(['update']);

// One-click auto-discovery of mouse↔dongle relationships.
Route::post('/auto-discover', [AutoDiscoverController::class, 'store']);

// USB scans - static routes must precede the resource's {usb_scan} binding.
Route::post('/usb-scans/scan', [UsbScanController::class, 'scan']);
Route::post('/usb-scans/live-match', [UsbScanController::class, 'liveMatch']);
Route::get('/usb-scans/latest', [UsbScanController::class, 'latest']);
Route::apiResource('usb-scans', UsbScanController::class);

// Identifications - static routes must precede the {identification} binding.
Route::get('/identifications', [IdentificationController::class, 'index']);
Route::post('/identifications', [IdentificationController::class, 'store']);
Route::post('/identifications/analyze', [IdentificationController::class, 'analyze']);
Route::post('/identifications/ocr', [IdentificationController::class, 'ocr']);
Route::post('/identifications/save-analysis', [IdentificationController::class, 'saveAnalysis']);
Route::get('/identifications/{identification}', [IdentificationController::class, 'show']);
Route::post('/identifications/{identification}/save-analysis', [IdentificationController::class, 'saveAnalysis']);

// Local VID/PID database
Route::get('/usb-ids/lookup', [UsbIdController::class, 'lookup']);
Route::post('/usb-ids/refresh', [UsbIdController::class, 'refresh']);

// Web research
Route::post('/research/search', [ResearchController::class, 'search']);
Route::get('/research/software', [ResearchController::class, 'software']);

// Downloads - static routes must precede the {download} binding.
Route::get('/downloads', [DownloadController::class, 'index']);
Route::post('/downloads', [DownloadController::class, 'store']);
Route::post('/downloads/fetch', [DownloadController::class, 'fetch']);
Route::get('/downloads/{download}/verify', [DownloadController::class, 'verify']);
Route::post('/downloads/{download}/open', [DownloadController::class, 'open']);
Route::get('/downloads/{download}', [DownloadController::class, 'show']);
Route::delete('/downloads/{download}', [DownloadController::class, 'destroy']);
