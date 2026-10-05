# PRD — PM Preventive (Maintenance Mesin)

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

**Versi:** 1.0 · **Status:** Draft · **Pemilik:** Engineering / Maintenance
**Referensi:** `source-page.html` (prototipe single-file React + SharePoint/Power Automate)

---

## 1. Latar Belakang

Prototipe saat ini berjalan sebagai satu file HTML dengan data di `localStorage` dan sinkronisasi ke SharePoint via Power Automate. Keterbatasannya:

- Tidak ada akun/login dan kontrol akses; siapa pun yang memegang file bisa mengubah data.
- Data tersebar di browser tiap perangkat dan SharePoint; sulit diaudit.
- Master mesin dan template checklist tersimpan di `localStorage`, tidak terpusat.
- Tidak ada jejak revisi yang andal, notifikasi, atau persetujuan.

Produk baru memindahkan semuanya ke aplikasi web terpusat (Laravel + MySQL) dengan antarmuka mobile-first.

## 2. Tujuan & Metrik

| Tujuan | Metrik keberhasilan |
|---|---|
| Satu sumber data PM yang terpusat | 100% record PM tersimpan di MySQL, tanpa ketergantungan SharePoint |
| Akses sesuai peran | 0 aksi di luar hak akses role (diuji via policy test) |
| Mudah dipakai teknisi di lapangan | Isi 1 checklist < 3 menit di ponsel; semua target sentuh ≥ 44px |
| Kepatuhan PM terpantau | Dashboard kepatuhan per periode real-time |
| Auditabilitas | Setiap perubahan record tercatat (siapa, kapan, apa) |

**Non-tujuan (v1):** integrasi IoT/sensor, manajemen suku cadang/inventori penuh, work order korektif lengkap.

## 3. Pengguna & Role

| Role | Deskripsi | Hak akses utama |
|---|---|---|
| **admin** | Pengelola sistem | Semua fitur; kelola pengguna & role; kelola mesin/template; hapus/arsip data |
| **manager** | Kepala maintenance/produksi | Lihat semua; kelola mesin & template checklist; setujui/tolak PM; laporan & ekspor |
| **technician** | Pelaksana PM | Isi & revisi checklist PM miliknya; lihat riwayat mesin; lihat dashboard & laporan |
| **user** | Operator produksi / pengguna umum | Lihat dashboard & status mesin; (v1.1) kirim laporan kendala |
| **viewer** | Pemantau (audit, direksi) | Hanya-baca: dashboard, laporan, riwayat |

Matriks izin ringkas (✔ = boleh):

| Fitur | admin | manager | technician | user | viewer |
|---|:-:|:-:|:-:|:-:|:-:|
| Lihat dashboard & status mesin | ✔ | ✔ | ✔ | ✔ | ✔ |
| Isi/revisi checklist PM | ✔ | – | ✔ | – | – |
| Setujui PM | ✔ | ✔ | – | – | – |
| Lihat riwayat & laporan | ✔ | ✔ | ✔ | ✔ | ✔ |
| Ekspor Excel / cetak | ✔ | ✔ | ✔ | – | ✔ |
| CRUD mesin & template | ✔ | ✔ | – | – | – |
| CRUD pengguna & role | ✔ | – | – | – | – |
| Audit log | ✔ | ✔ | – | – | – |

## 4. Lingkup Fitur

### 4.1 Autentikasi & Akun (P0)
- Login email + password, logout, lupa password.
- Satu akun = satu role utama. Akun dapat dinonaktifkan oleh admin.
- Sesi aman (CSRF, throttle login, password hash bcrypt/argon).

### 4.2 Dashboard PM (P0)
- Pilih **periode** (Jan–Feb, Mar–Apr, Mei–Jun, Jul–Ags, Sep–Okt, Nov–Des) dan **tahun**.
- Ring progres kepatuhan + hitungan Selesai / Belum / Kendala.
- Daftar mesin dengan pencarian (kode/nama), filter status, dan pengelompokan **minggu pelaksanaan (1–9)**.
- Periode yang belum tiba **terkunci** (tidak bisa diisi), periode berjalan ditandai.

### 4.3 Checklist PM (P0)
- Item dikelompokkan per kategori (Listrik, Mekanik, Pelumasan, Pneumatik, Hidrolik, Keselamatan, Kalibrasi, Output).
- Per item: **Aktual** (wajib), tindakan (Bersihkan / Perbaiki / Lumasi / Ganti), Kondisi Akhir, Jumlah Part Diganti.
- Template checklist per **tipe mesin**: filling, labeling, inject_blow, tom, mixer, timbangan, coding, vehicle, generic. Mesin dapat memiliki template kustom.
- Validasi: semua Aktual wajib; error ditandai di item terkait.
- Simpan, **revisi** (counter + riwayat), catatan umum, nama pelaksana otomatis dari akun login.
- Status record: `draft` → `submitted` → `approved` / `rejected`.

### 4.4 Riwayat Mesin (P1)
- Timeline PM per mesin (3 tahun terakhir), jumlah revisi, ringkasan tindakan.
- Statistik: total PM, total part diganti, total diperbaiki, **komponen paling sering diganti**.

### 4.5 Laporan (P1)
- Grafik kepatuhan per periode, ringkasan tindakan, kendala terbuka.
- Ekspor **Excel (.xlsx)** dan **cetak/PDF A4** per periode atau per minggu.

### 4.6 Manajemen Master Data (P1)
- Mesin: tambah, ubah, nonaktifkan (soft delete), set minggu & tipe.
- Template checklist: tambah/ubah/hapus/urutkan item, kategori, spesifikasi.
- Impor awal 100+ mesin dari prototipe (seeder dari `DEFAULT_MACHINES` dan `DEFAULT_TPL`).

### 4.7 Manajemen Pengguna (P0, admin)
- Undang/buat pengguna, ubah role, reset password, aktif/nonaktif.

### 4.8 Offline-tolerant (P2)
- PWA: dapat di-install ke layar utama; draf checklist disimpan lokal saat sinyal buruk dan dikirim ulang otomatis (sync queue) saat online.

### 4.9 Audit Log (P1)
- Catat login, perubahan record PM, perubahan master data, perubahan role.

## 5. Persyaratan Non-Fungsional

- **Desain:** tema *minimalist neumorphism light* (permukaan lembut, bayangan timbul/tenggelam). Kontras teks minimum WCAG AA; status tidak hanya bergantung warna (selalu ada label teks).
- **Responsif:** mobile-first (≥ 360px), bottom navigation di ponsel, sidebar di layar ≥ 1024px. Mendukung safe-area iOS.
- **Performa:** halaman dashboard < 2 dtk pada 4G; daftar mesin dipaginasi/di-query efisien (eager loading, indeks).
- **Keamanan:** otorisasi via Policy di server (bukan hanya menyembunyikan UI), validasi di server, rate limiting, backup DB harian.
- **Bahasa:** Bahasa Indonesia; zona waktu `Asia/Jakarta`.
- **Cetak:** layout A4 portrait, tombol dan navigasi disembunyikan saat print.

## 6. User Flow Utama

1. Teknisi login → Dashboard (periode berjalan otomatis terpilih).
2. Cari mesin → buka checklist → isi Aktual + tindakan → **Simpan**.
3. Manager membuka daftar `submitted` → **Setujui** atau **Tolak** dengan catatan.
4. Jika ditolak, teknisi merevisi; revisi tercatat.
5. Manager/viewer membuka Laporan → ekspor Excel/PDF.

## 7. Model Data (ringkas)

`users`, `machines`, `machine_types`, `checklist_templates`, `checklist_template_items`, `pm_records`, `pm_record_items`, `pm_record_revisions`, `audit_logs`. Detail kolom dan relasi ada di `Architecture.md`.

## 8. Rencana Rilis

| Fase | Isi | Estimasi |
|---|---|---|
| **M0 – Fondasi** | Setup Laravel 11 + Inertia React + Tailwind, auth, role/permission, design system neumorphism | 1 minggu |
| **M1 – Inti PM** | Master mesin + template (seeder), dashboard, form checklist, simpan/revisi | 2 minggu |
| **M2 – Kontrol** | Persetujuan, riwayat mesin, laporan, ekspor Excel/PDF | 1–2 minggu |
| **M3 – Admin** | Kelola mesin/template/pengguna, audit log | 1 minggu |
| **M4 – Polishing** | PWA + offline queue, uji device, UAT, go-live | 1 minggu |

## 9. Risiko & Asumsi

- Migrasi data historis dari SharePoint memerlukan ekspor/format yang konsisten → disediakan *import command* (CSV/JSON).
- Banyak teknisi memakai ponsel Android entry-level → hindari bayangan/efek berat; uji performa.
- Neumorphism rawan kontras rendah → gunakan token warna teks yang sudah diuji, hindari informasi hanya lewat bayangan.
- Asumsi: satu pabrik/satu tenant; aplikasi dijalankan di jaringan internal atau VPS dengan HTTPS.

## 10. Pertanyaan Terbuka

1. Apakah perlu alur persetujuan (approval) wajib, atau opsional per mesin?
2. Apakah role `user` perlu membuat laporan kendala di v1?
3. Apakah satu pengguna boleh memiliki lebih dari satu role?
4. Siapa yang menyediakan data historis SharePoint untuk impor?
5. Apakah dibutuhkan notifikasi (email/WhatsApp) untuk PM yang terlambat?
