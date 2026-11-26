# Changelog

All notable changes to MouseDongleFinder are documented in this file.

## [Unreleased]

### Added
- Comprehensive documentation suite (README, API, DATABASE, TESTING, TROUBLESHOOTING, USER_GUIDE, HANDOVER, RESEARCH, CONTRIBUTING, SECURITY)
- Git repository initialization and GitHub remote

### Changed
- None

### Deprecated
- None

### Removed
- None

### Fixed
- None

## [1.0.0] - 2026-11-22

### Added

**Core app**
- Laravel 13 + Vue 3 + Tailwind CSS v4 + SQLite single-user desktop app
- Mouse database: CRUD, search, filter, photos, detail pages
- Dongle database: CRUD, unknown-brand dongles allowed, VID/PID, hardware IDs, photos, status filters
- Matching Center: two-panel review, manual physical-test workflow, four match states, duplicate-active match protection
- Match List: search, status filter, edit notes, remove
- USB Detective: real Windows PnP scan (PowerShell), device details, driver metadata, receiver candidates
- Live Pairing Detector: plug a dongle, switch the mouse on, Windows-reported HID devices grouped per receiver and matched to mice with evidence/confidence
- Dongle Lab: batch session to label all plugged dongles (A/B/C), brand guess, USB port, observe one mouse at a time, save match
- Auto-Discover: one-click discovery that reads real HID connections, identifies the mouse from the global catalogue, auto-registers missing devices, proposes suspected matches
- Identification engine: evidence-based inference with explicit confidence and "remaining unknowns"
- Web research: configurable search API or honest official-link fallback (keyless Wikipedia MediaWiki search by default)
- Safe downloads: official links, HTTPS-only, SHA-256, no automatic execution
- Optional photo OCR (configured service or local Tesseract), manual entry always available
- Reference photo galleries: real product images per sample mouse/dongle stored locally with source links
- Development seed data flagged with `is_seed`; empty database fully supported

**Backend**
- `app/Http/Controllers/Api/` — JSON API controllers (Dashboard, Mouse, Dongle, DeviceMatch, UsbScan, Identification, Download, UsbId, Research)
- `app/Models/` — Eloquent models (Mouse, Dongle, DeviceMatch, Identification, Download, UsbScan, UsbVendor, UsbProduct, MouseProfile, MatchingSession, DevicePhoto)
- `app/Services/` — UsbDeviceScanner, LivePairingDetector, DongleLabService, UsbIdDatabase, IdentificationEngine, WebResearchService, DownloadService, ImageAnalysisService, AutoDiscoverService, Concerns/RunsWindowsProcess
- API contract with paginated JSON responses, `photo_url` and `device_match_status` accessors, eager-loaded `reference_photos`

**Frontend**
- `resources/js/views/` — 10 pages (Dashboard, MouseDatabase, DongleDatabase, DongleDetail, MatchingCenter, MatchList, UsbDetective, NotFound)
- `resources/js/components/` — 16 reusable UI components
- `resources/js/composables/` — useToast, useConfirm, useFormat, useDevicePhotos
- `resources/ps/` — scan-usb.ps1, scan-connected-mice.ps1 Windows PowerShell scripts

**Database**
- 14 migrations (mice, dongles, device_matches, usb_scans, identifications, downloads, usb_vendors, usb_products, device_photos, mouse_profiles, matching_sessions, plus Laravel defaults)
- 9 factories, seeders including usb-ids-snapshot.php (3,427 vendors, 2,878 products)
- Reference photo manifest and resolved paths

**Documentation**
- README.md — end-user setup and feature list
- HANDOVER.md — AI agent handover (architecture, API contract, data model, conventions)
- RESEARCH.md — technical research, comparison matrix, engineering decisions
- USER_GUIDE.md — step-by-step usage incl. live dongle→mouse workflow
- API.md — every endpoint with parameters, request bodies and response shapes
- DATABASE.md — schema, relations, ID formats, seed policy, backup
- TESTING.md — full test inventory and commands
- TROUBLESHOOTING.md — common Windows/PHP/Vite/USB issues and fixes
- AGENTS.md — Laravel Boost rules and code style

**Testing**
- 132 tests / 1,315 assertions covering CRUD, search, photos, matching, dashboard, every API endpoint, validation contracts, seeder integrity

## Earlier

Development history is recorded in `HANDOVER.md` §10 (change log of this handover).