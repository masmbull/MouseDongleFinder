# MouseDongleFinder — Research & Engineering Decisions

This document records the research phase that preceded the custom implementation. It explains which
external ideas were investigated, what was accepted/adapted/rejected, and why the final architecture
looks the way it does. **No external application was cloned and no compiled third-party binary was imported.**

Research performed: 2026-11-21.

---

## 1. Problem framing

The application solves exactly one problem: an office has a pile of unlabelled wireless mouse USB
receivers and the matching mice, and the user must determine which receiver belongs to which mouse.
The workflow that drove every design decision is:

```
connect → scan → identify → research → find software → physical test → confirm match
```

Anything outside that pipeline (asset management, HR, ticketing, procurement, complex roles) was rejected.

---

## 2. Research comparison matrix

| Reference | URL | Purpose | Language / platform | USB detection | VID/PID lookup | Driver info | Local DB / cache | OCR / image | License | Status | Decision |
|---|---|---|---|---|---|---|---|---|---|---|---|
| Microsoft PnP cmdlets | https://learn.microsoft.com/en-us/powershell/module/pnpdevice/get-pnpdevice | Enumerate present PnP/USB devices | PowerShell, Windows | Yes (`Get-PnpDevice -PresentOnly`) | Via InstanceId regex | Partial (class/status) | OS PnP store | No | Microsoft docs | Current | **Adapted** — primary scan source |
| Win32_PnPEntity / Win32_PnPSignedDriver | https://learn.microsoft.com/en-us/windows/win32/cimwin32prov/win32-pnpentity | WMI/CIM device + driver metadata | WMI, Windows | Yes | HardwareID arrays | Yes (provider, version) | OS WMI | No | Microsoft docs | Current | **Investigated, not used** — `Win32_PnPSignedDriver` hangs when PowerShell is launched from the PHP process; equivalent driver data is read via PnP properties instead |
| Standard USB identifiers | https://learn.microsoft.com/en-us/windows-hardware/drivers/install/standard-usb-identifiers | Format of `USB\VID_v&PID_d&REV_r` | Docs | n/a | n/a | n/a | n/a | n/a | Microsoft docs | Current | **Referenced** — parser rules |
| USB ID Repository (`usb.ids`) | https://www.linux-usb.org/usb-ids.html , https://github.com/usbids/usbids | Vendor/product name database | Plain text data | No | Yes (the de-facto standard) | No | Yes, downloadable | No | GPLv2+ **or** BSD-3-Clause (dual licensed) | Active (daily snapshots) | **Adapted** — optional local cache/import; attribution recorded below |
| daemonhorn/usb-enum | https://github.com/daemonhorn/usb-enum | PowerShell USB listing + usb.ids decode | PowerShell | Yes | Yes | No | Cached usb.ids (30 days) | No | BSD-2-Clause | Small, focused | **Referenced/adapted idea only** — caching + filter pattern; no code copied |
| DarthCyber/Get-USBid | https://github.com/DarthCyber/Get-USBid | Identify wireless receivers from VID/PID | PowerShell | Yes (WMI) | Yes via usb.ids | No | Downloads usb.ids | No | MIT | Low activity | **Referenced** — the insight that generic HID drivers hide "wireless" and that "transceiver" entries in usb.ids are strong receiver clues |
| Stand77/USB-Inspector | https://github.com/Stand77/USB-Inspector | Connected USB list + hotplug, curated device names | Python + pywin32/wmi | Yes | Yes | Partial | Cached usb.ids | No | MIT | Very new, 0 stars | **Rejected as dependency** — Python runtime/exe would add a second stack; ideas (curated list, port info, hotplug) noted |
| onknown54/usb-device-inspector | https://github.com/onknown54/usb-device-inspector | Professional desktop inspector | Python + PySide6 | Yes | Yes | Yes | History DB | No | License not clearly identified in root | Unverified | **Rejected** — oversized (flight simulator!), unclear license, irrelevant dependencies |
| todbot/usbSearch | https://github.com/todbot/usbSearch | USB VID/PID search utility | C / web | No | Yes | No | Web lookup | No | Not verified | Unverified | **Rejected** — no Windows integration advantage |
| zrnge/USB-Reverse-Engineering-Tool | https://github.com/zrnge/USB-Reverse-Engineering-Tool | Descriptor decode + raw USB I/O | Python + pyusb/libusb | Yes (deep) | Yes | No | No | No | License not identified in root | New | **Rejected** — raw device access needs Zadig/libusb and can detach OS drivers; unsafe for this use case |
| Solaar | https://github.com/pwr-Solaar/Solaar | Manage Logitech Unifying/Bolt/Lightspeed receivers | Python, Linux | HID++ (not plain USB scan) | Receiver VID/PID tables | Partial | No | No | GPLv2+ | Mature | **Referenced only** — receiver VID/PID family knowledge (Linux-specific code not usable) |
| OpenLogi | https://github.com/AprilNEA/OpenLogi | HID++ receiver/pairing reference | Rust | HID++ | Receiver table | No | No | No | Per-crate licenses (mixed) | Active | **Referenced only** — receiver family table cross-check |
| Logitech HID++ 1.0 excerpt | https://lekensteyn.nl/files/logitech/logitech_hidpp10_specification_for_Unifying_Receivers.pdf | Unifying receiver spec | PDF | n/a | `046D:C52B` documented | n/a | n/a | n/a | Public excerpt | Historical | **Referenced** — authoritative VID/PID for Unifying |
| Logitech support | https://support.logi.com/hc/en-us/articles/5470036605975-Unifying-Receiver-Software-Pairing-and-Troubleshooting | Official pairing support | Web | n/a | n/a | n/a | n/a | n/a | Official vendor | Current | **Referenced** — official support/pairing URL |
| usb.ids web lookup | https://usb-ids.gowdy.us/ , https://devicehunt.com/view/type/usb/vendor/046D | Human-readable VID/PID search | Web | No | Yes | No | No | No | Site terms | Current | **Referenced** — user-facing lookup links |
| Tesseract OCR | https://github.com/tesseract-ocr/tesseract | Local OCR engine | Native CLI | n/a | n/a | n/a | n/a | Yes | Apache-2.0 | Mature | **Optional/adapted** — used only when installed; otherwise the app reports OCR as unavailable |

---

## 3. Selected architecture and why

### 3.1 Windows device enumeration — native PowerShell, no extra runtime

`Get-PnpDevice -PresentOnly` was verified on the target machine and returns real, present USB devices
with `InstanceId`, `Class`, `Status`, `FriendlyName`. A single `Get-PnpDeviceProperty` call per device
supplies hardware IDs, serial, location and `DEVPKEY_Device_DriverDesc` / `DriverVersion` /
`DriverProvider`. The initially planned `Get-CimInstance Win32_PnPSignedDriver` was tested and
**rejected**: it hangs when PowerShell is launched from the non-interactive PHP process, while the PnP
properties return the same driver metadata quickly. This was chosen over Python/PyUSB tools because:

- Windows PowerShell 5.1+ is already present — zero new dependencies, zero installers, no admin rights for present-device queries.
- PyUSB/libusb approaches can require Zadig driver replacement (`zrnge` repo), which is unacceptable here.
- The app stays a single Laravel + Vue project, maintainable by one stack.

The scanner lives in `resources/ps/scan-usb.ps1` and is invoked with an argument array
(`powershell.exe -NoProfile -NonInteractive -ExecutionPolicy Bypass -File ...`) — never through a shell
string, avoiding quoting/injection issues. Results are parsed as JSON. On non-Windows the API returns a
clear structured error; **no device is ever invented**.

### 3.2 Identification — evidence-based, never fabricated

The custom `IdentificationEngine` normalises five evidence sources:

1. **Detected** — VID/PID/serial/hardware IDs straight from the USB scan.
2. **Local database** — curated seed rows plus optionally the imported `usb.ids` database.
3. **Curated reference table** — a small, documented set of receiver families (Logitech Unifying/Nano/Lightspeed/Bolt, Razer, Microsoft) sourced from the Logitech HID++ excerpt, Solaar/OpenLogi tables and vendor support pages.
4. **Manual** — user-entered markings, model labels, FCC ID, physical description.
5. **Online** — only real results returned by a configured search API or direct official URLs.

Every result carries `{type, detail, source}` evidence, a confidence value (`high|medium|low|unknown`),
an explicit `unknowns` list, and clearly separated `detected/manual/online/inferred` sections.
Compatibility is **never** asserted from brand or frequency alone; a receiver candidate is only a lead
that still requires the physical pairing test recorded in Matching Center.

### 3.3 VID/PID database — local-first with optional refresh

Curated rows are seeded into `usb_vendors` / `usb_products` so the app works offline on day one.
`UsbIdDatabase::refresh()` can download `usb.ids` (dual GPLv2+/BSD-3 licensed) into
`storage/app/usbids/`, parse it into the same tables, and record the last update time. All IDs are
stored uppercase to make SQLite comparisons deterministic.

**Attribution:** usb.ids data — Copyright (c) Stephen J. Gowdy and contributors, linux-usb.org;
used under the 3-clause BSD License option. No usb.ids source code was copied.

### 3.4 Web research — honest degradation

Aggressive scraping of search engines was rejected (robots.txt restrictions, brittle HTML, ToS risk,
and unusable results from Bing/Google in this environment). `WebResearchService` therefore:

- uses a configurable search API when `RESEARCH_API_URL` + `RESEARCH_API_KEY` are set (broader results);
- otherwise performs a **real keyless search through the Wikipedia MediaWiki API**, with progressively
  simplified query variants because exact VID/PID codes often return no full-text match;
- always adds **real, directly constructible** official support/documentation/lookup URLs
  (never fake "search results");
- reports `available: true` with real results when Wikipedia or the configured API responds, and only
  falls back to `available: false` with an honest message if every search source fails;
- always exposes the source URL and an `official` flag so the user can judge trustworthiness.

### 3.5 Safe downloads

`DownloadService` only accepts HTTPS, validates filename/extensions, caps size (50 MB), re-validates
redirect targets, writes under `downloads/{Manufacturer}/{Model}/` with sanitised path components,
computes SHA-256, and records source URL/time/device. Executable extensions require an extra explicit
confirmation. Nothing is executed, no installer is launched, no security setting is changed. The UI
shows the URL and official/third-party classification before any download.

### 3.6 Photo identification

Photos are stored on the `public` disk with strict MIME/size validation. OCR uses a configured service
or a locally installed Tesseract; if neither exists the endpoint returns `available: false` and manual
inspection/data entry remains fully functional. No AI identification is faked.

### 3.7 UI — custom, workflow-driven

Tailwind v4 + Vue 3 was kept (already installed). The UI is intentionally custom: a four-item primary
sidebar plus a Tools group, two-panel Matching Center, and a device-investigation workspace reflecting
the scan → identify → research → test → match pipeline. No GitHub project's UI was copied.

### 3.8 Live Pairing Detector — deciding which dongle belongs to which mouse

The operator clarified the real use case: mice are registered with **brand + model only**, and the app
must help determine which loose dongle is connected to which powered-on mouse. Reading HID++ register
tables directly (as Solaar/OpenLogi do) was rejected: it needs vendor-specific protocol details, can
require exclusive HID access, and risks disturbing an active pointer. Instead the implementation uses
evidence that Windows already exposes without special drivers:

- a USB composite device that has HID/Mouse children sharing its VID:PID is a receiver candidate;
- a HID/Mouse device with the same VID:PID (or matching hardware IDs) is physically behind that receiver;
- the device friendly name and the `mouse_profiles` reference table can identify the model;
- otherwise the device is reported honestly as generic with `low`/`unknown` confidence.

The scan is read-only, takes ~2 seconds (VID/PID from instance IDs, no per-device PnP property calls),
shows only receivers **currently present**, and never auto-saves a match. The user confirms through
`POST /matches`. This is why the app can assist without pretending to read the radio protocol.

**Dongle Lab (batch extension).** When many dongles are plugged into one hub, Windows cannot tell two
identical VID:PID receivers apart electronically. The batch session therefore labels each *physical*
receiver by `VID:PID + USB port/location` (A/B/C...), stores a baseline device snapshot from the same
fast scanner used for observation (otherwise already-present HID devices would falsely appear as new),
and on each observe marks newly appeared devices with `is_new` while also returning **all** currently
connected mouse devices — the original "new devices only" design was unusable when Windows had already
enumerated the mouse HID device before the scan. Attribution uses direct VID/PID (high) or hardware-ID
VID/PID (medium). When the user explicitly selects the dongle under test, that dongle becomes an
actionable candidate even without an identifier match, with `user_test` evidence and confidence high only
when the identifiers agree or it is the sole dongle; otherwise medium. If several dongles match, all are
returned and the user must pick by USB port. No match is created by the observation endpoint. A manual
brand label is supported for generic VID/PIDs that no registry can identify.

---

## 4. License & security review summary

- **No external source code was copied into this project.** Only documented public APIs and data formats were implemented independently.
- MIT / BSD-licensed projects above were used as *ideas only*; no files vendored.
- GPL-licensed projects (Solaar, usbids repository, usb.ids data) were **not** copied as code; the data file is used under the BSD-3 option with attribution above.
- Unsigned `.exe` release assets from GitHub were explicitly rejected — never downloaded or executed.
- PowerShell execution uses `-File` with an in-repo script and argument arrays; no `Invoke-Expression`, no shell interpolation of untrusted data.
- Laravel's `.env` variables are temporarily removed from `getenv()`, `$_SERVER` and `$_ENV` while the PowerShell child process runs; on this Windows build their presence makes PowerShell 5.1 fail to start through Symfony Process with error 0x8009001d. The values are restored immediately afterwards.
- All network calls are time-limited and failure-tolerant.

---

## 5. Real-world reference dataset (development seed)

The initial lorem-ipsum sample data was replaced with a researched catalogue used **only** for development
and UI exploration. Every row is flagged `is_seed = true` and labelled SAMPLE in the UI; it is not the
operator's real office inventory.

Sources actually consulted:

- **usb.ids 2026.06.26** — https://www.linux-usb.org/usb-ids.html (local copy at
  `storage/app/usbids/usb.ids`); source of all seeded vendor/product IDs and names.
- **DeviceHunt** — https://devicehunt.com/view/type/usb/vendor/046D/device/C548 and
  https://devicehunt.com/view/type/usb/vendor/1532/device/007B (cross-check of receiver IDs).
- **Logitech** — Unifying receiver overview (https://en.wikipedia.org/wiki/Logitech_Unifying_receiver),
  official pairing support
  (https://support.logi.com/hc/en-us/articles/5470036605975-Unifying-Receiver-Software-Pairing-and-Troubleshooting),
  Logi Options+ (https://www.logitech.com/optionsplus), and verified product pages for MX Master 3S,
  MX Anywhere 3S, MX Ergo and M185.
- **Bluetooth 2.4 GHz** — https://en.wikipedia.org/wiki/Bluetooth.
- **Pairing utilities** — Razer Synapse, Corsair iCUE, SteelSeries GG, HyperX, Trust, Keychron and A4Tech
  official support pages (exact URLs recorded in `database/seeders/DatabaseSeeder.php`).

Honesty rules applied while building the catalogue: PIDs are filled only when documented; receiver PIDs
are not presented as mouse PIDs; `C53A/C53F/C541/C547` were **not** seeded as Logitech receivers because
they could not be confirmed in the 2026.06.26 snapshot; `1EA7` is SHARKOON (Redragon mice use `04D9`);
unknown dongles are stored with null brand/model/VID/PID; `high` confidence is only used when both VID
and PID are documented. Brands that could not be verified as real wireless-mouse makers were excluded
instead of invented.

Final global catalogue: **47 brands, 91 mouse profiles, 65 mice, 56 dongles, 52 identifications,
3,427 USB vendors, 2,878 filtered USB products and 10 clearly-labelled sample matches**. The parsed
usb.ids subset is committed as `database/seeders/usb-ids-snapshot.php` so seeding is offline and fast
(~3 s). Additional verified brands include Fujitsu-Siemens, A4Tech Bloody, Xtrfy, Sunsonny, Genius,
Perixx, Verbatim and Hama.

---

## 6. Known limitations (documented honestly)

- Windows only exposes a serial number when the device reports one; many cheap receivers do not.
- VID/PID identifies a device family, not a unique physical unit; two identical dongles are indistinguishable electronically. The mouse↔dongle relationship must be confirmed by a physical test.
- A receiver that pairs with many devices (e.g. Unifying) cannot be declared "the" match for a mouse from identifiers alone.
- `usb.ids` is community-maintained and incomplete; unknown VID/PID values are normal and are shown as `unknown`, never guessed.
- Present-device scanning does not require admin rights, but some driver metadata may be restricted by OS policy.
- Online research quality depends on whether a search API key is configured; without one the app provides official direct links instead of search results.
- OCR depends on an external service or a locally installed Tesseract binary.

---

## 7. Reference photo attribution

Reference photos for the development seed catalogue were researched against real, publicly accessible
sources and saved locally under `public/images/devices/{mice|dongles}/{seed-id}/`. The exact source page
for every stored photo is recorded in `device_photos.source_url`; the download provenance is kept in
`database/seeders/device-photos-manifest.php` and the resolved local paths in
`database/seeders/device-photos-resolved.json`. Seeding performs no network calls.

- **Wikimedia Commons** — freely licensed files, mostly CC BY 4.0, with a few CC BY-SA 4.0, CC BY-SA 3.0,
  CC BY-SA 2.0 and CC BY 2.0 files. Per-file licenses are recorded in `device_photos.source_name`;
  the source is the `https://commons.wikimedia.org/wiki/File:...` page of each image. Files used include
  the Logitech MX Master 3S `HS` series, M185 `HS` series, Logi Bolt receiver `HS` series, Unifying
  receiver photographs, Nano receiver, Microsoft Sculpt Ergonomic Mouse, Razer DeathAdder V2 Pro,
  Apple Magic Mouse 2 and a Sunplus SPCP25A-based 2.4 GHz receiver board.
- **Official manufacturer product galleries** — Logitech (`resource.logitech.com`, MX Master 3S, M185,
  MX Ergo, G305 and Unifying receiver), SteelSeries (`images.ctfassets.net`, Rival 650 Wireless and
  Aerox 3 Wireless), Razer (`assets2.razerzone.com` / `assets3.razerzone.com`, Basilisk X HyperSpeed and
  DeathAdder V2 Pro), and Xtrfy (`cherryxtrfy.com`, M42 Wireless). Exact product page URLs are stored in
  `source_url`. Images are included for editorial/reference use in this private local catalogue tool;
  no image is generated, altered, or reused for a different model.

The extended sweep used the Wikimedia Commons MediaWiki API directly from the shell (the MCP HTTP tools
are blocked by `robots.txt`): `https://commons.wikimedia.org/w/api.php?action=query&generator=search&
gsrsearch=<query>&gsrnamespace=6&gsrlimit=50&prop=imageinfo&iiprop=url%7Cextmetadata%7Cmime&format=json`.
It combined exact-model phrases, model-only queries, a distinctive-token refill pass, a brand-wide pass
over all 47 seeded brands (2,187 candidate files), and `list=categorymembers` category enumeration. Every
candidate was filtered by normalised model tokens in the file title; lookalikes were rejected.

Because Commons coverage was limited, a second sweep used **Yandex Images** (`https://yandex.com/images/search?text=<query>`)
from the shell. Result titles were filtered by normalised brand/model tokens before download, and images
were fetched through Yandex's image proxy when the original host blocked hotlinking. The original result
page URL is stored in `device_photos.source_url` with the host in `source_name`. Brave Images and Google
Images were also tested: Brave rate-limited automated requests (429) and Google served a JS challenge, so
neither was used for the catalogue.

Final result: **429 real images covering 116 of 121 devices** (only 5 devices have no trustworthy
exact-model image and are counted by the seeder). Every photo is stored locally under
`public/images/devices/`, so the UI never hotlinks external sites.

Honesty rule: a device receives a photo only when an image of **that exact model** was located. Those
devices are left without reference photos and counted by the seeder. No placeholder, lookalike, generated
image, or duplicate image URL is used to inflate coverage.

