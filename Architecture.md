# Architecture — PM Preventive System

Dokumen arsitektur teknis aplikasi **Preventive Maintenance (PM)**.
Untuk tujuan & ruang lingkup produk, lihat [PRD.md](PRD.md).
Untuk panduan setup, lihat [README.md](README.md).

**Versi dokumen:** 1.2

---

## Daftar Isi

1. [Ikhtisar](#1-ikhtisar)
2. [Technology Stack](#2-technology-stack)
3. [Diagram Arsitektur](#3-diagram-arsitektur)
4. [Layer & Struktur Kode](#4-layer--struktur-kode)
5. [Model Data](#5-model-data)
6. [Mesin Status PM](#6-mesin-status-pm)
7. [Alur Rantai Tanda Tangan](#7-alur-rantai-tanda-tangan)
8. [Lapisan Otorisasi](#8-lapisan-otorisasi)
9. [Alur Request Utama](#9-alur-request-utama)
10. [Frontend](#10-frontend)
11. [Ekspor](#11-ekspor)
12. [Impor CSV](#12-impor-csv)
13. [Activity Logs](#13-activity-logs)
14. [Backup & Restore](#14-backup--restore)
15. [Keamanan](#15-keamanan)
16. [Deployment](#16-deployment)
17. [Testing](#17-testing)
18. [Trade-off & Keputusan Desain](#18-trade-off--keputusan-desain)
19. [Known Limitations](#19-known-limitations)

---

## 1. Ikhtisar

Aplikasi **monolitik** berbasis Laravel dengan **SPA hybrid** melalui Inertia.js.
Tidak ada API terpisah: halaman Vue/React dikembalikan sebagai *Inertia page* dengan props JSON,
lalu di-render client-side tanpa reload penuh.

Tiga invariant yang harus dijaga oleh seluruh kode:

| # | Invariant | Dijaga oleh |
|---|-----------|-------------|
| 1 | Tahap persetujuan ditentukan server, tidak bisa dilompati | `PmStatus::awaiting()` + `SignatureChain` |
| 2 | Checklist terkunci setelah ditandatangani | `PmStatus::isEditable()` + `PmRecordPolicy::update()` |
| 3 | Otorisasi ditegakkan di server, bukan hanya disembunyikan di UI | `can:` middleware + Policy |

---

## 2. Technology Stack

| Lapisan | Teknologi | Versi |
|---------|-----------|-------|
| Runtime | PHP | ^8.2 (image `php:8.3-fpm-alpine`) |
| Framework | Laravel | ^11.31 |
| Adapter SPA | Inertia.js Laravel | ^2.0 |
| UI | React | ^18.2 |
| Bundler | Vite | ^6.0.11 |
| Styling | Tailwind CSS | ^3.2 |
| RBAC | Spatie Laravel Permission | ^6.25 |
| Auth scaffold | Laravel Breeze | — |
| Database | MySQL | — |
| Spreadsheet | Maatwebsite Excel | ^3.1 |
| PDF | Barryvdh DomPDF | ^3.1 |
| Web server | Nginx alpine | 1.27 |
| Testing | PHPUnit | ^11.0 |

Ekstensi PHP yang diaktifkan di image: `pdo_mysql`, `mbstring`, `zip`, `gd`, `intl`, `bcmath`, `opcache`, `pcntl`.

---

## 3. Diagram Arsitektur

```
┌──────────────┐        HTTP :8082        ┌─────────────────────┐
│   Browser    │ ───────────────────────▶ │  vip_nginx          │
│  (Inertia +  │ ◀─────────────────────── │  nginx:1.27-alpine  │
│   React SPA) │     static + HTML        └──────────┬──────────┘
└──────────────┘                                   │ FastCGI :9000
                                     try_files …/index.php
                                                   ▼
                                    ┌──────────────────────────────┐
                                    │  vip_app  (php:8.3-fpm)      │
                                    │                              │
                                    │  Middleware                  │
                                    │   web: Session, CSRF,        │
                                    │        HandleInertiaRequests │
                                    │   auth, verified             │
                                    │   can:*, role:*              │
                                    │            ▼                 │
                                    │  Controller  ──▶ FormRequest │
                                    │            ▼                 │
                                    │  Action / Service             │
                                    │            ▼                 │
                                    │  Policy (Gate)                │
                                    │            ▼                 │
                                    │  Eloquent Model               │
                                    └───────────┬──────────────────┘
                                                │
                    ┌───────────────────────────┼───────────────────────────┐
                    ▼                           ▼                           ▼
             ┌────────────┐            ┌──────────────┐            ┌────────────────┐
             │  MySQL DB  │            │ Filesystem   │            │ public/build   │
             │            │            │ storage/     │            │ (Vite assets)  │
             │ users      │            │  app/public/ │            │ volume:shared  │
             │ machines   │            │  signatures/ │            └────────────────┘
             │ pm_records │            └──────────────┘
             │ ...        │
             └────────────┘
```

**Catatan deployment penting:** nginx membaca `public/` dari **named volume**, bukan dari image —
dan volume hanya diisi dari image saat pertama kali dibuat. Hasil `vite build` karena itu ditaruh di
`/var/www/html/public-image` (di luar volume) lalu disalin ke volume oleh `docker/app-entrypoint.sh`
setiap container start. Akibatnya `docker compose up -d --build app` sudah cukup untuk perubahan
frontend. Detailnya ada di [README — Catatan Deployment](README.md#catatan-deployment).

---

## 4. Layer & Struktur Kode

Pola yang dipakai: **Controller tipis → Action/Service → Model**, dengan validasi masuk ke `FormRequest`
dan otorisasi ke `Policy`.

```
app/
├── Enums/                    Sumber kebenaran nilai domain
│   ├── PmStatus.php          draft/submitted/pic_approved/approved/rejected
│   ├── SignatureStage.php    technician/pic/supervisor + permission
│   ├── Period.php            6 periode 2-bulanan + deteksi periode berjalan
│   ├── Role.php              admin/manager/technician/user/viewer + tech-machines/tech-utilities/tech-all
│   ├── MachineType.php       9 jenis mesin
│   └── PmDisplayStatus.php   Status turunan untuk tampilan
│
├── Http/
│   ├── Middleware/
│   │   ├── HandleInertiaRequests.php   Shared props: auth.user, auth.can, flash
│   │   └── EnsureAnyPermission.php     Alias `can_any` (scope mesin vs utility)
│   ├── Requests/             Validasi (FormRequest)
│   │   ├── StorePmRecordRequest / SignPmRecordRequest / RejectPmRecordRequest
│   │   ├── StoreMachineRequest / UpdateMachineRequest
│   │   ├── StoreUserSignatureRequest
│   │   └── ProfileUpdateRequest
│   └── Controllers/
│       ├── DashboardController.php      Beranda + form-maintenance + export (XLSX/PDF)
│       ├── MachineController.php        CRUD Mesin (tabel `assets`) + restore + history
│       ├── UtilityController.php        Daftar unit Utility + komponennya
│       ├── PmRecordController.php       create/store/sign/reject + riwayat + admin PM Records
│       ├── PmSignatureController.php    Streaming gambar tanda tangan dokumen
│       ├── ApprovalController.php       Antrean per permission
│       ├── ReportController.php         Statistik + export
│       ├── ProfileController.php        Info akun + tanda tangan tersimpan
│       ├── SparePartController.php      Master suku cadang + transaksi stok + impor
│       ├── SparePartUsageController.php Pemakaian suku cadang ke unit per periode (form-maintenance/suku-cadang)
│       └── Admin/
│           ├── UserController.php       CRUD pengguna + ganti password
│           ├── RoleController.php       CRUD role (authorizeResource)
│           ├── ComponentController.php  Master Komponen + penugasan ke Mesin/Utility
│           ├── ActivityLogController.php Activity Logs (cari/filter/paginasi + detail)
│           └── BackupController.php     Backup & Restore database (khusus Admin)
│
├── Actions/Pm/              use-case satu proses bisnis
│   ├── SubmitPmRecord.php    draft → submitted
│   ├── SignPmRecord.php      tahap aktif → tahap berikutnya
│   └── RejectPmRecord.php    submitted/pic_approved → rejected
│
├── Policies/                Gate per resource
│   ├── UserPolicy.php        create/update/updatePassword/delete + anti self-delete
│   ├── MachinePolicy.php     create/update/delete/restore
│   ├── PmRecordPolicy.php    create/update/sign/reject/delete
│   └── RolePolicy.php        Registered manual untuk model Spatie
│
├── Services/                 Logika yang dipakai lintas banyak halaman
│   ├── SignatureChain.php    ATURAN TUNGGAL siapa boleh tanda tangan/menolak
│   ├── SignatureStorage.php  Decode/validasi/simpan PNG tanda tangan
│   ├── AssetScope.php        Batas scope tipe aset (Mesin vs Utility) per role
│   ├── ComponentMigrationService.php  Migrasi item template lama → master Komponen
│   ├── PeriodService.php     Periode, tahun, dot notifikasi
│   ├── DashboardService.php  Agregasi status mesin + filter
│   ├── ReportService.php     Kepatuhan, top part, ringkasan aksi
│   ├── AuditLogService.php   Penulis audit log
│   ├── BackupService.php     Dump/restore database via PDO (tanpa biner mysqldump)
│   ├── MachineImportService.php  Impor CSV mesin idempoten
│   └── SparePartImportService.php Impor CSV suku cadang idempoten (toleran header dua-baris & koma)
│
├── Exports/                  Maatwebsite Excel mapping
├── Models/                   Eloquent + cast/relasi (Machine memakai tabel `assets`; + Component; + Backup)
└── Console/Commands/
    ├── ImportMachinesCommand.php
    ├── ImportSparePartsCommand.php
    └── BackupDatabaseCommand.php   backup:database (manual/scheduled + prune)
```

### Kenapa Enum dipakai di mana-mana

`PmStatus::awaiting()` dan `SignatureStage::permission()` adalah contoh. Kalau aturan "tahap berikutnya
apa" ditulis ulang di controller, policy, dan frontend, ketiganya pasti akan menyimpang.
Enum dipakai sebagai **sumber kebenaran tunggal** yang dibaca PHP dan diserialisasi ke props Inertia,
sehingga frontend dapat menampilkan label yang konsisten dengan aturan server.

---

## 5. Model Data

### ERD

```
                        ┌──────────────┐
                        │    roles     │◀──┐
                        └──────┬───────┘   │
                               │        ┌──┴────────────────┐
                        model_has_roles│ (pivot Spatie)     │ permissions
                               │        └──┬────────────────┘
                               │           │
                        ┌──────┴───────────────────────────┐
                        │              users               │
                        │  ─────────────────────────────  │
                        │  name, email UNIQUE              │
                        │  password (cast: hashed)         │
                        │  signature_path (PNG tersimpan)  │
                        │  email_verified_at                │
                        └──────┬─────────────────────┬─────┘
                               │                     │
              FK technician_id │ (nullOnDelete)      │ FK user_id
                    ┌──────────┴───────────┐         │ (nullOnDelete)
                    │      pm_records       │   ┌─────┴──────┐
                    │  ───────────────────  │   │ audit_logs │
                    │  machine_id (cascade) │   │  action    │
                    │  year, period         │   │  subject_* │
                    │  status               │   │  changes   │
                    │  technician_id        │   │  ip        │
                    │  technician_name ★    │   │  created_at│
                    │  pic_user_id (null)   │   └────────────┘
                    │  approved_by (null)   │
                    │  general_note         │
                    │  revision_count       │
                    │  submitted_at/approved_at
                    │  UNIQUE(machine_id, year, period)
                    └───┬──────────────┬────┘
                        │              │
        FK pm_record_id │              │ FK pm_record_id (cascade)
          ┌─────────────┴───┐      ┌───┴──────────────────┐
          │ pm_record_items │      │   pm_signatures     │
          │  item_name      │      │  stage              │
          │  actual         │      │  signed_by (null)   │
          │  act_clean      │      │  signed_by_name ★   │
          │  act_repair     │      │  signed_by_role  ★  │
          │  act_lubricate  │      │  image_path         │
          │  act_replace    │      │  UNIQUE(record,stage)
          │  parts_replaced │      └──────────────────────┘
          │  replace_note  │
          └─────────────────┘
                    ┌──────────────────┐
                    │pm_record_revisions│
                    │  revised_by      │
                    │  snapshot (JSON) │
                    │  reason          │
                    └──────────────────┘

┌──────────────────────┐         ┌──────────────────────────────┐
│      assets          │         │   components (master)       │
│  (Mesin + Utility)   │         │  name, category, spec       │
│  code UNIQUE         │         │  sort_no                    │
│  name, location      │         └───────────┬──────────────────┘
│  category            │                     │
│  sub_category        │         ┌───────────┴──────────────────┐
│  type                │         │  component_machine (pivot)  │
│  week_group          │         │  component_id, machine_id   │
│  template_id (null)⁑ │◀────────│  sort_no, UNIQUE(comp,unit) │
│  is_active           │         └──────────────────────────────┘
│  deleted_at (soft)   │
└──────────────────────┘

⁑ `template_id` + `checklist_template_items` dipertahankan sebagai cadangan
  untuk data lama; sumber item PM utama kini master `components` lewat pivot
  (lihat `Machine::effectiveComponents()`). Tabel `assets` menampung Mesin
  DAN Utility — model `Machine` mengarah ke tabel ini.

┌──────────────────────────────┐        ┌───────────────────────────────┐
│         spare_parts          │        │     spare_part_movements      │
│  code UNIQUE                 │◀───────│  spare_part_id (cascade)      │
│  category, name, brand, type │        │  user_id (nullOnDelete)       │
│  description, unit           │        │  type (in/out)                │
│  qty, stock, stock_start     │        │  qty                          │
│  stock_in/out(_log)          │        │  moved_at                     │
│  min_stock, supplier         │        │  note                         │
│  needed_for, note            │        │  machine_id (unit tujuan)*    │
│  location_code, location     │        │  year, period (periode PM)*   │
│  sort_no, deleted_at (soft)  │  ← global, TIDAK terikat ke assets        │
└──────────────────────────────┘        └───────────────────────────────┘
```
*Diisi bila transaksi adalah **pemakaian ke unit** (lihat `SparePartUsageController`); transaksi stok biasa membiarkannya `null`.

★ = snapshot, sengaja diduplikasi agar jejaknya tidak hilang saat akun dihapus.

### Catatan `onDelete` yang dipilih

| Relasi | Perilaku | Alasan |
|--------|----------|--------|
| `pm_records.machine_id` | `cascade` | Record PM tidak bermakna tanpa mesinnya |
| `pm_records.technician_id` | **`nullOnDelete`** | History tetap harus terbaca meski teknisi akunnya dihapus (nama dokumen ada di `technician_name`) |
| `pm_records.approved_by` | **`nullOnDelete`** | Sama seperti `technician_id` — jejak persetujuan tidak boleh ikut hilang |
| `pm_record_items/revisions/signatures.pm_record_id` | `cascade` | Data turunan dari record |
| `machines.template_id` | `nullOnDelete` | Hapus template tidak boleh menghapus mesin (kolom warisan; item PM kini dari `components`) |
| `component_machine.component_id/machine_id` | `cascade` | Penugasan komponen tidak bermakna bila komponen atau unitnya hilang |
| `audit_logs.user_id` | **`nullOnDelete`** | Log harus bertahan setelah pelaku keluar |
| `backups.created_by` | **`nullOnDelete`** | Riwayat backup tetap ada meski akun pembuatnya dihapus |
| `spare_part_movements.spare_part_id` | `cascade` | Riwayat transaksi tidak bermakna tanpa suku cadangnya |
| `spare_part_movements.user_id` | **`nullOnDelete`** | Riwayat stok tetap terbaca meski akun pelakunya dihapus |

### Index

- `assets`: `(is_active, week_group)`, `(is_active, sub_category)`, unique `code`
- `components`: `(category, name)`
- `component_machine`: unique `(component_id, machine_id)`, `(machine_id, sort_no)`; kolom `standard` (nullable) = standar per unit
- `pm_records`: `(year, period, status)`, `(machine_id, year)`, unique `(machine_id, year, period)`
- `pm_record_items`: `pm_record_id`
- `pm_signatures`: `signed_by`, unique `(pm_record_id, stage)`
- `audit_logs`: `(subject_type, subject_id)`
- `backups`: `created_at`
- `spare_parts`: unique `code`, `category`, `name`, `location_code`, `(category, name)`
- `spare_part_movements`: `(spare_part_id, moved_at)`, `(machine_id, year, period)`

### Komponen / Parts (pengganti Template)

Sejak refactor aset, item yang diperiksa saat PM tidak lagi ditentukan lewat
`checklist_templates` per jenis mesin, melainkan lewat **master Komponen**:

- `components` — daftar tunggal komponen (nama, kategori, spesifikasi, urutan).
- `component_machine` — pivot many-to-many; satu komponen bisa dipakai banyak
  Mesin/Utility, dan tiap unit punya urutan komponen sendiri (`sort_no`) serta
  **standar per unit** (`standard`, nullable).
- Halaman **`/admin/komponen`** (`Admin/Components.jsx`) mengelola master dan
  penugasannya; URL lama `/admin/templates` diarahkan ke sini.
- **Edit Komponen + Standar per unit** juga tersedia langsung dari halaman
  **`/machines`** dan **`/utility`**: tombol `Komponen → Edit Komponen` membuka
  `ComponentAssignModal.jsx` (multi-select + input standar) yang menyimpan lewat
  `PUT /machines/{machine}/components` (`machines.components.update`) atau
  `PUT /utility/{machine}/components` (`utility.components.update`), ditangani
  `MachineController@updateComponents`.
- `standard` kosong disimpan `NULL` supaya form PM/daftar jatuh ke
  `components.spec`; karena itu UI memakai `standard ?? spec` (isi sebagai nilai,
  spec master sebagai placeholder/cadangan).
- Saat halaman PM dimuat, `Machine::effectiveComponents()` memilih: snapshot
  record lama → komponen pivot unit → item template lama (cadangan). Ini menjaga
  form unit yang belum termigrasi tidak kosong. Untuk sumber pivot, `spec` pada
  item PM diisi `pivot.standard ?: component.spec`.
- `ComponentMigrationService` memindahkan data template lama ke `components` dan
  mengikatnya ke unit saat migrasi dijalankan.

---

## 6. Mesin Status PM

`App\Enums\PmStatus` adalah mesin status PM. Setiap method punya tugas spesifik:

```
  draft ──────────────sign(Teknisi)──────▶ submitted ─────sign(PIC)─────▶ pic_approved ────sign(Supervisor)───▶ approved
   ▲                                       │                                │                                  ▲
   │                                       │                                │                                  │
   │                                       └──────── reject(PIC) ───────────┴──────────────────────────────────┘
   │                                                          │
   └──────────────────── reject(Supervisor) ──────────────────┘
                                        │
                          rejected ─────┘  (revision_count++)
                          lalu teknisi memperbaiki & menandatangani ulang
```

| Method | Kegunaan | Dipakai di |
|--------|----------|-----------|
| `awaiting()` | Tahap yang harus ditandatangani berikutnya | `SignatureChain`, `PmRecordController`, badge antrean |
| `stageAfterApproval()` | Tahap terakhir yang dilewati (untuk centang stepper) | `Approvals/Index.jsx` |
| `isEditable()` | `true` hanya untuk `draft`/`rejected` | `PmRecordPolicy::update()` |
| `isFinal()` | `true` hanya untuk `approved` | UI |
| `isInApproval()` | `submitted`/`pic_approved` | Filter dashboard, laporan |
| `label()` / `shortLabel()` | Teks panjang & teks pendek | Tooltip, pill, ekspor |

**Keputusan kunci:** status menentukan tahap, bukan parameter request. Route cukup satu endpoint
`POST /pm/{record}/sign`; tahap aktif dibaca dari record. Kalau tahap dikirim dari client,
client bisa memilih tahap sendiri; percobaan melewati tahap harus ditolak oleh server.

---

## 7. Alur Rantai Tanda Tangan

### Alur signing

```
Teknisi isi checklist + nama teknisi + tanda tangan
        │
        ▼
POST /machines/{machine}/pm   (technician_name + signature WAJIB)
        │
        ├─▶ SubmitPmRecord  → simpan record + items (status=draft)
        │
        ▼
                         SignPmRecord (stage=Technician)
                                    │  guard: canSign()
                                    ▼
                            status = submitted
                                    │
                                    ▼
                         SignPmRecord (stage=PIC)      ← PIC juga wajib isi nama
                                    │  guard: canSign() + anti self-approval
                                    ▼
                            status = pic_approved
                                    │
                                    ▼
                         SignPmRecord (stage=Supervisor)  ← Atasan juga wajib isi nama
                                    │  guard: canSign() + anti self-approval
                                    ▼
                            status = approved  (final)
```

`POST /pm/{record}/reject` (PIC/Supervisor) → `status = rejected`, `revision_count++`, simpan snapshot.

### Nama penanda tangan

Nama di dokumen **diketik manual**, bukan diambil dari akun yang login, karena di
lapangan orang yang mengisi checklist tidak selalu orang yang masuk ke aplikasinya.
Tiap tahap punya sumber kolom yang berbeda:

| Authentikasi | Frontend | Disimpan di |
|--------------|----------|-------------|
| Nama Teknisi | `Pm/Form` | `pm_records.technician_name` **dan** `pm_signatures.signed_by_name` (stage `technician`) |
| Nama Penanda Tangan | `SignatureActions` (form PM & kartu Persetujuan) | `pm_signatures.signed_by_name` (stage `pic` / `supervisor`) |

`technician_name` sengaja tidak ditimpa tahap 2/3 — yang menandatangani tahap 2
adalah User PIC, bukan teknisi. Pembacaan nama teknisi untuk daftar persetujuan,
riwayat, dan ekspor lewat `PmRecord::technicianName()`: kolom manual didahulukan,
nama akun jadi cadangan untuk record lama.

`pm_records.technician_id` tetap diisi `Auth::id()` karena itu yang dipakai untuk
kepemilikan checklist dan anti konflik kepentingan — bukan untuk identitas di dokumen.

### `SignatureChain` sebagai sumber kebenaran

`app/Services/SignatureChain.php` dipanggil oleh `PmRecordPolicy::sign()`, `PmRecordPolicy::reject()`,
`ApprovalController`, dan badge sidebar. Tidak ada aturan "boleh tanda tangan" yang ditulis ulang di
tempat lain.

| Method | Aturan |
|--------|--------|
| `stagesFor($user)` | Semua tahap yang permission-nya dimiliki user |
| `pendingStatusesFor($user)` | Status PM yang menunggu salah satu tahap tersebut |
| `pendingQuery($user)` | Query record yang benar-benar menunggu user |
| `pendingCountFor($user)` | Jumlah untuk badge sidebar |
| `canSign($user, $record)` | Permission sesuai tahap **dan** bukan konflik kepentingan |
| `canReject($user, $record)` | Hanya tahap PIC/Supervisor, bukan stage Technician |

Dua keputusan yang perlu dilihat:

1. **`pendingQuery()` dipakai badge dan daftar** agar keduanya tidak berbeda. Kalau badge menampilkan 5
   tetapi daftar 3, user akan menekan menu lalu bingung tanpa penjelasan.
2. **Teknisi melihat record yang ia isi sendiri; admin melihat semua** (ekspresi di `pendingQuery()`
   memakai `hasRole(admin)`). Ini mengikuti aturan `canSign()` yang mengizinkan admin di semua tahap.

### Anti konflik kepentingan

- Tahap Technician: boleh oleh pembuat checklist (Justru titik tanda tangannya).
- Tahap PIC/Supervisor: `$record->technician_id !== $user->id` (dilarang sama).
- Admin: lolos semua tahap karena tidak mengisi checklist — sekaligus cadangan bila tidak ada pihak lain.

### Signature storage & snapshot

- Canvas → data URL PNG → `SignatureStorage::store()`.
- Validasi diulang di service (prefix `data:image/png;base64,`, PNG magic bytes, ≤ 2 MB) —
  bukan hanya regex di FormRequest — supaya file tidak pernah berisi payload bebas.
- Path: `signatures/pm-record-{id}/{stage}-{timestamp}-{random}.png` (disimpan di disk `public`).
- Disajikan lewat `PmSignatureController` yang ber-otorisasi, bukan path mentah
  → nama file tidak bisa ditebak dan URL tidak bergantung `APP_URL`.
- `signed_by_name` + `signed_by_role` disimpan sebagai **snapshot**.

---

## 8. Lapisan Otorisasi

Otorisasi ditegakkan di tiga lapis. UI hanya kosmetik.

### Lapis 1 — Route middleware

```php
Route::middleware(['auth', 'verified'])->group(function () {
    // …otorisasi per-resource:
    Route::get('/machines', …)->middleware('can_any:machine.manage,machine.view');
    Route::get('/utility', …)->middleware('can_any:utility.view,dashboard.view');
    Route::middleware('can:utility.manage')->group(function () { /* CRUD /utility */ });
    Route::get('/machines/{machine}/pm', …)->middleware('can_any:pm.fill,pm.acknowledge,pm.approve'); // form PM (approver read-only)
    Route::post('/machines/{machine}/pm', …)->middleware('can:pm.fill'); // simpan checklist
    Route::middleware('can:pm.manage')->group(function () { /* /admin/pm-records */ });
    Route::middleware('can:user.manage')->group(function () { /* /admin/users */ });
    Route::middleware('role:admin')->group(function () { /* /admin/roles */ });
    Route::middleware('can:template.manage')->group(function () { /* /admin/komponen */ });
    Route::middleware('can:activity.view')->group(function () { /* /admin/activity-logs */ });
    Route::middleware('can:backup.manage')->group(function () { /* /admin/backups */ });
});
```

Alias `role` didaftarkan manual di `bootstrap/app.php` (Spatie v6 tidak mendaftarkannya sendiri).
Alias tambahan **`can_any`** (`App\Http\Middleware\EnsureAnyPermission`) meloloskan request bila user
memegang **salah satu** permission — dipakai untuk guard scope aset (mesin vs utility) supaya role
teknisi terbatas (tech-machines/tech-utilities) tidak perlu membawa `dashboard.view`.

### Lapis 2 — Policy per resource

| Policy | Method |
|--------|--------|
| `UserPolicy` | `view/create/update/updatePassword` → `user.manage`; `delete` → `user.manage` **dan bukan diri sendiri** |
| `MachinePolicy` | `view` → `dashboard.view`; `create/update/delete/restore` → `machine.manage` (di controller izin kelola kini disesuaikan tipe aset lewat `authorizeType()`, lihat Lapis 3) |
| `PmRecordPolicy` | `create` → `pm.fill`; `update` → `pm.fill` + `isEditable()` + kepemilikan; `sign/reject` → `SignatureChain` |
| `RolePolicy` | Didaftarkan manual di `AppServiceProvider` (model Spatie, bukan `App\Models\Role`) |

### Lapis 3 — Guard di dalam controller

Beberapa aturan terlalu spesifik untuk ditulis sebagai Policy generik, sehingga ditegakkan langsung:

- `UserController::update()` — admin **tidak boleh mengubah role sendiri**.
  Alasannya: satu-satunya akun admin yang kehilangan `user.manage` akan terkunci dari halaman
  yang mengatur role, dan tidak ada halaman lain untuk memulihkannya.
- `UserController::destroy()` — cek "diri sendiri" **sebelum** `authorize()`, supaya pesan yang
  muncul jelas ("Tidak dapat menghapus akun sendiri") dan bukan 403 yang membingungkan.
- `MachineController::store/update/destroy/restore` — izin kelola **mengikuti tipe aset**
  (`authorizeType()`): tipe `utility` wajib `utility.manage`, selain itu `machine.manage`.
  Dengan begitu satu endpoint tidak bisa dipakai menembus scope (mis. membuat unit utility
  lewat endpoint mesin), meski tabel `assets` menampung keduanya.
- `PmRecordController::create/pmHistory` + `MachineController::history` — selain izin route
  (`can_any:pm.fill,pm.acknowledge,pm.approve` untuk form PM; `pm.history.view` untuk riwayat), scope tipe
  unit ditegakkan lewat `App\Services\AssetScope` supaya teknisi mesin tidak membuka form/riwayat unit
  utility lewat URL langsung. Approver membuka form yang sama dengan `canFill=false` (read-only); panel
  tanda tangan di form itu yang menyetujui/menolak, dan penyimpanan tetap butuh `pm.fill`.

### Permission model

Permission berasal dari `RoleSeeder`:

| Permission | Pemakai utama |
|------------|---------------|
| `dashboard.view` | role general (admin, manager, technician, user, viewer) — baseline akses aplikasi |
| `pm.fill` | technician (termasuk tech-machines/tech-utilities/tech-all) |
| `pm.sign` | technician (termasuk tech-*) |
| `pm.acknowledge` | user (PIC) |
| `pm.approve` | manager (supervisor) |
| `pm.history.view` | semua role |
| `report.view` | admin, manager, technician, user, tech-* |
| `report.export` | admin, manager, technician, viewer, tech-* |
| `machine.manage` | admin, manager, tech-all |
| `machine.view` | tech-machines, tech-all (scope halaman/daftar Mesin) |
| `utility.manage` | admin, tech-all (CRUD Utility) |
| `utility.view` | tech-utilities, tech-all (scope halaman/daftar Utility) |
| `template.manage` | admin, manager, tech-all |
| `sparepart.view` | **semua role** — suku cadang global, di luar scope Mesin/Utility |
| `sparepart.manage` | admin, tech-all (CRUD + transaksi stok + impor) |
| `pm.manage` | admin (halaman Admin PM Records — lihat semua record + hapus permanen) |
| `user.manage` | admin |
| `activity.view` | admin, manager (halaman Activity Logs) |
| `backup.manage` | admin (Backup & Restore database) |

Tiga permission tanda tangan **sengaja dipisah** (`pm.sign` / `pm.acknowledge` / `pm.approve`)
supaya role tidak bisa melompat tahap dengan satu permission generik.

**Scope aset.** `machine.view` / `utility.view` menandai scope halaman aset. Role general tetap
membuka keduanya lewat `dashboard.view`. Role teknisi **tech-machines** / **tech-utilities**
sengaja **tanpa** `dashboard.view`: hanya satu scope terbuka, `/form-maintenance` menampilkan kartu
di luar scope sebagai abu-abu, dan URL langsungnya diblokir. **tech-all** memegang `machine.manage`
+ `utility.manage` + `template.manage` (form-maintenance lengkap).

### Shared props

`HandleInertiaRequests` mengirim `auth.user` (name, email, roles) dan **`auth.can`** (peta
permission boolean) ke seluruh halaman. Sidebar menggunakan `auth.can` untuk menampilkan menu,
sehingga menu dan server memakai sumber kebenaran yang sama.

---

## 9. Alur Request Utama

### Mengisi checklist PM

```
GET  /machines/{machine}/pm?period=&year=
   ├─ middleware: auth, verified, can_any:pm.fill,pm.acknowledge,pm.approve
   ├─ resolve period & year (query, default = periode berjalan)
   ├─ cari record existing (machine, year, period) → item dari record (snapshot)
   ├─ kalau tidak ada → item dari master Komponen unit (pivot)
   │    ├─ spec item = pivot.standard ?: components.spec (standar per unit)
   │    └─ item template lama dipakai hanya bila unit belum punya komponen
   ├─ canFill = user punya pm.fill (approver → read-only, panel tanda tangan tetap tampil)
   └─ teruskan filter Beranda yang sedang aktif ke "Kembali"/redirect

POST /machines/{machine}/pm
   ├─ FormRequest: validasi item[], actual, aksi, parts_replaced
   │    └─ replace_note WAJIB bila act_replace dicentang (detail bagian diganti)
   ├─ FormRequest: technician_name + pic_user_id WAJIB + signature WAJIB (data URL PNG)
   ├─ authorize: pm.fill DAN pm.sign
   │    └─ record existing harus lolos Policy::update (belum terkunci)
   ├─ tolak bila periode future/terkunci
   ├─ Fail: 422 / redirect dengan flash error
   ├─ SubmitPmRecord → simpan record + items (status=draft)
   └─ SignPmRecord → tanda tangan tahap Technician (status=submitted)
```

### Menandatangani

```
POST /pm/{record}/sign
   ├─ FormRequest: signature (data URL PNG) + signer_name WAJIB, note opsional
   ├─ Policy::sign → SignatureChain::canSign()
   │    ├─ tahap aktif = $record->status->awaiting()
   │    ├─ user harus punya permission tahap
   │    └─ anti konflik kepentingan
   ├─ SignatureStorage::store() → validasi PNG + simpan
   ├─ pm_signatures::updateOrCreate(record, stage, …)  (snapshot nama/role)
   ├─ advance status; bump revision_count bila dari rejected
   └─ flash sukses
```

### Endpoint list per route

| Route | Method | Controller | Middleware tambahan |
|-------|--------|-----------|---------------------|
| `/dashboard` | GET | `DashboardController@index` | — |
| `/dashboard/export` | GET | `DashboardController@export` | `can:report.export` |
| `/dashboard/report/export` | GET | `ReportController@export` (XLSX/PDF laporan PM) | `can:report.export` |
| `/profile` | GET/PATCH/DELETE | `ProfileController` | — |
| `/profile/signature` | GET/POST/DELETE | `ProfileController` (tanda tangan tersimpan) | — |
| `/form-maintenance` | GET | `DashboardController@formMaintenance` | — |
| `/approvals` | GET | `ApprovalController@index` | — |
| `/machines/{machine}/pm` | GET | `PmRecordController@create` | `can_any:pm.fill,pm.acknowledge,pm.approve` + `AssetScope` (tipe unit) — approver membuka read-only |
| `/machines/{machine}/pm-history` | GET | `PmRecordController@pmHistory` | `can:pm.history.view` + `AssetScope` (tipe unit) |
| `/machines/{machine}/pm` | POST | `PmRecordController@store` | `can:pm.fill` + `pm.sign` (dalam FormRequest) |
| `/pm/{record}/sign` | POST | `PmRecordController@sign` | Policy::sign |
| `/pm/{record}/reject` | POST | `PmRecordController@reject` | Policy::reject |
| `/pm/{record}/signature/{stage}` | GET | `PmSignatureController@show` | Policy |
| `/machines` | GET | `MachineController@index` | `can_any:machine.manage,machine.view` |
| `/machines` | POST | `MachineController@store` | `can:machine.manage` + `authorizeType()` |
| `/machines/{machine}` | PUT | `MachineController@update` | `can:machine.manage` + `authorizeType()` |
| `/machines/{machine}` | DELETE | `MachineController@destroy` | `can:machine.manage` + `authorizeType()` |
| `/machines/{machine}/restore` | PATCH | `MachineController@restore` | `can:machine.manage` + `authorizeType()` |
| `/machines/{machine}/components` | PUT | `MachineController@updateComponents` | `can:machine.manage` + `authorizeType()` |
| `/machines/{machine}/history` | GET | `MachineController@history` | `can:pm.history.view` + `AssetScope` |
| `/utility` | GET | `UtilityController@index` | `can_any:utility.view,dashboard.view` |
| `/utility` | POST | `MachineController@store` | `can:utility.manage` + `authorizeType()` |
| `/utility/{machine}` | PUT | `MachineController@update` | `can:utility.manage` + `authorizeType()` |
| `/utility/{machine}` | DELETE | `MachineController@destroy` | `can:utility.manage` + `authorizeType()` |
| `/utility/{machine}/restore` | PATCH | `MachineController@restore` | `can:utility.manage` + `authorizeType()` |
| `/utility/{machine}/components` | PUT | `MachineController@updateComponents` | `can:utility.manage` + `authorizeType()` |
| `/form-maintenance/mesin` | GET | `DashboardController@formMaintenanceMesin` | `can_any:machine.manage,machine.view,dashboard.view` |
| `/form-maintenance/utility` | GET | `DashboardController@formMaintenanceUtility` | `can_any:utility.view,dashboard.view` |
| `/form-maintenance/suku-cadang` | GET | `SparePartUsageController@index` | `can:sparepart.view` |
| `/form-maintenance/suku-cadang` | POST | `SparePartUsageController@store` | `can:sparepart.manage` |
| `/admin/users` | GET | `UserController@index` | `can:user.manage` |
| `/admin/users` | POST | `UserController@store` | `can:user.manage` + Policy |
| `/admin/users/{user}` | PUT | `UserController@update` | `can:user.manage` + Policy |
| `/admin/users/{user}/password` | PUT | `UserController@updatePassword` | `can:user.manage` + Policy |
| `/admin/users/{user}` | DELETE | `UserController@destroy` | `can:user.manage` + Policy |
| `/admin/roles` | resource | `RoleController` | `role:admin` + `authorizeResource` |
| `/admin/komponen` | GET/POST/PUT/DELETE | `Admin\ComponentController` | `can:template.manage` |
| `/admin/komponen/penugasan` | POST | `Admin\ComponentController@assign` | `can:template.manage` |
| `/admin/templates` | GET | redirect → `/admin/komponen` | `can:template.manage` |
| `/admin/activity-logs` | GET | `Admin\ActivityLogController@index` | `can:activity.view` |
| `/admin/backups` | GET | `Admin\BackupController@index` | `can:backup.manage` |
| `/admin/backups` | POST | `Admin\BackupController@store` | `can:backup.manage` |
| `/admin/backups/{backup}/download` | GET | `Admin\BackupController@download` | `can:backup.manage` |
| `/admin/backups/{backup}/restore` | POST | `Admin\BackupController@restore` | `can:backup.manage` |
| `/admin/backups/restore-upload` | POST | `Admin\BackupController@uploadRestore` | `can:backup.manage` |
| `/admin/backups/{backup}` | DELETE | `Admin\BackupController@destroy` | `can:backup.manage` |
| `/admin/pm-records` | GET | `PmRecordController@adminIndex` | `can:pm.manage` |
| `/admin/pm-records/{record}` | DELETE | `PmRecordController@adminDestroy` | `can:pm.manage` |
| `/suku-cadang` | GET | `SparePartController@index` | `can:sparepart.view` |
| `/suku-cadang` | POST | `SparePartController@store` | `can:sparepart.manage` |
| `/suku-cadang/import` | POST | `SparePartController@import` | `can:sparepart.manage` (unggah CSV) |
| `/suku-cadang/template` | GET | `SparePartController@template` | `can:sparepart.manage` (unduh template CSV) |
| `/suku-cadang/bulk` | DELETE | `SparePartController@bulkDestroy` | `can:sparepart.manage` (hapus massal) |
| `/suku-cadang/{sparePart}` | PUT | `SparePartController@update` | `can:sparepart.manage` |
| `/suku-cadang/{sparePart}` | DELETE | `SparePartController@destroy` | `can:sparepart.manage` (soft delete) |
| `/suku-cadang/{sparePart}/restore` | PATCH | `SparePartController@restore` | `can:sparepart.manage` |
| `/suku-cadang/{sparePart}/movements` | GET | `SparePartController@movements` | `can:sparepart.view` (JSON riwayat) |
| `/suku-cadang/{sparePart}/movements` | POST | `SparePartController@storeMovement` | `can:sparepart.manage` |

---

## 10. Frontend

### Konvensi Inertia

- Halaman di `resources/js/Pages/**`, dikembalikan via `Inertia::render()`.
- Aksi memakai `router.post/put/delete` dengan `preserveScroll` → **tanpa reload penuh**.
- Form memakai `useForm`; error validasi dari server ditampilkan per-field.
- Flash sukses/gagal dibaca dari shared props `flash`.

### Design system

Komponen `Neu*` menerapkan gaya **Neumorphism** (permukaan dua arah) agar konsisten:

| Komponen | Fungsi |
|----------|--------|
| `NeuCard` | Wadah konten |
| `NeuButton` | Tombol dengan variant (primary, secondary, danger, ghost, dll.) |
| `NeuInput` | Input berlabel + pesan error |
| `NeuPill` | Badge status/role dengan variant warna |
| `NeuRing` | Progress ring untuk persentase kepatuhan |
| `NeuTrack` | Progress bar |
| `NeuBars` | Bar chart sederhana |
| `NeuToast` | Notifikasi aksi |
| `NeuSignaturePad` | Canvas tanda tangan |
| `SignatureActions` | Panel tanda tangan sebagai kartu utuh, dipakai di `Pm/Form` untuk tahap PIC/Atasan |
| `useSignatureActions` | Logika approve/tolak (nama manual, canvas, catatan, request). Dipakai bersama oleh `SignatureActions` dan `Approvals/ApprovalRow` supaya tidak ada jalur validating mandatory yang berbeda |
| `Approvals/ApprovalRow` | Satu baris tabel antrean: kolom Nama Penanda Tangan dan Tanda Tangan terpisah |
| `MachineFields` | Form input mesin (dipakai bersama create/edit) |
| `ComponentAssignModal` | Modal "Edit Komponen" di halaman Mesin/Utility: multi-select komponen + input Standar per unit |

### Layout

`AuthenticatedLayout.jsx` menyusun sidebar + header:

- Item menu didefinisikan sekali dengan flag `show` berdasarkan `auth.can`.
- Item **Settings** (`/settings`) menggantikan item terpisah Form Maintenance/Mesin/Utility/Suku Cadang/Komponen; menunya tetap ditandai aktif saat berada di salah satu halaman modul (`/machines`, `/utility`, `/admin/komponen`, `/suku-cadang`).
- Item **Mesin**/**Utility**/**Suku Cadang** mengarah langsung ke halaman pengisian form (`/form-maintenance/mesin`, `/form-maintenance/utility`, `/form-maintenance/suku-cadang`); saat mengisi PM (`/machines/{id}/pm`) menu Mesin/Utility ditandai sesuai tipe unitnya.
- Badge antrean "Persetujuan" memakai `SignatureChain::pendingCountFor()` yang dikirim backend,
  sehingga angka pada badge selalu sama dengan daftar. Karena badge memakai query itu
  juga, daftar memuat record `draft`/`rejected` milik teknisi — jadi panel tanda tangan
  di halaman ini dipasang untuk tahap apa pun yang sedang menunggu, bukan hanya PIC/Atasan.
- Sidebar responsif: rail vertikal (desktop) dan drawer (mobile).

### Halaman

| Path | Halaman | Ringkas |
|------|---------|---------|
| `/dashboard` | `Dashboard/Index` | Grid status mesin, filter, dot periode, ringkasan tindakan + komponen sering diganti, ekspor |
| `/settings` | `Settings/Index` | Pusat kartu menu pengelolaan aset: Mesin → `/machines`, Utility → `/utility`; Komponen / Parts & Suku Cadang "Segera hadir"; menggantikan item sidebar terpisah |
| `/approvals` | `Approvals/Index` | Antrean per permission, stepper, tabel nama + signature per baris |
| `/machines` | `Machines/Index` | CRUD mesin, pencarian, restore, Edit Komponen + Standar per unit |
| `/machines/{machine}/history` | `Machines/History` | Riwayat PM mesin |
| `/machines/{machine}/pm` | `Pm/Form` | Checklist + tanda tangan |
| `/form-maintenance` | `FormMaintenance/Index` | Kartu pilih scope Mesin/Utility |
| `/form-maintenance/mesin` | `FormMaintenance/Mesin` | Daftar mesin + status PM per periode |
| `/form-maintenance/utility` | `FormMaintenance/Utility` | Daftar unit utility + status PM per periode |
| `/form-maintenance/suku-cadang` | `FormMaintenance/SukuCadang` | Catat pemakaian suku cadang ke unit (Mesin/Utility) per periode PM + riwayat; pemakaian = transaksi stok keluar bertujuan unit |
| `/utility` | `Utility/Index` | Daftar unit utility + komponen maintenance, Edit Komponen + Standar per unit |
| `/admin/users` | `Admin/Users` | CRUD pengguna + ganti password + pencarian |
| `/admin/roles` | `Admin/Roles` | CRUD role + matriks permission |
| `/admin/komponen` | `Admin/Components` | Master Komponen + penugasan ke Mesin/Utility |
| `/admin/pm-records` | `Admin/PmRecords/Index` | Semua PM Record: filter, cari, hapus permanen |
| `/admin/activity-logs` | `Admin/ActivityLogs` | Activity Logs: cari, filter aksi/pelaku/tanggal, statistik, detail perubahan |
| `/admin/backups` | `Admin/Backups` | Backup & Restore database: buat, unduh, pulihkan, unggah, hapus |
| `/suku-cadang` | `SpareParts/Index` | Master suku cadang global, transaksi stok masuk/keluar, riwayat, impor/unggah CSV, unduh template CSV, hapus massal |
| `/profile` | `Profile/Edit` | Info akun, ganti password, tanda tangan tersimpan, hapus akun |

---

## 11. Ekspor

| Ekspor | Format | Closure | Sumber |
|--------|--------|---------|--------|
| Status mesin (Beranda) | XLSX | `Exports/MachineStatusExport` | `DashboardService::machineStatuses` + filter aktif |
| Daftar mesin (Beranda) | PDF | DomPDF | Blade view, data terfilter |
| Laporan PM | XLSX | `Exports/PmReportExport` | `ReportService` |
| Laporan PM | PDF | `views/reports/pdf.blade.php` | `ReportService` |

Ekspor Beranda **mengikuti filter yang sedang aktif** (periode, area, status, pencarian), supaya hasil
cetak identik dengan tabel di layar dan tidak menyesatkan.

---

## 12. Impor CSV

### Impor Mesin

```
php artisan machines:import [--purge] [--force]
```

Sumber data: `references/data-source/data.csv` di proyek (121 mesin, jalur `base_path('references/data-source/data.csv')`).

| Asal | Tujuan | Cara |
|------|--------|------|
| `NO` | `sort_no` | langsung |
| `Machine Name` | `name` + penentuan `type` | keyword berurutan (`TYPE_KEYWORDS`) |
| `Location` | `location` | langsung |
| `Category` | `category` | langsung |
| `Sub-Category` | `sub_category` + `week_group` | angka pada label (`"W.7"` → 7) |

Keputusan desain:

- **Idempoten** — aman dijalankan berulang; mesin yang sudah ada di-update, bukan diduplikasi.
- **Baris sentinel dilewati** — file asli memuat baris penanda (`MASTER`), bukan mesin sungguhan.
- **Urutan keyword penting** — `"Timbangan - Mixer 1"` harus menjadi `Timbangan`, bukan `Mixer`;
  `"TOM - Filling Otomatis"` harus `TOM`, bukan `Filling`. Kunci pertama yang cocok menang.
- **`--purge` bersifat destruktif & perlu konfirmasi** — mesin yang hilang dari CSV dihapus permanen
  beserta riwayat PM-nya. Karena itu ada `--force` untuk CI/non-interaktif.
- Dibungkus `DB::transaction()` agar impor gagal di tengah tidak meninggalkan data setengah jadi.

### Impor Suku Cadang

```
php artisan spare-parts:import [path?] [--purge]
```

Sumber data: `references/data-source/suku_cadang.csv` (jalur `base_path('references/data-source/suku_cadang.csv')`),
atau file apa pun lewat argumen `path`. Halaman `/suku-cadang` juga bisa **mengunggah CSV**
lewat `POST /suku-cadang/import`; keduanya memakai `SparePartImportService` yang sama.
Tombol **Unduh Template** di halaman itu memanggil `GET /suku-cadang/template` yang
mengembalikan CSV berisi baris header (tanpa data) dengan urutan 21 kolom di bawah, siap
diisi lalu diunggah kembali.

Pemetaan 21 kolom nyata CSV → tabel `spare_parts`:

| Posisi CSV | Kolom tabel |
|-----------|-------------|
| `Code.asset` | `code` (unik) |
| `CATEGORY DEVICE` | `category` |
| `Suku Cadang` | `name` |
| `MEREK` / `TYPE` / `Desc` | `brand` / `type` / `description` |
| `Last - Modified` / `Ket. Last - Modified` | `last_modified` / `last_modified_note` |
| `UNIT` | `unit` |
| `QTY` | `qty` |
| `STOK AKHIR` | `stock` |
| `BARANG KELUAR` / `Log.Barang Keluar` / `BARANG MASUK` | `stock_out` / `stock_out_log` / `stock_in` |
| `STOK AWAL` / `MIN` | `stock_start` / `min_stock` |
| `SUPLIER` / `Kebutuhan Untuk` / `Penjelasan` | `supplier` / `needed_for` / `note` |
| `Code.loc` / `LOC` | `location_code` / `location` |

Keputusan desain (parser sengaja dibuat toleran karena CSV berasal dari spreadsheet):

- **BOM UTF-8 dibuang lebih dulu.** BOM tepat di depan tanda kutip pembuka membuat `fgetcsv`
  tidak mengenali kutip, sehingga header dua-baris terbelah jadi dua record dan baris keduanya
  (`STOK AKHIR…`) ikut terbaca sebagai data. Setelah BOM dibuang, header terbaca sebagai satu field
  dan dikenali lewat kata kunci `Code.asset` / `stok akhir`.
- **Header memuat 22 nama tetapi data hanya 21 kolom** — ada kolom `Log.Barang Masuk` tanpa data.
  Pemetaan mengikuti urutan kolom nyata (21), yang tanpa kolom tersebut.
- **Koma tanpa kutip di nama barang** (mis. `"...380VAC/220VDC, 10A"`) menggeser kolom jadi 22.
  Baris 22 kolom dideteksi dan dua kolom nama digabung kembali dengan `, `.
- **Idempoten** — dicocokkan berdasarkan `code`; baris yang sudah ada di-update, bukan diduplikasi.
- **`--purge`** menghapus permanen suku cadang yang tidak ada di CSV (riwayat transaksinya ikut
  terhapus via cascade).
- Dibungkus `DB::transaction()`.

---

## 13. Activity Logs

Backing store tetap bernama `audit_logs` (model `AuditLog`, service `AuditLogService`), tetapi
permukaan fitur di UI kini bernama **Activity Logs** dengan permission `activity.view`.

`AuditLogService::record($action, $subjectType, $subjectId, $changes)` menulis:

| Kolom | Isi |
|-------|-----|
| `user_id` | Pelaku (`Auth::id()`), `nullOnDelete` |
| `action` | Contoh: `machine.create`, `user.update`, `user.password` |
| `subject_type` / `subject_id` | Objek yang diubah |
| `changes` | JSON nilai sebelum → sesudah |
| `ip` | `Request::ip()` |
| `created_at` | Waktu (tabel ini tidak punya `updated_at` — log bersifat append-only) |

Action yang tercatat:

| Prefix | Action | Sumber |
|--------|--------|--------|
| `machine.*` | `create`, `update`, `delete`, `restore` | `MachineController` |
| `pm.*` | `submit` | `Actions/Pm/SubmitPmRecord` |
| | `sign.{stage}` (mis. `pm.sign.pic`) | `Actions/Pm/SignPmRecord` |
| | `reject` | `Actions/Pm/RejectPmRecord` |
| `user.*` | `create`, `update`, `password`, `delete` | `Admin/UserController` |
| `sparepart.*` | `create`, `update`, `delete` (tunggal & massal, ditandai `bulk`), `restore`, `movement` (termasuk pemakaian ke unit), `import` | `SparePartController`, `SparePartUsageController` |
| `backup.*` | `create`, `restore`, `delete` | `Admin/BackupController` |

> **Cakupan saat ini:** perubahan pada **template checklist** dan **role/permission** belum
> tercatat di audit log. Keduanya baru bisa ditambah dengan satu baris
> `AuditLogService::record(...)` di controller masing-masing.

**Aturan privasi:** aksi `user.password` **tidak pernah** menulis nilai password, hanya nama & email
sasaran supaya jejaknya tetap bisa ditelusuri. Password lama pun tidak ikut.

Halaman Activity Logs (`Admin/ActivityLogs`) menampilkan log berurutan `created_at` descending,
**dipaginasi 25 per halaman**, dengan **pencarian** (aksi/pelaku/subjek/IP) dan **filter** aksi,
pelaku, serta rentang tanggal (`from`/`to`). Setiap baris bisa dibuka untuk melihat **detail
perubahan** (JSON `changes`), disertai kartu statistik (total, hari ini, pelaku aktif).

---

## 14. Backup & Restore

Fitur cadangkan/pulihkan **database** (khusus Admin, permission `backup.manage`).

### Mengapa murni PDO, bukan `mysqldump`

Implementasi awal memanggil biner `mysqldump`/`mysql`. Itu gagal di deployment Docker:
image `php:8.3-fpm-alpine` memakai klien MariaDB, sementara server dananya **MySQL 8** dengan
autentikasi `caching_sha2_password` — plugin-nya tidak ada di klien Alpine
(`Plugin caching_sha2_password could not be loaded`). `BackupService` karena itu melakukan
dump dan restore **lewat koneksi PDO aplikasi** (`DB::connection()`), yang sudah menangani
autentikasi, charset, dan opsi koneksi dengan benar. Tidak ada ketergantungan biner eksternal.

### Alur

| Langkah | Detail |
|---------|--------|
| Buat | Enumerasi tabel (`SHOW FULL TABLES`, hanya `BASE TABLE`), lalu per tabel tulis `DROP TABLE IF EXISTS`, `SHOW CREATE TABLE`, dan `INSERT` per baris |
| Escaping | Nilai di-quote dengan `PDO::quote` (setara `mysql_real_escape_string`) sehingga newline/quote/backslash aman dan tiap pernyataan tetap satu baris |
| Simpan | File `.sql` di disk privat **`backups`** (`storage/app/backups`), tidak diekspos web |
| Prune | `backup.keep` (default 30) backup terbaru dipertahankan; sisanya beserta filenya dihapus |
| Pulihkan | File dibaca, dipecah menjadi pernyataan oleh `splitStatements()` (menghormati kutip, backtick, komentar baris/blok), lalu dieksekusi satu per satu via `PDO::exec` setelah `FOREIGN_KEY_CHECKS=0` |

### Pengaman restore

- **Backup tersimpan**: wajib mengetik ulang **nama berkas** yang persis sebelum tombol aktif.
- **Unggahan `.sql`**: wajib mencentang pernyataan bahwa data akan ditimpa.
- Setiap aksi (`backup.create`, `backup.restore`, `backup.delete`) dicatat di Activity Logs.
- `restorePath()` juga dipakai bersama oleh restore tersimpan dan restore unggahan, jadi hanya ada
  satu jalur eksekusi.

### Konfigurasi & penjadwalan

`config/backup.php`: `disk` (default `backups`), `keep` (default 30), `schedule` (default `02:00`).
`routes/console.php` menjadwalkan `backup:database --type=scheduled --prune` setiap hari pada
`config('backup.schedule')`. Karena container **tidak** menjalankan cron, scheduler harus
diaktifkan dari host (lihat [Deployment](#16-deployment) dan README).

---

## 15. Keamanan

| Aspek | Implementasi |
|-------|--------------|
| Hashing password | Cast `hashed` pada model `User` — plaintext dari form otomatis di-hash, tidak ada kode `Hash::make` yang bisa terlewat |
| CSRF | Middleware `web` bawaan; token disuntik lewat `@routes`/Inertia |
| Session | `SESSION_ENCRYPT` & `SESSION_DRIVER` dari `.env` |
| Otorisasi | `can:` / `role:` middleware **dan** Policy pada setiap aksi |
| Anti self-lock | Guard di `UserController` (role sendiri, hapus diri sendiri) |
| Anti self-approval | `SignatureChain` untuk tahap 2 & 3 |
| Nama & tanda tangan wajib | `technician_name` + `signature` di `StorePmRecordRequest`, `signer_name` + `signature` di `SignPmRecordRequest` |
| Checklist lengkap sebelum tahap teknisi | `PmRecord::isChecklistComplete()`, dicek di `SignPmRecordRequest::withValidator()` dan di `SignPmRecord` |
| Tombol submit nonaktif | `disabled` bila nama kosong atau canvas kosong — dicek lagi di server, bukan hanya di UI |
| Kunci setelah tanda tangan | `PmRecordPolicy::update()` menolak record non-editable |
| Upload tanda tangan | Validasi prefix data URL + PNG magic bytes + batas 2 MB di `SignatureStorage` |
| Akses gambar tanda tangan | Disajikan lewat route ber-otorisasi, bukan path mentah |
| Jejak audit | Append-only, menyimpan pelaku + IP |
| Validasi input | Seluruh form memakai FormRequest, bukan validasi di frontend |
| Logout & session | `Auth::logout()` + invalidate session + regenerate token |

**Catatan produksi:** akun demo dari `UserSeeder` memakai password `password`. Wajib diganti sebelum
dipakai di lingkungan nyata.

---

## 16. Deployment

### Arsitektur container

```
docker-compose.yml
├── vip_app     php:8.3-fpm-alpine   (container_name: vip_app)
│   ├── build: .          ← Dockerfile multi-stage
│   ├── volumes: app_public:/var/www/html/public
│   │              ./storage:/var/www/html/storage
│   └── network: vipnet
└── vip_nginx   nginx:1.27-alpine    (container_name: vip_nginx)
    ├── ports: "8082:80"
    ├── volumes: app_public:/var/www/html/public:ro
    │              ./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf:ro
    └── network: vipnet
```

### Dockerfile (multi-stage)

| Stage | Isi |
|-------|-----|
| `nodebuild` (`node:20-alpine`) | `npm ci` → `npm run build` → hasil di `public/build` |
| `php:8.3-fpm-alpine` | Ekstensi PHP, Composer, `composer install --no-dev --optimize-autoloader`, copy hasil build ke `public-image/build`, `chown www-data`, `ENTRYPOINT app-entrypoint.sh` |

### Konfigurasi nginx

```nginx
root /var/www/html/public;
client_max_body_size 50M;
location / { try_files $uri $uri/ /index.php?$query_string; }
location ~ \.php$ {
    fastcgi_pass app:9000;
    include fastcgi_params;
    fastcgi_read_timeout 120;
}
location ~ /\.(?!well-known).* { deny all; }
```

`client_max_body_size 50M` memberi ruang untuk data URL tanda tangan; `deny all` menutup akses dotfile.

### ⚠️ Named volume `app_public` menutupi `/public` di dalam image

Nginx dan app berbagi named volume untuk `public/`. Volume hanya diisi dari image
saat **pertama kali dibuat** — sesudah itu isi `/public` di dalam image baru tidak
pernah masuk ke volume. Akibatnya `docker compose up -d --build` saja tidak cukup:
frontend diam-diam revert ke versi lama.

Dua hal yang menutup celah itu:

| Bagian | Peran |
|--------|-------|
| `Dockerfile`: `COPY --from=nodebuild /app/public/build ./public-image/build` | Hasil Vite ditaruh di `/var/www/html/public-image`, **di luar** `/public` yang tertutup volume |
| `docker/app-entrypoint.sh` | `ENTRYPOINT` image: menyalin `public-image/build` → `public/build` di dalam volume tiap container start, lalu `chmod a+rX` agar nginx (user berbeda, mount `ro`) bisa membacanya |

Hanya `build/` yang disentuh; `img/`, symlink `storage`, dan berkas lain di dalam
volume milik instance itu dan tetap utuh.

```bash
docker compose up -d --build app     # PHP + frontend, cukup satu perintah

# verifikasi sync terjadi
docker logs vip_app | grep 'menyinkronkan public/build'
docker exec vip_app ls /var/www/html/public/build/assets | head
```

Kalau baris `entrypoint: menyinkronkan ...` tidak muncul di log, image yang
dijalankan bukan hasil build terbaru — periksa `docker compose build app` dulu.

`.dockerignore` mengecualikan `storage` seluruhnya: folder itu di-bind mount saat
runtime, dan menyertakannya membuat build context gagal di
`storage/framework/testing` (izin 700) serta membocorkan berkas upload ke layer image.

### Volume & persistensi

| Volume | Jenis | Isi |
|--------|-------|-----|
| `app_public` | named (shared app ↔ nginx) | build assets Vite + `img/` + symlink `storage` |
| `./storage` | bind mount host | logs, sessions, cache, `app/public/signatures`, `app/backups` |

`storage/` di-bind mount agar log, tanda tangan, **dan file backup database** tetap ada setelah
container di-recreate. Image **tidak** menjalankan `migrate` saat start — langkah migrasi dijalankan
terpisah (lihat Operasional).

### Operasional

```bash
docker compose up -d --build app     # deploy (PHP + aset frontend)
docker compose logs -f app           # log
docker compose restart app           # restart
docker compose exec app php artisan migrate --force
```

Health check Laravel tersedia di `/up` (dikonfigurasi lewat `withRouting(health:)`).

**Backup harian otomatis.** Container tidak menyertakan cron, jadi scheduler harus dijalankan dari
host, misalnya:

```bash
* * * * * docker exec -u www-data vip_app php artisan schedule:run >> /dev/null 2>&1
```

Tanpa entri itu, jadwal `02:00` tidak berjalan; tombol **Buat Backup Sekarang** tetap berfungsi.

---

## 17. Testing

| Jenis | Tool | Cakupan |
|-------|------|---------|
| Feature | PHPUnit 11 (`tests/Feature`) | Auth (login/register/reset/verify/password), Profile & tanda tangan tersimpan, Mesin/Utility (termasuk master Komponen + penugasan + Edit Komponen/Standar per unit), PM & rantai tanda tangan, Admin PM Records, Suku Cadang (impor CSV, CRUD, hapus massal, transaksi stok, pembatasan akses), Activity Logs (akses + filter + pencarian), Backup & Restore (akses + guard konfirmasi) |
| Unit | PHPUnit 11 (`tests/Unit`) | Pemecah skrip SQL `BackupService::splitStatements` (kutip, backtick, komentar, dump bawaan aplikasi) |
| Manual terotomasi | Puppeteer (`pptr-test.mjs`) | Sweep halaman: login nyata, cek blank page, error console, HTTP 5xx |
| Fungsional | Skrip Puppeteer ad-hoc (`/tmp/opencode/*.mjs`) | CRUD pengguna, ganti password, guard, login dengan role berbeda |

Pola verifikasi yang dipakai: **perilaku server, bukan tampilan UI** — misalnya memastikan role
pengguna tetap `admin` setelah percobaan demote, dan akun tetap ada setelah percobaan self-delete.
UI bisa-disable tombol, jadi hanya state server yang membuktikan guard bekerja.

---

## 18. Trade-off & Keputusan Desain

| Keputusan | Alasan | Konsekuensi |
|-----------|--------|-------------|
| Enum sebagai sumber kebenaran (`PmStatus::awaiting`) | Aturan tahap tidak bisa menyimpang antar policy/controller/frontend | Enum harus di-maintain bila aturan berubah |
| Satu endpoint sign untuk 3 tahap | Tahap dari status, bukan dari client → rantai tidak bisa dilompati | Endpoint tidak bisa dipanggil paralel tanpa race condition |
| `SignatureChain` dipakai policy **dan** UI | Badge antrean dan daftar tidak bisa berbeda | Penulisan aturan tidak terduplikasi; biaya maintenance aturan tetap rendah |
| Snapshot nama/role di `pm_signatures` | Jejak tetap terbaca setelah akun dihapus | Data denormalisasi (disengaja) |
| `nullOnDelete` pada `technician_id`/`approved_by` | History lebih penting daripada referensial user | Query perlu handle `technician_id = null` |
| Izin berbeda untuk `restore` = `delete` | Tidak ada user yang bisa restore tanpa bisa delete | Sederhana |
| Permission per tahap | Mencegah lompatan tahap | Tiga permission yang harus dikelola |
| Guard self-lock di controller (bukan policy) | Butuh pesan spesifik, bukan 403 | Logika di controller, perlu konsistensi UI |
| Soft delete mesin | Salah hapus tidak langsung merusak history | Tabel bertambah `deleted_at`; query wajib `SoftDeletes` |
| Service terpisah untuk logika lintas halaman | Rapikan controller | Lebih banyak file |
| Template default per jenis mesin | Form PM otomatis terisi | Perubahan template tidak mengubah record lama (snapshot di items) |
| Impor CSV idempoten | Aman dijalankan ulang | Perlu logika upsert, bukan `insert` |
| `--purge` eksplisit | Tidak menghapus history tanpa sengaja | Butuh CLI untuk machine purge |
| Filter diteruskan ke form PM | User tidak kehilangan filter | URL form jadi lebih panjang |

---

## 19. Known Limitations

Hal-hal yang diketahui ada di sistem ini dan perlu dipertimbangkan:

1. **Asset frontend harus disalin manual setelah rebuild** karena named volume `app_public`.
   Ini penyebab utama "perubahan frontend tidak muncul".
2. **Tidak ada notifikasi otomatis** (email/WhatsApp) untuk pengingat persetujuan atau PM jatuh tempo.
3. **Rantai 3 tahap bersifat hardcoded** di enum, belum bisa dikonfigurasi per unit.
4. **Activity Logs bersifat append-only** — tidak bisa dihapus dari UI. Kini sudah bisa dicari,
   difilter (aksi/pelaku/rentang tanggal), dan dipaginasi 25 per halaman.
5. **Scheduler tidak berjalan sendiri di container** — backup harian `02:00` hanya berjalan bila
   `schedule:run` dipasang pada cron host. Tidak ada pengingat otomatis lain (email/WhatsApp).
6. **Tidak ada pagination** pada daftar besar; daftar mesin & pengguna memuat seluruh data.
7. **Tidak ada resize/gambar** — tanda tangan disimpan ukuran canvas apa adanya (dibatasi 2 MB).
8. **Tidak ada pencarian global** — pencarian hanya per halaman.
9. **Tidak ada pagination pada laporan** — agregasi dihitung untuk seluruh dataset.
10. **`pm_records` tidak punya soft delete** — penghapusan mesin yang di-`purge` akan menghapus
    record PM-nya secara permanen (sengaja, demi kebenaran data).
11. **Tidak ada rate limiting** pada endpoint login (boneka Laravel default belum diaktifkan).
12. **Race condition pada signing** — dua request bersamaan pada tahap yang sama bisa saling
    menimpa; `unique(pm_record_id, stage)` mencegah duplikasi baris, tapi bukan konfirmasi transaksional.
    Menambahkan lock pada record akan menutup celah ini.
13. **Backup hanya mencakup database**, bukan berkas unggahan/tanda tangan di `storage/app/public`.
14. **Restore bersifat menimpa total (all-or-nothing)** dan belum divalidasi untuk file `mysqldump`
    yang memakai `DELIMITER` (stored routine/trigger); dump buatan aplikasi tidak memakai `DELIMITER`.

---

## Related Documents

- [README.md](README.md) — panduan setup & penggunaan
- [PRD.md](PRD.md) — kebutuhan produk & aturan bisnis