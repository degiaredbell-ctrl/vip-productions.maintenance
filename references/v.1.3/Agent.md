# Agent.md — Panduan untuk AI Coding Agent

Dokumen ini adalah instruksi kerja untuk agent (Claude Code, Cursor, Copilot,
dll.) yang mengerjakan repo **PM Preventive**. Baca [PRD.md](PRD.md) (apa yang
dibangun), [Architecture.md](Architecture.md) (bagaimana dibangun), dan
[Guide.md](Guide.md) (bagaimana pengguna memakai fiturnya) sebelum mengubah
kode.

**Versi dokumen:** 1.3 · Zona waktu aplikasi: **Asia/Jakarta (WIB)** · Bahasa UI: **Bahasa Indonesia**.

---

## 1. Peta Dokumentasi

| Dokumen | Isi |
|---------|-----|
| [`../../README.md`](../../README.md) | Setup, perintah, akun demo, catatan deployment |
| [`PRD.md`](PRD.md) | Tujuan, persona, kebutuhan fungsional (REQ-*), aturan bisnis |
| [`Architecture.md`](Architecture.md) | Stack, layer, skema database, alur, otorisasi, keamanan, modul notifikasi |
| [`Guide.md`](Guide.md) | Panduan pengguna per peran: cara login, isi PM, tanda tangan, notifikasi, laporan |
| `Agent.md` (dokumen ini) | Aturan kerja agent: perintah, konvensi, larangan, DoD |
| [`../v.1.2/`](../v.1.2/) | **Arsip** dokumen versi sebelumnya (superseded) — jangan dijadikan acuan |
| [`../v.1.1/`](../v.1.1/) | **Arsip** dokumen desain awal (superseded) — jangan dijadikan acuan |
| [`../data-source/`](../data-source/) | CSV sumber impor + pratinjau design system |

Bila kode dan dokumen berbeda, **kode adalah kebenaran**; perbarui dokumen dalam
perubahan yang sama.

---

## 2. Konteks Proyek

Aplikasi web maintenance mesin pabrik (PM berkala 2 bulanan, checklist per unit,
rantai tanda tangan 3 tahap, riwayat, laporan, **notifikasi dua saluran
In-App + Email**). Pengguna utama teknisi di ponsel.

**Stack:** Laravel `^11.31` · PHP `^8.2` (image `php:8.3-fpm-alpine`) ·
Inertia.js `^2.0` + React `^18.2` · Tailwind CSS `^3.2` · MySQL · Vite `^6` ·
Spatie Laravel Permission · Maatwebsite Excel + DomPDF · Nginx + PHP-FPM (Docker) ·
PHPUnit 11.

**Role** (`app/Enums/Role.php` + `RoleSeeder`): `admin`, `manager`,
`technician`, `user`, `viewer`, plus role teknisi ber-scope `tech-machines`,
`tech-utilities`, `tech-all`.

Penting: model `App\Models\Machine` memakai tabel **`assets`** yang menampung
**Mesin dan Utility** sekaligus. Jangan menganggap ada tabel `machines` terpisah.

Pada v1.3 aplikasi punya 4 container: `vip_app` (web), `vip_queue` (kirim email
di latar belakang), `vip_scheduler` (jadwal notifikasi/laporan/backup), dan
`vip_nginx`. Modul notifikasi hidup di `app/Services/Notifications/` +
`app/Notifications/`; pengaturan matriks & jadwal disimpan di tabel
`app_settings` (bukan `.env`).

---

## 3. Perintah Penting

```bash
# Produksi lokal (Docker) — satu perintah untuk PHP + frontend + queue + scheduler
docker compose up -d --build
docker exec vip_app php artisan migrate --force    # migrasi TIDAK otomatis
docker exec vip_app php artisan optimize:clear
docker exec vip_app php artisan <command>           # artisan lain

# Test (wajib; DB dipaksa sqlite in-memory oleh tests/TestCase.php)
docker run --rm -v "$PWD":/app -w /app --entrypoint php \
  vip-maintenance-app vendor/bin/phpunit
#       atau, di dalam container: php artisan test

# Format & build
./vendor/bin/pint            # format PHP (Pint)
npm run build                # build frontend (Vite)
npm run dev                  # dev server Vite (untuk development lokal)
```

- `php artisan test` dan `vendor/bin/phpunit` setara; jalankan **seluruh** suite
  sebelum menyatakan selesai, bukan hanya test yang baru dibuat.
- Impor data: `php artisan machines:import` (Mesin/Utility),
  `php artisan spare-parts:import` (suku cadang), dan
  `php artisan templates:import` (template komponen).
  File default dibaca dari `references/data-source/`.
- Notifikasi & laporan (test tanpa mengirim):
  `php artisan notifications:pm-alert overdue --dry-run`
  `php artisan integration:send-pm-report weekly --dry-run`
- Backup: `php artisan backup:database --type=manual`
- Darurat (matikan login OTP): `php artisan integration:disable-otp`

---

## 4. Aturan Arsitektur (wajib dipatuhi)

1. **Controller tetap tipis.** Orkestrasi ada di controller; aturan keputusan
   yang dipakai lebih dari satu halaman ada di `app/Services/`.
2. **Aturan rantai tanda tangan hanya di `App\Services\SignatureChain`.** Jangan
   menyalin logika "siapa boleh tanda tangan tahap apa" ke controller/policy.
3. **Validasi di FormRequest**, bukan di controller (`app/Http/Requests/`).
   Pesan validasi ditulis dalam Bahasa Indonesia.
4. **Otorisasi di Policy** (`app/Policies/`) dan/atau middleware route; jangan
   hanya menyembunyikan tombol di UI. Akses URL langsung tetap harus diblokir.
5. **Proses yang mengubah state memakai `DB::transaction`** dan Action di
   `app/Actions/Pm/` untuk submit/sign/reject.
6. **Riwayat tidak boleh hilang.** `pm_signatures` menyimpan `signed_by_name` &
   `signed_by_role` (snapshot); relasi ke user memakai `nullOnDelete`; mesin
   memakai soft delete. Jangan menghapus jejak audit.
7. **Satu record PM per (machine_id, year, period).** Hormati unique constraint
   dan kunci periode (periode terkunci/future tidak bisa diisi).
8. **Item checklist sumbernya master Komponen.** `Machine::effectiveComponents()`
   memilih: snapshot record lama → komponen pivot unit → item template lama
   (cadangan). Jangan memakai `ChecklistTemplate` sebagai sumber utama lagi.
9. **Nilai domain memakai Enum** (`PmStatus`, `SignatureStage`, `Period`, `Role`,
   `MachineType`, `PmDisplayStatus`). Jangan pakai string ajaib.
10. **Aset hasil build disajikan lewat mekanisme entrypoint**, bukan `docker cp`
    manual (lihat §9).
11. **Aturan penerima notifikasi hanya di `App\Services\Notifications\NotificationRecipients`.**
    Jangan menulis ulang "siapa boleh menerima notifikasi apa" di controller,
    policy, atau command. Lihat §4a.
12. **Notifikasi tidak boleh menggagalkan proses utama.** Semua pemanggilan
    notifikasi pada approve/reject dibungkus `try/catch` + `Log::warning()`.
13. **Anti duplikasi lewat `notification_dispatches.dedupe_key` (UNIQUE).**
    Jangan menambah cek "sudah terkirim?" di memori — harus lewat DB.
14. **Angka laporan = angka Beranda.** Laporan email/dashboard wajib memakai
    `DashboardService`, bukan query terpisah yang bisa menyimpang.
15. **Halaman error dirender di satu tempat** (`bootstrap/app.php` →
    `Exceptions::respond()`), dan **tidak boleh** menampilkan pesan exception
    ke pengguna.

### 4a. Aturan modul notifikasi (wajib dipatuhi)

| Aturan | Kenapa | Di mana |
|--------|--------|---------|
| Teknisi hanya menerima notifikasi unit sesuai scope-nya (Mesin vs Utility) | Menghindari notifikasi tak relevan | `NotificationRecipients::technicians()` |
| User/PIC hanya menerima unit yang menjadi tanggung jawabnya | PIC per perangkat | `assets.pic_user_id` → fallback PIC riwayat PM terakhir |
| Atasan = pemegang `pm.approve` selain Admin; Admin hanya fallback | Notifikasi tidak menumpuk di kotak masuk yang tidak dipakai | `NotificationRecipients::supervisors()` |
| Satu orang = satu notifikasi gabungan | Mencegah inbox & email dobel | `PmAlertDispatcher` |
| Baris 3 (approval) & 4 (laporan) penerimanya **tidak dapat** diubah dari UI | Aturan bisnis, bukan preferensi | `NotificationController::updateMatrix` |
| Baris 3.4 (Perlu revisi) **Apps saja** bawaan | Alasan revisi internal | `IntegrationSettings` |
| Baris 4.x **Email saja** | Laporan tidak relevan sebagai notifikasi In-App | `PmReportSender` |
| Reminder sekali per periode; keterlambatan sekali per slot jam | Anti spam | `dedupe_key` |
| Email gagal → In-App tetap jalan, status `failed` dicatat | Notifikasi tidak boleh memblokir proses | `notification_dispatches` |

---

## 5. Konvensi Kode

- **Bahasa:** seluruh teks UI, pesan validasi, flash message, dan komentar domain
  dalam **Bahasa Indonesia**. Nama class/variabel dalam bahasa Inggris.
- **PHP:** ikuti style Pint; tipe balik eksplisit; `declare` tidak dipakai.
  Enum untuk nilai tetap; FormRequest untuk validasi; Policy untuk otorisasi.
- **React (Inertia):** halaman di `resources/js/Pages/`, komponen dibagi di
  `resources/js/Components/`. Props dikirim sebagai array biasa dari controller
  (`Inertia::render`). Gunakan `route()` helper dan komponen `Neu*`.
- **Tailwind:** pakai token/utility yang ada; jangan menambah CSS global baru
  kecuali perlu. Ikuti design system Neumorphism (lihat §6).
- **Migrations:** satu perubahan skema per file, `up()` dan `down()` lengkap;
  jangan mengubah migration yang sudah pernah dijalankan — buat yang baru.
- **Nama route:** `admin.<resource>` untuk halaman admin (mis. `admin.components`,
  `admin.pm-records`); `notifications.*` untuk Kotak Masuk, feed lonceng, dan
  pengaturan notifikasi (mis. `notifications.inbox`, `notifications.index`,
  `notifications.preview`).
- **Halaman error:** komponen tunggal `resources/js/Pages/Errors/Error.jsx` +
  kelas CSS `err-*` di `resources/css/app.css`. Jangan membuat halaman error
  per status.
- **Notifikasi:** payload `data` notifikasi harus memuat **nomor dokumen**,
  **nama perangkat**, **pengirim**, **waktu**, dan **tautan** — semua baris
  matriks memakai struktur yang sama.

---

## 6. Desain — Minimalist Neumorphism Light

Design system ada di `resources/js/Components/` (`NeuCard`, `NeuButton`, `NeuInput`,
`NeuPill`, `NeuRing`, `NeuToast`, `NeuTrack`, `NeuBars`, `NeuSignaturePad`).
Pratinjau visual: [`../data-source/PM Preventive – Preview Neumorphism.html`](../data-source/PM%20Preventive%20–%20Preview%20Neumorphism.html).

- Latar terang, bayangan ganda (terang + gelap), sudut membulat.
- Warna status PM mengikuti `PmDisplayStatus` (biru/oranye/merah/hijau).
- Prioritaskan keterbacaan di ponsel; target sentuh besar.

---

## 7. Hak Akses (ringkas)

Sumber tunggal: `database/seeders/RoleSeeder.php` (+ matriks di README/PRD).

- `pm.fill` + `pm.sign` — mengisi & menandatangani tahap teknisi.
- `pm.acknowledge` (tahap User PIC) dan `pm.approve` (tahap Atasan) dipisah per
  tahap — **jangan** digabung.
- `pm.manage` — halaman `/admin/pm-records`: lihat semua + hapus permanen.
- `machine.manage` / `utility.manage` / `template.manage` — CRUD Mesin, Utility,
  dan Master Komponen.
- `machine.view` / `utility.view` — scope daftar (tanpa CRUD).
- `sparepart.view` (semua role) dan `sparepart.manage` (admin, tech-all).
- `user.manage` (admin), `activity.view` (admin, manager — nama lama `audit.view`),
  `role:admin` untuk halaman Role.
- `component.manage` (master Komponen), `masterdata.manage` (Tipe Mesin & Lokasi),
  `backup.manage` (admin), `integration.manage` (admin).
- `notification.manage` (admin) — mengatur **konfigurasi** notifikasi
  (matriks, jadwal, PIC perangkat, kirim manual, riwayat). Permission ini
  **tidak** membatasi siapa yang *menerima* notifikasi.

Admin **tidak boleh** mengubah role sendiri atau menghapus akunnya sendiri
(guard di controller + Policy).

---

## 8. Alur Kerja Agent

1. **Baca dulu**: `PRD.md` → `Architecture.md` → kode terkait sebelum mengubah.
2. **Cari implementasi serupa** (pola controller/service/action yang sudah ada).
3. **Ubah kode** dengan perubahan minimum yang konsisten dengan sekitarnya.
4. **Migrations** (bila skema berubah): buat migration baru, jalankan
   `php artisan migrate` di container — migrasi tidak dijalankan otomatis saat deploy.
5. **Model/factory/seeder** bila perlu; jaga `Deleting`/relasi tetap utuh.
6. **Test**: tambah/ubah feature test di `tests/Feature/`, lalu jalankan **seluruh**
   suite (lihat §3). Jangan mengandalkan `pint --test` (mock/format) sebagai bukti.
7. **Format**: `./vendor/bin/pint` pada file yang disentuh.
8. **Frontend & layanan**: `docker compose up -d --build` agar aset baru masuk
   volume **dan** container `vip_queue` / `vip_scheduler` ikut ter-deploy.
   Setelah itu `docker exec vip_app php artisan optimize:clear`.
9. **Verifikasi UI** bila relevan (halaman admin/role sensitif).
10. **Perbarui dokumen** (`README.md`, `PRD.md`, `Architecture.md`, `Guide.md`,
    dan snapshot `references/v.1.3/`) bila perilaku/route/skema berubah. Bila
    yang berubah adalah alur atau label yang dilihat pengguna (menu, tombol,
    langkah per peran), `Guide.md` wajib ikut diperbarui.

---

## 9. Catatan Lingkungan (gotchas)

- **Migrasi tidak otomatis** di produksi — selalu jalankan `migrate --force`
  setelah menambah migration.
- **Named volume `app_public` menutupi `/public`.** Aset build disalin ke volume
  oleh `docker/app-entrypoint.sh` saat container start; build ulang dengan
  `docker compose up -d --build`. Kalau aset "tidak berubah", cek log sync.
- **`vip_scheduler` & `vip_queue` wajib hidup.** Tanpa scheduler tidak ada
  reminder/keterlambatan/laporan/backup terjadwal; tanpa worker, email
  notifikasi menumpuk di tabel `jobs`. Cek: `docker compose ps`.
- **Email keluar lewat antrean.** Notifikasi memakai `ShouldQueue` — kalau
  worker mati, `email_sent` pada riwayat pengiriman tetap 0.
- **Test memaksa sqlite in-memory** (`tests/TestCase.php`). `DB_*` di shell/`.env`
  tidak menembusnya; ini melindungi data produksi dari `migrate:fresh`.
- **`storage/` di-bind mount** dari host — log/session/tanda tangan bertahan saat
  container di-recreate; layout `references/` terbaru: `data-source/` (CSV),
  `v.1.1/` & `v.1.2/` (arsip), `v.1.3/` (dokumen terkini: PRD, Architecture,
  Agent, Guide).
- **Zona waktu WIB.** `APP_TIMEZONE=Asia/Jakarta`, koneksi DB dikunci `+07:00`,
  dan jadwal memakai `->timezone('Asia/Jakarta')`. Tanggal di browser juga
  dipaksa WIB — jangan menghitung dengan asumsi UTC.
- **`pint --test` sudah punya kegagalan lama** di beberapa file yang tak terkait;
  format file yang Anda sentuh saja, jangan memformat ulang seluruh repo.
- **Dokumen versi terbaru ada di `references/v.1.3/`** (`Agent.md`,
  `Architecture.md`, `Guide.md`, `PRD.md`). `v.1.1/` dan `v.1.2/` adalah arsip —
  jangan jadi acuan.

---

## 10. Larangan

- Jangan mengubah/menghapus migration yang sudah dijalankan.
- Jangan menaruh `APP_KEY`, kredensial DB, atau rahasia lain di repo/log/audit.
- Jangan melakukan `migrate:fresh` pada database non-test.
- Jangan melewati otorisasi server dengan hanya menyembunyikan tombol.
- Jangan menghapus jejak audit/tanda tangan demi "merapikan" data.
- Jangan menambah dependency baru tanpa alasan kuat.
- Jangan membuat UI berbahasa Inggris.

---

## 11. Definition of Done

1. Fitur berjalan sesuai `PRD.md` dan aturan di `Architecture.md`.
2. Otorisasi ditegakkan di server (Policy/middleware), bukan hanya di UI.
3. Seluruh test suite lulus (`php artisan test`).
4. File yang disentuh lolos `./vendor/bin/pint`.
5. Migrasi (bila ada) tercatat dan sudah dijalankan.
6. Teks UI & pesan validasi berbahasa Indonesia.
7. `README.md`/`PRD.md`/`Architecture.md`/`Guide.md` (dan snapshot v1.3)
   diperbarui bila relevan.
8. Bila menyentuh notifikasi: **tidak ada** email yang terkirim saat test, dan
   `notifications:pm-alert --dry-run` sudah dicek bila mengubah penerima.
9. Bila menyentuh halaman error: **tidak boleh** ada detail exception yang
   bocor ke pengguna, dan respons JSON tetap JSON.

---

## 12. Dokumen Terkait

- [README.md](../../README.md) — setup & perintah
- [PRD.md](PRD.md) — kebutuhan produk & aturan bisnis
- [Architecture.md](Architecture.md) — arsitektur teknis
- [Guide.md](Guide.md) — panduan pengguna per peran
- [`../v.1.2/`](../v.1.2/) — arsip dokumen versi sebelumnya
