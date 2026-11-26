# MouseDongleFinder — Database Reference

Local database: `database/database.sqlite` (SQLite). Connection is configured in `.env`
(`DB_CONNECTION=sqlite`). Run `php artisan migrate:status` to see the applied migrations.

## Entity overview

```
Mouse  ──< DeviceMatch >──  Dongle ──< Identification ──< IdentificationPhoto?
  │                           │
  └──< (matches)              └──< Download ──> Identification
                                   │
                              UsbVendor / UsbProduct (local VID/PID decode)
                              UsbScan (JSON snapshot of a scan)
                              MouseProfile (model → wireless VID/PID reference)
```

No user accounts are required; `users` exists only as the Laravel default.

---

## Tables

### `mice`
| Column | Notes |
|---|---|
| `id` | PK |
| `mouse_id` | unique, generated `MS-XXXXXXXX` |
| `brand` | required in forms/API |
| `model`, `color`, `connectivity_type` | nullable |
| `physical_description`, `notes` | nullable text |
| `photo_path` | path on `public` disk; `photo_url` accessor provides URL/placeholder |
| `is_seed` | boolean; `true` = development sample data |

Relations: `match()` (latest `DeviceMatch`), `matches()` (all history).
Accessors: `photo_url`, `device_match_status` (`unmatched` when no match).

### `dongles`
| Column | Notes |
|---|---|
| `id`, `dongle_id` | PK, unique `DG-XXXXXXXX` |
| `brand`, `model` | **nullable** — unidentified dongles are allowed |
| `color`, `physical_description`, `receiver_type`, `photo_path`, `notes` | nullable |
| `vid`, `pid` | uppercase 4-hex USB IDs |
| `hardware_id`, `serial_number` | nullable |
| `is_seed` | boolean |

Relations: `match()`, `matches()`, `identification()` (latest), `downloads()`.
Accessors: `photo_url`, `device_match_status`.

### `device_matches`
| Column | Notes |
|---|---|
| `id`, `mouse_id` FK→`mice` CASCADE, `dongle_id` FK→`dongles` CASCADE | |
| `status` | enum `matched` \| `suspected` \| `unmatched` \| `unknown`, default `unknown` |
| `notes`, `matched_at`, `is_seed` | `matched_at` set only for `matched` |

Indexed on `status`.

**Invariants enforced in `DeviceMatchController`:**
- One dongle cannot be in an active (`matched`/`suspected`) relationship with two mice.
- One mouse cannot be in an active relationship with two dongles.
- Transitioning into an active status re-checks the rule (excluding the current record).
- Removing a match makes both devices unmatched again.

### `identifications`
`dongle_id` FK null-on-delete; `vid`, `pid`, `hardware_id`; `probable_manufacturer`,
`probable_model`, `device_category`, `wireless_technology`, `frequency`, `receiver_family`,
`pairing_utility`, `official_support_url`, `confidence`
(`high`|`medium`|`low`|`unknown`); JSON `compatible_mice`, `evidence`, `research_sources`;
`notes`, `is_seed`.

`frequency` is only stored when a trustworthy source supports it; otherwise `null`.

### `downloads`
`dongle_id` FK null-on-delete, `identification_id` FK null-on-delete; `filename`, `file_path`,
`source_url`, `source_type` (`official`|`third-party`|`unknown`), `manufacturer`, `model`,
`software_type`, `file_size` (bytes, string column), `sha256_hash`, `download_status`,
`downloaded_at`, `is_seed`.

### `usb_scans`
`scan_id` unique (`SCN-...`), JSON `devices`, `device_count`, `scanned_at`, `scanned_by`, `is_seed`.

### `usb_vendors`, `usb_products`
Local decode tables populated from the committed `database/seeders/usb-ids-snapshot.php` (all 3,427
vendors and 2,878 mouse/receiver/wireless-related products from `usb.ids` 2026.06.26) plus curated rows,
and optionally refreshed from a live `usb.ids` download. `vid`/`pid` are stored **uppercase** (SQLite `=`
is case-sensitive). `UsbProduct` has a unique `(vid, pid)` index.

### `mouse_profiles`
Reference data for Live Pairing: `brand`, `model`, `vid`, `pid`, `receiver_family`,
`wireless_technology`, `frequency`, `pairing_utility`, `official_support_url`, `notes`, `source`.
Unique `(brand, model)`. Used by `LivePairingDetector` to recognise a connected mouse from its
Windows-reported name or its wireless VID/PID.

### `device_photos`
Polymorphic reference images (`photoable_type`, `photoable_id`) for mice and dongles. Columns:
`photo_type` (top/underside/receiver/marking/side/detail), `caption`, `path` (relative to `public/`),
`source_url`, `source_name`, `is_seed`, timestamps. Downloaded files live under
`public/images/devices/{mice|dongles}/{mouse_id|dongle_id}/`. Eager-loaded on Mouse/Dongle as
`reference_photos`; user-uploaded photos remain in the single `photo_path` column.

### `matching_sessions`
Dongle Lab batch session. `name`, `status` enum(`scanning`|`observing`|`completed`|`abandoned`), JSON
`dongles` (labelled A/B/C with VID/PID, brand guess, USB port, confidence), JSON `observations`
(baseline snapshot plus each observe result, including `new_devices`, grouped and flat candidates),
`started_at`, `completed_at`, timestamps. No `is_seed` — sessions are transient work artifacts and can be
deleted freely.

### Laravel defaults
`users`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs` — not used by the app logic.

---

## Seed policy

- `php artisan db:seed --class=DatabaseSeeder` is **optional** and development-only.
- Every seeded row carries `is_seed = true` and is shown with a **SAMPLE** badge in the UI.
- Seed content is researched real-world data (brands, models, VID/PID, receiver families,
  frequencies, pairing utilities, official URLs) — it is a reference catalogue, **not** the user's
  real office inventory.
- The app must work with an empty database. To clear everything:
  `php artisan migrate:fresh --force` (omit `--seed`).
- To restore the reference catalogue: `php artisan migrate:fresh --seed --force`.

## ID formats

| Entity | Format |
|---|---|
| Mouse | `MS-` + 8 random uppercase alphanumerics |
| Dongle | `DG-` + 8 random uppercase alphanumerics |
| Scan | `SCN-` + 10 random uppercase alphanumerics |

## Backup

Back up these together: `database/database.sqlite`, `storage/app/public/` (photos),
`storage/app/usbids/` (local id database), `downloads/` (downloaded files).
