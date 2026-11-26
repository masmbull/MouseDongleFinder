# MouseDongleFinder — API Reference

Base URL: `http://127.0.0.1:8000/api` (configured in `resources/js/app.js` as axios `baseURL = '/api'`).

All responses are JSON. Laravel error responses use:

```json
{ "message": "The given data was invalid.", "errors": { "brand": ["The brand field is required."] } }
```

Business-rule errors use `{ "error": "...", "existing_match": {...} }` with HTTP 422.

List endpoints return Laravel paginator objects:

```json
{
  "current_page": 1,
  "data": [ ... ],
  "first_page_url": "...",
  "from": 1,
  "last_page": 3,
  "last_page_url": "...",
  "links": [ ... ],
  "next_page_url": "...",
  "path": "...",
  "per_page": 15,
  "prev_page_url": null,
  "to": 15,
  "total": 31
}
```

`per_page` is capped at 100. All model responses include computed attributes `photo_url` and
`device_match_status`, plus an eager-loaded `reference_photos` array:
`{id, photo_type, caption, path, url, source_url, source_name, is_seed}`. Mouse/dongle lists are ordered by
reference-photo count descending, then newest first, so real example images appear on the first page.

---

## Dashboard

### `GET /dashboard`

```json
{
  "stats": {
    "total_mice": 10,
    "total_dongles": 8,
    "matched_pairs": 0,
    "suspected_pairs": 3,
    "unmatched_dongles": 3,
    "unmatched_mice": 5,
    "unknown_devices": 0
  },
  "recent_mice": [ /* Mouse[] */ ],
  "recent_dongles": [ /* Dongle[] */ ],
  "recent_matches": [ /* Match[] */ ],
  "status_overview": { "matched": 0, "suspected": 3, "unmatched": 2, "unknown": 1 }
}
```

All values are computed from the database on every request.

---

## Mice

### `GET /mice`

Query: `search`, `brand`, `status` (`matched`|`suspected`|`unmatched`|`unknown`), `per_page`.
Search covers `mouse_id`, `brand`, `model`, `physical_description`.

### `GET /mice/{mouse}`

### `POST /mice`

`multipart/form-data` when a photo is included.

| Field | Rules |
|---|---|
| `brand` | required, string, max 255 |
| `model`, `color`, `connectivity_type` | nullable string |
| `physical_description`, `notes` | nullable text |
| `photo` | nullable image: jpeg/png/jpg/gif/webp, max 5120 KB |

Returns 201 with the new Mouse (ID format `MS-XXXXXXXX`).

### `POST /mice/{mouse}` with `_method=PUT`

Same fields as store, plus `remove_photo=1` to delete the current photo. If a new `photo` is sent, it
replaces the old one (the old file is deleted from the `public` disk).

### `DELETE /mice/{mouse}`

Deletes the mouse, its photo and its related matches. Returns `{"message": "..."}`.

---

## Dongles

### `GET /dongles`

Query: `search`, `brand`, `status` (also supports `unmatched`, meaning no active
`matched`/`suspected` relationship), `per_page`. Search covers `dongle_id`, `brand`, `model`,
`physical_description`, `vid`, `pid`.

### `GET /dongles/{dongle}`

Includes `match`, `identification`, `downloads`.

### `POST /dongles` / `POST /dongles/{dongle}` with `_method=PUT`

All fields are nullable — an unidentified dongle can be registered with no brand or model:

`brand`, `model`, `color`, `physical_description`, `receiver_type`, `notes`, `vid`, `pid`,
`hardware_id`, `serial_number`, `photo`, `remove_photo`.

VID/PID are normalised to uppercase. ID format `DG-XXXXXXXX`. Returns 201 on store.

### `DELETE /dongles/{dongle}`

Deletes the dongle, its photo and related matches/identifications according to FK rules.

---

## Matches (mouse ↔ dongle)

### `GET /matches`

Query: `search` (mouse/dongle ID, brand, model), `status`, `per_page`.

### `GET /matches/{match}`

### `POST /matches`

```json
{ "mouse_id": 7, "dongle_id": 12, "status": "matched", "notes": "Physically tested: cursor moves." }
```

`status` ∈ `matched` | `suspected` | `unmatched` | `unknown`. Returns 201.

**Duplicate guard:** a mouse or dongle already in an active (`matched`/`suspected`) relationship
cannot be assigned again:

```json
HTTP 422
{ "error": "This mouse or dongle already has an active match.", "existing_match": { ... } }
```

`matched_at` is set when status becomes `matched`, cleared otherwise.

### `POST /matches/{match}` with `_method=PUT`

`status?`, `notes?`. Transitioning into an active status re-runs the duplicate guard (excluding self).

### `DELETE /matches/{match}`

Both devices return to their appropriate unmatched state.

---

## USB scanning & live pairing

### `POST /usb-scans`

Runs a real Windows PnP scan, stores it, returns:

```json
{ "scan": { "scan_id": "SCN-...", "device_count": 8, ... }, "devices": [ /* UsbDevice[] */ ], "count": 8 }
```

### `POST /usb-scans/scan`

`{"save": false}` → `{ "devices": [...], "count": 8, "scanned_at": "...", "error": null }`.
With `save: true` the result is persisted.

### `GET /usb-scans`, `GET /usb-scans/latest`, `GET /usb-scans/{usb_scan}`, `POST /usb-scans/{usb_scan}` with `_method=PUT`, `DELETE /usb-scans/{usb_scan}`

### `POST /usb-scans/live-match` — Live Pairing Detector

No request body. Reads the HID/mouse devices Windows currently reports as connected, groups them by
receiver (shared VID/PID or hardware IDs), and suggests which registered mouse each one is.

```json
{
  "scanned_at": "2026-11-22T...",
  "error": null,
  "dongles": [
    {
      "vid": "046D",
      "pid": "C52B",
      "name": "Logitech Unifying Receiver",
      "dongle_id": 12,
      "connected_devices": [
        { "name": "Logitech MX Master 3S", "class": "Mouse", "status": "OK",
          "vid": "046D", "pid": "C52B", "hardware_ids": [],
          "instance_id": "...", "location": "Port_#0004.Hub_#0002" }
      ],
      "suggestions": [
        { "mouse_id": 7, "brand": "Logitech", "model": "MX Master 3S",
          "confidence": "high", "reason": "...",
          "evidence": [ { "type": "topology", "detail": "...", "source": "windows_pnp" } ],
          "device_index": 0 }
      ]
    }
  ],
  "unattributed_devices": [],
  "message": "..."
}
```

This endpoint **never creates a match**. The UI saves the result through `POST /matches` after the
user confirms. On non-Windows or scanner failure it returns `error` with empty arrays.

---

## Dongle Lab (batch matching session)

### `POST /matching-sessions`

Scans all currently plugged receivers. Returns 201:

```json
{
  "session": { "id": 1, "name": "...", "status": "scanning", "started_at": "..." },
  "dongles": [
    { "key": "A", "vid": "046D", "pid": "C52B", "name": "Logitech Unifying Receiver",
      "brand_guess": "Logitech", "receiver_family": "Unifying", "location": "Port_#0004.Hub_#0002",
      "port": "Port_#0004.Hub_#0002", "dongle_id": 2, "confidence": "high",
      "note": "VID+PID documented",
      "matched_mice": [
        { "match_id": 3, "status": "matched", "notes": "...", "matched_at": "...",
          "dongle_id": 2, "match_source": "registered", "mouse_id": 7,
          "mouse_code": "MS-XXXXXXXX", "brand": "Logitech", "model": "M185", "photo_url": "..." }
      ] }
  ],
  "message": "..."
}
```

Labels `A`, `B`, `C`... are assigned in detection order so identical dongles can be told apart by USB
port. `dongle_id` is null until the receiver is registered. `matched_mice` lists every recorded
relationship for that receiver, newest first; when the receiver is not registered, matches from
registered dongles with the same VID:PID are returned with `match_source: 'same_vid_pid_family'` and
must be treated only as a family clue.

### `GET /matching-sessions`, `GET /matching-sessions/{id}`, `DELETE /matching-sessions/{id}`

### `POST /matching-sessions/{id}/observe`

Call after switching ON exactly one mouse:

```json
{ "dongle_key": "A", "mouse_id": 7, "notes": "Turned on the black M185" }
```

```json
{
  "observed_at": "...",
  "tested_dongle_key": "A",
  "connected_devices": [ { "name": "HID-compliant mouse", "class": "Mouse", "status": "OK",
                     "vid": "046D", "pid": "C52B", "hardware_ids": [], "instance_id": "...",
                     "location": "", "is_new": false } ],
  "new_devices": [ { "name": "HID-compliant mouse", "class": "Mouse", "status": "OK",
                     "vid": "046D", "pid": "C52B", "hardware_ids": [], "instance_id": "...", "location": "" } ],
  "candidates": [
    { "device": {...}, "device_instance_id": "...",
      "candidates": [ { "dongle_key": "A", "dongle_id": 2, "reason": "...",
                        "evidence": [...], "confidence": "high" } ],
      "message": null }
  ],
  "flat_candidates": [
    { "dongle_key": "A", "dongle_id": 2, "vid": "046D", "pid": "C52B",
      "reason": "...", "evidence": [...], "confidence": "high", "device": {...} }
  ],
  "message": "...",
  "observation_count": 1
}
```

`candidates` is grouped by newly-observed device; `flat_candidates` is the same information flattened
for the Vue UI. The endpoint only observes and records. Save through `POST /matches` after the user
chooses the mouse and dongle. When several identical dongles match, all candidates are returned and
the user decides by USB port.

### `POST /matching-sessions/{id}/label`

Manually label a receiver whose brand cannot be guessed from VID/PID:

```json
{ "dongle_key": "A", "brand": "Mikuso", "model": "2.4G Receiver", "notes": "Marking from the device shell" }
```

Updates the labelled dongle in the session JSON and returns `{session, dongles, message}`. The entry gets
`brand_source: 'manual'` so the UI can show that the brand came from the user, not detection. Unknown
`dongle_key` → 422.

### `POST /matching-sessions/{id}/complete`

Marks the session `completed`.

### `POST /auto-discover`

One-click discovery. Reads real HID connections, identifies the mouse from the global catalogue, and
auto-registers unknown mice/dongles. Returns:

```json
{
  "scanned_at": "...",
  "error": null,
  "message": "...",
  "discovered": [
    { "dongle": { "vid": "046D", "pid": "C52B", "name": "...", "port": "Port_#0004",
                  "dongle_id": 12, "auto_created": false },
      "device": { "name": "Logitech MX Master 3S", "vid": "046D", "pid": "C52B",
                  "instance_id": "...", "class": "Mouse", "status": "OK",
                  "hardware_ids": [], "location": "" },
      "identity": { "brand": "Logitech", "model": "MX Master 3S",
                    "source": "device_name", "confidence": "high" },
      "mouse": { "id": 7, "mouse_id": "MS-XXXXXXXX", "brand": "Logitech",
                 "model": "MX Master 3S", "photo_url": "...", "auto_created": true },
      "match": { "id": 3, "status": "suspected", "notes": "..." },
      "evidence": [ { "type": "hid_connection", "detail": "...", "source": "windows_pnp" } ],
      "needs_user_review": false }
  ],
  "unattributed_devices": []
}
```

Auto-created matches are always `suspected`, never `matched` — the physical test is still the user's job.
`needs_user_review = true` for weak evidence or ambiguous identical dongles.

---

## Identification & research

### `POST /identifications/analyze`

```json
{ "vid": "046D", "pid": "C52B", "hardware_id": "", "dongle_id": 12,
  "manufacturer": "Logitech", "model": "Unifying", "notes": "" }
```

Returns the full evidence-based analysis (keys: `vid`, `pid`, `vendor_name`, `product_name`,
`probable_manufacturer`, `probable_model`, `device_category`, `wireless_technology`, `frequency`,
`receiver_family`, `compatible_mice`, `pairing_utility`, `support_url`, `confidence`, `evidence`,
`candidates`, `recommended_software`, `research_sources`, `search_queries`, `unknowns`, `detected`,
`manual`, `online`, `inferred`). Missing information stays `null` and appears in `unknowns`.

### `POST /identifications`, `POST /identifications/save-analysis`, `POST /identifications/{id}/save-analysis`, `GET /identifications`, `GET /identifications/{id}`

### `POST /identifications/ocr`

`multipart/form-data`: `dongle_id?`, `photo_type`, `photo`. Returns
`{ "available": bool, "extracted_text": null|string, "fields": {...}, "message": null|string }`.
`available: false` is returned honestly when OCR is not configured.

### `POST /research/search`

```json
{ "queries": ["046D C52B receiver"], "vid": "046D", "pid": "C52B", "manufacturer": "Logitech", "model": "Unifying" }
```

Returns `{ "available": bool, "message": null|string, "results": [ ... ], "searches": [ ... ] }`.
Uses the configured search API when present, otherwise a real keyless Wikipedia search, and always
adds directly constructible official URLs. `searches[].source` is `configured_api` or `wikipedia`.
`available` is `false` only when every search source fails; no fabricated results are ever returned.

### `GET /research/software?manufacturer=Logitech&model=Unifying&vid=046D&pid=C52B`

Official drivers / pairing utilities / manuals with `official` flags.

### `GET /usb-ids/lookup?vid=046D&pid=C52B`

Local VID/PID decode from `usb_vendors` / `usb_products`.

### `POST /usb-ids/refresh`

Downloads `usb.ids`, imports it into the local tables, returns `{ imported, last_updated, message }`.

---

## Downloads

### `GET /downloads`, `GET /downloads/{download}`

Query filters: `dongle_id`, `source_type`, `download_status`.

### `POST /downloads/fetch`

```json
{
  "url": "https://example.com/driver.exe",
  "source_url": "https://example.com/driver.exe",
  "source_type": "official",
  "manufacturer": "Logitech", "model": "Unifying",
  "software_type": "pairing", "dongle_id": 12,
  "confirmed": true
}
```

Safety: HTTPS only, size cap 50 MB, redirect targets re-validated, safe filenames, dynamic
`downloads/{Manufacturer}/{Model}/` folders, SHA-256 computed. Executable extensions require
`confirmed: true` (otherwise 422). Nothing is executed. Returns `{ download, message }` (201).

### `GET /downloads/{download}/verify`

`{ "verified": bool, "computed_hash": "...", "stored_hash": "..." }`.

### `POST /downloads/{download}/open`

Opens the containing folder in Explorer (Windows only). Does **not** execute the file.
Returns `{ "opened": bool, "path": "...", "error": null|string }`.

### `DELETE /downloads/{download}`

Deletes file and record.
