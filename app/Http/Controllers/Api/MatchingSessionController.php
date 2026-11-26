<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MatchingSession;
use App\Services\DongleLabService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Dongle Lab session endpoints. The controller only coordinates validation,
 * persistence of snapshots/observations and responses; matches are saved by
 * the existing POST /api/matches endpoint after the user confirms a test.
 */
class MatchingSessionController extends Controller
{
    public function __construct(private DongleLabService $dongleLab) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);

        return response()->json(MatchingSession::query()->latest()->paginate($perPage));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
        ]);

        $scan = $this->dongleLab->scanDongles();
        $dongles = $scan['dongles'];
        $baseline = $this->dongleLab->baselineDevices();

        $session = MatchingSession::create([
            'name' => $validated['name'] ?? 'Dongle Lab '.now()->format('Y-m-d H:i:s'),
            'status' => 'scanning',
            'dongles' => $dongles,
            'observations' => [$this->baselineObservation(
                $baseline['devices'] !== [] ? $baseline['devices'] : $scan['devices'],
                $scan['scanned_at'],
            )],
            'started_at' => now(),
        ]);

        $response = [
            'session' => $session,
            'dongles' => $dongles,
            'message' => $scan['message'],
        ];

        if ($scan['error'] !== null) {
            $response['error'] = $scan['error'];
        }

        return response()->json($response, 201);
    }

    public function show(MatchingSession $matching_session): JsonResponse
    {
        return response()->json($matching_session);
    }

    public function observe(Request $request, MatchingSession $matching_session): JsonResponse
    {
        $dongles = is_array($matching_session->dongles) ? $matching_session->dongles : [];
        $validated = $request->validate([
            'mouse_id' => ['nullable', 'exists:mice,id'],
            'notes' => ['nullable', 'string'],
            'dongle_key' => ['nullable', 'string', Rule::in($this->dongleKeys($dongles))],
        ]);

        $testedDongleKey = $validated['dongle_key'] ?? null;
        $observations = is_array($matching_session->observations) ? $matching_session->observations : [];
        $previousDevices = $this->lastSnapshotDevices($observations);
        $result = $this->dongleLab->observe($dongles, $previousDevices, $testedDongleKey);

        $response = [
            'observed_at' => $result['observed_at'],
            'new_devices' => $result['new_devices'],
            'connected_devices' => $result['connected_devices'] ?? [],
            'candidates' => $result['candidates'],
            'flat_candidates' => $result['flat_candidates'] ?? [],
            'message' => $result['message'],
            'tested_dongle_key' => $testedDongleKey,
        ];

        if ($result['error'] !== null) {
            $response['error'] = $result['error'];
            $response['observation_count'] = count($observations);

            return response()->json($response);
        }

        $observations[] = [
            'observed_at' => $result['observed_at'],
            'mouse_id' => $validated['mouse_id'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'dongle_key' => $testedDongleKey,
            'devices' => $result['devices'],
            'connected_devices' => $result['connected_devices'] ?? [],
            'new_devices' => $result['new_devices'],
            'candidates' => $result['candidates'],
            'flat_candidates' => $result['flat_candidates'] ?? [],
        ];

        $matching_session->observations = $observations;

        if ($matching_session->status === 'scanning') {
            $matching_session->status = 'observing';
        }

        $matching_session->save();

        $response['observation_count'] = count($observations);

        return response()->json($response);
    }

    /**
     * Stores an operator-provided label for one detected dongle. VID/PID often
     * cannot identify a generic receiver, so the label is explicitly marked as
     * manual rather than presented as a detection result.
     */
    public function label(Request $request, MatchingSession $matching_session): JsonResponse
    {
        $validated = $request->validate([
            'dongle_key' => ['required', 'string'],
            'brand' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $dongles = is_array($matching_session->dongles) ? $matching_session->dongles : [];
        $dongleIndex = null;

        foreach ($dongles as $index => $dongle) {
            if (is_array($dongle) && ($dongle['key'] ?? null) === $validated['dongle_key']) {
                $dongleIndex = $index;

                break;
            }
        }

        if ($dongleIndex === null) {
            throw ValidationException::withMessages([
                'dongle_key' => 'The selected dongle key is not part of this session.',
            ]);
        }

        $dongle = $dongles[$dongleIndex];
        $brand = filled($validated['brand'] ?? null) ? trim($validated['brand']) : null;
        $model = filled($validated['model'] ?? null) ? trim($validated['model']) : null;
        $notes = filled($validated['notes'] ?? null) ? trim($validated['notes']) : null;
        $noteParts = [];

        if ($brand !== null) {
            $dongle['brand_guess'] = $brand;
            $noteParts[] = 'brand set manually to '.$brand;
        }

        if ($model !== null) {
            $dongle['name'] = $model;
            $noteParts[] = 'model set manually to '.$model;
        }

        if ($notes !== null) {
            $noteParts[] = $notes;
        }

        $noteParts[] = 'user-provided label, not detected';
        $existingNote = is_string($dongle['note'] ?? null) ? trim($dongle['note']) : '';
        $dongle['note'] = ($existingNote === '' ? '' : $existingNote.'; ').implode('; ', $noteParts).'.';
        $dongle['manual_label'] = true;
        $dongle['brand_source'] = 'manual';
        $dongles[$dongleIndex] = $dongle;

        $matching_session->dongles = $dongles;
        $matching_session->save();
        $session = $matching_session->fresh();

        return response()->json([
            'session' => $session,
            'dongles' => $session->dongles,
            'message' => sprintf('Dongle %s was labelled manually. Confirm the relationship with a physical pairing test before saving a match.', $validated['dongle_key']),
        ]);
    }

    public function complete(MatchingSession $matching_session): JsonResponse
    {
        $matching_session->status = 'completed';
        $matching_session->completed_at = now();
        $matching_session->save();

        return response()->json($matching_session);
    }

    public function destroy(MatchingSession $matching_session): JsonResponse
    {
        $matching_session->delete();

        return response()->json(['message' => 'Matching session deleted successfully']);
    }

    /**
     * @param  array<int, array<string, mixed>>  $devices
     * @return array<string, mixed>
     */
    private function baselineObservation(array $devices, string $scannedAt): array
    {
        return [
            'observed_at' => $scannedAt,
            'mouse_id' => null,
            'notes' => 'Initial device snapshot captured when the lab session was created.',
            'devices' => $devices,
            'new_devices' => [],
            'candidates' => [],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $dongles
     * @return array<int, string>
     */
    private function dongleKeys(array $dongles): array
    {
        $keys = [];

        foreach ($dongles as $dongle) {
            if (! is_array($dongle)) {
                continue;
            }

            $key = $dongle['key'] ?? null;

            if (is_string($key) && trim($key) !== '') {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    /**
     * @param  array<int, array<string, mixed>>  $observations
     * @return array<int, array<string, mixed>>
     */
    private function lastSnapshotDevices(array $observations): array
    {
        foreach (array_reverse($observations) as $observation) {
            if (is_array($observation) && isset($observation['devices']) && is_array($observation['devices'])) {
                return $observation['devices'];
            }
        }

        return [];
    }
}
