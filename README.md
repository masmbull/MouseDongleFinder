# MouseDongleFinder

A focused local web app for matching wireless mouse USB receivers (dongles) with their mice, and for
investigating unknown receivers. Built with **Laravel 13 + Vue 3 + Tailwind CSS v4 + SQLite**.

It does one job: connect → scan → identify → research → find software → physical test → confirm match.

## Features

- Dashboard with real database statistics and recent activity
- Mouse database: add / edit / delete / search / filter / photos / detail pages
- Dongle database: unknown-brand dongles allowed, VID/PID, hardware IDs, photos, status filters
- Matching Center: two-panel review, manual physical-test workflow, four match states, duplicate-active
  match protection, confirm dialogs
- Match List: search, status filter, edit notes, remove
- USB Detective: real Windows PnP scan (PowerShell), device details, driver metadata, receiver candidates
- **Live Pairing Detector**: plug a dongle, switch the mouse on, and Windows-reported HID devices are
  grouped per receiver and matched to mice registered with only brand + model (with evidence/confidence)
- **Reference photo galleries**: multiple real product images per sample mouse/dongle (top, underside,
  receiver, markings) stored locally with source links — compare with the device in your hand
- **Auto-Discover**: plug dongles, switch a mouse ON, click once — the app reads the real Windows HID
  connection, identifies the mouse from the global catalogue, registers new devices automatically and
  proposes the pair (saved as `suspected` until you physically test it)
- **Dongle Lab**: plug *all* loose dongles into a hub at once → each gets a label (A/B/C), brand guess and USB
  port → switch mice on one at a time → observe which new HID device appeared → choose the mouse and dongle
  and save the match
- Identification engine: evidence-based inference with explicit confidence and "remaining unknowns"
- Web research: configurable search API or honest official-link fallback
- Software & safe downloads: official links, HTTPS-only, SHA-256, no automatic execution
- Optional photo OCR (configured service or local Tesseract), manual entry always available
- Development seed data clearly flagged with `is_seed`; empty database fully supported

## Requirements

- Windows (USB Detective requires PowerShell 5.1+)
- PHP 8.3 with `fileinfo`, `pdo_sqlite`, `mbstring`, `openssl`
- Composer 2
- Node.js + npm

## Installation

```powershell
cd C:\Projects\MouseDongleFinder
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate --force
php artisan storage:link
npm install
npm run build
composer run dev
```

Optional development sample data:

```powershell
php artisan db:seed --class=DatabaseSeeder
```

> If Composer complains about the missing `fileinfo` extension, enable it in the php.ini reported by
> `php --ini`, or add `--ignore-platform-req=ext-fileinfo` to the Composer command.

Open the URL printed by `composer run dev` (usually `http://127.0.0.1:8000`).

## Configuration (optional)

Add to `.env` to enable extra capabilities:

```dotenv
RESEARCH_API_URL=       # search API endpoint (expects {"results":[...]})
RESEARCH_API_KEY=       # search API key
OCR_SERVICE_URL=        # OCR service endpoint (expects {"text":"..."})
OCR_API_KEY=            # OCR service key
```

Without these, research returns official direct links (`available:false`) and OCR reports itself as
unavailable. Nothing is faked.

## Testing

```powershell
php artisan test --compact
vendor/bin/pint --dirty --format agent
npm run build
```

## Documentation

- `HANDOVER.md` — **read this first when continuing with another AI agent**: architecture, API contract,
  data model, conventions, gotchas.
- `RESEARCH.md` — global research, GitHub comparison matrix, license/security review, engineering decisions.
- `USER_GUIDE.md` — step-by-step usage, including the live dongle→mouse workflow.
- `API.md` — every endpoint with parameters, request bodies and response shapes.
- `DATABASE.md` — schema, relations, ID formats, seed policy, backup.
- `TESTING.md` — full test inventory and commands.
- `TROUBLESHOOTING.md` — common Windows/PHP/Vite/USB issues and fixes.
- `CONTRIBUTING.md` — how to contribute, scope rules, coding conventions.
- `SECURITY.md` — security design principles and vulnerability reporting.
- `CHANGELOG.md` — release history.

## Scope

This is intentionally not an IT asset manager. No employee management, ticketing, procurement, finance,
complex roles, or unrelated reporting.

## Data

All application data is local: `database/database.sqlite`, uploaded photos in `storage/app/public/`,
and downloaded drivers/manuals in `downloads/`. Back up these locations together.