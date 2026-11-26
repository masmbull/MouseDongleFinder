<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Searches the web using a configured search API when available, otherwise a
 * keyless public Wikipedia search. Official direct links are always added.
 * No result is ever simulated.
 */
class WebResearchService
{
    private const TIMEOUT_SECONDS = 10;

    private const WIKIPEDIA_API = 'https://en.wikipedia.org/w/api.php';

    private const UNAVAILABLE_MESSAGE = 'Live web search is temporarily unavailable. Verified official direct links are provided below instead.';

    /** @var array<string, string> */
    private const VENDOR_MAP = [
        '046D' => 'Logitech',
        '1532' => 'Razer',
        '045E' => 'Microsoft',
    ];

    /**
     * @param  array<int, string>  $queries
     * @param  array<string, mixed>  $context
     * @return array{available: bool, message: string|null, results: array<int, array<string, mixed>>, searches: array<int, array<string, mixed>>}
     */
    public function search(array $queries, array $context = []): array
    {
        $url = $this->stringOrNull(config('services.research.url'));
        $key = $this->stringOrNull(config('services.research.key'));
        $searches = [];
        $results = [];

        foreach ($queries as $query) {
            $queryResults = null;

            if ($url !== null && $key !== null) {
                try {
                    $response = Http::withToken($key)
                        ->connectTimeout(3)
                        ->timeout(self::TIMEOUT_SECONDS)
                        ->post($url, ['query' => $query]);

                    if ($response->successful()) {
                        $queryResults = $this->parseResults($response->json(), $query);
                        $searches[] = ['query' => $query, 'status' => 'ok', 'source' => 'configured_api'];
                    } else {
                        $searches[] = ['query' => $query, 'status' => 'failed', 'error' => 'HTTP '.$response->status()];
                    }
                } catch (Throwable $exception) {
                    Log::warning('Configured research API failed, falling back to Wikipedia.', ['query' => $query, 'message' => $exception->getMessage()]);
                    $searches[] = ['query' => $query, 'status' => 'failed', 'error' => $exception->getMessage()];
                }
            }

            if ($queryResults === null) {
                try {
                    $queryResults = $this->searchWikipedia($query);
                    $searches[] = $queryResults === []
                        ? ['query' => $query, 'status' => 'empty', 'source' => 'wikipedia']
                        : ['query' => $query, 'status' => 'ok', 'source' => 'wikipedia'];
                } catch (Throwable $exception) {
                    Log::warning('Wikipedia research request failed.', ['query' => $query, 'message' => $exception->getMessage()]);
                    $searches[] = ['query' => $query, 'status' => 'failed', 'error' => $exception->getMessage()];
                    $queryResults = [];
                }
            }

            $results = array_merge($results, $queryResults);
        }

        $anySucceeded = array_filter($searches, fn (array $search) => $search['status'] === 'ok') !== [];

        return [
            'available' => $anySucceeded,
            'message' => $anySucceeded ? null : self::UNAVAILABLE_MESSAGE,
            'results' => $this->deduplicate(array_merge($results, $this->officialLinks($context))),
            'searches' => $searches,
        ];
    }

    /**
     * Keyless public search using the Wikipedia MediaWiki API. Returns real
     * encyclopedia pages with their canonical URLs and search snippets.
     *
     * @return array<int, array<string, mixed>>
     */
    private function searchWikipedia(string $query): array
    {
        $attempts = $this->wikipediaQueries($query);
        $items = [];

        foreach ($attempts as $attempt) {
            $response = Http::withHeaders([
                'User-Agent' => 'MouseDongleFinder/1.0 (local device research)',
                'Accept' => 'application/json',
            ])->connectTimeout(3)
                ->timeout(self::TIMEOUT_SECONDS)
                ->get(self::WIKIPEDIA_API, [
                    'action' => 'query',
                    'list' => 'search',
                    'srsearch' => $attempt,
                    'srlimit' => 6,
                    'format' => 'json',
                ]);

            if (! $response->successful()) {
                continue;
            }

            $found = $response->json('query.search');

            if (is_array($found) && $found !== []) {
                $items = $found;

                break;
            }
        }

        if ($items === []) {
            return [];
        }

        $results = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $title = $this->stringOrNull($item['title'] ?? null);

            if ($title === null) {
                continue;
            }

            $snippet = $this->stringOrNull($item['snippet'] ?? null) ?? '';
            $snippet = trim(preg_replace('/\s+/', ' ', strip_tags($snippet)) ?: '');

            $results[] = $this->researchLink(
                $title,
                'https://en.wikipedia.org/wiki/'.rawurlencode(str_replace(' ', '_', $title)),
                'Wikipedia',
                false,
                $this->categoryForQuery($query),
                $snippet !== '' ? $snippet : 'Wikipedia article for '.$title,
            );
        }

        return $results;
    }

    /**
     * Wikipedia full-text search is strict: exact VID/PID codes and intent words
     * ("driver", "pairing") often return nothing. Try the original query, then a
     * simplified phrase containing only the meaningful device words.
     *
     * @return array<int, string>
     */
    private function wikipediaQueries(string $query): array
    {
        $attempts = [trim($query)];

        $withoutCodes = preg_replace('/\b[0-9A-F]{4}\b/i', ' ', $query) ?? $query;
        $withoutIntent = preg_replace('/\b(driver|drivers|software|pairing|manual|download|identification|receiver compatibility)\b/i', ' ', $withoutCodes) ?? $withoutCodes;
        $tokens = preg_split('/\s+/', mb_strtolower(trim($withoutIntent))) ?: [];
        $tokens = array_values(array_filter($tokens, fn (string $token): bool => mb_strlen($token) >= 3));
        $meaningful = implode(' ', array_slice($tokens, 0, 5));

        if ($meaningful !== '' && ! in_array($meaningful, $attempts, true)) {
            $attempts[] = $meaningful;
        }

        if (count($tokens) >= 2) {
            $short = implode(' ', array_slice($tokens, 0, 2));

            if (! in_array($short, $attempts, true)) {
                $attempts[] = $short;
            }
        }

        return array_slice(array_values(array_unique(array_filter($attempts))), 0, 4);
    }

    private function categoryForQuery(string $query): string
    {
        $query = mb_strtolower($query);

        if (str_contains($query, 'pairing')) {
            return 'pairing';
        }
        if (str_contains($query, 'driver') || str_contains($query, 'software')) {
            return 'driver';
        }
        if (str_contains($query, 'manual')) {
            return 'manual';
        }
        if (str_contains($query, 'compatib')) {
            return 'receiver_compatibility';
        }

        return 'device_identification';
    }

    /**
     * Curated, known-good official software links for the detected manufacturer.
     *
     * @param  array<string, mixed>  $context
     * @return array{available: bool, message: string|null, results: array<int, array<string, mixed>>, searches: array<int, array<string, mixed>>}
     */
    public function software(array $context = []): array
    {
        $manufacturer = $this->stringOrNull($context['manufacturer'] ?? null);
        $vid = strtoupper((string) ($context['vid'] ?? ''));

        if ($manufacturer === null && isset(self::VENDOR_MAP[$vid])) {
            $manufacturer = self::VENDOR_MAP[$vid];
        }

        $software = [];

        if ($manufacturer === 'Logitech' || $vid === '046D') {
            $software[] = $this->softwareLink('Logi Options+', 'pairing', 'Official Logitech utility for supported receivers and devices.', 'https://support.logi.com/');
            $software[] = $this->softwareLink('Logitech Unifying Software', 'pairing', 'Official pairing software and troubleshooting for Unifying receivers.', 'https://support.logi.com/hc/en-us/articles/5470036605975-Unifying-Receiver-Software-Pairing-and-Troubleshooting');
        }

        if ($manufacturer === 'Razer' || $vid === '1532') {
            $software[] = $this->softwareLink('Razer Synapse', 'pairing', 'Official Razer configuration and pairing software.', 'https://www.razer.com/support');
        }

        if ($manufacturer === 'Microsoft' || $vid === '045E') {
            $software[] = $this->softwareLink('Microsoft Mouse and Keyboard Center', 'driver', 'Official Microsoft utility for supported mice, keyboards and receivers.', 'https://support.microsoft.com/');
        }

        $software = array_merge($software, array_map(
            fn (array $link) => $this->softwareLink($link['title'], $link['category'], $link['snippet'] ?? '', $link['url'], $link['official']),
            $this->officialLinks($context),
        ));

        return [
            'available' => true,
            'message' => null,
            'results' => $this->deduplicate($software),
            'searches' => [],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function parseResults(mixed $payload, string $query): array
    {
        if (! is_array($payload)) {
            return [];
        }

        $items = $payload['results'] ?? $payload['data'] ?? $payload['items'] ?? $payload['search_results'] ?? $payload;

        if (! is_array($items) || ! array_is_list($items)) {
            return [];
        }

        $results = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $url = $this->stringOrNull($item['url'] ?? $item['link'] ?? $item['href'] ?? null);

            if ($url === null) {
                continue;
            }

            $host = parse_url($url, PHP_URL_HOST) ?: 'web';
            $results[] = [
                'title' => $this->stringOrNull($item['title'] ?? $item['name'] ?? $item['heading'] ?? null) ?? $url,
                'url' => $url,
                'source' => $this->stringOrNull($item['source'] ?? $item['domain'] ?? $item['site_name'] ?? null) ?? $host,
                'official' => (bool) ($item['official'] ?? $this->isOfficialHost($host)),
                'category' => $this->stringOrNull($item['category'] ?? $item['type'] ?? null) ?? 'web',
                'snippet' => $this->stringOrNull($item['snippet'] ?? $item['description'] ?? $item['summary'] ?? null) ?? 'Result for: '.$query,
            ];
        }

        return array_slice($results, 0, 10);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<int, array<string, mixed>>
     */
    private function officialLinks(array $context): array
    {
        $vid = strtoupper((string) ($context['vid'] ?? ''));
        $pid = strtoupper((string) ($context['pid'] ?? ''));
        $manufacturer = $this->stringOrNull($context['manufacturer'] ?? null);

        if ($manufacturer === null && isset(self::VENDOR_MAP[$vid])) {
            $manufacturer = self::VENDOR_MAP[$vid];
        }

        $links = [
            $this->researchLink('USB ID Repository (usb.ids)', 'https://www.linux-usb.org/usb.ids', 'linux-usb.org', false, 'reference', 'Community-maintained VID/PID database.'),
            $this->researchLink('usb.ids web lookup', 'https://usb-ids.gowdy.us/', 'gowdy.us', false, 'lookup', 'Human-readable USB identifier lookup.'),
        ];

        if ($vid !== '') {
            $deviceHuntUrl = 'https://devicehunt.com/view/type/usb/vendor/'.$vid.($pid !== '' ? '/device/'.$pid : '');
            $links[] = $this->researchLink('DeviceHunt USB lookup', $deviceHuntUrl, 'devicehunt.com', false, 'lookup', 'Lookup for the detected VID and PID.');
        }

        if ($manufacturer === 'Logitech' || $vid === '046D') {
            $links[] = $this->researchLink('Logitech official support', 'https://support.logi.com/', 'logi.com', true, 'support', 'Official product support and downloads.');
            $links[] = $this->researchLink('Logitech receiver pairing', 'https://support.logi.com/hc/en-us/articles/5470036605975-Unifying-Receiver-Software-Pairing-and-Troubleshooting', 'logi.com', true, 'manual', 'Official pairing and troubleshooting instructions.');
        }

        if ($manufacturer === 'Razer' || $vid === '1532') {
            $links[] = $this->researchLink('Razer official support', 'https://www.razer.com/support', 'razer.com', true, 'support', 'Official product support and downloads.');
        }

        if ($manufacturer === 'Microsoft' || $vid === '045E') {
            $links[] = $this->researchLink('Microsoft official support', 'https://support.microsoft.com/', 'microsoft.com', true, 'support', 'Official product support and downloads.');
        }

        return $this->deduplicate($links);
    }

    /**
     * @return array<string, mixed>
     */
    private function researchLink(string $title, string $url, string $source, bool $official, string $category, string $snippet): array
    {
        return [
            'title' => $title,
            'url' => $url,
            'source' => $source,
            'official' => $official,
            'category' => $category,
            'snippet' => $snippet,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function softwareLink(string $name, string $type, string $description, string $url, bool $official = true): array
    {
        return [
            'name' => $name,
            'title' => $name,
            'type' => $type,
            'description' => $description,
            'url' => $url,
            'source' => parse_url($url, PHP_URL_HOST) ?: 'web',
            'official' => $official,
            'category' => $type,
            'snippet' => $description,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $links
     * @return array<int, array<string, mixed>>
     */
    private function deduplicate(array $links): array
    {
        $seen = [];
        $unique = [];

        foreach ($links as $link) {
            if (isset($seen[$link['url']])) {
                continue;
            }

            $seen[$link['url']] = true;
            $unique[] = $link;
        }

        return $unique;
    }

    private function isOfficialHost(string $host): bool
    {
        foreach (['logi.com', 'razer.com', 'microsoft.com', 'logitech.com'] as $officialHost) {
            if ($host === $officialHost || str_ends_with($host, '.'.$officialHost)) {
                return true;
            }
        }

        return false;
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
