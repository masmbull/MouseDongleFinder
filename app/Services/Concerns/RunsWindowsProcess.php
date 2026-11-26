<?php

namespace App\Services\Concerns;

use Symfony\Component\Process\Process;

/**
 * Runs a child process without Laravel's .env variables in its environment.
 *
 * Windows PowerShell 5.1 can fail to start through Symfony Process with error
 * 0x8009001d when those variables are inherited. Symfony reads env from
 * getenv(), $_SERVER and $_ENV, so the keys are removed from all three sources
 * only while the process runs and are restored immediately afterwards.
 */
trait RunsWindowsProcess
{
    protected function runWithCleanEnvironment(Process $process): void
    {
        $backup = [];

        foreach ($this->loadedEnvKeys() as $key) {
            $backup[$key] = [
                'getenv' => getenv($key),
                'server' => $_SERVER[$key] ?? null,
                'env' => $_ENV[$key] ?? null,
            ];

            if ($backup[$key]['getenv'] !== false) {
                putenv($key);
            }
            unset($_SERVER[$key], $_ENV[$key]);
        }

        try {
            $process->run();
        } finally {
            foreach ($backup as $key => $values) {
                if ($values['getenv'] !== false) {
                    putenv($key.'='.$values['getenv']);
                }
                if ($values['server'] !== null) {
                    $_SERVER[$key] = $values['server'];
                }
                if ($values['env'] !== null) {
                    $_ENV[$key] = $values['env'];
                }
            }
        }
    }

    /**
     * @return array<int, string>
     */
    protected function loadedEnvKeys(): array
    {
        $path = base_path('.env');

        if (! is_file($path)) {
            return [];
        }

        $keys = [];

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = ltrim(trim($line));

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (str_starts_with($line, 'export ')) {
                $line = substr($line, 7);
            }

            $separator = strpos($line, '=');

            if ($separator !== false) {
                $key = trim(substr($line, 0, $separator));

                if ($key !== '') {
                    $keys[] = $key;
                }
            }
        }

        return $keys;
    }
}
