# Architecture — PM Preventive

> ## ARSIP - DOKUMEN DESAIN AWAL (SUPERSEDED)
>
> Berkas ini adalah **spesifikasi desain sebelum aplikasi dibangun**, berasal dari
> prototipe single-file HTML. **Bukan sumber kebenaran.**
>
> Gunakan dokumen versi final di root proyek:
> - Kebutuhan produk & aturan bisnis -> [`../PRD.md`](../PRD.md)
> - Arsitektur teknis & skema database -> [`../Architecture.md`](../Architecture.md)
>
> Berikut hal yang **tidak lagi sesuai** dengan implementasi aktual:
>
> | Dikatakan di dokumen ini | Kenyataan di implementasi |
> |---|---|
> | Status PM 4: `draft`/`submitted`/`approved`/`rejected` | 5 status; ada `pic_approved` karena rantai persetujuan 3 tahap |
> | Persetujuan dilakukan `manager` | Rantai 3 tahap: `technician` -> `user` (User PIC) -> `manager` (Atasan), tiap tahap punya permission sendiri (`pm.sign`/`pm.acknowledge`/`pm.approve`) |
> | Tabel `machine_types`, kolom `users.is_active` | Tidak ada; jenis mesin memakai enum `App\Enums\MachineType` |
> | `TemplateSeeder`, `pm:import`, `RevisePmRecord`, `Http/Resources/*`, `lib/offlineQueue.js` | Tidak ada di implementasi; revisi dicatat di `pm_record_revisions`, impor lewat `machines:import` |
> | PWA + offline queue (M4), Recharts, Pest | Belum diimplementasikan; pengujian memakai PHPUnit + verifikasi UI via Playwright |
> | Setup lokal Laragon | Deploy memakai Docker (PHP-FPM + Nginx + Vite) |
>
> Dipertahankan hanya sebagai catatan keputusan desain & asal requirement.

Dokumen ini menjelaskan arsitektur teknis aplikasi PM Preventive. Kebutuhan produk ada di `PRD.md`.

## 1. Tech Stack

| Lapisan | Pilihan |
|---|---|
| Backend | Laravel 11 (PHP 8.2+) |
| Frontend | React 18 + Inertia.js (adapter `@inertiajs/react`), Vite |
| Styling | Tailwind CSS 3/4 + token neumorphism kustom |
| Database | MySQL 8 (Laragon, `utf8mb4`) |
| Auth | Laravel Breeze (stack React + Inertia) |
| Role & izin | `spatie/laravel-permission` + Laravel Policy |
| Ekspor | `maatwebsite/excel` (xlsx), `barryvdh/laravel-dompdf` atau print CSS |
| Chart | `recharts` atau SVG/CSS ringan |
| Test | Pest (backend), Vitest + Testing Library (opsional, frontend) |
| PWA (M4) | `vite-plugin-pwa` (service worker + manifest) |

## 2. Gambaran Sistem

```
┌────────────────────────── Browser / PWA (ponsel & desktop) ──────────────────────────┐
│  React pages (Inertia)  ─  Tailwind (neumorphism)  ─  IndexedDB sync queue (M4)       │
└───────────────▲───────────────────────────────────────────────┬───────────────────────┘
                │ Inertia response (props JSON)                  │ XHR/Inertia visit
┌───────────────┴───────────────────────────────────────────────▼───────────────────────┐
│ Laravel 11                                                                             │
│  Routes ─ Middleware(auth, role, HandleInertiaRequests) ─ Controllers (tipis)         │
│        └─ FormRequest (validasi) ─ Policy (otorisasi) ─ Service/Action (logika bisnis) │
│        └─ Eloquent Models ─ Resource (bentuk props) ─ Export/PDF ─ Activity log         │
└───────────────────────────────────────────┬───────────────────────────────────────────┘
                                            │
                                  MySQL (Laragon)
```

Prinsip: server adalah sumber kebenaran untuk **validasi dan otorisasi**. UI hanya menyembunyikan aksi yang tidak diizinkan; server tetap menolaknya.

## 3. Struktur Direktori

```
app/
  Actions/Pm/            SubmitPmRecord, RevisePmRecord, SignPmRecord, RejectPmRecord
  Enums/                 Role, PmStatus, PmDisplayStatus, SignatureStage, MachineType, Period
  Http/
    Controllers/         DashboardController, PmRecordController, ApprovalController,
                         PmSignatureController, MachineController, ChecklistTemplateController,
                         ReportController, UserController
    Middleware/          HandleInertiaRequests (share auth.user, auth.can, flash,
                         pendingApprovals)
    Requests/            StorePmRecordRequest, SignPmRecordRequest, RejectPmRecordRequest,
                         StoreMachineRequest, ...
    Resources/           MachineResource, PmRecordResource
  Models/                User, Machine, ChecklistTemplate, ChecklistTemplateItem,
                         PmRecord, PmRecordItem, PmRecordRevision, PmSignature, AuditLog
  Policies/              MachinePolicy, PmRecordPolicy (view/create/update/sign/reject), UserPolicy
  Services/              PeriodService, ReportService, DashboardService, SignatureChain,
                         SignatureStorage
database/
  migrations/  seeders/  (RoleSeeder, MachineSeeder, TemplateSeeder, UserSeeder)
resources/js/
  Components/            NeuCard, NeuButton, NeuInput, NeuChip, NeuPill, NeuToast,
                         NeuSignaturePad, SignatureChain, SignatureActions,
                         MachineFields (kolom form mesin, dipakai form tambah & ubah),
                         NeuButton (prop native = anchor biasa untuk unduhan)
  Layouts/               AuthenticatedLayout (sidebar ≥md, bottom-nav <md), GuestLayout
  Pages/                 Dashboard/Index, Approvals/Index, Pm/Form, Machines/Index,
                         Machines/History, Reports/Index, Admin/Templates, Admin/Users,
                         Admin/AuditLogs
  lib/                   period.js, format.js, offlineQueue.js
routes/web.php
tests/Feature/  tests/Unit/
```

## 4. Model Data (MySQL)

```
users                 id, name, email*, password, role_name (via spatie), is_active, timestamps
machines              id, code*(MC.1-FLLFM-101…), name, location, category, sub_category,
                      type (enum), week_group(1–9), template_id?, is_active,
                      sort_no, deleted_at, timestamps
checklist_templates   id, machine_type*, name, is_default, timestamps
checklist_template_items
                      id, template_id→, category, name, spec, sort_no
pm_records            id, machine_id→, year, period (enum 6 nilai), technician_id→users,
                      status (draft|submitted|pic_approved|approved|rejected), general_note,
                      revision_count, submitted_at, approved_by?, approved_at?, timestamps
                      UNIQUE(machine_id, year, period)
pm_record_items       id, pm_record_id→, item_name, category, spec, actual,
                      act_clean, act_repair, act_lubricate, act_replace (bool),
                      final_condition, parts_replaced (uint)
pm_record_revisions   id, pm_record_id→, revised_by→users, snapshot (json), reason, created_at
pm_signatures         id, pm_record_id→, stage (technician|pic|supervisor), image_path,
                      signed_by→users, signed_by_name, signed_by_role, note?, signed_at
                      UNIQUE(pm_record_id, stage)
audit_logs            id, user_id?, action, subject_type, subject_id, changes (json), ip, created_at
```

Catatan desain:
- `pm_record_items` menyimpan **salinan** nama/spesifikasi item saat PM dilakukan, sehingga perubahan template di kemudian hari tidak mengubah riwayat.
- Indeks: `pm_records(year, period, status)`, `pm_records(machine_id, year)`, `machines(is_active, week_group)`, `machines(is_active, sub_category)`.
- Soft delete pada `machines`; riwayat PM tetap utuh. Halaman **Kelola Mesin** (`/machines`, admin + manager) menyediakan tambah, ubah, hapus, dan pulihkan. Hapus = soft delete, jadi mesin yang sengaja dilepas bisa dikembalikan dari seksi "Mesin Dihapus" dan seluruh riwayat PM-nya tetap ada. `sort_no` mesin baru = nilai maksimum **termasuk mesin terhapus** + 1 supaya urutan tetap unik bila mesin itu dipulihkan.
- Semua aksi CRUD mesin dicatat ke `audit_logs` (`machine.create`, `machine.update`, `machine.delete`, `machine.restore`).
- Satu record per mesin per periode (unique key); revisi menambah `revision_count` dan satu baris `pm_record_revisions`.
- Daftar mesin diimpor dari `references/data.csv` lewat `php artisan machines:import` (`app/Services/MachineImportService.php`). Kolom yang tidak ada di CSV diturunkan: `type` dari kata kunci pada Machine Name, `week_group` dari angka Sub-Category (`C.7` → 7), `sort_no` dari kolom NO.

## 5. Autentikasi & Otorisasi

**Role:** `admin`, `manager`, `technician`, `user`, `viewer` (enum `App\Enums\Role`, disimpan lewat spatie permission).

**Permission (contoh):**
`dashboard.view`, `pm.fill`, `pm.sign`, `pm.acknowledge`, `pm.approve`, `pm.history.view`,
`report.view`, `report.export`, `machine.manage`, `template.manage`, `user.manage`, `audit.view`.

| Role | Permission |
|---|---|
| admin | semua |
| manager | dashboard.view, pm.approve, pm.history.view, report.*, machine.manage, template.manage, audit.view |
| technician | dashboard.view, pm.fill, pm.sign, pm.history.view, report.view, report.export |
| user | dashboard.view, pm.acknowledge, pm.history.view, report.view |
| viewer | dashboard.view, pm.history.view, report.view, report.export |

Tiga permission tanda tangan memetakan langsung ke tiga tahap rantai
(`SignatureStage`): `technician → pm.sign`, `user → pm.acknowledge`,
`manager → pm.approve`. `admin` lolos di semua tahap sebagai cadangan.

Penerapan:
1. **Route:** `->middleware('can:pm.fill')`, `can:dashboard.view`, atau `role:admin|manager`.
2. **Policy:** `PmRecordPolicy` mengatur lima gate:
   - `view` — semua user aktif boleh melihat record.
   - `create` — butuh `pm.fill`.
   - `update` — hanya saat `PmStatus::isEditable()` (draft atau rejected) **dan** periode
     belum lewat. Semenjak ada tanda tangan, checklist terkunci otomatis.
   - `sign` — user harus pemilik record pada tahap `technician`, atau punya permission
     tahap yang sedang menunggu pada tahap 2/3; user yang sama yang mengisi checklist
     tidak boleh menyetujui tahap 2/3.
   - `reject` — hanya tahap 2/3, minimal `pm.acknowledge`.
3. **Inertia share:** `HandleInertiaRequests` mengirim `auth.user`, `auth.can` (map boolean
   permission), dan `pendingApprovals` (jumlah antrean, dipakai badge sidebar) ke React.
   Komponen memakai `can('pm.fill')` untuk menampilkan/menyembunyikan aksi.

## 6. Routing Utama

| Method | URI | Fungsi | Izin |
|---|---|---|---|
| GET | `/` | Dashboard (query `period`, `year`, `q`, `status`, `sub`) | dashboard.view |
| GET | `/dashboard/export` | Ekspor daftar mesin + status PM (query filter sama + `format=xlsx\|pdf`) | report.export |
| GET | `/approvals` | Antrean persetujuan milik user yang sedang login | auth + punya tahap |
| GET | `/machines/{machine}/pm` | Form checklist | dashboard.view (read-only bila sudah ditandatangani) |
| POST | `/machines/{machine}/pm` | Simpan/revisi PM | pm.fill |
| POST | `/pm/{record}/sign` | Tanda tangan + lanjutkan tahap | PmRecordPolicy@sign |
| POST | `/pm/{record}/reject` | Tolak + kembalikan ke teknisi | PmRecordPolicy@reject |
| GET | `/pm/{record}/signature/{stage}` | Ambil PNG tanda tangan | PmRecordPolicy@view |
| GET | `/machines/{machine}/history` | Riwayat mesin | pm.history.view |
| GET | `/reports` · `/reports/export` | Laporan, ekspor xlsx/pdf | report.view / report.export |
| GET/POST | `/machines` | Daftar + tambah mesin (Kelola Mesin) | machine.manage |
| PUT/DELETE | `/machines/{machine}` | Ubah / hapus mesin (soft delete) | machine.manage |
| PATCH | `/machines/{machine}/restore` | Pulihkan mesin yang dihapus | machine.manage |
| resource | `/admin/templates`, `/admin/users` | CRUD master | template/user.manage |
| GET | `/admin/audit-logs` | Jejak audit | audit.view |

Catatan CRUD mesin:
- Form tambah dan ubah memakai komponen yang sama (`MachineFields`), jadi daftar kolomnya tidak bisa berbeda.
- Kolom yang dikelola: `code`, `name`, `location`, `category`, `sub_category`, `type`, `week_group`, `template_id`, `is_active`. `sort_no` hanya diisi otomatis saat mesin dibuat dan tidak ikut diubah.
- Pelanggaran izin dikembalikan sebagai **403** (middleware `can:machine.manage` dan policy), sedangkan validasi yang gagal mengembalikan redirect + `errors` yang ditampilkan tepat di bawah kolomnya.

Catatan ekspor Beranda:
- Endpoint `/dashboard/export` memakai `DashboardService` yang sama dengan halaman Beranda, jadi tabel di layar dan file yang diunduh tidak mungkin berbeda.
- Hanya `format=xlsx` (Excel) dan `format=pdf` (DomPDF) yang diterima; format lain ditolak validasi.
- File menghormati filter aktif (`year`, `period`, `status`, `sub`, `q`). Izin memakai `report.export`; role `user` (tidak punya izin itu) tidak melihat tombolnya dan ditolak **403** bila memanggil langsung.
- Tombol "Ekspor Excel" / "Cetak PDF" dirender sebagai anchor native (prop `native` pada `NeuButton`), bukan Inertia `<Link>`, karena Inertia menganggap respons file (xlsx/pdf) sebagai respons tidak valid dan tidak memulai unduhan.

## 7. Alur Simpan Checklist

```
React form ──POST──▶ StorePmRecordRequest (validasi: semua actual wajib, periode tidak future)
   └▶ PmRecordPolicy@create/update
      └▶ SubmitPmRecord / RevisePmRecord (DB::transaction)
           ├ upsert pm_records (machine_id, year, period)
           ├ replace pm_record_items
           ├ hapus tanda tangan lama — isi checklist berubah, tanda tangan batal
           ├ jika revisi: simpan snapshot lama → pm_record_revisions, revision_count++
           └ AuditLog::record(...)
      └▶ status = draft (bukan submitted) + flash "Checklist tersimpan" (Inertia)
```

Menyimpan checklist **tidak lagi** langsung menaikkan status ke `submitted`.
Status naik hanya saat tanda tangan, supaya tidak ada tahap approval yang
terlewati.

## 7b. Rantai Persetujuan 3 Tahap (Tanda Tangan)

```
Isi checklist + Nama Teknisi + tanda tangan ──▶ SATU SUBMIT
                                                        │
                                                        ▼
                        [1] Teknisi (selesai di submit yang sama)
                                                        │
                                                        ▼
                                    [2] User PIC "sebagai diketahui"
                                                        │
                                                        ▼
                                                    [3] Atasan
                                                        │
                                                        ▼
                                                     Selesai
```

Status dan label yang tampil:

| Status | Label di UI | Dot Beranda |
|---|---|---|
| `draft` | Menunggu Tanda Tangan | abu |
| `submitted` | On Progress Approval by User PIC | oranye |
| `pic_approved` | On Progress Approval by Atasan | oranye |
| `approved` | Selesai | hijau |
| `rejected` | Perlu Revisi | merah |

`submitted` dan `pic_approved` memakai satu bucket tampilan baru,
`PmDisplayStatus::Progress` ("Sedang Approval"). Bucket ini **tidak** dihitung
selesai oleh dot notifikasi maupun laporan; `ReportService::complianceByPeriod`
mendapat kolom `approving` untuk memisahkannya dari `completed`.

```
Tahap 1 — React ──POST /machines/{machine}/pm
            (items, technician_name, signature=dataURL PNG, note)
  └▶ StorePmRecordRequest (validasi: pm.fill + pm.sign, technician_name & signature WAJIB,
                           PNG sungguhan, record existing harus belum terkunci)
     └▶ SubmitPmRecord (simpan record + items, technician_name, status=draft)
        └▶ SignPmRecord (stage=Technician) → status=submitted
           └▶ kalau penyimpanan tanda tangan gagal: flash error, PM tetap di draft

Tahap 2 & 3 — React ──POST /pm/{record}/sign (signature=dataURL PNG, signer_name, note)
  Sumber formnya dua: Pm/Form dan kartu di /approvals. Badge sidebar memakai
  pendingQuery yang sama dengan daftar /approvals, jadi daftar ikut memuat record
  draft/rejected milik teknisi sendiri; karena itu SignatureActions dipasang
  embedded untuk tahap apa pun yang sedang menunggu, bukan hanya PIC/Atasan.
  └▶ SignPmRecordRequest (validasi: signer_name WAJIB, PNG sungguhan, ≤2 MB;
                           stage=technician → PmRecord::isChecklistComplete() WAJIB,
                           supaya PM kosong tidak bisa diteruskan dari antrean)
     └▶ PmRecordPolicy@sign  → SignatureChain::canSign($record, $user)
        └▶ SignPmRecord (DB::transaction)
             ├ SignatureStorage::put() → storage/app/public/signatures/...
             ├ upsert pm_signatures (UNIQUE pm_record_id, stage)
             ├ status = SignatureStage::stageAfterApproval(status)
             └ AuditLog::record('pm.sign.{stage}')
```

- **Satu endpoint untuk tahap 2 & 3.** Tahap aktif dibaca dari status record, jadi
  rantai tidak bisa dilompati atau diurutkan ulang; tidak ada endpoint terpisah per tahap.
  Tahap 1 memang berbeda: menyatu dengan penyimpanan checklist lewat
  `POST /machines/{machine}/pm` supaya teknisi cukup sekali submit.
- **Nama penanda tangan diketik manual di tiap tahap**, bukan diambil dari akun login,
  dan disimpan terpisah dari `technician_id` (yang tetap `Auth::id()` untuk kepemilikan
  dan anti konflik kepentingan). Tahap 1 menyimpannya di `pm_records.technician_name`
  sekaligus `pm_signatures.signed_by_name`; tahap 2 & 3 hanya di `signed_by_name`.
  Pembacaan untuk daftar/riwayat/ekspor lewat `PmRecord::technicianName()`, yang
  mendahulukan nama manual dan memakai nama akun sebagai cadangan untuk record lama.
- **SignatureStorage** memvalidasi data URL, memastikan magic bytes PNG, menulis ke
  disk, dan menghapus berkas lama saat tahap ditandatangani ulang. Dilayani lewat
  route ber-otorisasi `PmSignatureController@show` (bukan `storage:link`) supaya path
  tidak bergantung pada `APP_URL`.
- **Snapshot** `signed_by_name` dan `signed_by_role` disimpan di `pm_signatures`,
  jadi history tetap terbaca meski user-nya nanti berubah nama atau dinonaktifkan.
- **Penolakan** (`RejectPmRecord`) mengembalikan status ke `rejected`, menghapus
  seluruh tanda tangan, dan mencatat alasan di `audit_logs` (`pm.reject`).
  `PmRecord::rejectReason()` membacanya kembali. Catatan: Eloquent punya properti
  internal `protected $changes`, jadi di dalam kelas model kolom `changes`
  **harus** dibaca lewat `getAttribute('changes')`, bukan `$log->changes`.
- **Badge sidebar** dan daftar `/approvals` memakai `SignatureChain::pendingQuery`
  yang sama, sehingga angkanya tidak pernah berbeda.

## 8. Frontend & Design System (Neumorphism Light)

### Token Tailwind (`tailwind.config.js`)
```js
theme: { extend: {
  colors: { neu: { bg:'#E8ECF1', light:'#FFFFFF', dark:'#C3CAD6', text:'#2B3445', sub:'#5A6475',
                   accent:'#0F6E56', warn:'#A86B0B', bad:'#B93A2E' } },
  boxShadow: {
    'neu-up':   '6px 6px 14px #C3CAD6, -6px -6px 14px #FFFFFF',
    'neu-up-sm':'3px 3px 8px #C3CAD6, -3px -3px 8px #FFFFFF',
    'neu-in':   'inset 4px 4px 9px #C3CAD6, inset -4px -4px 9px #FFFFFF',
  },
  borderRadius: { neu: '22px', 'neu-sm': '14px' },
  fontFamily: { sans: ['"Plus Jakarta Sans"','system-ui','sans-serif'] },
}}
```

### Aturan komponen
- **Timbul (`shadow-neu-up`)** = elemen yang bisa dibaca/ditekan (kartu, tombol). **Tenggelam (`shadow-neu-in`)** = input, track progres, state aktif/ditekan.
- Latar halaman dan permukaan komponen **sama** (`bg-neu-bg`); kedalaman hanya dari bayangan.
- Satu aksen warna (`neu-accent`). Status memakai warna **dan** label teks.
- Target sentuh ≥ 44px; fokus keyboard wajib terlihat (`focus-visible:ring-2 ring-neu-accent`).
- Hormati `prefers-reduced-motion`.

### Branding / Logo
- Aset logo aplikasi: `public/img/logo.png` (tampilan, 310×301) dan `public/img/logo.ico` (favicon, 32×32).
- Komponen `ApplicationLogo.jsx` merender `<img src="/img/logo.png">` (bukan SVG Laravel lagi); dipakai `GuestLayout` (halaman login/register/lupa sandi).
- `resources/views/app.blade.php` memasang `<link rel="icon" href="/img/logo.ico">` + `apple-touch-icon`; `public/favicon.ico` juga sudah diganti dengan logo aplikasi agar tab browser konsisten.

### Layout responsif
- `< md` (ponsel): header atas + **bottom navigation** (fixed, `pb-[env(safe-area-inset-bottom)]`), konten 1 kolom.
- `≥ md` (tablet/iPad): **sidebar** 210px, daftar mesin 2 kolom.
- `≥ lg`: sidebar melebar ke 260px. `≥ xl`: daftar mesin 3 kolom.
- Konten dibungkus kelas `.page-container` (`max-w-3xl lg:max-w-4xl 2xl:max-w-5xl`) supaya tidak terlalu sempit di layar besar.
- Form (tambah/ubah mesin, pengguna, template) memakai 1 kolom di ponsel lalu 2 kolom mulai `sm`.
- Viewport: `width=device-width, initial-scale=1, viewport-fit=cover`.

### Pola Inertia
- Halaman = komponen React di `resources/js/Pages`, data lewat props dari controller (`Inertia::render`).
- Form: `useForm()` dari Inertia (error validasi otomatis ke field).
- Pencarian/filter: `router.get(url, params, { preserveState: true, replace: true })` dengan debounce.
- Props besar (riwayat) dimuat dengan *deferred/lazy props*.

## 9. Offline Queue (M4)

- Service worker meng-cache aset (app shell).
- Saat submit gagal karena offline, payload checklist disimpan di IndexedDB (`offlineQueue.js`) dan ditandai "Menunggu sinkronisasi".
- Saat `online`, antrean dikirim berurutan ke endpoint POST yang sama. Server idempoten berkat unique key `(machine_id, year, period)`; konflik revisi diselesaikan dengan *last-write-wins* + catatan di `pm_record_revisions`.

## 10. Setup Lokal (Laragon)

```bash
# Laragon: PHP 8.2+, MySQL, Node 20+, Composer
composer create-project laravel/laravel pm-preventive "11.*"
cd pm-preventive
composer require laravel/breeze spatie/laravel-permission maatwebsite/excel barryvdh/laravel-dompdf --dev # breeze dev saja
php artisan breeze:install react     # pilih Inertia + React (tanpa SSR untuk awal)
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
```

`.env`:
```
APP_NAME="PM Preventive"
APP_TIMEZONE=Asia/Jakarta
APP_LOCALE=id
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=pm_preventive
DB_USERNAME=root
DB_PASSWORD=
```

```bash
php artisan migrate --seed       # role, permission, mesin, template, akun demo
npm install && npm run dev       # terminal 1
php artisan serve                # terminal 2 (atau pakai pretty URL Laragon: pm-preventive.test)
```

Akun seeder (hanya lokal): `admin@example.test`, `manager@…`, `tech@…`, `user@…`, `viewer@…` — password diganti sebelum produksi.

## 11. Deployment & Operasional

- Web server Nginx/Apache + PHP-FPM, HTTPS wajib. `APP_DEBUG=false`.
- `npm run build`, `php artisan config:cache route:cache view:cache`.
- Berkas tanda tangan di `storage/app/public/signatures/`, dilayani lewat route
  ber-otorisasi (bukan symlink `storage:link`), jadi tidak bergantung pada `APP_URL`.
  Tetap masuk ke backup bersama folder `storage/`.
- Queue (`database` driver) untuk ekspor besar; scheduler untuk backup & pengingat PM terlambat.
- Backup: `mysqldump` harian + retensi 30 hari; uji restore berkala.
- Monitoring: log harian Laravel; opsional Sentry.

## 12. Strategi Pengujian

- **Feature test (Pest):** tiap role vs tiap endpoint (matriks izin di PRD), alur simpan/revisi, kunci periode masa depan, unique record.
- **Unit test:** `PeriodService` (mapping bulan → periode, lock periode), `ReportService` (hitungan kepatuhan).
- **Manual/UAT:** uji di Android + iOS Safari, mode cetak A4, koneksi lambat.
- **Aksesibilitas:** cek kontras token neumorphism dan navigasi keyboard.

Skenario yang wajib dijaga dalam uji alur persetujuan:

| Skenario | Ekspektasi |
|---|---|
| Submit tanpa nama teknisi | 422 `technician_name`, tidak ada record tersimpan |
| Submit tanpa tanda tangan | 422 `signature`, tidak ada record tersimpan |
| Submit lengkap | checklist tersimpan **dan** tahap teknisi lewat → `submitted` |
| Nama teknisi di form vs akun login | yang tersimpan adalah nama yang diketik (`technician_name`) |
| Tanda tangan bukan PNG | 422, tidak ada record tersimpan |
| Simpan ulang checklist yang sedang di-approval | 403 (record terkunci) |
| Manager/`pm.fill` tanpa `pm.sign` | 403 (tutup checklist butuh izin tanda tangan) |
| Tahap 2/3 tanpa nama penanda tangan | 422 `signer_name`, status tidak berubah |
| Nama tahap 2/3 | tersimpan di `signed_by_name`, **tidak** menimpa `technician_name` |
| Salah tahap | 403 (PIC/Atasan tidak bisa menandatangani tahap teknisi) |
| Tollar tahap 2/3 oleh orang yang mengisi checklist | 403 |
| Penolakan | status `rejected`, semua tanda tangan hilang, alasan tercatat di history |
| Penolakan tanpa alasan | ditolak |
| Rantai penuh | `draft → submitted → pic_approved → approved`, tiap tahap 1 tanda tangan |
| Setelah `approved` | checklist terkunci, tidak bisa tanda tangan/tolak lagi |

Matrix di atas dijaga oleh `tests/Feature/PmTechnicianSignatureTest.php`. Semua test berjalan
di sqlite in-memory karena `tests/TestCase.php` memaksa koneksinya — `RefreshDatabase` dari
test bawaan Breeze memakai `migrate:fresh`, jadi tanpa itu ia akan menghapus database produksi.

## 13. Migrasi dari Prototipe

| Prototipe | Aplikasi baru |
|---|---|
| `DEFAULT_MACHINES` (12 mesin prototype) | `references/data.csv` → `php artisan machines:import` → tabel `machines` (122 mesin) |
| `DEFAULT_TPL` (per tipe) | `TemplateSeeder` → `checklist_templates` + `_items` |
| `cfg.extraMachines/machineEdits/customChecklists` (localStorage) | CRUD mesin di database (admin + manager): tambah/ubah/hapus/pulihkan + jejak `audit_logs` |
| Power Automate `save/load` | Controller + Eloquent (endpoint Inertia) |
| `teknisi` (nama di localStorage) | Nama dari akun login (`technician_id`) |
| `syncQueue` | Offline queue IndexedDB (M4) |
| Data lama di SharePoint | `php artisan pm:import storage/import/pm.json` (command satu kali) |
