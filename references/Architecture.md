# Architecture — PM Preventive

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
  Actions/Pm/            SubmitPmRecord, RevisePmRecord, ApprovePmRecord
  Enums/                 Role, PmStatus, MachineType, Period
  Http/
    Controllers/         DashboardController, PmRecordController, MachineController,
                         ChecklistTemplateController, ReportController, UserController
    Middleware/          HandleInertiaRequests (share auth.user, auth.can, flash)
    Requests/            StorePmRecordRequest, StoreMachineRequest, ...
    Resources/           MachineResource, PmRecordResource
  Models/                User, Machine, ChecklistTemplate, ChecklistTemplateItem,
                         PmRecord, PmRecordItem, PmRecordRevision, AuditLog
  Policies/              MachinePolicy, PmRecordPolicy, UserPolicy
  Services/              PeriodService, ReportService
database/
  migrations/  seeders/  (RoleSeeder, MachineSeeder, TemplateSeeder, UserSeeder)
resources/js/
  Components/ui/         NeuCard, NeuButton, NeuInput, NeuChip, NeuPill, NeuRing, NeuBars
  Layouts/               AppLayout (sidebar ≥lg, bottom-nav <lg), GuestLayout
  Pages/                 Dashboard, Pm/Form, Machines/History, Reports/Index,
                         Admin/Machines, Admin/Templates, Admin/Users
  lib/                   period.js, format.js, offlineQueue.js
routes/web.php
tests/Feature/  tests/Unit/
```

## 4. Model Data (MySQL)

```
users                 id, name, email*, password, role_name (via spatie), is_active, timestamps
machines              id, code*(M2…), name, type (enum), week_group(1–9), template_id?, is_active,
                      sort_no, deleted_at, timestamps
checklist_templates   id, machine_type*, name, is_default, timestamps
checklist_template_items
                      id, template_id→, category, name, spec, sort_no
pm_records            id, machine_id→, year, period (enum 6 nilai), technician_id→users,
                      status (draft|submitted|approved|rejected), general_note,
                      revision_count, submitted_at, approved_by?, approved_at?, timestamps
                      UNIQUE(machine_id, year, period)
pm_record_items       id, pm_record_id→, item_name, category, spec, actual,
                      act_clean, act_repair, act_lubricate, act_replace (bool),
                      final_condition, parts_replaced (uint)
pm_record_revisions   id, pm_record_id→, revised_by→users, snapshot (json), reason, created_at
audit_logs            id, user_id?, action, subject_type, subject_id, changes (json), ip, created_at
```

Catatan desain:
- `pm_record_items` menyimpan **salinan** nama/spesifikasi item saat PM dilakukan, sehingga perubahan template di kemudian hari tidak mengubah riwayat.
- Indeks: `pm_records(year, period, status)`, `pm_records(machine_id, year)`, `machines(is_active, week_group)`.
- Soft delete pada `machines`; riwayat PM tetap utuh.
- Satu record per mesin per periode (unique key); revisi menambah `revision_count` dan satu baris `pm_record_revisions`.

## 5. Autentikasi & Otorisasi

**Role:** `admin`, `manager`, `technician`, `user`, `viewer` (enum `App\Enums\Role`, disimpan lewat spatie permission).

**Permission (contoh):**
`dashboard.view`, `pm.fill`, `pm.approve`, `pm.history.view`, `report.view`, `report.export`, `machine.manage`, `template.manage`, `user.manage`, `audit.view`.

| Role | Permission |
|---|---|
| admin | semua |
| manager | dashboard.view, pm.approve, pm.history.view, report.*, machine.manage, template.manage, audit.view |
| technician | dashboard.view, pm.fill, pm.history.view, report.view, report.export |
| user | dashboard.view, pm.history.view, report.view |
| viewer | dashboard.view, pm.history.view, report.view, report.export |

Penerapan:
1. **Route:** `->middleware('can:pm.fill')` atau `role:admin|manager`.
2. **Policy:** `PmRecordPolicy@update` memastikan technician hanya merevisi record miliknya, status bukan `approved`, dan periode tidak di masa depan.
3. **Inertia share:** `HandleInertiaRequests` mengirim `auth.user` dan `auth.can` (map boolean permission) ke React. Komponen memakai `can('pm.fill')` untuk menampilkan/menyembunyikan aksi.

## 6. Routing Utama

| Method | URI | Fungsi | Izin |
|---|---|---|---|
| GET | `/` | Dashboard (query `period`, `year`, `q`, `status`) | dashboard.view |
| GET | `/machines/{machine}/pm` | Form checklist | pm.fill / dashboard.view (read-only) |
| POST | `/machines/{machine}/pm` | Simpan/revisi PM | pm.fill |
| POST | `/pm/{record}/approve` · `/reject` | Persetujuan | pm.approve |
| GET | `/machines/{machine}/history` | Riwayat mesin | pm.history.view |
| GET | `/reports` · `/reports/export` | Laporan, ekspor xlsx/pdf | report.view / report.export |
| resource | `/admin/machines`, `/admin/templates`, `/admin/users` | CRUD master | machine/template/user.manage |

## 7. Alur Simpan Checklist

```
React form ──POST──▶ StorePmRecordRequest (validasi: semua actual wajib, periode tidak future)
   └▶ PmRecordPolicy@create/update
      └▶ SubmitPmRecord / RevisePmRecord (DB::transaction)
           ├ upsert pm_records (machine_id, year, period)
           ├ replace pm_record_items
           ├ jika revisi: simpan snapshot lama → pm_record_revisions, revision_count++
           └ AuditLog::record(...)
      └▶ redirect + flash "Checklist tersimpan" (Inertia)
```

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

### Layout responsif
- `< lg`: header + **bottom navigation** (fixed, `pb-[env(safe-area-inset-bottom)]`), konten 1 kolom.
- `≥ lg`: **sidebar** 270px, daftar mesin 2 kolom.
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
- Queue (`database` driver) untuk ekspor besar; scheduler untuk backup & pengingat PM terlambat.
- Backup: `mysqldump` harian + retensi 30 hari; uji restore berkala.
- Monitoring: log harian Laravel; opsional Sentry.

## 12. Strategi Pengujian

- **Feature test (Pest):** tiap role vs tiap endpoint (matriks izin di PRD), alur simpan/revisi, kunci periode masa depan, unique record.
- **Unit test:** `PeriodService` (mapping bulan → periode, lock periode), `ReportService` (hitungan kepatuhan).
- **Manual/UAT:** uji di Android + iOS Safari, mode cetak A4, koneksi lambat.
- **Aksesibilitas:** cek kontras token neumorphism dan navigasi keyboard.

## 13. Migrasi dari Prototipe

| Prototipe | Aplikasi baru |
|---|---|
| `DEFAULT_MACHINES` | `MachineSeeder` → tabel `machines` |
| `DEFAULT_TPL` (per tipe) | `TemplateSeeder` → `checklist_templates` + `_items` |
| `cfg.extraMachines/machineEdits/customChecklists` (localStorage) | CRUD admin di database |
| Power Automate `save/load` | Controller + Eloquent (endpoint Inertia) |
| `teknisi` (nama di localStorage) | Nama dari akun login (`technician_id`) |
| `syncQueue` | Offline queue IndexedDB (M4) |
| Data lama di SharePoint | `php artisan pm:import storage/import/pm.json` (command satu kali) |
