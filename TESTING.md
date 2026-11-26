# MouseDongleFinder — Testing Reference

The suite uses **PHPUnit 12** through `php artisan test`. It runs entirely in memory
(`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:` in `phpunit.xml`) with `RefreshDatabase`, so it never
touches the real `database/database.sqlite`, real hardware, the network, or downloaded files.

## Commands

```powershell
php artisan test --compact                 # full suite
php artisan test --compact tests/Feature/MatchingTest.php
php artisan test --compact --filter=test_name
vendor/bin/phpunit                         # direct runner
vendor/bin/pint --format agent             # formatting
```

**Last verified result:** `146 tests, 5,212 assertions, all passing` (2026-11-23).

## Test inventory

| File | Tests | What it proves |
|---|---:|---|
| `tests/Feature/MouseCrudTest.php` | 7 | Create / show / update / delete mouse, required brand validation, generated `MS-` ID |
| `tests/Feature/DongleCrudTest.php` | 7 | Create / show / update / delete dongle, unknown-brand dongles allowed, uppercase VID/PID |
| `tests/Feature/MatchingTest.php` | 6 | Create match, status transitions, duplicate-active guard (both directions and on transition), remove match, notes |
| `tests/Feature/SearchTest.php` | 4 | Search mice/dongles by ID, brand, model, description |
| `tests/Feature/PhotoUploadTest.php` | 7 | Photo upload/replace/remove, validation, placeholder URL, file deleted from `Storage::fake('public')` |
| `tests/Feature/DashboardTest.php` | 4 | All dashboard statistics are computed from the DB and change after CRUD |
| `tests/Feature/DeviceStatusFilterTest.php` | 4 | Mouse/dongle status filters, including `unmatched` |
| `tests/Feature/ApiValidationContractTest.php` | 8 | 404 for missing ids, enum validation, 422 contracts, error message shapes |
| `tests/Feature/UsbScanTest.php` | 6 | USB scan endpoint with a mocked `UsbDeviceScanner`, persistence, show/update/destroy |
| `tests/Feature/UsbScanEndpointTest.php` | 6 | `scan` (save true/false), `latest`, `index`, and the `/live-match` endpoint shape |
| `tests/Feature/LivePairingTest.php` | 9 | Live Pairing Detector: topology attribution, profile/name matching, confidence levels, evidence, empty/non-Windows paths, nothing auto-saved |
| `tests/Feature/MatchingSessionTest.php` | 23 | Dongle Lab: receiver labelling A/B/C with brand/port/confidence, identical VID:PID on distinct ports, connected-device list with `is_new`, user-declared dongle candidates, attribution via VID/PID and hardware IDs, manual labels, observation persistence, complete/delete, validation, failure paths |
| `tests/Feature/IdentificationTest.php` | 7 | Rule-based engine, 046D/C52B family, suggestion queries, storage |
| `tests/Feature/IdentificationEndpointTest.php` | 7 | `analyze`, `store`, `index`, `show`, `save-analysis`, `ocr` (available/unavailable paths) |
| `tests/Feature/ResearchEndpointTest.php` | 5 | `research/search` and `research/software`, with/without API config; no fabricated results |
| `tests/Feature/UsbIdEndpointTest.php` | 5 | `usb-ids/lookup`, uppercase normalisation, mocked `usb-ids/refresh` |
| `tests/Feature/DownloadTest.php` | 6 | Original download record/verify tests |
| `tests/Feature/DownloadEndpointTest.php` | 8 | `fetch` (HTTPS/executable confirmation), SHA-256, saved path, `verify`, `open`, `index`, `show`, `delete` |
| `tests/Feature/DevicePhotoApiTest.php` | 3 | Reference photos eager-loaded in API payloads, morph scoping, URL accessor |
| `tests/Feature/AutoDiscoverTest.php` | 9 | Auto-discover: exact-name/profile/VID-PID identity, auto-registered mouse/dongle + suspected match, ambiguous identical dongles, existing matches reused, scanner failure with no writes |
| `tests/Feature/DatabaseSeederSampleDataTest.php` | 1 | Global seeder counts + reference-photo coverage/count assertions (47 brands, 91 profiles, 65 mice, 56 dongles, 3,427 vendors, 2,878 products), `is_seed` flags, brand coverage, real research URLs, frequency/confidence rules, match invariants, idempotency |
| `tests/Feature/ExampleTest.php` | 1 | SPA entry route returns 200 (`withoutVite()` in base TestCase) |
| `tests/Unit/ExampleTest.php` | 1 | PHPUnit sanity test |

## Test conventions

- Extend `Tests\TestCase` (it calls `withoutVite()` and loads a small `finfo` shim for local PHP CLIs
  that lack `ext-fileinfo`; production still requires the extension).
- Use factories for models; check factory states before creating ad-hoc records.
- Mock external boundaries: `$this->mock(...)` for scanners/detectors, `Http::fake()` +
  `Http::preventStrayRequests()` for web research, ID refresh and downloads, `Storage::fake('public')`
  for photos.
- Use `RefreshDatabase`; never assume records from the real DB exist.
- Assert response shape and status code, not just "something happened".

## Manual verification performed (in addition to the suite)

- `php artisan migrate:fresh --seed --force` → 30 mice, 18 dongles, 13 identifications,
  36 mouse profiles, 25 vendors, 68 products, 6 sample matches.
- `POST /api/usb-scans/scan` against the real Windows host → 8 real devices with VID/PID and driver
  metadata.
- `POST /api/usb-scans/live-match` against the real host → 1 real composite receiver candidate with
  8 connected HID/Mouse devices and one honest low-confidence "HID-compliant mouse" suggestion.
- Dongle Lab: created a real session (dongle A, VID 3151:3020, port `Port_#0004`), observed with no
  extra mouse powered on → connected mouse devices listed with `is_new=false`, one actionable
  user-declared candidate for dongle A; manually labelled the dongle "Mikuso" and confirmed it persisted;
  browser navigation through all four steps produced no console errors. Test sessions were deleted.
- Global seeder: `migrate:fresh --seed --force` → 47 brands, 91 profiles, 65 mice, 56 dongles,
  52 identifications, 3,427 vendors, 2,878 products, 10 sample matches, **429 reference photos across
  116 of 121 devices**, 0 missing files, ~4 s.
- Browser end-to-end: all 9 pages render without console errors; created and deleted a mouse through
  the UI; registered a detected receiver inline through the Live Pairing panel; dashboard numbers
  updated from the DB. All temporary test records were removed afterwards.
- `npm run build` succeeds; Vite manifest contains the SPA entry and all lazy view chunks.

## Known test caveats

- Unknown **GET** API paths return 200 because `routes/web.php` has a catch-all SPA route; tests assert
  this actual behaviour rather than an assumed 404.
- The download test writes one tiny fake file under a uniquely named `downloads/{Manufacturer}/{Model}/`
  path to verify the real save logic, then removes it.
- Live detection tests mock the detector; no CI machine needs a wireless mouse attached.
