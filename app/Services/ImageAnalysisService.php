<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Optional OCR. Manual entry is always available when OCR cannot be used.
 */
class ImageAnalysisService
{
    /**
     * @return array{available: bool, extracted_text: string|null, fields: array<string, string|null>, message: string|null}
     */
    public function analyze(UploadedFile $file, string $photoType): array
    {
        $serviceUrl = $this->stringOrNull(config('services.ocr.url'));

        if ($serviceUrl !== null) {
            $text = $this->analyzeWithService($file, $serviceUrl, $photoType);

            if ($text !== null) {
                return $this->result($text);
            }

            return $this->unavailable('OCR is not configured correctly: the configured OCR service did not return text. Manual entry remains available.');
        }

        $tesseract = $this->findTesseract();

        if ($tesseract !== null) {
            $text = $this->analyzeWithTesseract($file, $tesseract);

            if ($text !== null) {
                return $this->result($text);
            }

            return $this->unavailable('OCR is not configured correctly: Tesseract did not return text. Manual entry remains available.');
        }

        return $this->unavailable();
    }

    /**
     * @return array<string, string|null>
     */
    public function extractFields(string $text): array
    {
        $fields = [
            'fcc_id' => null,
            'ic_id' => null,
            'model' => null,
            'serial' => null,
            'vid' => null,
            'pid' => null,
        ];

        if (preg_match('/\bFCC\s*ID\s*[:#]?\s*([A-Z0-9-]{6,})/i', $text, $matches) === 1) {
            $fields['fcc_id'] = strtoupper(trim($matches[1]));
        }

        if (preg_match('/\bIC\s*(?:ID)?\s*[:#]?\s*([A-Z0-9-]{6,})/i', $text, $matches) === 1) {
            $fields['ic_id'] = strtoupper(trim($matches[1]));
        }

        if (preg_match('/\bModel(?:\s*(?:Number|No\.?|#))?\s*[:#]?\s*([A-Za-z0-9][A-Za-z0-9._-]{1,})/i', $text, $matches) === 1) {
            $fields['model'] = trim($matches[1]);
        }

        if (preg_match('/(?:S\/N|Serial(?:\s*(?:Number|No\.?))?)\s*[:#]?\s*([A-Za-z0-9][A-Za-z0-9-]{3,})/i', $text, $matches) === 1) {
            $fields['serial'] = trim($matches[1]);
        }

        if (preg_match('/\bVID[_\s:-]*([0-9A-Fa-f]{4})\b/', $text, $matches) === 1) {
            $fields['vid'] = strtoupper($matches[1]);
        }

        if (preg_match('/\bPID[_\s:-]*([0-9A-Fa-f]{4})\b/', $text, $matches) === 1) {
            $fields['pid'] = strtoupper($matches[1]);
        }

        return $fields;
    }

    private function analyzeWithService(UploadedFile $file, string $url, string $photoType): ?string
    {
        try {
            $request = Http::connectTimeout(3)
                ->timeout(30)
                ->attach('image', (string) file_get_contents($file->getRealPath()), $file->getClientOriginalName());

            $apiKey = $this->stringOrNull(config('services.ocr.key'));

            if ($apiKey !== null) {
                $request = $request->withToken($apiKey);
            }

            $response = $request->post($url, ['photo_type' => $photoType]);

            if (! $response->successful()) {
                Log::warning('OCR service returned an error.', ['status' => $response->status()]);

                return null;
            }

            $payload = $response->json();
            $text = is_array($payload) ? ($payload['text'] ?? $payload['extracted_text'] ?? null) : null;

            return is_string($text) && trim($text) !== '' ? $text : null;
        } catch (Throwable $exception) {
            Log::warning('OCR service request failed.', ['message' => $exception->getMessage()]);

            return null;
        }
    }

    private function findTesseract(): ?string
    {
        try {
            $binary = (new ExecutableFinder)->find('tesseract');

            return is_string($binary) && $binary !== '' ? $binary : null;
        } catch (Throwable $exception) {
            Log::warning('Could not look up Tesseract.', ['message' => $exception->getMessage()]);

            return null;
        }
    }

    private function analyzeWithTesseract(UploadedFile $file, string $binary): ?string
    {
        $temporaryPath = tempnam(sys_get_temp_dir(), 'mdf-ocr-');

        if ($temporaryPath === false) {
            return null;
        }

        try {
            $extension = $file->getClientOriginalExtension();
            $imagePath = $extension !== '' ? $temporaryPath.'.'.$extension : $temporaryPath;

            if (! copy($file->getRealPath(), $imagePath)) {
                return null;
            }

            $process = new Process([$binary, $imagePath, 'stdout', '-l', 'eng']);
            $process->setTimeout(30);
            $process->run();

            if (is_file($imagePath)) {
                @unlink($imagePath);
            }

            if (! $process->isSuccessful()) {
                Log::warning('Tesseract failed.', ['error' => trim($process->getErrorOutput())]);

                return null;
            }

            $text = trim($process->getOutput());

            return $text !== '' ? $text : null;
        } catch (Throwable $exception) {
            Log::warning('Tesseract OCR failed.', ['message' => $exception->getMessage()]);

            return null;
        } finally {
            if (is_file($temporaryPath)) {
                @unlink($temporaryPath);
            }
        }
    }

    /**
     * @return array{available: bool, extracted_text: string|null, fields: array<string, string|null>, message: string|null}
     */
    private function result(string $text): array
    {
        return [
            'available' => true,
            'extracted_text' => $text,
            'fields' => $this->extractFields($text),
            'message' => null,
        ];
    }

    /**
     * @return array{available: bool, extracted_text: string|null, fields: array<string, string|null>, message: string|null}
     */
    private function unavailable(?string $message = null): array
    {
        return [
            'available' => false,
            'extracted_text' => null,
            'fields' => [
                'fcc_id' => null,
                'ic_id' => null,
                'model' => null,
                'serial' => null,
                'vid' => null,
                'pid' => null,
            ],
            'message' => $message ?? 'OCR is not configured. Set OCR_SERVICE_URL (optionally OCR_API_KEY) or install Tesseract on PATH. Manual entry remains available.',
        ];
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
