# MouseDongleFinder — User Guide

This app solves one office problem: **a drawer full of loose wireless mouse dongles, and you don't
know which mouse each one belongs to.** You register the mice (brand + model is enough), plug a
dongle in, let Windows tell the app which mouse is actually connected through it, then save the
result.

---

## 1. First-time setup

Follow `README.md`. On Windows, open <http://127.0.0.1:8000>. The sidebar has:

- **Dashboard** — live numbers from the database
- **Mouse Database** — mice you own
- **Dongle Database** — receivers you found
- **Matching Center** — record manual test results
- Tools: **Match List** and **USB Detective**

---

## 2. Register a mouse (only brand + type needed)

1. Open **Mouse Database** → **Add mouse**.
2. Fill **Brand** and **Model / type** (e.g. `Logitech`, `MX Master 3S`).
3. Everything else (colour, description, photo, notes) is optional — skip it if you don't know.
4. Save. The mouse gets an ID like `MS-7K2P9QXL`.

---

## 3. Find out which mouse a dongle belongs to

### 3.1 Plug and detect

1. Plug the unknown dongle into the PC.
2. Switch the mouse **on** (make sure it has batteries).
3. Open **USB Detective** → **Live Pairing** → **Detect connected mice**.
4. Windows reports the devices currently connected through that dongle. The app groups them by
   receiver (VID:PID) and shows which registered mouse each one most likely is, with **evidence**
   and a **confidence level**.

### 3.2 Read the suggestion

- **High** — the device name matches the registered model and it is behind the same receiver.
- **Medium** — name/model tokens match but evidence is partial.
- **Low / Unknown** — only the receiver topology is known; the model could not be determined.
  You must verify manually.

Tap the evidence rows to see where each clue came from. Nothing is invented: if Windows only says
"HID-compliant mouse", the app says it cannot identify the model.

### 3.3 Save the result

- If the suggestion is correct: **Confirm Match** (or **Save as Suspected** if you haven't
  physically confirmed it yet).
- If the connected mouse is not registered yet: use the small **brand + model** form on the
  suggestion row to register it, then detect again.
- If the receiver itself is not registered yet, the card shows a **Register receiver** field. The VID
  and PID are saved automatically; the name is optional and can be edited later in Dongle Database.
- A dongle cannot be actively matched to two mice. If it already has an active match, the app shows
  that match instead of silently replacing it.

### 3.4 If detection finds nothing

Check: mouse switched on, batteries, dongle seated properly, mouse actually paired to that dongle,
try a different USB port. Some devices only expose a generic name until the manufacturer's driver is
installed — in that case fall back to the manual route below.

---

## 4. Manual route (when live detection is inconclusive)

1. **USB Detective** → **Scan USB devices** to record the dongle's VID/PID, hardware IDs, serial and
   driver details.
2. Select the device → **Identify** to see probable manufacturer, receiver family, frequency,
   wireless technology, evidence and remaining unknowns.
3. **Identify Online** for real official links; **Find software** for pairing utility / driver.
4. Register the dongle (blank brand/model is allowed) and open **Matching Center**.
5. Pick a mouse in the left panel and a dongle in the right panel, perform the physical test
   (move the mouse, confirm the cursor responds), then choose **Confirm Match** / **Mark as
   Suspected** / **Mark as Unmatched** / **Remove Match**.

The app never declares a pair compatible just because the brands or frequencies match. VID/PID and
radio frequency are clues, not proof; the live-detected connection or your physical test is the proof.

---

## 5. Other pages

- **Match List** — every recorded relationship; search, filter by status, edit notes, remove.
  Removing a match returns both devices to unmatched.
- **Dashboard** — totals and recent activity, updated immediately after every change.
- **Software & Downloads** (in Dongle Detail / USB Detective) — official links, URL shown before
  download, extra confirmation for `.exe`/`.msi`, SHA-256 verification, open-folder action.
  Downloaded files are **never executed**.
- **Photos** — optional, validated (JPG/PNG/GIF/WebP ≤ 5 MB), replaceable, placeholder shown when
  absent. Photos help you recognise the physical device.
- **Reference photo gallery** — sample devices come with several real product images (top, underside,
  receiver, markings) from public sources, stored locally. Open a mouse/dongle detail page and use the
  thumbnails to compare angles with the device in your hand. Each image shows a caption and a source link.
  The gallery is labelled "Public reference · not user upload" so it is never confused with your own photo.

---

## 6. Dongle Lab — cocokkan banyak dongle sekaligus

Pakai menu ini kalau kamu menemukan tumpukan dongle dan belum tahu merk/pasangannya.

1. **Colok semua dongle** ke laptop (boleh lewat USB hub), lalu buka **Dongle Lab** → **Scan all plugged
   dongles**. Setiap receiver dapat label **A / B / C**, perkiraan merk dari VID/PID, receiver family, dan
   **nomor port USB**. Port penting kalau beberapa dongle identik.
2. Kalau merk tidak bisa ditebak dari VID/PID (sering terjadi pada dongle generik), klik **Set brand /
   label manually** pada kartu dongle dan isi merk yang kamu tahu, misalnya `Mikuso`. Label ini ditandai
   sebagai **data yang kamu berikan**, bukan hasil deteksi.
3. **Pilih dongle yang sedang diuji** di dropdown **Dongle being tested**. Untuk hasil paling kuat, **cabut
   dongle lain** supaya hanya dongle itu yang terhubung. Nyalakan **satu mouse saja**, lalu klik
   **Observe selected dongle**.
4. Observasi menampilkan **semua perangkat mouse yang sedang terhubung sekarang** (yang baru muncul
   diberi badge **NEW**) dan kandidat dongle beserta bukti/confidence. Kalau VID/PID tidak cocok, kandidat
   tetap muncul karena kamu sudah menandai dongle yang diuji — dengan confidence lebih rendah bila masih
   ada dongle lain yang tercolok.
5. **Simpan hasil**: pilih mouse yang baru kamu nyalakan (atau daftarkan baru dengan merk + tipe), pilih
   status **Suspected** atau **Confirmed**, lalu simpan. Kalau dongle belum terdaftar, klik **Register this
   receiver** — VID/PID diisi otomatis.
6. **Finish session** saat selesai, atau hapus sesi kalau ingin mengulang. Riwayat observasi tetap tersimpan
   dalam sesi.

Setelah scan, panel **Plugged dongles → recorded matched mice** langsung menunjukkan untuk tiap dongle
yang tercolok: mouse mana saja yang **sudah tercatat** sebagai pasangannya di database, beserta status dan
catatannya. Kalau dongle belum terdaftar, aplikasi juga menampilkan match dari dongle lain yang memiliki
VID:PID sama dan menandainya **family clue** — dongle identik tidak bisa dibedakan secara elektronik.
Kalau belum ada match, akan tertulis "No recorded match yet". Halaman detail dongle juga punya bagian
**Matched mice** berisi seluruh riwayat relasinya.

Aplikasi tidak pernah mengklaim "pasti match" hanya dari VID/PID. Yang dibuktikan adalah perangkat HID
nyata yang terhubung saat dongle yang kamu pilih sedang diuji; konfirmasi akhir tetap kamu lakukan.

## 7. Auto-Discover — aplikasi yang mencari pasangannya sendiri

Kalau kamu tidak mau mengisi merk/model manual:

1. Colok dongle-dongle yang mau diuji (boleh lewat hub).
2. **Nyalakan satu mouse** yang ingin kamu cocokkan — mouse harus sudah ter-pair dengan salah satu dongle itu.
3. Di **Dongle Lab**, klik **Auto-discover pairs**.
4. Aplikasi membaca perangkat HID yang benar-benar terhubung, mencocokkan nama/VID-PID-nya dengan katalog
   global, lalu **mendaftarkan mouse dan dongle otomatis** bila belum ada. Hasilnya muncul sebagai pasangan
   dengan confidence dan bukti.
5. Pasangan yang jelas disimpan sebagai **Suspected** (belum `matched`) — naikkan jadi **Confirmed** setelah
   kamu tes fisik. Kalau buktinya lemah atau beberapa dongle identik, aplikasi menandai **Needs verification**
   dan tidak menebak-nebak.

Kalau mouse mati atau belum ter-pair, Windows tidak menampilkan koneksi apa pun — tidak ada aplikasi yang
bisa mengetahui pasangannya dalam kondisi itu. Nyalakan mouse dulu, lalu jalankan Auto-Discover.

## 8. Sample data

After `db:seed`, the database contains researched real-world model data clearly marked **SAMPLE**.
It is a reference catalogue for exploring the UI, **not** your office inventory. Start with an empty
database (`php artisan migrate:fresh --force`) whenever you are ready to enter your own devices.
