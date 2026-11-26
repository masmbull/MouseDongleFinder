<?php

namespace App\Services;

use App\Models\Download;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Downloads files to a controlled local folder. Never executes anything.
 */
class DownloadService
{
    public const MAX_BYTES = 52428800; // 50 MB

    private const ALLOWED_EXTENSIONS = ['exe', 'msi', 'zip', 'pdf', 'dmg', 'bin', 'hex', 'inf', 'txt'];

    private const EXECUTABLE_EXTENSIONS = ['exe', 'msi'];

    /** @var array<int, string> */
    private const OFFICIAL_HOSTS = [
        'logitech.com', 'logi.com', 'razer.com', 'microsoft.com',
    ];

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function fetch(array $input): array
    {
        $url = $this->stringOrNull($input['source_url'] ?? null);
        $this->assertHttpsUrl($url);

        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new InvalidArgumentException('URLs containing credentials are not allowed.');
        }

        $extension = strtolower(pathinfo((string) (parse_url($url, PHP_URL_PATH) ?? ''), PATHINFO_EXTENSION));

        if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new InvalidArgumentException('File type .'.$extension.' is not allowed. Allowed types: '.implode(', ', self::ALLOWED_EXTENSIONS).'.');
        }

        $confirmed = (bool) ($input['confirmed'] ?? false);

        if (in_array($extension, self::EXECUTABLE_EXTENSIONS, true) && ! $confirmed) {
            throw new InvalidArgumentException('This is an executable file. Confirm explicitly that you trust the source before downloading it.');
        }

        if (! $this->isHostReachable($host)) {
            throw new RuntimeException('The download host could not be resolved: '.$host);
        }

        $response = Http::connectTimeout(5)
            ->timeout(30)
            ->withOptions([
                'stream' => true,
                'allow_redirects' => ['max' => 5, 'strict' => true, 'referer' => false],
            ])
            ->get($url);

        if (! $response->successful()) {
            throw new RuntimeException('The download server returned HTTP '.$response->status().'.');
        }

        $finalUrl = (string) $response->effectiveUri();
        $this->assertHttpsUrl($finalUrl, 'The download was redirected to an unsafe URL.');

        $finalHost = strtolower((string) (parse_url($finalUrl, PHP_URL_HOST) ?? ''));
        $finalExtension = strtolower(pathinfo((string) (parse_url($finalUrl, PHP_URL_PATH) ?? ''), PATHINFO_EXTENSION));

        if ($finalHost === '' || ! in_array($finalExtension, self::ALLOWED_EXTENSIONS, true)) {
            throw new RuntimeException('The final redirect target is not an allowed download URL.');
        }

        if (in_array($finalExtension, self::EXECUTABLE_EXTENSIONS, true) && ! $confirmed) {
            throw new InvalidArgumentException('The redirected file is an executable. Explicit confirmation is required.');
        }

        $contentLength = (int) ($response->header('Content-Length') ?? 0);

        if ($contentLength > self::MAX_BYTES) {
            throw new RuntimeException('The remote file is larger than the 50 MB download limit.');
        }

        $manufacturer = $this->safeSegment($input['manufacturer'] ?? null);
        $model = $this->safeSegment($input['model'] ?? null);
        $targetDirectory = base_path('downloads').DIRECTORY_SEPARATOR.$manufacturer.DIRECTORY_SEPARATOR.$model;

        if (! is_dir($targetDirectory) && ! mkdir($targetDirectory, 0775, true) && ! is_dir($targetDirectory)) {
            throw new RuntimeException('Could not create the download directory.');
        }

        $fileName = $this->sanitizeFilename($input['filename'] ?? null, $finalExtension);
        $destination = $this->uniquePath($targetDirectory, $fileName);
        $temporaryPath = tempnam(sys_get_temp_dir(), 'mdf-download-');

        if ($temporaryPath === false) {
            throw new RuntimeException('Could not create a temporary file for the download.');
        }

        try {
            $handle = fopen($temporaryPath, 'wb');

            if ($handle === false) {
                throw new RuntimeException('Could not open the temporary download file.');
            }

            $written = 0;
            $body = $response->toPsrResponse()->getBody();

            while (! $body->eof()) {
                $chunk = $body->read(8192);

                if ($chunk === '') {
                    break;
                }

                $written += strlen($chunk);

                if ($written > self::MAX_BYTES) {
                    fclose($handle);
                    throw new RuntimeException('The download exceeded the 50 MB limit and was stopped.');
                }

                fwrite($handle, $chunk);
            }

            fclose($handle);

            if (! rename($temporaryPath, $destination)) {
                throw new RuntimeException('Could not move the downloaded file into place.');
            }
        } catch (Throwable $exception) {
            if (is_file($temporaryPath)) {
                @unlink($temporaryPath);
            }

            throw $exception;
        }

        return [
            'filename' => basename($destination),
            'file_path' => $destination,
            'source_url' => $url,
            'final_url' => $finalUrl,
            'source_type' => $this->sourceType($input['source_type'] ?? null, $finalHost),
            'manufacturer' => $manufacturer,
            'model' => $model,
            'software_type' => $this->stringOrNull($input['software_type'] ?? null),
            'file_size' => (int) filesize($destination),
            'sha256_hash' => hash_file('sha256', $destination) ?: null,
        ];
    }

    /**
     * @return array{verified: bool, computed_hash: string|null, stored_hash: string|null, error?: string}
     */
    public function verify(Download $download): array
    {
        $path = $this->resolvePath($download->file_path);

        if ($path === null) {
            return [
                'verified' => false,
                'computed_hash' => null,
                'stored_hash' => $download->sha256_hash,
                'error' => 'File not found',
            ];
        }

        $computedHash = hash_file('sha256', $path) ?: null;

        return [
            'verified' => $computedHash !== null && hash_equals((string) $download->sha256_hash, $computedHash),
            'computed_hash' => $computedHash,
            'stored_hash' => $download->sha256_hash,
        ];
    }

    /**
     * Opens the containing folder in Explorer. The downloaded file is never launched.
     *
     * @return array{opened: bool, path: string, error?: string}
     */
    public function open(Download $download): array
    {
        $path = $this->resolvePath($download->file_path);
        $folder = $path !== null ? dirname($path) : base_path('downloads');

        if (PHP_OS_FAMILY !== 'Windows') {
            return [
                'opened' => false,
                'path' => $folder,
                'error' => 'Opening folders is only supported on Windows.',
            ];
        }

        try {
            $process = new Process(['explorer.exe', $folder]);
            $process->setTimeout(10);
            $process->run();

            return ['opened' => true, 'path' => $folder];
        } catch (Throwable $exception) {
            return [
                'opened' => false,
                'path' => $folder,
                'error' => $exception->getMessage(),
            ];
        }
    }

    public function deleteFile(Download $download): bool
    {
        $path = $this->resolvePath($download->file_path);

        if ($path !== null && is_file($path)) {
            return @unlink($path);
        }

        return false;
    }

    private function assertHttpsUrl(?string $url, string $message = 'Only HTTPS URLs can be downloaded.'): void
    {
        if ($url === null || filter_var($url, FILTER_VALIDATE_URL) === false || strtolower((string) parse_url($url, PHP_URL_SCHEME)) !== 'https') {
            throw new InvalidArgumentException($message);
        }
    }

    private function isHostReachable(string $host): bool
    {
        if ($host === '') {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return true;
        }

        return gethostbyname($host) !== $host;
    }

    private function sourceType(mixed $provided, string $host): string
    {
        if (in_array($provided, ['official', 'third-party', 'unknown'], true)) {
            return $provided;
        }

        foreach (self::OFFICIAL_HOSTS as $officialHost) {
            if ($host === $officialHost || str_ends_with($host, '.'.$officialHost)) {
                return 'official';
            }
        }

        return 'unknown';
    }

    private function safeSegment(mixed $value): string
    {
        $value = is_string($value) ? $value : '';
        $value = (string) preg_replace('/[^A-Za-z0-9._-]+/', '_', $value);
        $value = trim($value, '._-');

        return $value !== '' ? substr($value, 0, 60) : 'Unknown';
    }

    private function sanitizeFilename(mixed $value, string $fallbackExtension): string
    {
        $name = is_string($value) ? str_replace('\\', '/', $value) : '';
        $name = basename($name);
        $name = (string) preg_replace('/[\x00-\x1F\x7F]/', '', $name);
        $name = (string) preg_replace('/[^A-Za-z0-9._-]/', '_', $name);
        $name = trim($name, '.');

        if ($name === '') {
            $name = 'download';
        }

        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            $name = $name.'.'.$fallbackExtension;
        }

        return substr($name, 0, 120);
    }

    private function uniquePath(string $directory, string $fileName): string
    {
        $extension = pathinfo($fileName, PATHINFO_EXTENSION);
        $baseName = pathinfo($fileName, PATHINFO_FILENAME);
        $candidate = $directory.DIRECTORY_SEPARATOR.$fileName;
        $suffix = 1;

        while (is_file($candidate)) {
            $candidate = $directory.DIRECTORY_SEPARATOR.$baseName.'-'.$suffix.($extension !== '' ? '.'.$extension : '');
            $suffix++;
        }

        return $candidate;
    }

    private function resolvePath(string $storedPath): ?string
    {
        $candidates = [$storedPath];

        if (! str_starts_with($storedPath, DIRECTORY_SEPARATOR) && ! preg_match('/^[A-Za-z]:[\\\\\/]/', $storedPath)) {
            $candidates[] = base_path($storedPath);
            $candidates[] = storage_path('app'.DIRECTORY_SEPARATOR.$storedPath);
        }

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
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
