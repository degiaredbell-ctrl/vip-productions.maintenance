# PM Preventive — Preventive Maintenance System

Aplikasi web untuk mengelola **Preventive Maintenance (PM)** mesin produksi: mulai dari daftar mesin, pengisian checklist lapangan, **rantai tanda tangan 3 tahap**, sampai laporan kepatuhan dan ekspor XLSX/PDF.

Dibangun dengan **Laravel 11 + Inertia.js v2 + React 18 + Tailwind CSS**, dideploy dalam container **Docker (PHP-FPM + Nginx + Vite)**.

> Nama aplikasi pada UI: **PM Preventive** · Client: Verra Inter Pangan / Redbell Group

---

## Daftar Isi

- [Ringkasan Fitur](#ringkasan-fitur)
- [Teknologi](#teknologi)
- [Peran dan Permission](#peran-dan-permission)
- [Rantai Tanda tangan PM](#rantai-tanda-tangan-pm)
- [Setup / Instalasi](#setup--instalasi)
- [Akun Demo](#akun-demo)
- [Perintah Artisan](#perintah-artisan)
- [Struktur Proyek](#struktur-proyek)
- [Catatan Deployment](#catatan-deployment)
- [Dokumentasi Lain](#dokumentasi-lain)

---

## Ringkasan Fitur

### 1. Autentikasi & Profil
- Register, Login, Logout
- Remember me dan lupa password (reset via email)
- Konfirmasi password untuk aksi sensitif
- Verifikasi email
- **Halaman Profil**: ubah nama/email, ganti password sendiri, hapus akun
- **Tanda tangan tersimpan**: gambar tanda tangan sekali di Profil (`signature_path`) lalu pakai ulang saat menyetujui, tanpa menggambar ulang
- Ganti password memakai password lama sebagai pembuktian

### 2. Beranda / Dashboard
- Grid kartu mesin dengan **status PM untuk periode terpilih**
- Filter: **periode (6 periode 2-bulanan), tahun, area/sub-kategori, status, pencarian**
- Filter aktif **diteruskan ke halaman form PM** dan tombol "Kembali" (tidak kehilangan filter)
- **Dot notifikasi 4 warna** per periode:
  - 🔵 biru — periode berjalan, masih ada mesin belum selesai
  - 🟠 oranye — periode sebelumnya masih ada tunggakan
  - 🔴 merah — tunggakan lebih lama dari periode sebelumnya
  - 🟢 hijau — tidak ada tunggakan
- Periode di masa depan terkunci (tidak bisa diisi)
- Statistik ringkasan (total mesin, selesai, sedang approval, perlu revisi)
- **Ekspor XLSX** status mesin + **PDF** daftar mesin sesuai filter yang sedang aktif

### 3. Pengisian Checklist PM
- Checklist **di-generate otomatis dari master Komponen** yang ditugaskan ke unit; item template lama hanya dipakai sebagai cadangan untuk data yang belum termigrasi
- Bila record sudah ada, form diisi dari data sebelumnya (snapshot, bukan item kosong)
- Per item checklist:
  - nilai aktual (`actual`)
  - aksi: Bersihkan / Perbaiki / Lumasi / Ganti
  - kondisi akhir + jumlah part yang diganti
  - **detail bagian yang diganti wajib diisi** (`replace_note`) bila aksi **Ganti** dicentang
- Catatan umum per record
- Teknisi memilih **User PIC** yang akan menyetujui tahap 2 (`pic_user_id`) sebelum menyimpan
- Validasi periode: periode terkunci/future tidak bisa diisi
- Mendukung revisi setelah ditolak (revision counter & snapshot)

### 4. Rantai Tanda Tangan (3 Tahap)
Satu submit untuk tahap 1 (`POST /machines/{machine}/pm`), satu endpoint untuk tahap 2 & 3 (`POST /pm/{record}/sign`). Tahap aktif dibaca dari `status` record sehingga rantai **tidak bisa dilompati atau diurutkan ulang**.

| # | Tahap | Permission | Status setelah |
|---|-------|-----------|----------------|
| 1 | Tanda Tangan Teknisi/Pemeriksa | `pm.sign` | `submitted` |
| 2 | Persetujuan User PIC | `pm.acknowledge` | `pic_approved` |
| 3 | Persetujuan Atasan | `pm.approve` | `approved` |

- **Tahap 1 menyatu dengan pengisian checklist**: teknisi isi Aktual → nama → tanda tangan → sekali submit, checklist langsung naik ke `submitted`. Tidak ada lagi PM nyangkut di `draft`.
- **Nama teknisi wajib diketik manual** (`technician_name`), bukan diambil dari nama akun login — di lapangan orang yang mengisi checklist tidak selalu orang yang login. Nama itu juga dipakai sebagai nama pada dokumen bertanda tangan.
- **Nama penanda tangan wajib diisi manual di ketiga tahap** (`signer_name` / kolom `technician_name`), dan tombol submit **mati** selama nama atau tanda tangan kosong.
- **Tanda tangan gambar** (canvas) — disimpan sebagai PNG di disk, disajikan lewat route ber-otorisasi
- **Snapshot nama & peran** penanda tangan disimpan terpisah dari akun → jejak tetap terbaca meski akun dihapus
- **Anti konflik kepentingan**: approver (tahap 2 & 3) tidak boleh orang yang sama dengan mengisi checklist
- **Penolakan** hanya di tahap User PIC / Atasan → kembalikan ke teknisi (`rejected`) dengan alasan
- Checklist **terkunci setelah ditandatangani** (`isEditable()`), tidak bisa diubah di belakang layar — `POST /machines/{machine}/pm` juga menolak record yang sedang berjalan di rantai approval
- Kalau gambar tanda tangannya gagal ditulis setelah checklist tersimpan, PM dibiarkan di `draft` dengan pesan error, bukan hilang tanpa jejak

### 5. Halaman Persetujuan
- Daftar record yang menunggu tindakan user **berdasarkan permission-nya**
- Badge sidebar memakai **query yang sama** dengan daftar halaman → angka tidak pernah berbeda
- Rantai approval ditampilkan sebagai stepper dengan centang per tahap
- **Murni antrean (tanpa tanda tangan di daftar)**: halaman ini menjawab "apa
  yang menunggu saya". Persetujuan dilakukan di **form checklist PM-nya sendiri**
  supaya isi yang disetujui dibaca bersamaan dengan tanda tangannya
- Tombol **Buka Checklist** membuka form PM. Approver (`pm.acknowledge` /
  `pm.approve`) dapat membukanya walaupun tanpa `pm.fill` — form tampil
  **read-only**, isi checklist tetap terbaca, dan **panel tanda tangan** di
  bawahnya yang menyetujui/menolak
- Form yang dibuka dari antrean memakai `?return=approvals`, jadi tombol
  **Kembali** mengembalikan approver ke antrean ini, bukan ke Beranda
- Record yang checklist-nya belum lengkap tidak bisa ditutup dari antrean —
  tahap teknisi hanya naik status setelah semua nilai Aktual terisi

### 6. Daftar Mesin & Utility
- CRUD mesin (kode unik, nama, lokasi, kategori, sub-kategori, jenis, status aktif) — tabel `assets` menampung **Mesin dan Utility**
- Halaman **Utility** terpisah (`/utility`) untuk unit utility beserta komponen maintenance-nya
- **Soft delete + restore**
- **Impor massal dari CSV** (`machines:import`), idempoten
- Pengelompokan minggu dari Sub-Category
- Riwayat PM per mesin

### 7. Komponen / Parts
- **Master komponen** (nama, kategori, spesifikasi, urutan) — pengganti template checklist per jenis mesin
- **Penugasan ke unit** Mesin/Utility lewat relasi banyak-ke-banyak: satu komponen bisa dipakai banyak unit, dan tiap unit punya urutan komponen sendiri
- **Edit Komponen + Standar per unit** langsung dari halaman **`/machines`** dan **`/utility`** (tombol `Komponen → Edit Komponen`): pilih beberapa komponen sekaligus (multi-select) lalu isi nilai **Standar**. Standar disimpan per unit, jadi komponen yang sama bisa berbeda standar antar unit; bila kosong, form PM memakai spesifikasi master komponen
- Halaman **`/admin/komponen`** (permission `template.manage`) untuk mengelola master + penugasan; URL lama `/admin/templates` dialihkan ke sini
- Form PM menyusun item dari komponen unit; **item template lama hanya cadangan** untuk data yang belum termigrasi (oleh `ComponentMigrationService`)

### 8. Form Maintenance
- Halaman perantara **`/form-maintenance`**: kartu pilih **Mesin** atau **Utility**
- Daftar unit + status PM per periode di `/form-maintenance/mesin` dan `/form-maintenance/utility` sebagai pintu masuk ke form PM
- Hanya scope yang boleh diakses yang ditampilkan; kartu di luar scope tampil abu-abu dan tidak bisa diklik maupun dibuka lewat URL

### 9. Manajemen Pengguna (role `admin`)
- **CRUD pengguna penuh**: tambah, edit nama/email/role, hapus
- **Ubah password login pengguna lain** (dengan konfirmasi password)
- Daftar role diambil **dinamis dari tabel `roles`** → role baru langsung bisa dipilih
- Pencarian pengguna di halaman
- **Perlindungan akun sendiri**: admin tidak bisa mengubah role sendiri atau menghapus dirinya (UI nonaktif + guard di backend)
- Audit `user.create` / `user.update` / `user.password` / `user.delete` — **password tidak pernah masuk log**

### 10. Manajemen Role & Permission
- CRUD role custom (5 role sistem tidak bisa dihapus)
- Set permission per role via checkbox

### 11. Manajemen PM Record (role `admin`)
- Halaman **`/admin/pm-records`** (permission `pm.manage`) menampilkan **seluruh PM Record** lintas mesin, status, periode, dan tahun
- Filter (status, periode, tahun, tipe) + pencarian (nama teknisi, catatan, kode/nama/lokasi mesin)
- **Hapus permanen** record beserta item, tanda tangan, revisi, dan audit log terkait

### 12. Laporan
Kartu laporan dulu berada di halaman **`/reports`** (sudah dihapus, termasuk
item menu **Laporan**), kini dilebur ke **Beranda (`/dashboard`)**:
- **Ringkasan aksi** (bersihkan/perbaiki/lumasi/ganti)
- **Komponen yang paling sering diganti** (5 teratas)
- Mengikuti filter **tahun** & **periode** Beranda
- **Ekspor XLSX** (Maatwebsite Excel) dan **PDF** (DomPDF) lewat
  `GET /dashboard/report/export` (permission `report.export`)

Ekspor PDF juga memuat **kepatuhan per periode** dan **detail komponen diganti**
per mesin, meski kartunya tidak lagi tampil di layar.

### 13. Activity Logs
- Halaman **`/admin/activity-logs`** (permission `activity.view`) — pengganti halaman Audit Log lama (backing store tetap tabel `audit_logs`)
- Mencatat aksi penting: `machine.create`, `machine.update`, `machine.delete`, `machine.restore`, `pm.submit`, `pm.sign.{stage}`, `pm.reject`, `user.create`, `user.update`, `user.password`, `user.delete`, `sparepart.create`, `sparepart.update`, `sparepart.delete`, `sparepart.restore`, `sparepart.movement`, `sparepart.import`, `backup.create`, `backup.restore`, `backup.delete`
- Menyimpan user pelaku, subject, perubahan (JSON), dan IP
- **Pencarian** (aksi/pelaku/subjek/IP) dan **filter** aksi, pelaku, serta rentang tanggal (`from`/`to`)
- **Statistik ringkas** (total, hari ini, pelaku aktif) + **detail perubahan** yang bisa dibuka-tutup per baris
- Paginasi 25 log per halaman, mempertahankan filter saat berpindah halaman

### 14. Suku Cadang
- Master suku cadang **global** (tidak terikat unit Mesin/Utility): `Code.asset`, kategori, nama, merek, type, deskripsi, satuan, stok, stok awal/minimum, supplier, kebutuhan untuk, dan lokasi
- **Transaksi stok masuk/keluar** — stok terkini menyesuaikan otomatis; barang keluar melebihi stok ditolak
- **Riwayat transaksi** per suku cadang (tipe, jumlah, tanggal, catatan, pelaku)
- Pencarian + filter kategori, lokasi, dan status (**stok menipis / habis**)
- **Impor massal dari CSV** (`spare-parts:import`) + **unggah CSV** lewat halaman — idempoten (baris dicocokkan berdasarkan kode)
- **Soft delete** (hapus dari daftar; pulih lewat `restore`/impor ulang) — termasuk **hapus massal** lewat checkbox baris
- Tombol aksi berupa ikon (masuk `+`, keluar `−`, riwayat, ubah, hapus) agar tabel lebih ringkas
- Semua user login boleh **melihat**; **kelola** (CRUD, transaksi, impor) hanya pemegang `sparepart.manage` (Admin & tech-all)

### 15. UI / UX
- Design system **Neumorphism** (`NeuCard`, `NeuButton`, `NeuInput`, `NeuPill`, `NeuRing`, `NeuToast`, `NeuTrack`, `NeuBars`, `NeuSignaturePad`)
- Sidebar responsif (desktop + mobile)
- Toast notifikasi untuk setiap aksi sukses/gagal
- Tampilan dalam Bahasa Indonesia

### 16. Backup & Restore
- Halaman **`/admin/backups`** (permission `backup.manage`, khusus **Admin**) untuk mencadangkan dan memulihkan **database** aplikasi
- **Backup manual** sekali klik; file `.sql` disimpan di disk privat `backups` (`storage/app/backups`, tidak terekspos web)
- **Unduh**, **pulihkan**, dan **hapus** tiap backup dari halaman; konfirmasi wajib mengetik ulang nama berkas sebelum restore
- **Restore dari unggahan** berkas `.sql` (dengan centang pernyataan "data akan ditimpa")
- **Backup harian otomatis** via Laravel scheduler (`routes/console.php`, `backup:database --type=scheduled --prune`) dan **retensi** `backup.keep` (default 30 backup terbaru)
- Implementasi **murni PDO** (tanpa biner `mysqldump`): dump dan restore memakai koneksi aplikasi, sehingga konsisten dengan charset/otentikasi MySQL 8
- Setiap aksi (`backup.create`, `backup.restore`, `backup.delete`) tercatat di Activity Logs

---

## Teknologi

| Lapisan | Teknologi |
|---------|-----------|
| Backend | Laravel `^11.31`, PHP `^8.2` (image `php:8.3-fpm-alpine`) |
| Frontend | Inertia.js `^2.0`, React `^18.2`, Vite `^6.0` |
| Styling | Tailwind CSS `^3.2` |
| Auth & RBAC | Laravel Breeze, Spatie Laravel Permission `^6.25` |
| Database | MySQL |
| Export | Maatwebsite Excel (XLSX), Barryvdh DomPDF (PDF) |
| Web server | Nginx `1.27-alpine` + PHP-FPM port 9000 |
| Testing | PHPUnit 11, Puppeteer (`pptr-test.mjs`, verifikasi UI end-to-end) |

---

## Peran dan Permission

Daftar permission berasal dari `database/seeders/RoleSeeder.php`.

| Permission | Admin | Manager | Technician | User | Viewer | tech-machines | tech-utilities | tech-all |
|---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| `dashboard.view` | ✅ | ✅ | ✅ | ✅ | ✅ | — | — | — |
| `pm.fill` | ✅ | — | ✅ | — | — | ✅ | ✅ | ✅ |
| `pm.sign` | ✅ | — | ✅ | — | — | ✅ | ✅ | ✅ |
| `pm.acknowledge` | ✅ | — | — | ✅ | — | — | — | — |
| `pm.approve` | ✅ | ✅ | — | — | — | — | — | — |
| `pm.manage` | ✅ | — | — | — | — | — | — | — |
| `pm.history.view` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `report.view` | ✅ | ✅ | ✅ | ✅ | — | ✅ | ✅ | ✅ |
| `report.export` | ✅ | ✅ | ✅ | — | ✅ | ✅ | ✅ | ✅ |
| `machine.manage` | ✅ | ✅ | — | — | — | — | — | ✅ |
| `machine.view` | ✅ | — | — | — | — | ✅ | — | ✅ |
| `utility.manage` | ✅ | — | — | — | — | — | — | ✅ |
| `utility.view` | ✅ | — | — | — | — | — | ✅ | ✅ |
| `template.manage` | ✅ | ✅ | — | — | — | — | — | ✅ |
| `sparepart.view` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `sparepart.manage` | ✅ | — | — | — | — | — | — | ✅ |
| `user.manage` | ✅ | — | — | — | — | — | — | — |
| `activity.view` | ✅ | ✅ | — | — | — | — | — | — |
| `backup.manage` | ✅ | — | — | — | — | — | — | — |

**Catatan penting:**
- `pm.sign`, `pm.acknowledge`, `pm.approve` **dipisah per tahap** — bukan satu permission `pm.approve` generik, supaya peran tidak bisa melompat tahap.
- Manager **tidak** bisa mengisi checklist lapangan (tidak punya `pm.fill`).
- **Approver bisa membuka form PM (read-only)**: route `GET /machines/{machine}/pm` dibuka dengan `can_any:pm.fill,pm.acknowledge,pm.approve`. Role **User** (tahap 2) dan **Manager** (tahap 3) dapat meninjau isi checklist lalu menyetujui/menolak lewat panel tanda tangan di form itu, walaupun tanpa `pm.fill`. `viewer` (hanya `pm.history.view`) tetap **tidak** bisa membukanya. Penyimpanan checklist tetap butuh `pm.fill`.
- Halaman **Role** hanya untuk role `admin`; halaman **Pengguna** untuk siapa pun yang punya `user.manage` (hanya admin saat ini).
- `machine.view` / `utility.view` = **scope** halaman/daftar Mesin & Utility. Role general (Manager ke atas) membuka keduanya lewat baseline `dashboard.view`, jadi tidak perlu `*.view` eksplisit.
- Role teknisi **tech-machines** / **tech-utilities** sengaja tanpa `dashboard.view`: hanya satu scope terbuka, dan `/form-maintenance` menampilkan kartu di luar scope sebagai **abu-abu** yang tidak bisa diklik maupun dibuka lewat URL. **tech-all** memegang `machine.manage` + `utility.manage` + `template.manage` (CRUD mesin & utility, form-maintenance lengkap).
- **Suku Cadang** tidak terikat scope Mesin/Utility: `sparepart.view` diberikan ke **semua** role (semua user login boleh melihat), sedangkan `sparepart.manage` hanya Admin & **tech-all**.
- Otorisasi tetap ditegakkan di server lewat **Policy/middleware** (termasuk blokir akses URL langsung ke form PM unit di luar scope), bukan hanya dengan menyembunyikan menu.
- **Activity Logs**: `activity.view` menggantikan `audit.view` (migration rename, link role dipertahankan lewat `permission_id`). Nama lama tetap dipakai sebagai backing store — tabel `audit_logs`, model `AuditLog`, dan `AuditLogService` tidak berubah.
- **Backup & Restore**: `backup.manage` hanya dimiliki **Admin**; halaman dan seluruh route backup dilindungi middleware `can:backup.manage`.

---

## Rantai Tanda Tangan PM

```
  isi checklist + nama + tanda tangan
                │
                ▼
  draft ──sign(technician)──▶ submitted ──sign(pic)──▶ pic_approved ──sign(supervisor)──▶ approved
    ▲                                                                                        │
    └──────────────────── reject (pic / supervisor) ──────────────────────────────────────────┘
                                        │
                                     rejected ──isi + sign(technician)──▶ submitted
```

- `draft` / `rejected` → menunggu tahap **Technician**
- `submitted` → menunggu **User PIC**
- `pic_approved` → menunggu **Supervisor**
- `approved` → final, tidak bisa diubah lagi

Aturan yang ditegakkan `app/Services/SignatureChain.php`:
1. Tahap aktif dibaca dari `status` → rantai tidak bisa dilompati.
2. Approver tidak boleh sama dengan teknisi pengisi checklist.
3. Admin tetap boleh di semua tahap (ia tidak melakukan pekerjaan lapangan) dan menjadi pengecualian saat tidak ada orang lain.
4. Penolakan hanya di tahap 2 dan 3.
5. Tahap 1 hanya bisa ditutup lewat `POST /machines/{machine}/pm`, yang mewajibkan `technician_name` + `signature`. Akses `pm.fill` saja tidak cukup — `pm.sign` ikut diperiksa, jadi form yang sengaja disembunyikan tidak bisa dipanggil langsung.

---

## Setup / Instalasi

### Prasyarat
- Docker & Docker Compose
- Node.js 20 & Composer 2 (hanya bila mau development lokal tanpa Docker)

### Menjalankan dengan Docker

```bash
# 1. Siapkan environment
cp .env.example .env
php artisan key:generate

# 2. Siapkan database (pastikan service MySQL sudah jalan & .env sudah diisi)
php artisan migrate --seed

# 3. Build & jalankan
docker compose up -d --build
```

Aplikasi tersedia di **http://localhost:8082**.

### Build ulang setelah ubah kode

```bash
docker compose up -d --build app
```

Satu perintah itu sudah cukup untuk perubahan PHP **dan** frontend. Asset hasil
`vite build` otomatis disalin dari image ke named volume `app_public` oleh
`docker/app-entrypoint.sh` setiap container start — tidak ada langkah `docker cp`
manual lagi (lihat [Catatan Deployment](#catatan-deployment)).

### Development lokal tanpa Docker

```bash
composer install
npm install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed
composer run dev     # server + queue + logs + vite
```

---

## Akun Demo

Dari `UserSeeder`, seluruh akun memakai password **`password`**.

| Nama | Email | Role |
|------|-------|------|
| Admin Maintenance | `admin.maintenance@redbellgroup.com` | admin |
| Manager Maintenance | `manager.maintenance@redbellgroup.com` | manager |
| Teknisi Maintenance | `technician.maintenance@redbellgroup.com` | technician |
| User Maintenance | `user.maintenance@redbellgroup.com` | user |
| Viewer Maintenance | `viewer.maintenance@redbellgroup.com` | viewer |

> Email `@example.test` juga tersedia sebagai akun uji. **Ganti password seluruh akun demo sebelum dipakai di lingkungan produksi.**

---

## Perintah Artisan

| Perintah | Fungsi |
|----------|--------|
| `php artisan migrate --seed` | Siapkan skema + role + mesin + user demo |
| `php artisan machines:import` | Impor mesin dari `references/data-source/data.csv` |
| `php artisan machines:import --purge` | Impor + hapus permanen mesin yang tidak ada di CSV (**riwayat PM ikut terhapus**) |
| `php artisan machines:import --purge --force` | Sama seperti di atas tanpa konfirmasi interaktif |
| `php artisan spare-parts:import` | Impor suku cadang dari `references/data-source/suku_cadang.csv` |
| `php artisan spare-parts:import {path?}` | Impor dari file CSV lain |
| `php artisan spare-parts:import --purge` | Impor + hapus permanen suku cadang yang tidak ada di CSV |
| `php artisan backup:database` | Buat backup database manual; file `.sql` disimpan ke `storage/app/backups` |
| `php artisan backup:database --type=scheduled --prune` | Backup terjadwal + hapus backup melebihi retensi (`backup.keep`, default 30) |
| `php artisan test` | Jalankan test suite |

> **Catatan test suite.** Database test **dipaksa ke sqlite in-memory** oleh
> `tests/TestCase.php`, bukan dibaca dari `.env`. Ini wajib: test bawaan memakai
> `RefreshDatabase` yang menjalankan `migrate:fresh`, dan tanpa isolasi tersebut
> seluruh data produksi (akun, mesin, riwayat PM) terhapus setiap kali test suite
> dijalankan. Variabel `DB_*` di shell maupun `.env` tidak bisa menembusnya.
> Butuh ekstensi PHP `pdo_sqlite` di lingkungan tempat test dijalankan (image
> aplikasi memasang `pdo_mysql`, jadi `pdo_sqlite` perlu ditambahkan bila ingin
> menjalankan test suite dari dalam container).

**Perilaku impor CSV:** bersifat idempoten (aman dijalankan berulang), melewati baris sentinel spreadsheet, dan menentukan `type` dari kata kunci pada nama mesin serta `week_group` dari angka pada Sub-Category.

---

## Struktur Proyek

```
app/
├── Actions/Pm/            SubmitPmRecord, SignPmRecord, RejectPmRecord
├── Console/Commands/      ImportMachinesCommand, ImportSparePartsCommand, BackupDatabaseCommand
├── Enums/                 PmStatus, SignatureStage, Period, Role, MachineType, PmDisplayStatus
├── Exports/               MachineStatusExport, PmReportExport (XLSX)
├── Http/
│   ├── Controllers/       Dashboard, Machine, Utility, PmRecord, Approval, Report, Profile, SparePart, Admin/*
│   │                      Admin/: User, Role, Component (Komponen), ActivityLog, Backup
│   └── Requests/          Form Request untuk validasi
├── Models/                User, Machine (tabel `assets`), Component, PmRecord, PmRecordItem,
│                          PmRecordRevision, PmSignature, ChecklistTemplate, ChecklistTemplateItem,
│                          AuditLog, Backup, SparePart, SparePartMovement
├── Policies/              UserPolicy, MachinePolicy, PmRecordPolicy, RolePolicy
└── Services/              SignatureChain, SignatureStorage, AssetScope, AuditLogService, BackupService,
                           DashboardService, ReportService, PeriodService,
                           ComponentMigrationService, MachineImportService, SparePartImportService

database/
├── migrations/            Skema tabel
└── seeders/               RoleSeeder, MachineSeeder, UserSeeder

references/
├── data-source/           Sumber data CSV + pratinjau desain
│   ├── data.csv               Sumber data mesin (121 baris) untuk `machines:import`
│   ├── suku_cadang.csv        Sumber data suku cadang untuk `spare-parts:import`
│   ├── utility.csv            Sumber data unit utility
│   ├── utility_components.csv Komponen maintenance per unit utility
│   └── PM Preventive – Preview Neumorphism.html   Pratinjau visual design system
├── v.1.1/                 ARSIP dokumen desain awal (superseded)
│   ├── PRD.md
│   ├── Architecture.md
│   └── Agent.md
└── v.1.2/                 Dokumen versi terkini (snapshot)
    ├── PRD.md
    ├── Architecture.md
    ├── Agent.md
    └── Guide.md            Panduan pengguna per peran

resources/
├── js/
│   ├── Components/        Design system Neu* + SignaturePad
│   ├── Layouts/           AuthenticatedLayout (sidebar + navigasi)
│   └── Pages/             Halaman per fitur (Inertia)
└── views/reports/         Template Blade untuk ekspor PDF

routes/web.php             Seluruh definisi route
```

---

## Catatan Deployment

1. **Named volume `app_public` menutupi `/public` di dalam image.**
   `docker-compose.yml` me-mount `app_public:/var/www/html/public`, dan nginx
   membaca volume yang sama secara read-only. Volume hanya diisi dari image saat
   **pertama kali dibuat** — sesudah itu `npm run build` pada image baru tidak
   pernah masuk ke volume, sehingga nginx terus menyajikan aset lama meski
   `docker compose up -d --build` sudah dijalankan.

   Dua hal mencegah masalah ini:

   - Hasil `vite build` ditaruh di `/var/www/html/public-image/build` di dalam
     image, yaitu **di luar** `/public` yang tertutup volume.
   - `docker/app-entrypoint.sh` menyalin folder itu ke
     `/var/www/html/public/build` setiap container start, lalu `chmod a+rX`
     supaya nginx (user berbeda, mount read-only) tetap bisa membacanya.

   Kalau suatu saat aset di layar tidak sesuai dengan kode, cek hasil sync-nya:

   ```bash
   docker logs vip_app | grep 'menyinkronkan public/build'
   docker exec vip_app ls /var/www/html/public/build/assets | head
   ```

2. **`storage/` di-bind mount** dari host, sehingga log, session, `storage/app/public/signatures`, dan file backup database (`storage/app/backups`) tetap ada saat container di-recreate.

3. **Restart policy** `unless-stopped` pada kedua service.

4. **Migrasi database** dijalankan terpisah dari build image (image tidak menjalankan `migrate` saat start). Setelah menarik kode baru, jalankan `php artisan migrate` (menangani rename permission `audit.view` → `activity.view`, penambahan `backup.manage`, dan tabel `backups`).

5. **Backup harian otomatis** butuh Laravel scheduler. Container **tidak** menyertakan cron, jadi tambahkan entri crontab di host, misalnya:

   ```bash
   * * * * * docker exec -u www-data vip_app php artisan schedule:run >> /dev/null 2>&1
   ```

   Tanpa itu, tombol **Buat Backup Sekarang** tetap berfungsi, tetapi jadwal `02:00` tidak berjalan.

---

## Dokumentasi Lain

| Dokumen | Isi |
|---------|-----|
| [PRD.md](PRD.md) | Product Requirements Document — tujuan, persona, kebutuhan fungsional, aturan bisnis, kriteria penerimaan |
| [Architecture.md](Architecture.md) | Arsitektur teknis — stack, layer, skema database, alur, keamanan, deployment |
| [references/v.1.2/Agent.md](references/v.1.2/Agent.md) | Panduan kerja untuk AI coding agent — perintah, konvensi, aturan arsitektur, definition of done |
| [references/v.1.2/Guide.md](references/v.1.2/Guide.md) | **Panduan pengguna per peran** — login, isi checklist PM, rantai tanda tangan, persetujuan, laporan, suku cadang |
| [references/v.1.2/PRD.md](references/v.1.2/PRD.md) | Snapshot PRD versi 1.2 |
| [references/v.1.2/Architecture.md](references/v.1.2/Architecture.md) | Snapshot arsitektur versi 1.2 |

> **Catatan versi dokumen.** `PRD.md` dan `Architecture.md` di root adalah dokumen **aktif**
> (versi 1.2). Folder [`references/v.1.2/`](references/v.1.2/) menyimpan **snapshot** versi
> yang sama (plus `Agent.md` dan `Guide.md`), sedangkan [`references/v.1.1/`](references/v.1.1/) adalah
> **arsip dokumen desain awal** sebelum aplikasi dibangun (masih menyebut 4 status PM,
> rencana PWA/offline, `TemplateSeeder`, dan `pm:import`) — gunakan versi 1.2 sebagai acuan.
> Data sumber CSV untuk impor ada di [`references/data-source/`](references/data-source/).

---

## License

Lisensi internal. Seluruh hak cipta dilindungi.