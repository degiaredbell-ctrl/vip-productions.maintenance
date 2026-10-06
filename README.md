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
- Checklist **di-generate otomatis dari template** sesuai jenis mesin
- Bila record sudah ada, form diisi dari data sebelumnya (bukan template kosong)
- Per item checklist:
  - nilai aktual (`actual`)
  - aksi: Bersihkan / Perbaiki / Lumasi / Ganti
  - kondisi akhir + jumlah part yang diganti
- Catatan umum per record
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
- **Nama teknisi wajib diketik manual** (`technician_name`), bukan diambil dari nama akun login — di lapangan orang yang mengisi checklist tidak selalu orang yang squeez-in-nya. Nama itu juga dipakai sebagai nama pada dokumen bertanda tangan.
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
- **Tanda tangan bisa diselesaikan langsung dari daftar**: setiap record yang
  awaiting punya kolom **Nama Penanda Tangan** (input manual, wajib) di sebelah
  canvas **Signature** di dalam kartunya. Tombol approve mati selama salah satu
  kosong, jadi tidak ada approval tanpa identitas penandatanganannya. Panel ini
  ditambahkan untuk tahap apa pun yang sedang menunggu user tersebut, termasuk
  `rejected` milik teknisi sendiri
- Tombol **Buka Checklist** tetap ada supaya PM bisa dibaca dulu sebelum diteken
- Record yang checklist-nya belum lengkap tidak bisa ditutup dari antrean —
  tahap teknisi hanya naik status setelah semua nilai Aktual terisi

### 6. Daftar Mesin
- CRUD mesin (kode unik, nama, lokasi, kategori, sub-kategori, jenis, template, status aktif)
- **Soft delete + restore**
- **Impor massal dari CSV** (`machines:import`), idempoten
- Pengelompokan minggu dari Sub-Category
- Riwayat PM per mesin

### 7. Template Checklist
- Template per `machine_type`, dengan item (kategori, nama, spesifikasi, urutan)
- Template default ditandai `is_default`
- Hapus template (item ikut terhapus via cascade)

### 8. Manajemen Pengguna (role `admin`)
- **CRUD pengguna penuh**: tambah, edit nama/email/role, hapus
- **Ubah password login pengguna lain** (dengan konfirmasi password)
- Daftar role diambil **dinamis dari tabel `roles`** → role baru langsung bisa dipilih
- Pencarian pengguna di halaman
- **Perlindungan akun sendiri**: admin tidak bisa mengubah role sendiri atau menghapus dirinya (UI nonaktif + guard di backend)
- Audit `user.create` / `user.update` / `user.password` / `user.delete` — **password tidak pernah masuk log**

### 9. Manajemen Role & Permission
- CRUD role custom (5 role sistem tidak bisa dihapus)
- Set permission per role via checkbox

### 10. Laporan
- **Kepatuhan per periode** (total, selesai, sedang approval, %)
- **Top part yang paling sering diganti**
- **Ringkasan aksi** (bersihkan/perbaiki/lumasi/ganti)
- Filter tahun & periode
- **Ekspor XLSX** (Maatwebsite Excel) dan **PDF** (DomPDF)

### 11. Audit Log
- Mencatat aksi penting: `machine.create`, `machine.update`, `machine.delete`, `machine.restore`, `pm.submit`, `pm.sign.{stage}`, `pm.reject`, `user.create`, `user.update`, `user.password`, `user.delete`
- Menyimpan user pelaku, subject, perubahan (JSON), dan IP
- 100 log terbaru per halaman

### 12. UI / UX
- Design system **Neumorphism** (`NeuCard`, `NeuButton`, `NeuInput`, `NeuPill`, `NeuRing`, `NeuToast`, `NeuTrack`, `NeuBars`, `NeuSignaturePad`)
- Sidebar responsif (desktop + mobile)
- Toast notifikasi untuk setiap aksi sukses/gagal
- Tampilan dalam Bahasa Indonesia

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
| Testing | PHPUnit 11, Playwright (verifikasi UI end-to-end) |

---

## Peran dan Permission

Daftar permission originates dari `database/seeders/RoleSeeder.php`.

| Permission | Admin | Manager | Technician | User | Viewer |
|---|:---:|:---:|:---:|:---:|:---:|
| `dashboard.view` | ✅ | ✅ | ✅ | ✅ | ✅ |
| `pm.fill` | ✅ | — | ✅ | — | — |
| `pm.sign` | ✅ | — | ✅ | — | — |
| `pm.acknowledge` | ✅ | — | — | ✅ | — |
| `pm.approve` | ✅ | ✅ | — | — | — |
| `pm.history.view` | ✅ | ✅ | ✅ | ✅ | ✅ |
| `report.view` | ✅ | ✅ | ✅ | ✅ | — |
| `report.export` | ✅ | ✅ | ✅ | — | ✅ |
| `machine.manage` | ✅ | ✅ | — | — | — |
| `template.manage` | ✅ | ✅ | — | — | — |
| `user.manage` | ✅ | — | — | — | — |
| `audit.view` | ✅ | ✅ | — | — | — |

**Catatanatan penting:**
- `pm.sign`, `pm.acknowledge`, `pm.approve` **dipisah per tahap** — bukan satu permission `pm.approve` generik, supaya peran tidak bisa melompat tahap.
- Manager **tidak** bisa mengisi checklist lapangan (tidak punya `pm.fill`).
- Halaman **Role** hanya untuk role `admin`; halaman **Pengguna** untuk siapa pun yang punya `user.manage` (hanya admin saat ini).
- Otorisasi tetap ditegakkan di server lewat **Policy**, bukan hanya dengan menyembunyikan menu.

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
docker compose build app
docker compose up -d --force-recreate app
```

> **Penting:** `public/` di dalam image **tidak langsung dipakai** nginx karena `public` di-*mount* sebagai named volume. Setelah build, asset hasil Vite harus disalin ke volume tersebut (lihat [Catatan Deployment](#catatan-deployment)).

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
| `php artisan machines:import` | Impor mesin dari `references/data.csv` |
| `php artisan machines:import --purge` | Impor + hapus permanen mesin yang tidak ada di CSV (**riwayat PM ikut terhapus**) |
| `php artisan machines:import --purge --force` | Sama seperti di atas tanpa konfirmasi interaktif |
| `php artisan test` | Jalankan test suite |

> **Catatan test suite.** Database test **dipaksa ke sqlite in-memory** oleh
> `tests/TestCase.php`, bukan dibaca dari `.env`. Ini wajib: test bawaan memakai
> `RefreshDatabase` yang menjalankan `migrate:fresh`, dan tanpaIsolation tersebut
> seluruh data produksi (akun, mesin, riwayat PM) terhapus setiap kali test suite
> dijalankan. Variabel `DB_*` di shell maupun `.env` tidak bisa menembusnya.
> Butuh ekstensi PHP `pdo_sqlite` (sudah ada di image Docker).

**Perilaku impor CSV:** bersifat idempoten (aman dijalankan berulang), melewati baris sentinel spreadsheet, dan menentukan `type` dari kata kunci pada nama mesin serta `week_group` dari angka pada Sub-Category.

---

## Struktur Proyek

```
app/
├── Actions/Pm/            SubmitPmRecord, SignPmRecord, RejectPmRecord
├── Console/Commands/      ImportMachinesCommand
├── Enums/                 PmStatus, SignatureStage, Period, Role, MachineType, PmDisplayStatus
├── Exports/               MachineStatusExport, PmReportExport (XLSX)
├── Http/
│   ├── Controllers/       Dashboard, Machine, PmRecord, Approval, Report, Profile, Admin/*
│   └── Requests/          Form Request untuk validasi
├── Models/                User, Machine, PmRecord, PmRecordItem, PmRecordRevision,
│                          PmSignature, ChecklistTemplate, ChecklistTemplateItem, AuditLog
├── Policies/              UserPolicy, MachinePolicy, PmRecordPolicy, RolePolicy
└── Services/              SignatureChain, SignatureStorage, AuditLogService,
                           DashboardService, ReportService, PeriodService, MachineImportService

database/
├── migrations/            Skema tabel
└── seeders/               RoleSeeder, MachineSeeder, UserSeeder

references/                 Material desain awal (ARSIP — lihat catatan di bawah)
├── data.csv               Sumber data mesin (121 baris) untuk `machines:import`
├── PRD.md                 Spesifikasi desain awal — sudah digantikan PRD.md di root
├── Architecture.md        Rancangan arsitektur awal — digantikan Architecture.md di root
├── Agent.md               Catatan untuk AI agent
└── PM Preventive – Preview Neumorphism.html   Pratinjau visual design system

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

1. **Named volume `app_public`** — `docker-compose.yml` me-mount `app_public:/var/www/html/public`. Nginx membaca volume yang sama secara read-only. Asset hasil `vite build` yang ada di dalam image **tidak** terlihat oleh nginx sampai disalin:

   ```bash
   rm -rf /tmp/build && mkdir -p /tmp/build
   cid=$(docker create vip-maintenance-app:latest)
   docker cp "$cid:/var/www/html/public/build/." /tmp/build/
   docker rm "$cid" >/dev/null
   docker exec vip_app sh -c 'rm -rf /var/www/html/public/build'
   docker cp /tmp/build/. vip_app:/var/www/html/public/build
   docker exec vip_app sh -c 'chown -R www-data:www-data /var/www/html/public/build'
   ```

2. **`storage/` di-bind mount** dari host, sehingga log, session, dan `storage/app/public/signatures` tetap ada saat container di-recreate.

3. **Restart policy** `unless-stopped` pada kedua service.

---

## Dokumentasi Lain

| Dokumen | Isi |
|---------|-----|
| [PRD.md](PRD.md) | Product Requirements Document — tujuan, persona, kebutuhan fungsional, aturan bisnis, kriteria penerimaan |
| [Architecture.md](Architecture.md) | Arsitektur teknis — stack, layer, skema database, alur, keamanan, deployment |

> **Catatan:** folder `references/` berisi dokumen **desain awal** sebelum aplikasi dibangun
> (spesifikasi dari prototipe single-file HTML). Dokumen tersebut sudah **tidak sepenuhnya
> sesuai implementasi** — misalnya masih menyebut 4 status PM, rencana PWA/offline,
> `TemplateSeeder`, dan `pm:import`. Gunakan `PRD.md` dan `Architecture.md` di root sebagai
> sumber kebenaran; `references/` disimpan sebagai arsip.`references/data.csv` tetap dipakai
> oleh `php artisan machines:import`.

---

## License

Lisensi internal. Seluruh hak cipta dilindungi.