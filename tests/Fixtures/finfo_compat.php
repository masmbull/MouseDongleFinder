<?php

/*
 * Minimal test-only stand-in for ext-fileinfo. Some local PHP CLI builds do not
 * load fileinfo, while Flysystem's local adapter instantiates finfo in its
 * constructor even when no MIME detection is performed by the application.
 * This shim lets Storage::fake() work in those environments only; production
 * still requires ext-fileinfo as documented in README.md.
 */
if (! class_exists('finfo', false)) {
    if (! defined('FILEINFO_MIME_TYPE')) {
        define('FILEINFO_MIME_TYPE', 16);
    }

    class finfo
    {
        public function __construct(int $flags = 0, string $magicFile = '') {}

        public function buffer(string $string, int $flags = 0, $context = null): string|false
        {
            return $this->detectMimeType($string);
        }

        public function file(string $filename, int $flags = 0, $context = null): string|false
        {
            $contents = @file_get_contents($filename);

            return $contents === false ? false : $this->detectMimeType($contents);
        }

        private function detectMimeType(string $contents): string
        {
            if (str_starts_with($contents, "\x89PNG\r\n\x1a\n")) {
                return 'image/png';
            }

            if (str_starts_with($contents, "\xFF\xD8\xFF")) {
                return 'image/jpeg';
            }

            if (str_starts_with($contents, 'GIF87a') || str_starts_with($contents, 'GIF89a')) {
                return 'image/gif';
            }

            if (str_starts_with($contents, 'RIFF') && substr($contents, 8, 4) === 'WEBP') {
                return 'image/webp';
            }

            if (str_starts_with($contents, '%PDF')) {
                return 'application/pdf';
            }

            return 'application/octet-stream';
        }
    }
}
