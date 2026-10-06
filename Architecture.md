# Architecture — PM Preventive System

Dokumen arsitektur teknis aplikasi **Preventive Maintenance (PM)**.
Untuk tujuan & ruang lingkup produk, lihat [PRD.md](PRD.md).
Untuk panduan setup, lihat [README.md](README.md).

**Versi dokumen:** 1.0

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
12. [Impor Mesin](#12-impor-mesin)
13. [Audit Log](#13-audit-log)
14. [Keamanan](#14-keamanan)
15. [Deployment](#15-deployment)
16. [Testing](#16-testing)
17. [Trade-off & Keputusan Desain](#17-trade-off--keputusan-desain)
18. [Known Limitations](#18-known-limitations)

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

**Catatan deployment penting:** asset hasil `vite build` di-*copy* ke dalam image pada tahap build,
tetapi nginx membaca `public/` dari **named volume** yang sama dengan yang di-mount ke container app.
Karena itu setelah rebuild, `public/build` harus disalin dari image ke volume sebelum perubahan frontend
terlihat. Detail perintahnya ada di [README — Catatan Deployment](README.md#catatan-deployment).

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
│   ├── Role.php              admin/manager/technician/user/viewer
│   ├── MachineType.php       9 jenis mesin
│   └── PmDisplayStatus.php   Status turunan untuk tampilan
│
├── Http/
│   ├── Middleware/
│   │   └── HandleInertiaRequests.php   Shared props: auth.user, auth.can, flash
│   ├── Requests/             Validasi (FormRequest)
│   │   ├── StorePmRecord.php / SignPmRecord / RejectPmRecord
│   │   ├── StoreMachine / UpdateMachine
│   │   └── ProfileUpdateRequest.php
│   └── Controllers/
│       ├── DashboardController.php      + export (XLSX/PDF)
│       ├── MachineController.php        CRUD + restore + history
│       ├── PmRecordController.php       create/store/sign/reject
│       ├── PmSignatureController.php    Streaming gambar tanda tangan
│       ├── ApprovalController.php       Antrean per permission
│       ├── ReportController.php         Statistik + export
│       ├── ProfileController.php
│       └── Admin/
│           ├── UserController.php       CRUD pengguna + ganti password
│           ├── RoleController.php       CRUD role (authorizeResource)
│           ├── TemplateController.php   Template checklist
│           └── AuditLogController.php   100 log terbaru
│
├── Actions/Pm/              use-case satu proses bisnis
│   ├── SubmitPmRecord.php    draft → submitted
│   ├── SignPmRecord.php      tahap aktif → tahap berikutnya
│   └── RejectPmRecord.php    submitted/pic_approved → rejected
│
├── Policies/                Gate per resource
│   ├── UserPolicy.php        create/update/updatePassword/delete + anti self-delete
│   ├── MachinePolicy.php     create/update/delete/restore
│   ├── PmRecordPolicy.php    create/update/sign/reject
│   └── RolePolicy.php        Registered manual untuk model Spatie
│
├── Services/                 Logika yang dipakai lintas banyak halaman
│   ├── SignatureChain.php    ATURAN TUNGGAL siapa boleh tanda tangan/ menolak
│   ├── SignatureStorage.php  Decode/validasi/simpan PNG tanda tangan
│   ├── PeriodService.php     Periode, tahun, dot notifikasi
│   ├── DashboardService.php  Agregasi status mesin + filter
│   ├── ReportService.php     Kepatuhan, top part, ringkasan aksi
│   ├── AuditLogService.php   Penulis audit log
│   └── MachineImportService.php  Impor CSV idempoten
│
├── Exports/                  Maatwebsite Excel mapping
├── Models/                   Eloquent + cast/relasi
└── Console/Commands/
    └── ImportMachinesCommand.php
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
                    │  technician_name ★    │   │  ip        │
                    │  approved_by (null)   │   └────────────┘
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
          └─────────────────┘
                    ┌──────────────────┐
                    │pm_record_revisions│
                    │  revised_by      │
                    │  snapshot (JSON) │
                    │  reason          │
                    └──────────────────┘

┌──────────────────────┐         ┌──────────────────────────────┐
│      machines        │         │   checklist_templates       │
│  code UNIQUE         │         │  machine_type               │
│  name, location      │         │  is_default                 │
│  category            │         └───────────┬──────────────────┘
│  sub_category        │                     │ cascade
│  type                │         ┌───────────┴──────────────────┐
│  week_group          │         │ checklist_template_items    │
│  template_id (null)  │────────▶│  category, name, spec       │
│  is_active           │         │  sort_no                    │
│  deleted_at (soft)   │         └──────────────────────────────┘
└──────────────────────┘
```

★ = snapshot, sengaja diduplikasi agar jejaknya tidak hilang saat akun dihapus.

### Catatan `onDelete` yang dipilih

| Relasi | Perilaku | Alasan |
|--------|----------|--------|
| `pm_records.machine_id` | `cascade` | Record PM tidak bermakna tanpa mesinnya |
| `pm_records.technician_id` | **`nullOnDelete`** | History tetap harus terbaca meski teknisi akunnya dihapus (nama dokumen ada di `technician_name`) |
| `pm_records.approved_by` | **`nullOnDelete`** | Sama seperti `technician_id` — jejak persetujuan tidak boleh ikut hilang |
| `pm_record_items/revisions/signatures.pm_record_id` | `cascade` | Data turunan dari record |
| `machines.template_id` | `nullOnDelete` | Hapus template tidak boleh menghapus mesin |
| `audit_logs.user_id` | `nullOnDelete`** | Log harus bertahan setelah pelaku keluar |

### Index

- `machines`: `(is_active, week_group)`, `(is_active, sub_category)`, unique `code`
- `pm_records`: `(year, period, status)`, `(machine_id, year)`, unique `(machine_id, year, period)`
- `pm_record_items`: `pm_record_id`
- `pm_signatures`: `signed_by`, unique `(pm_record_id, stage)`
- `audit_logs`: `(subject_type, subject_id)`

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

Nama di dokumen **diketik manual**, bukan diambil dari akun yang squeez-in, karena di
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
    Route::get('/machines', …)->middleware('can:machine.manage');
    Route::middleware('can:user.manage')->group(function () { /* /admin/users */ });
    Route::middleware('role:admin')->group(function () { /* /admin/roles */ });
    Route::middleware('can:template.manage')->group(function () { /* /admin/templates */ });
    Route::middleware('can:audit.view')->group(function () { /* /admin/audit-logs */ });
});
```

Alias `role` didaftarkan manual di `bootstrap/app.php` (Spatie v6 tidak mendaftarkannya sendiri).

### Lapis 2 — Policy per resource

| Policy | Method |
|--------|--------|
| `UserPolicy` | `view/create/update/updatePassword` → `user.manage`; `delete` → `user.manage` **dan bukan diri sendiri** |
| `MachinePolicy` | `view` → `dashboard.view`; `create/update/delete/restore` → `machine.manage` |
| `PmRecordPolicy` | `create` → `pm.fill`; `update` → `pm.fill` + `isEditable()` + kepemilikan; `sign/reject` → `SignatureChain` |
| `RolePolicy` | Didaftarkan manual di `AppServiceProvider` (model Spatie, bukan `App\Models\Role`) |

### Lapis 3 — Guard di dalam controller

Beberapa aturan terlalu spesifik untuk ditulis sebagai Policy generik, sehingga ditegakkan langsung:

- `UserController::update()` — admin **tidak boleh mengubah role sendiri**.
  Alasannya: satu-satunya akun admin yang kehilangan `user.manage` akan terkunci dari halaman
  yang mengatur role, dan tidak ada halaman lain untuk memulihkannya.
- `UserController::destroy()` — cek "diri sendiri" **sebelum** `authorize()`, supaya pesan yang
  muncul jelas ("Tidak dapat menghapus akun sendiri") dan bukan 403 yang membingungkan.

### Permission model

Permission originates dari `RoleSeeder`:

| Permission | Pemakai utama |
|------------|---------------|
| `dashboard.view` | semua role |
| `pm.fill` | technician |
| `pm.sign` | technician |
| `pm.acknowledge` | user (PIC) |
| `pm.approve` | manager (supervisor) |
| `pm.history.view` | semua role |
| `report.view` | admin, manager, technician, user |
| `report.export` | admin, manager, technician, viewer |
| `machine.manage` | admin, manager |
| `template.manage` | admin, manager |
| `user.manage` | admin |
| `audit.view` | admin, manager |

Tiga permission tanda tangan **sengaja dipisah** (`pm.sign` / `pm.acknowledge` / `pm.approve`)
supaya role tidak bisa melompat tahap dengan satu permission generik.

### Shared props

`HandleInertiaRequests` mengirim `auth.user` (name, email, roles) dan **`auth.can`** (peta
permission boolean) ke seluruh halaman. Sidebar menggunakan `auth.can` untuk menampilkan menu,
sehingga menu dan server memakai sumber kebenaran yang sama.

---

## 9. Alur Request Utama

### Mengisi checklist PM

```
GET  /machines/{machine}/pm?period=&year=
   ├─ middleware: auth, verified, can:dashboard.view
   ├─ resolve period & year (query, default = periode berjalan)
   ├─ cari record existing (machine, year, period) → item dari record
   ├─ kalau tidak ada → item dari template default mesin
   └─ teruskan filter Beranda yang sedang aktif ke "Kembali"/redirect

POST /machines/{machine}/pm
   ├─ FormRequest: validasi item[], actual, aksi, parts_replaced
   ├─ FormRequest: technician_name WAJIB + signature WAJIB (data URL PNG)
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
| `/profile` | GET/PUT/DELETE | `ProfileController` | — |
| `/approvals` | GET | `ApprovalController@index` | — |
| `/machines/{machine}/pm` | GET | `PmRecordController@create` | `can:dashboard.view` |
| `/machines/{machine}/pm` | POST | `PmRecordController@store` | `can:pm.fill` + `pm.sign` (dalam FormRequest) |
| `/pm/{record}/sign` | POST | `PmRecordController@sign` | Policy::sign |
| `/pm/{record}/reject` | POST | `PmRecordController@reject` | Policy::reject |
| `/pm/{record}/signature/{stage}` | GET | `PmSignatureController@show` | Policy |
| `/machines` | GET | `MachineController@index` | `can:machine.manage` |
| `/machines` | POST | `MachineController@store` | `can:machine.manage` |
| `/machines/{machine}` | PUT | `MachineController@update` | `can:machine.manage` |
| `/machines/{machine}` | DELETE | `MachineController@destroy` | `can:machine.manage` |
| `/machines/{machine}/restore` | PATCH | `MachineController@restore` | `can:machine.manage` |
| `/machines/{machine}/history` | GET | `MachineController@history` | `can:pm.history.view` |
| `/reports` | GET | `ReportController@index` | `can:report.view` |
| `/reports/export` | GET | `ReportController@export` | `can:report.export` |
| `/admin/users` | GET | `UserController@index` | `can:user.manage` |
| `/admin/users` | POST | `UserController@store` | `can:user.manage` + Policy |
| `/admin/users/{user}` | PUT | `UserController@update` | `can:user.manage` + Policy |
| `/admin/users/{user}/password` | PUT | `UserController@updatePassword` | `can:user.manage` + Policy |
| `/admin/users/{user}` | DELETE | `UserController@destroy` | `can:user.manage` + Policy |
| `/admin/roles` | resource | `RoleController` | `role:admin` + `authorizeResource` |
| `/admin/templates` | GET/POST | `TemplateController` | `can:template.manage` |
| `/admin/templates/{template}` | DELETE | `TemplateController@destroy` | `can:template.manage` |
| `/admin/audit-logs` | GET | `AuditLogController@index` | `can:audit.view` |

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

### Layout

`AuthenticatedLayout.jsx` menyusun sidebar + header:

- Item menu didefinisikan sekali dengan flag `show` berdasarkan `auth.can`.
- Badge antrean "Persetujuan" memakai `SignatureChain::pendingCountFor()` yang dikirim backend,
  sehingga angka pada badge selalu sama dengan daftar. Karena badge memakai query itu
  juga, daftar memuat record `draft`/`rejected` milik teknisi — jadi panel tanda tangan
  di halaman ini dipasang untuk tahap apa pun yang sedang menunggu, bukan hanya PIC/Atasan.
- Sidebar responsif: rail vertikal (desktop) dan drawer (mobile).

### Halaman

| Path | Halaman | Ringkas |
|------|---------|---------|
| `/dashboard` | `Dashboard/Index` | Grid status mesin, filter, dot periode, ekspor |
| `/approvals` | `Approvals/Index` | Antrean per permission, stepper, tabel nama + signature per baris |
| `/reports` | `Reports/Index` | Kepatuhan, top part, ringkasan aksi, ekspor |
| `/machines` | `Machines/Index` | CRUD mesin, pencarian, restore |
| `/machines/{machine}/history` | `Machines/History` | Riwayat PM mesin |
| `/machines/{machine}/pm` | `Pm/Form` | Checklist + tanda tangan |
| `/admin/users` | `Admin/Users` | CRUD pengguna + ganti password + pencarian |
| `/admin/roles` | `Admin/Roles` | CRUD role + matriks permission |
| `/admin/templates` | `Admin/Templates` | Template checklist per jenis |
| `/admin/audit-logs` | `Admin/AuditLogs` | 100 log terbaru |
| `/profile` | `Profile/Edit` | Info akun, ganti password, hapus akun |

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

## 12. Impor Mesin

```
php artisan machines:import [--purge] [--force]
```

Sumber data: `references/data.csv` di root proyek (121 mesin, jalur `base_path('references/data.csv')`).

| Asal | Tujuan | Cara |
|------|--------|------|
| `NO` | `sort_no` | langsung |
| `Machine Name` | `name` + penentuan `type` | keyword berurutan (`TYPE_KEYWORDS`) |
| `Location` | `location` | langsung |
| `Category` | `category` | langsung |
| `Sub-Category` | `sub_category` + `week_group` | angka pada label (`"C.7"` → 7) |

Keputusan desain:

- **Idempoten** — aman dijalankan berulang; mesin yang sudah ada di-update, bukan diduplikasi.
- **Baris sentinel dilewati** — file asli memuat baris penanda (`MASTER`), bukan mesin sungguhan.
- **Urutan keyword penting** — `"Timbangan - Mixer 1"` harus menjadi `Timbangan`, bukan `Mixer`;
  `"TOM - Filling Otomatis"` harus `TOM`, bukan `Filling`. Kunci pertama yang cocok menang.
- **`--purge` bersifat destruktif & perlu konfirmasi** — mesin yang hilang dari CSV dihapus permanen
  beserta riwayat PM-nya. Karena itu ada `--force` untuk CI/non-interaktif.
- Dibungkus `DB::transaction()` agar impor gagal di tengah tidak meninggalkan data setengah jadi.

---

## 13. Audit Log

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

> **Cakupan saat ini:** perubahan pada **template checklist** dan **role/permission** belum
> tercatat di audit log. Keduanya baru bisa ditambah dengan satu baris
> `AuditLogService::record(...)` di controller masing-masing.

**Aturan privasi:** aksi `user.password` **tidak pernah** menulis nilai password, hanya nama & email
sasaran supaya jejaknya tetap bisa ditelusuri. Password lama pun tidak ikut.

Halaman audit log menampilkan **100 log terbaru** dengan urutan `created_at` descending.

---

## 14. Keamanan

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

## 15. Deployment

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
| `php:8.3-fpm-alpine` | Ekstensi PHP, Composer, `composer install --no-dev --optimize-autoloader`, copy `public/build` dari stage `nodebuild`, `chown www-data` |

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

### ⚠️ Isu named volume `app_public`

Nginx dan app berbagi named volume untuk `public/`. Akibatnya, `public/build` yang ada di dalam image
**tidak terlihat** oleh nginx sampai disalin eksplisit ke volume. Rebuild tanpa langkah ini →
frontend revert ke versi lama (atau halaman kosong bila volume masih kosong).

```bash
docker compose build app
docker compose up -d --force-recreate app

# sinkronkan asset frontend dari image ke volume
rm -rf /tmp/build && mkdir -p /tmp/build
cid=$(docker create vip-maintenance-app:latest)
docker cp "$cid:/var/www/html/public/build/." /tmp/build/
docker rm "$cid" >/dev/null
docker exec vip_app sh -c 'rm -rf /var/www/html/public/build'
docker cp /tmp/build/. vip_app:/var/www/html/public/build
docker exec vip_app sh -c 'chown -R www-data:www-data /var/www/html/public/build'
```

### Volume & persistensi

| Volume | Jenis | Isi |
|--------|-------|-----|
| `app_public` | named (shared app ↔ nginx) | build assets Vite |
| `./storage` | bind mount host | logs, sessions, cache, `app/public/signatures` |

`storage/` di-bind mount agar log dan tanda tangan tetap ada setelah container di-recreate.

### Operasional

```bash
docker compose up -d --build         # deploy
docker compose logs -f app           # log
docker compose restart app           # restart
docker compose exec app php artisan migrate --force
```

Health check Laravel tersedia di `/up` (dikonfigurasi lewat `withRouting(health:)`).

---

## 16. Testing

| Jenis | Tool | Cakupan |
|-------|------|---------|
| Feature | PHPUnit 11 (`tests/Feature`) | Auth (login/register/reset/verify/password), Profile |
| Manual terotomasi | Playwright (via Docker image `mcr.microsoft.com/playwright`) | Sweep 9 halaman: login nyata, cek blank page, error console, HTTP 5xx |
| Fungsional | Skrip Playwright ad-hoc (`/tmp/opencode/*.mjs`) | CRUD pengguna, ganti password, guard, login dengan role berbeda |

Pola verifikasi yang dipakai: **perilaku server, bukan tampilan UI** — misalnya memastikan role
pengguna tetap `admin` setelah percobaan demote, dan akun tetap ada setelah percobaan self-delete.
UI bisa-disable tombol, jadi hanya state server yang membuktikan guard bekerja.

---

## 17. Trade-off & Keputusan Desain

| Keputusan | Alasan | Konsekuensi |
|-----------|--------|-------------|
| Enum sebagai sumber kebenaran (`PmStatus::awaiting`) | Aturan tahap tidak bisa menyimpang antar policy/controller/frontend | Enum harus di-maintain bila aturan berubah |
| Satu endpoint sign untuk 3 tahap | Tahap dari status, bukan dari client → rantai tidak bisa dilompati | Endpoint tidak bisa dipanggil paralel tanpa race condition |
| `SignatureChain` dipakai policy **dan** UI | Badge antrean dan daftar tidak bisa berbeda | Penulisan aturan tidak terduplikasi; biaya maintenance aturan tetap rendah |
| Snapshot nama/role di `pm_signatures` | Jejak tetap terbaca setelah akun dihapus | Data denormalisasi (s disengaja) |
| `nullOnDelete` pada `technician_id`/`approved_by` | History lebih penting daripada referensial user | Query perlu handle `technician_id = null` |
| Izin berbeda untuk `restore` = `delete` | Tidak ada user yang bisa restore tanpa bisa delete | Sederhana |
| Permission per tahap | Mencegah lompatan tahap | Tiga permission yang harus dikelola |
| Guard self-lock di controller (bukan policy) | Butuh pesan spesifik, bukan 403 | Logika di controller, perlu Consistensi UI |
| Soft delete mesin | Salah hapus tidak langsung merusak history | Tabel bertambah `deleted_at`; query wajib `SoftDeletes` |
| Service terpisah untuk logika lintas halaman | Rapikan controller | Lebih banyak file |
| Template default per jenis mesin | Form PM otomatis terisi | Perubahan template tidak mengubah record lama (snapshot di items) |
| Impor CSV idempoten | Aman dijalankan ulang | Perlu logika upsert, bukan `insert` |
| `--purge` eksplisit | Tidak menghapus history tanpa sengaja | Butuh CLI untuk machine purge |
| Filter diteruskan ke form PM | User tidak kehilangan filter | URL form jadi lebih panjang |

---

## 18. Known Limitations

Hal-hal yang diketahui ada di sistem ini dan perlu dipertimbangkan:

1. **Asset frontend harus disalin manual setelah rebuild** karena named volume `app_public`.
   Ini tanggal etiology utama "perubahan frontend tidak muncul".
2. **Tidak ada notifikasi otomatis** (email/WhatsApp) untuk pengingat persetujuan atau PM jatuh tempo.
3. **Rantai 3 tahap bersifat hardcoded** di enum, belum bisa dikonfigurasi per unit.
4. **Audit log tidak bisa difilter** (hanya 100 terbaru) dan tidak bisa dihapus dari UI.
5. **Tidak ada scheduled job** — tidak ada pengingat otomatis; semua dipicu manual.
6. **Tidak ada pagination** pada daftar besar; daftar mesin & pengguna memuat seluruh data.
7. **Tidak ada resize/gambar** — tanda tangan disimpan ukuran canvas apa adanya (dibatasi 2 MB).
8. **Tidak ada pencarian global** — pencarian hanya per halaman.
9. **Tidak ada pagination pada laporan** — agregasi dihitung untuk seluruh dataset.
10. **`pm_records` tidak punya soft delete** — penghapusan mesin yang di-`purge` akan menghapus
    record PM-nya secara permanen (sengaja, demi kebenaran data).
11. **Tidak ada rate limiting** pada endpoint login (boneka Laravel default belum diaktifkan).
12. **Race condition pada signing** — dua request bersamaan pada tahap yang sama bisa saling
    menimpa; `unique(pm_record_id, stage)` mencegah duplikasi baris, tapi bukan konfirmasi transaksional.
    Adding lock pada record akan menutup celah ini.

---

## Related Documents

- [README.md](README.md) — panduan setup & penggunaan
- [PRD.md](PRD.md) — kebutuhan produk & aturan bisnis