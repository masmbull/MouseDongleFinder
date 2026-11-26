# MouseDongleFinder — Troubleshooting

## PHP / Composer

**`ext-fileinfo is missing` during `composer require/update`**
Enable `extension=fileinfo` in the php.ini reported by `php --ini` (needs admin rights on this
machine). Temporary workaround: add `--ignore-platform-req=ext-fileinfo` to the Composer command.
Production should have the extension enabled.

**Tests say `Vite manifest not found`**
Run `npm run build`. The test suite calls `withoutVite()` so it normally does not need built assets,
but serving the app requires `public/build/manifest.json`.

## Frontend

**Page is blank / only the sidebar appears**
Check the browser console. Rebuild with `npm run build`. `resources/views/app.blade.php` must use
`@vite(['resources/js/app.js'])`.

**Photos do not show**
Run `php artisan storage:link`. Uploaded files live in `storage/app/public/` and are served through
`public/storage`. If no photo exists, the app shows a placeholder SVG from `public/images/`.

## USB Detective

**`USB scan failed: Internal Windows PowerShell error ... 8009001d`**
Already handled in code: `UsbDeviceScanner` temporarily removes Laravel `.env` variables from
`getenv()`, `$_SERVER` and `$_ENV` while PowerShell runs, then restores them. If you see this again,
make sure you are on the current code and that PowerShell 5.1 is available.

**Scan is slow (~8–10 s)**
Normal. The scanner queries Windows PnP properties for every present USB device. The UI shows a
loading state.

**No devices found**
USB scanning is Windows-only. It lists *present* devices; a device must be plugged in. Some driver
metadata depends on installed vendor drivers and OS policy. Nothing is invented — an empty result
is reported honestly.

**Live Pairing finds no mouse**
The mouse must be powered on and already paired with that dongle. Some devices appear only as
"HID-compliant mouse" until the manufacturer driver is installed, so the model cannot be determined.
Register the mouse manually (brand + model) and use the Matching Center route described in
`USER_GUIDE.md`.

## Research / OCR / downloads

**`Identify Online` shows no live results**
Keyless Wikipedia search works by default. If it shows `available: false`, the Wikipedia API was
unreachable at that moment — official direct links are still shown. Optionally set
`RESEARCH_API_URL` + `RESEARCH_API_KEY` in `.env` for a broader search API. The app never scrapes
Google/Bing or fabricates results.

**OCR says unavailable**
Set `OCR_SERVICE_URL` (optionally `OCR_API_KEY`) or install Tesseract on PATH. Manual image
inspection and data entry always remain available.

**Executable download rejected**
HTTPS is required and `.exe`/`.msi`/firmware installers require `confirmed: true` (an extra checkbox
in the UI). The app never executes or installs a downloaded file.

**Downloaded file missing**
Files are stored under `downloads/{Manufacturer}/{Model}/` at the project root. The folder is
gitignored except for `.gitkeep`. Use the download record's **Open location** action.

## Database

**Migrations pending after an update**
`php artisan migrate --force`. Check with `php artisan migrate:status`.

**Reset to a clean sample catalogue**
`php artisan migrate:fresh --seed --force`.

**Start with an empty database**
`php artisan migrate:fresh --force` (no seed).

**Where is the data?**
`database/database.sqlite`, photos in `storage/app/public/`, local `usb.ids` cache in
`storage/app/usbids/`, downloaded files in `downloads/`. Back them up together.

## Windows safety note

Never run the app against a production VPS or use credentials belonging to another project. Never
execute downloaded installers manually from inside the app workflow; the app intentionally does not
do it either.
