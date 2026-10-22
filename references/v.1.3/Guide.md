# Guide.md — Panduan Pengguna PM Preventive

Panduan **pemakaian aplikasi sehari-hari** untuk seluruh pengguna **PM
Preventive**, ditulis per peran (role). Dokumen ini menjawab "apa yang saya
lakukan di aplikasi ini?" — bukan "bagaimana aplikasi ini dibangun".

**Versi dokumen:** 1.3 · Zona waktu aplikasi: **Asia/Jakarta (WIB)** · Bahasa UI:
**Bahasa Indonesia**.

> Dokumen teknis ada di [Architecture.md](Architecture.md); kebutuhan produk &
> aturan bisnis di [PRD.md](PRD.md); instruksi kerja untuk AI coding agent di
> [Agent.md](Agent.md).

---

## Daftar Isi

1. [Tentang Panduan Ini](#1-tentang-panduan-ini)
2. [Apa Itu PM Preventive](#2-apa-itu-pm-preventive)
3. [Mulai Menggunakan Aplikasi](#3-mulai-menggunakan-aplikasi)
4. [Peran & Hak Akses](#4-peran--hak-akses)
5. [Fitur Umum (Semua Peran)](#5-fitur-umum-semua-peran)
6. [Alur Rantai Tanda Tangan 3 Tahap](#6-alur-rantai-tanda-tangan-3-tahap)
7. [Panduan per Peran](#7-panduan-per-peran)
8. [Notifikasi & Kotak Masuk](#8-notifikasi--kotak-masuk)
9. [Pengaturan Admin](#9-pengaturan-admin)
10. [Aturan Penting yang Perlu Diketahui](#10-aturan-penting-yang-perlu-diketahui)
11. [Tips & Penanganan Masalah (FAQ)](#11-tips--penanganan-masalah-faq)
12. [Glosarium](#12-glosarium)
13. [Dokumen Terkait](#13-dokumen-terkait)

---

## 1. Tentang Panduan Ini

- **Siapa yang perlu membaca:** semua pengguna yang punya akun — Admin, Manager,
  Teknisi, User PIC, Viewer, maupun teknisi ber-scope (`tech-machines`,
  `tech-utilities`, `tech-all`).
- **Cara membaca:** mulailah dari bab [3](#3-mulai-menggunakan-aplikasi) dan
  [4](#4-peran--hak-akses), lalu lompat ke bagian peran Anda di
  [bab 7](#7-panduan-per-peran). Bab [5](#5-fitur-umum-semua-peran) menjelaskan
  fitur yang dipakai bersama, bab [8](#8-notifikasi--kotak-masuk) menjelaskan
  notifikasi & Kotak Masuk, dan bab [9](#9-pengaturan-admin) khusus untuk Admin.
- **Konvensi teks:** **Tebal** = nama menu, tombol, kolom, atau status yang
  terlihat di layar. 🔵 🟠 🔴 🟢 = warna dot status periode.
- **Penting:** yang terlihat di layar tidak selalu sama dengan yang boleh
  dilakukan. Menu yang tidak relevan memang disembunyikan, dan setiap aksi tetap
  diperiksa di server. Kalau Anda tidak berhak, aksi akan ditolak.

> **What's new di v1.3:** notifikasi otomatis dua saluran (In-App + Email),
> **Kotak Masuk** di `/notifications`, **Settings → Notifications**,
> integrasi email Microsoft 365 dengan login OTP, master Tipe Mesin & Lokasi,
> halaman error beranimasi, impor Template dari CSV, activity log dengan
> filter IP, dan backup database terjadwal.

---

## 2. Apa Itu PM Preventive

**PM Preventive** adalah aplikasi untuk mengelola **Preventive Maintenance (PM)**
mesin produksi secara digital: daftar aset, pengisian checklist lapangan,
**rantai tanda tangan 3 tahap**, **notifikasi dua saluran (In-App + Email)**,
laporan, dan ekspor.

Istilah inti:

| Istilah | Arti |
|---------|------|
| **PM** | Preventive Maintenance — perawatan berkala agar mesin tidak rusak mendadak |
| **Unit / Aset** | Satu **Mesin** atau satu **Utility** yang dirawat (tabel `assets`) |
| **Periode** | Rentang 2 bulanan; ada 6 periode per tahun (lihat tabel di bawah) |
| **Checklist** | Daftar item/komponen yang diperiksa saat PM, berisi nilai **Aktual** dan aksi |
| **Record PM** | Satu lembar PM untuk satu unit pada satu periode & tahun |
| **Tanda tangan** | Persetujuan bertingkat yang harus dilalui berurutan |
| **Nomor dokumen** | Kode unik PM, mis. `PM/2026/SEP-OKT/00042` |
| **PIC** | *Person in Charge* — User yang bertanggung jawab atas sebuah unit |
| **Atasan** | Pemegang persetujuan tahap 3 (role `manager` / `pm.approve`) |
| **Kotak Masuk** | Halaman `/notifications` — tempat semua notifikasi In-App Anda |
| **Reminder** | Notifikasi pengingat PM untuk Minggu ke-2 setiap periode |
| **Keterlambatan** | Notifikasi unit yang PM-nya belum selesai melewati batas jadwal |
| **Digest** | Satu notifikasi berisi gabungan beberapa unit (bukan satu per unit) |

Enam periode PM:

| Periode | Bulan |
|---------|-------|
| Jan–Feb | Januari–Februari |
| Mar–Apr | Maret–April |
| Mei–Jun | Mei–Juni |
| Jul–Ags | Juli–Agustus |
| Sep–Okt | September–Oktober |
| Nov–Des | November–Desember |

Status satu record PM (yang tampil sebagai label warna):

| Status | Label di layar | Artinya | Menunggu siapa |
|--------|----------------|---------|----------------|
| `draft` | Belum Ditandatangani | Baru dibuat / belum tuntas | Teknisi |
| `submitted` | On Progress Approval by User PIC | Sudah diteken teknisi | User PIC |
| `pic_approved` | On Progress Approval by Atasan | Sudah disetujui User PIC | Atasan |
| `approved` | Selesai | Rantai tanda tangan lengkap | — (final) |
| `rejected` | Perlu Revisi | Ditolak pihak penyetuju | Teknisi |

---

## 3. Mulai Menggunakan Aplikasi

### 3.1 Login

1. Buka alamat aplikasi (default: **http://localhost:8082**).
2. Masukkan **Email** dan **Password** yang diberikan Admin.
3. Centang **Ingat saya** bila ingin tetap masuk di perangkat ini.
4. Klik **Masuk**. Anda akan diarahkan ke **Beranda**.

Bila Admin mengaktifkan **Verifikasi login (kode email)**, setelah password
benar Anda diminta **kode 6 digit** yang dikirim ke email Anda:

- Kode berlaku **10 menit**.
- Maksimal **5 kali** salah; setelah itu harus meminta kode baru.
- Kirim ulang Minimal **60 detik** setelah permintaan terakhir.
- Centang **"Ingat perangkat ini"** agar tidak diminta kode lagi di perangkat
  tersebut (default 30 hari). Opsi ini otomatis batal bila password Anda diubah.
- Akun **tanpa email valid** tidak pernah diminta kode OTP.

> Akun dibuatkan oleh Admin lewat menu **Pengguna**. Bila belum punya akun,
> hubungi Admin. Jangan membagikan password kepada orang lain.

### 3.2 Lupa password

1. Di halaman login klik **Lupa password?**.
2. Masukkan Email Anda, lalu klik **Kirim Tautan Reset Password**.
3. Buka email, ikuti tautan, lalu tetapkan password baru. Tautan berlaku
   **60 menit**.

Pesan yang muncul selalu sama, baik email Anda terdaftar atau tidak — ini
memastikan tidak ada orang yang bisa menebak akun yang terdaftar.

Bila tautan tidak sampai, hubungi Admin untuk direset lewat menu **Pengguna**
(Admin dapat mengganti password login Anda).

### 3.3 Mengenal navigasi

- **Di desktop (layar lebar):** menu utama ada di **sidebar kiri**.
- **Di ponsel / tablet:** menu berada di **bilah bawah** yang bisa digeser
  horizontal.
- Menu **Persetujuan** kadang menampilkan **badge angka** — itu jumlah record
  yang benar-benar menunggu tindakan Anda. Angka badge sama dengan isi daftar.
- **Lonceng 🔔 di header** menampilkan jumlah notifikasi yang belum dibaca.
  Klik lonceng untuk melihat **8 notifikasi terbaru**, atau klik **"Lihat semua
  notifikasi"** untuk membuka **Kotak Masuk** lengkap di `/notifications`.
- Klik nama Anda di kanan atas → **Profile** atau **Keluar**.

Menu yang Anda lihat berbeda-beda sesuai peran. Rinciannya ada di
[bab 4](#4-peran--hak-akses).

### 3.4 Profil & tanda tangan tersimpan

Buka **Profile** untuk:

- Mengubah **nama** dan **email**.
- Mengganti **password** sendiri (memasukkan password lama sebagai bukti).
- Mengatur **PIN login** (6 digit) sebagai alternatif cepat masuk.
- Menyimpan **Tanda Tangan Tersimpan**: gambar tanda tangan sekali, lalu pakai
  ulang saat menyetujui tanpa menggambar lagi.
- Menghapus akun sendiri (dengan konfirmasi).

> **Tanda tangan tersimpan bersifat pribadi.** Menyimpannya tidak mengubah
> tanda tangan pada record yang sudah ditandatangani. Menggambar ulang tanda
> tangan saat menyetujui juga tetap bisa, dan akan menimpa tanda tangan Anda
> untuk tahap itu.

### 3.5 Kalau halaman error muncul

Semua halaman error (403/404/405/419/429/500/503) memakai tampilan yang sama:

- **Ilustrasi beranimasi** sesuai jenis kesalahan.
- Tombol **✕** di kanan atas, atau tekan **Esc**, untuk menutup.
- Menutup mengembalikan Anda ke **halaman sebelumnya**; bila halaman error
  dibuka langsung, Anda diarahkan ke **Beranda**.
- Tombol tambahan: **Kembali**, **Ke Beranda**, atau **Muat Ulang** (untuk
  419/500/503).

Bila muncul **419 (sesi kedaluwarsa)** saat menekan tombol di dalam aplikasi,
Anda otomatis dikembalikan ke halaman sebelumnya dengan pesan, bukan halaman
error. Silakan coba aksi Anda sekali lagi.

---

## 4. Peran & Hak Akses

Aplikasi mengenal **8 peran**. Lima peran umum, tiga peran teknisi ber-scope.

| Peran | Sebutan di lapangan | Inti tugas |
|-------|---------------------|-----------|
| `admin` | Administrator | Mengelola seluruh data, akun, role, komponen, PM Record, pengaturan notifikasi, integrasi email, dan membaca activity log |
| `manager` | Atasan / Supervisor | Menyetujui **tahap 3**, mengelola mesin & komponen, memantau laporan, **menerima laporan mingguan/bulanan** |
| `technician` | Teknisi / Pemeriksa | Mengisi checklist & menandatangani **tahap 1** untuk Mesin dan Utility, **menerima reminder & keterlambatan** |
| `user` | User PIC | Menyetujui sebagai diketahui pada **tahap 2**, **menerima pengingat unitnya** |
| `viewer` | Auditor / manajemen | Hanya melihat (Beranda, riwayat, laporan + ekspor) |
| `tech-machines` | Teknisi Mesin | Sama seperti Teknisi, **hanya** untuk unit Mesin |
| `tech-utilities` | Teknisi Utility | Sama seperti Teknisi, **hanya** untuk unit Utility |
| `tech-all` | Teknisi penuh | Teknisi Mesin + Utility, plus kelola (CRUD) mesin, utility, komponen, dan suku cadang |

### 4.1 Menu yang terlihat per peran

| Menu | admin | manager | technician | user | viewer | tech-machines | tech-utilities | tech-all |
|------|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| Beranda | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Persetujuan | ✅ | ✅ | ✅ | ✅ | — | ✅ | ✅ | ✅ |
| Laporan | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Form Maintenance | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Mesin | ✅ | ✅ | — | — | — | ✅ | — | ✅ |
| Utility | ✅ | ✅ | ✅ | ✅ | ✅ | — | ✅ | ✅ |
| Suku Cadang | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| PM Records | ✅ | — | — | — | — | — | — | — |
| Pengguna | ✅ | — | — | — | — | — | — | — |
| Role | ✅ | — | — | — | — | — | — | — |
| Komponen / Parts | ✅ | ✅ | — | — | — | — | — | ✅ |
| Activity Log | ✅ | ✅ | — | — | — | — | — | — |
| Settings | ✅ | — | — | — | — | — | — | — |

Catatan:

- **Mesin** disembunyikan untuk peran umum (Teknisi/User/Viewer) — mereka masuk
  ke form PM lewat **Beranda** atau **Form Maintenance**.
- **tech-machines** hanya melihat menu Mesin; **tech-utilities** hanya melihat
  menu Utility. Kartu di luar scope-nya tampil **abu-abu** dan tidak bisa
  dibuka, termasuk lewat URL langsung.
- Semua peran dapat **melihat** **Suku Cadang**, tetapi hanya **Admin** dan
  **tech-all** yang bisa mengelola (tambah/ubah/hapus/transaksi/impor).
- Menu **Persetujuan** selalu muncul untuk yang punya wewenang tanda tangan,
  walau daftar sedang kosong.
- **Kotak Masuk** (`/notifications`) **tidak ada di menu mana pun** — ia
  berdiri sendiri dan diakses lewat **lonceng 🔔** di header. Semua user
  login bisa membukanya.
- **Settings** (termasuk **Notifications** & **Integrate**) hanya untuk
  **Admin**.

---

## 5. Fitur Umum (Semua Peran)

### 5.1 Beranda

Beranda menampilkan **kartu status PM setiap unit** untuk **periode & tahun**
yang dipilih, plus ringkasan angka (total, selesai, sedang approval, perlu
revisi).

- Tab **Mesin** dan **Utility** memisahkan kedua jenis aset. Unit tanpa scope
  Anda (mis. menu Utility bagi `tech-machines`) tidak ditampilkan.
- Angka ringkasan dihitung dari **seluruh unit aktif** pada periode terpilih —
  **tidak** ikut berubah saat Anda memakai filter. Jadi angka itu bisa
  dipercaya sebagai indikator.
- Klik kartu unit untuk masuk ke **Form PM** unit tersebut.

### 5.2 Filter

Filter di Beranda & Form Maintenance:

| Filter | Fungsi |
|--------|--------|
| **Periode** | Memilih 1 dari 6 periode 2-bulanan |
| **Tahun** | Memilih tahun |
| **Area / Sub-kategori** | Menyaring berdasarkan sub-kategori (mis. `C.7`) |
| **Status** | Selesai / Sedang Approval / Belum / Perlu Revisi |
| **Pencarian** | Mencari kode, nama, atau lokasi unit |

Filter yang sedang aktif **ikut terbawa** saat Anda membuka Form PM dan saat
menekan tombol kembali, sehingga Anda tidak perlu mengatur ulang.

### 5.3 Dot notifikasi 4 warna

Setiap periode bisa punya **dot** penanda tunggakan:

| Warna | Arti |
|-------|------|
| 🔵 **Biru** | Periode berjalan, masih ada unit belum selesai |
| 🟠 **Oranye** | Periode berjalan sudah beres, tetapi periode sebelumnya masih ada tunggakan |
| 🔴 **Merah** | Ada tunggakan dari periode yang lebih lama |
| 🟢 **Hijau** | Tidak ada tunggakan |

Pada **tahun selain tahun berjalan** tidak ada konsep "periode berjalan": ada
tunggakan = merah, beres = hijau. Periode **di masa depan** tampil **terkunci**
(tidak bisa diisi).

### 5.4 Form Maintenance

Halaman perantara **Form Maintenance** menampilkan kartu pilihan: **Mesin**,
**Utility**, dan (bagi yang berhak) **Komponen**.

- Kartu yang **abu-abu** berarti di luar hak akses Anda dan tidak bisa dibuka.
- Dari **Mesin/Utility** Anda masuk ke daftar unit + status PM per periode,
  lalu memilih unit untuk membuka **Form PM**.

### 5.5 Mengisi checklist PM (peran pengisi)

Ini langkah utama bagi Teknisi (`technician`, `tech-machines`,
`tech-utilities`, `tech-all`) — dan cadangan oleh Admin.

1. Buka **Beranda** atau **Form Maintenance → Mesin/Utility**, pilih periode
   dan tahun, lalu klik unit yang akan dikerjakan.
2. Isi setiap item checklist:
   - **Aktual** — kondisi/hasil pengukuran saat ini (wajib).
   - **Aksi** — centang **Bersihkan**, **Perbaiki**, **Lumasi**, dan/atau
     **Ganti** sesuai pekerjaan.
   - **Kondisi akhir** dan **jumlah part** bila ada penggantian.
   - **Detail bagian yang diganti** — **wajib** diisi untuk setiap item yang
     aksinya **Ganti**.
3. Isi **Catatan Umum** bila perlu.
4. Pilih **User PIC yang akan menyetujui** (tahap 2).
5. Isi **Nama Teknisi** dan gambar **Tanda Tangan Teknisi/Pemeriksa** pada
   canvas.
6. Klik simpan. Sistem menyimpan checklist **sekaligus** menandatangani tahap 1,
   sehingga status langsung naik ke **`submitted`**.

Hal yang perlu diingat:

- Bila record sudah pernah dibuat, form menampilkan **data sebelumnya** (bukan
  template kosong).
- Checklist hanya bisa diubah saat status **`draft`** atau **`rejected`**.
  Setelah diteken, isinya **terkunci**.
- Periode **terkunci / masa depan** tidak bisa diisi.
- Satu unit hanya punya **satu** record PM per (unit, tahun, periode).
- Tombol simpan **mati** selama nama atau tanda tangan masih kosong.

### 5.6 Riwayat PM

Buka riwayat lewat tombol **Riwayat** pada daftar **Mesin** atau **Utility**,
atau lewat panel **Riwayat dokumen** di dalam Form PM. Riwayat menampilkan
daftar record PM unit tersebut beserta status dan tanda tangannya. Riwayat
**tetap ada** meski akun pelakunya dihapus (nama & peran disimpan sebagai
snapshot).

### 5.7 Laporan

**Laporan PM** menampilkan:

- **Kepatuhan PM per periode** — total unit, selesai, sedang approval, dan
  persentase.
- **Top part** — 5 part yang paling sering diganti.
- **Ringkasan aksi** — hitungan Bersihkan / Perbaiki / Lumasi / Ganti.

Filter **tahun** dan **periode** tersedia. Peran dengan `report.export`
(Admin, Manager, Teknisi, Viewer, dan seluruh `tech-*`) dapat **Ekspor Excel**
(XLSX) dan **PDF**; User PIC dapat melihat laporan **tanpa** ekspor.

### 5.8 Suku Cadang

Master **suku cadang global** (tidak terikat unit Mesin/Utility): kode,
kategori, nama, merek, tipe, deskripsi, satuan, stok, stok awal/minimum,
supplier, kebutuhan untuk, dan lokasi.

- **Semua peran** dapat melihat daftar dan mencari/filter berdasarkan kategori,
  lokasi, serta status **stok menipis / habis**.
- **Admin & tech-all** (pemegang `sparepart.manage`) dapat:
  - menambah, mengubah, menghapus (soft delete), termasuk **hapus massal** via
    checkbox;
  - mencatat **transaksi barang masuk (+)** dan **barang keluar (−)** — stok
    terkini menyesuaikan otomatis; barang keluar melebihi stok ditolak;
  - melihat **riwayat transaksi** per suku cadang;
  - **mengunggah CSV** dan **mengunduh template CSV**; impor bersifat idempoten
    (dicocokkan berdasarkan kode, baris sama diperbarui bukan digandakan).

### 5.9 Halaman Persetujuan

Halaman **Persetujuan** menampilkan record yang **menunggu tindakan Anda**
sesuai wewenang: tabel berisi kolom **Nama Penanda Tangan** (diisi manual) dan
**Tanda Tangan** (canvas), plus tombol **Buka Checklist** untuk membaca isi PM
sebelum menandatangani.

- Daftar & badge sidebar memakai **query yang sama**, jadi jumlahnya tidak
  pernah berbeda.
- Tahap yang menunggu Anda ditampilkan sebagai pill di bagian atas.
- Tombol **Setujui & Tanda tangan** / **Tanda tangan & Setujui** **mati**
  selama nama atau tanda tangan kosong — tidak ada persetujuan tanpa identitas
  penandatangan.
- Anda juga bisa menyelesaikan tanda tangan dari **dalam Form PM**.

---

## 6. Alur Rantai Tanda Tangan 3 Tahap

Rantai persetujuan bersifat **tetap, berurutan, dan tidak bisa dilompati**.
Tahap aktif ditentukan oleh **status record**, bukan oleh pilihan pengguna.

```
  Isi checklist + nama + tanda tangan
                │
                ▼
  draft ──sign(Teknisi)──▶ submitted ──sign(User PIC)──▶ pic_approved ──sign(Atasan)──▶ approved (final)
    ▲                                                                                      │
    └───────────────────── reject (User PIC / Atasan) ────────────────────────────────────┘
                                │
                            rejected ── isi + sign(Teknisi) ──▶ submitted
```

| Tahap | Nama | Penandatangan | Wewenang | Status setelah |
|-------|------|---------------|----------|----------------|
| 1 | Tanda Tangan Teknisi/Pemeriksa | Teknisi | `pm.sign` | `submitted` |
| 2 | Persetujuan User PIC | User PIC | `pm.acknowledge` | `pic_approved` |
| 3 | Persetujuan Atasan | Manager | `pm.approve` | `approved` |

Aturan yang berlaku:

1. **Tidak bisa melompat tahap.** Tidak ada tombol "langsung setujui di tahap
   3" — urutan dijaga server.
2. **Anti konflik kepentingan.** Penyetuju tahap 2 & 3 **tidak boleh** orang
   yang sama dengan pengisi checklist.
3. **Admin** boleh menandatangani semua tahap (dianggap tidak melakukan
   pekerjaan lapangan) dan menjadi cadangan bila tidak ada pihak lain.
4. **Penolakan** hanya tersedia di tahap 2 & 3, **wajib** menyertakan alasan,
   dan mengembalikan record ke teknisi (`rejected`) untuk revisi.
5. **Final.** Record `approved` tidak bisa diubah maupun ditandatangani ulang.
6. **Nama & peran penanda tangan** disimpan sebagai snapshot, sehingga tetap
   terbaca meski akunnya dihapus.

---

## 7. Panduan per Peran

### 7.1 Admin

Admin memegang **seluruh** menu dan wewenang.

**Tugas rutin:**

1. **Kelola pengguna — menu Pengguna**
   - Tambah pengguna (nama, email, password terkonfirmasi, role).
   - Ubah nama/email/role; daftar role diambil dari halaman Role sehingga role
     baru langsung bisa dipilih.
   - **Ubah password login** pengguna lain (dengan konfirmasi password Anda).
   - Hapus pengguna.
   - ⚠️ Admin **tidak bisa** mengubah role sendiri atau menghapus akunnya
     sendiri — tombolnya nonaktif dan server tetap menolak.
2. **Kelola role & permission — menu Role**
   - Tambah role custom dan atur permission-nya lewat checkbox.
   - Lima role sistem (`admin`, `manager`, `technician`, `user`, `viewer`)
     **tidak dapat dihapus**.
3. **Kelola mesin & utility** lewat **Form Maintenance → Mesin/Utility** atau
   menu **Mesin/Utility**: tambah, ubah, hapus (soft delete), dan pulihkan
   (restore). Kode unit harus **unik**.
4. **Kelola master Komponen — menu Komponen / Parts**
   - Tambah komponen (nama, kategori, spesifikasi, urutan).
   - Tugaskan komponen ke satu atau banyak unit (Mesin & Utility) dengan urutan
     per unit. Komponen yang ditugaskan inilah yang menjadi item checklist PM.
5. **Kelola PM Record — menu PM Records**
   - Lihat **seluruh** record PM lintas mesin/status/periode/tahun.
   - Filter & cari (nama teknisi, catatan, kode/nama/lokasi mesin).
   - **Hapus permanen** record beserta item, tanda tangan, revisi, dan audit
     terkait. Tidak ada undo.
6. **Baca Activity Log — menu Activity Log**
   - Daftar aktivitas dengan **filter** (jenis aksi, pelaku, rentang tanggal) dan
     **pencarian**; panel detail menampilkan perubahan (sebelum→sesudah), IP
     berlabel, dan perangkat yang dipakai.
7. **Kelola Suku Cadang** (lihat [5.8](#58-suku-cadang)).
8. **Atur Notifikasi — Settings → Notifications** (lihat
   [bab 9](#9-pengaturan-admin)):
   - Matriks 4 bagian: nyalakan/matikan kanal Apps & Email, atur jadwal, dan
     penerima baris 1–2.
   - Tetapkan **PIC** setiap unit (bisa massal).
   - **Pratinjau penerima** tanpa mengirim, dan **kirim manual** untuk uji coba.
   - Pantau **Riwayat Pengiriman** bila ada email yang gagal.
9. **Hubungkan email — Settings → Integrate**: Microsoft 365 (Graph) atau SMTP,
   fitur **Kirim Email Uji**, **login OTP**, dan jadwal laporan otomatis.
10. **Kelola Master Data**: Tipe Mesin & Lokasi/Site.
11. **Backup & Restore** database (manual + otomatis 02:00 WIB).
12. **Cadangan tanda tangan**: bila pihak berwenang tidak ada, Admin dapat
    menandatangani tahap mana pun.

### 7.2 Manager / Atasan

Fokus: **persetujuan tahap 3** dan pengawasan.

**Tugas rutin:**

1. **Menyetujui PM di tahap akhir — menu Persetujuan**
   - Buka **Persetujuan** (badge menunjukkan jumlah antrean).
   - Pilih tahun/periode, lalu baca dengan **Buka Checklist**.
   - Isi **Nama Penanda Tangan**, gambar **tanda tangan** (atau pakai tanda
     tangan tersimpan dari Profile), lalu klik **Tanda tangan & Setujui**.
   - Untuk menolak: tulis **alasan** lalu klik **Tolak & Kembalikan ke
     Teknisi**.
2. **Kelola mesin & komponen** — Manager dapat mengelola (CRUD) Mesin dan
   master Komponen/Komponen. Hal ini mendukung peran pengawasan (mis.
   memperbaiki data unit atau komponen).
3. **Pantau Beranda & Laporan** — filter periode/tahun/area/status, baca
   kepatuhan, top part, ringkasan aksi.
4. **Ekspor** XLSX/PDF dari Beranda & Laporan.
5. **Baca Activity Log**.
6. **Terima laporan otomatis**: **laporan mingguan** (Senin pagi) dan
   **laporan bulanan** (tanggal 1) dikirim lewat **email** berisi ringkasan
   jumlah, persentase pencapaian, dan daftar unit terlambat (bila jadwalnya
   diaktifkan Admin).

> Manager **tidak mengisi checklist lapangan** (tanpa `pm.fill`). Pekerjaan
> mengisi & menandatangani tahap 1 adalah milik Teknisi.

### 7.3 Technician / Pemeriksa

Fokus: **melakukan & mendokumentasikan pemeriksaan**, lalu menandatangani
tahap 1.

**Tugas rutin:**

1. **Isi checklist PM** (langkah lengkap di
   [5.5](#55-mengisi-checklist-pm-peran-pengisi)) untuk Mesin dan Utility.
2. **Tandatangani tahap 1** menyatu dengan pengisian: satu submit untuk isi
   checklist + nama + tanda tangan → status `submitted`.
3. **Perbaiki bila ditolak** — record `rejected` kembali ke Anda; buka lagi,
   perbaiki item, lalu isi + tanda tangan ulang. Isi checklist yang lama
   disimpan sebagai **revisi** (snapshot).
4. **Lihat riwayat & laporan**, serta **ekspor** laporan.
5. **Lihat Suku Cadang** (tidak mengelola pada peran ini).
6. **Baca notifikasi** di **Kotak Masuk** (lihat [bab 8](#8-notifikasi--kotak-masuk)):
   - **Reminder PM** di Minggu ke-2 dan **pengingat keterlambatan** (07:00 &
     14:00) — hanya untuk unit sesuai scope Anda.
   - **"PM selesai"** saat Atasan menyetujui, dan **"Perlu revisi"** beserta
     alasan & peminta revisi.

### 7.4 User PIC

Fokus: **menyetujui sebagai diketahui** pada tahap 2.

**Tugas rutin:**

1. **Menyetujui — menu Persetujuan**
   - Buka record yang menunggu tahap **Persetujuan User PIC**.
   - Baca isi checklist lewat **Buka Checklist**.
   - Isi **Nama Penanda Tangan**, gambar **tanda tangan** (atau pakai yang
     tersimpan), lalu klik **Setujui & Tanda tangan**.
   - Bila ada masalah: tulis **alasan** lalu klik **Tolak & Kembalikan ke
     Teknisi**.
2. **Pantau Beranda** (status unit pada periode terpilih).
3. **Lihat Laporan** — User PIC **tidak** punya tombol ekspor.
4. **Lihat Suku Cadang**.
5. **Riwayat** unit untuk konteks pengambilan keputusan.
6. **Baca notifikasi** di **Kotak Masuk**: **Reminder PM** dan **"Menunggu
   persetujuan"** untuk unit yang menjadi tanggung jawab Anda sebagai PIC.

> Anda **tidak boleh** menyetujui checklist yang Anda sendiri isi. Bila Anda
> kebetulan mengisi, tanda tangan tahap 2 akan ditolak.

### 7.5 Viewer

Fokus: **hanya melihat** (audit / manajemen).

**Yang bisa dilakukan:**

- **Beranda** dan **Filter** untuk memantau status PM.
- **Riwayat** unit.
- **Laporan** + **Ekspor** XLSX & PDF.
- **Lihat Suku Cadang**.

**Yang tidak bisa:** mengisi/tanda tangan PM, menyetujui, maupun mengelola data
(mesin, komponen, pengguna, suku cadang). Menu yang relevan memang
disembunyikan dan akses server tetap diblokir.

### 7.6 tech-machines (Teknisi Mesin)

Sama seperti **Teknisi**, tetapi **hanya untuk unit Mesin**:

- **Hanya** menu terkait Mesin yang terbuka (Mesin, Form Maintenance, Suku
  Cadang, Laporan, Persetujuan, Beranda).
- Menu **Utility** disembunyikan; kartu **Utility** di Form Maintenance tampil
  **abu-abu** dan URL langsungnya diblokir.
- Beranda hanya menampilkan tab **Mesin**.
- Tetap mengisi checklist + tanda tangan tahap 1, melihat riwayat & laporan, dan
  melihat suku cadang.

### 7.7 tech-utilities (Teknisi Utility)

Kebalikan dari `tech-machines`: **hanya untuk unit Utility**.

- Menu **Utility** terbuka; menu **Mesin** disembunyikan.
- Beranda hanya menampilkan tab **Utility**.
- Mengisi checklist + tanda tangan tahap 1 untuk unit utility, melihat riwayat &
  laporan, dan melihat suku cadang.
- Kartu **Mesin** di Form Maintenance tampil abu-abu; URL langsung diblokir.

### 7.8 tech-all (Teknisi Penuh)

Gabungan **Mesin + Utility**, dengan tambahan wewenang kelola.

- Mengisi checklist & menandatangani tahap 1 untuk **Mesin dan Utility**.
- **Mengelola (CRUD)** Mesin, Utility, master **Komponen**, dan **Suku Cadang**
  (termasuk transaksi stok & impor CSV).
- Beranda menampilkan kedua tab; Form Maintenance lengkap termasuk kartu
  **Komponen**.
- **Tidak** memiliki menu **Activity Log**, **PM Records**, **Pengguna**,
  **Role**, maupun **Settings** — itu milik Admin.

---

## 8. Notifikasi & Kotak Masuk

Aplikasi mengirim notifikasi ke **dua saluran**:

| Saluran | Arti | Where to look |
|---------|------|---------------|
| **In-App (Apps)** | Masuk ke kotak masuk aplikasi | **Kotak Masuk** (`/notifications`) + lonceng 🔔 di header |
| **Email** | Dikirim ke alamat email Anda | Email masuk dari **PM Preventive** |

Keduanya **bisa dimatikan** oleh Admin (lihat
[9.1](#91-settings--notifications)). Mematikan Email **tidak** mematikan
notifikasi In-App, dan sebaliknya.

### 8.1 Lonceng di header

- Badge menunjukkan **jumlah notifikasi yang belum dibaca**.
- Klik lonceng → dropdown berisi **8 notifikasi terbaru**.
- Klik **"Lihat semua notifikasi"** → membuka **Kotak Masuk** lengkap.
- Lonceng menyegarkan sendiri secara berkala dan saat Anda kembali ke tab
  browser.
- Dari dropdown Anda bisa langsung **tandai sudah dibaca**.

### 8.2 Kotak Masuk (`/notifications`)

Halaman mandiri yang **tidak ada di dalam menu Settings**. Membuka lewat
lonceng di header atau alamat `/notifications`.

**Filter** yang tersedia:

| Filter | Isi |
|--------|-----|
| **Semua** | Semua notifikasi Anda |
| **Belum dibaca** | Yang belum Anda tandai terbaca |
| **Jadwal PM** | Reminder PM (minggu ke-2) |
| **Terlambat** | Unit yang PM-nya melewati batas |
| **Approval** | Notifikasi persetujuan (3.1–3.4) |

**Aksi yang tersedia:**

- Menandai satu notifikasi **terbaca**.
- **Tandai semua terbaca**.
- **Menghapus** satu notifikasi.
- **Menghapus semua** notifikasi yang sudah dibaca.

**Isi tiap jenis notifikasi:**

| Jenis | Yang ditampilkan |
|-------|-------------------|
| Reminder / Terlambat | Periode, minggu, batas waktu, dan **daftar unit** yang bisa dibuka (perangkat, lokasi, minggu, batas, hari terlambat) |
| Approval 3.1–3.3 | Nomor dokumen, nama perangkat, **pengirim (+perannya)**, waktu (WIB), alasan bila ada, dan tautan ke dokumen |
| Approval 3.4 (Perlu revisi) | Semua di atas **ditambah alasan revisi dan siapa yang meminta revisi** |

Klik notifikasi akan **membuka dokumen PM** yang terkait (bila Anda berhak
melihatnya).

### 8.3 Notifikasi yang Anda terima

| Notifikasi | Kapan | Anda terima bila | Kanal |
|------------|-------|-------------------|-------|
| **Reminder Minggu ke-2** | Sekali per periode, pada jam yang diatur | Anda **Teknisi** unit tersebut, **PIC** unit tersebut, atau **Atasan** | Apps + Email |
| **Keterlambatan PM** | Setiap hari pada jam yang diatur (bawaan 07:00 & 14:00) | Anda **Teknisi** unit tersebut atau **Atasan** | Apps + Email |
| **Menunggu persetujuan (3.1)** | Saat teknisi menandatangani | Anda **PIC** dokumen tersebut | Apps + Email |
| **Menunggu Atasan (3.2)** | Saat PIC menyetujui | Anda **Atasan** | Apps + Email |
| **PM selesai (3.3)** | Saat Atasan menyetujui | Anda **teknisi pembuat** | Apps + Email |
| **Perlu revisi (3.4)** | Saat PIC/Atasan menolak | Anda **teknisi pembuat** | **Apps saja** |
| **Laporan mingguan (4.1)** | Senin pagi (bila dijadwalkan) | Anda **Atasan** | **Email saja** |
| **Laporan bulanan (4.2)** | Tanggal 1 (bila dijadwalkan) | Anda **Atasan** | **Email saja** |

### 8.4 Aturan penerima (yang perlu Anda ingat)

1. **Teknisi hanya menerima notifikasi unit sesuai scope-nya.** Teknisi Mesin
   tidak akan menerima notifikasi unit Utility, dan sebaliknya.
2. **PIC hanya menerima notifikasi unit yang menjadi tanggung jawabnya.** Bila
   unit belum punya PIC, sistem memakai PIC dari **riwayat PM terakhir** unit
   tersebut sebagai cadangan.
3. **Satu orang = satu notifikasi.** Bila Anda memegang beberapa peran
   (misalnya teknisi **dan** PIC), Anda menerima **satu** notifikasi gabungan,
   bukan beberapa notifikasi terpisah.
4. **Reminder dikirim sekali per periode.** Bila Anda menerima reminder hari
   ini, Anda tidak akan menerima reminder lagi untuk periode yang sama.
5. **Keterlambatan dikirim sekali per slot jam** (07:00 dan 14:00 terpisah),
   dan **berhenti otomatis** bila unitnya sudah selesai di-PM.
6. **Notifikasi approval hanya sampai ke pihak yang berhak** — Anda tidak akan
   melihat approval unit orang lain.

### 8.5 Bila email tidak sampai

1. Cek **tab Kirim & Riwayat** di Settings → Notifications (Admin) untuk melihat
   status pengiriman & pesan errornya.
2. Pastikan koneksi email sudah aktif di **Settings → Integrate**.
3. Pastikan alamat email pada akun Anda benar dan tidak penuh.
4. Periksa folder **Spam/Quarantine**.

Notifikasi In-App tetap masuk walau email gagal — jadi aplikasi tetap bisa
dipakai sebagai acuan.

---

## 9. Pengaturan Admin

Semua bab di bawah ini hanya untuk **Admin**. Menu **Settings** berisi kartu
untuk: **Mesin**, **Utility**, **Template**, **Komponen**, **Tipe Mesin**,
**Lokasi**, **Suku Cadang**, **Notifications**, dan **Integrate**.

### 9.1 Settings → Notifications

Halaman ini punya **3 tab**.

#### Tab 1 — Matriks & Jadwal

Tabel **4 bagian** notifikasi. Setiap baris bisa dimatikan/dinyalakan per
**kanal** (Apps / Email), dan baris 1–2 punya sakelar penerima per peran.

| Baris | Notifikasi | Kanal | Penerima | Jadwal |
|-------|------------|-------|----------|--------|
| 1 | Reminder Minggu ke-2 | Apps + Email | Teknisi, PIC, Atasan | Sekali per periode, jam dapat diatur |
| 2 | Keterlambatan PM | Apps + Email | Teknisi, Atasan | Setiap hari, jam dapat bertambah (bawaan 07:00 & 14:00) |
| 3.1–3.3 | Approval real-time | Apps + Email | Ditetapkan aturan | Real-time |
| 3.4 | Perlu revisi | **Apps saja** (bawaan) | Teknisi pembuat | Real-time |
| 4.1 | Laporan mingguan | **Email saja** | Atasan + penerima tambahan | Hari & jam (bawaan Senin 07:00) |
| 4.2 | Laporan bulanan | **Email saja** | Atasan + penerima tambahan | Tanggal & jam (bawaan tanggal 1, 07:00) |

Baris **3** dan **4** **penerimanya tidak bisa diganti** — itu aturan bisnis.
Yang bisa diubah hanya **kanal** dan **jadwal**.

Tersedia juga:

- **Pratinjau penerima** — menampilkan siapa yang akan menerima, per peran,
  tanpa mengirim apa pun.
- **Kirim sekarang** — mengirim manual tanpa menunggu jadwal (berguna untuk
  uji coba).

#### Tab 2 — PIC Perangkat

Menetapkan **User/PIC** untuk setiap unit (Mesin/Utility):

- Cari unit berdasarkan kode/nama, lalu pilih PIC.
- Bisa ditetapkan **massal** untuk beberapa unit sekaligus, maupun dikosongkan.
- Kolom **Riwayat PIC** menampilkan PIC dari PM terakhir unit tersebut —
  dipakai sistem sebagai cadangan bila PIC belum ditetapkan.

> **Penting:** sebagian besar unit saat ini belum punya PIC. Until ditetapkan,
> User/PIC tidak akan menerima reminderalthough sistem tetap memakai fallback
> dari riwayat PM.

#### Tab 3 — Kirim & Riwayat

- **Kirim manual**: reminder, keterlambatan, laporan mingguan, laporan bulanan.
- **Riwayat Pengiriman**: daftar setiap pengiriman berisi **jenis**, **pemicu**
  (terjadwal / manual / real-time), **ringkasan**, **jumlah penerima**, jumlah
  terkirim ke **Apps** dan **Email**, **status** (`sent` / `skipped` / `failed`),
  dan **pesan error** bila gagal.

### 9.2 Settings → Integrate

| Bagian | Isi |
|--------|-----|
| **Koneksi email** | Microsoft 365 via **Graph API** (utama) atau **SMTP** (cadangan). Isi Tenant ID, Client ID, Client Secret, alamat pengirim. |
| **Uji** | Tombol **Kirim Email Uji** — menampilkan pesan error penyedia apa adanya. |
| **Fitur akun** | **Verifikasi login (OTP)**: nyalakan/matikan, masa berlaku, "ingat perangkat ini", dan tombol darurat. |
| **Laporan otomatis** | Jadwal laporan mingguan & bulanan, penerima tambahan, dan lampiran (PDF/Excel). |

Client secret & password SMTP **tidak pernah ditampilkan lagi** setelah
disimpan — kalau lupa, isi ulang.

Bila Admin terkunci karena email bermasalah, Admin dapat menjalankan di
server: `php artisan integration:disable-otp` untuk mematikan OTP sementara.

### 9.3 Master Data — Tipe Mesin & Lokasi

- **Tipe Mesin** (`/admin/tipe-mesin`): nama tampilan + kode. Tipe sistem
  **Umum** & **Utility** tidak dapat dihapus. Tipe yang masih dipakai unit atau
  template tidak dapat dihapus, dan **kode tidak bisa diubah** setelah dipakai.
- **Lokasi / Site** (`/admin/lokasi`): nama lokasi. Lokasi yang masih dipakai
  unit tidak dapat dihapus. Mengganti nama lokasi ikut memperbarui unit.
- Form Mesin/Utility memakai **dropdown** lokasi dari master — nilai di luar
  daftar ditolak.

### 9.4 Template Komponen

Menu **Template** manages template komponen per unit/jenis.

- **Terapkan Ke Unit**: pilih template lalu unit-unit tujuan. Komponen template
  menjadi checklist PM unit tersebut.
- **Impor dari CSV** (perintah `php artisan templates:import`): satu baris CSV
  = satu unit, berisi **Nama Template**, **Code unit**, dan kolom **Komponen
  1..N**. Impor bersifat idempoten (aman dijalankan berulang), dan nama
  komponen yang mirip dengan master akan dipetakan ke komponen yang sudah
  ada.

### 9.5 Backup & Restore

Menu **Backup** (hanya Admin):

- **Buat backup** sekarang, atau menunggu backup otomatis tiap hari **02:00
  WIB**.
- **Unduh** file backup, **Pulihkan** (wajib mengetik nama file untuk
  konfirmasi), **Unggah** file dari luar, dan **Hapus** backup lama.
- Retensi backup lama dibersihkan otomatis sesuai pengaturan.

> **Sangat disarankan** membuat backup manual sebelum mengimpor data massal
> atau mengubah skema database.

### 9.6 Activity Log

Menu **Activity Log** (Admin & Manager):

- **Filter** jenis aksi, pelaku, dan rentang tanggal; plus **pencarian**.
- Panel detail menampilkan perubahan **sebelum → sesudah**.
- Kolom **IP** diberi label (mis. "LAN kantor", "Gateway VPN / NAT") agar
  mudah dibaca. Aksi otomatis tampil sebagai "Sistem (otomatis, tanpa
  perangkat)".

---

## 10. Aturan Penting yang Perlu Diketahui

1. **Checklist terkunci setelah ditandatangani.** Isi hanya bisa diubah pada
   status `draft` atau `rejected`. Setelah diteken, isi tidak bisa diubah di
   belakang layar.
2. **Satu record per unit per periode.** Membuat record kedua untuk unit,
   periode, dan tahun yang sama tidak dimungkinkan.
3. **Periode terkunci / masa depan tidak bisa diisi.**
4. **Penolakan hanya di tahap 2 & 3** dan wajib beralasan.
5. **Anti konflik kepentingan**: penyetuju tahap 2 & 3 tidak boleh orang yang
   sama dengan pengisi checklist.
6. **`approved` bersifat final** — tidak bisa diedit maupun ditandatangani
   ulang.
7. **Jejak tidak dihapus.** Nama & peran penanda tangan disimpan sebagai
   snapshot; riwayat tetap terbaca meski akun pelakunya dihapus.
8. **Admin tidak bisa mengunci dirinya sendiri** (tidak bisa ganti role sendiri
   atau menghapus akun sendiri).
9. **Semua perubahan penting tercatat** di Activity Log (tanpa pernah menulis
   password).
10. **Otorisasi ditegakkan di server.** Menyembunyikan tombol hanyalah tampilan;
    URL langsung ke halaman yang bukan hak Anda tetap ditolak.
11. **Notifikasi tidak pernah menggagalkan proses utama.** Bila email gagal
    terkirim, persetujuan tetap berhasil; kegagalan hanya tercatat di log dan
    Riwayat Pengiriman.
12. **Tidak ada notifikasi ganda.** Reminder dikirim sekali per periode,
    keterlambatan sekali per slot jam, dan orang yang punya beberapa peran
    menerima satu notifikasi gabungan.
13. **Notifikasi hanya untuk yang berhak.** Teknisi hanya untuk unit sesuai
    scope-nya, PIC hanya untuk unitnya, approval hanya ke pihak yang terkait.
14. **Semua waktu dalam WIB.** Jadwal notifikasi, laporan, backup, dan seluruh
    tampilan waktu mengikuti zona waktu Asia/Jakarta.

---

## 11. Tips & Penanganan Masalah (FAQ)

**Saya tidak melihat menu tertentu.**
Menu disesuaikan dengan wewenang peran Anda. Lihat tabel
[4.1](#41-menu-yang-terlihat-per-peran). Bila merasa seharusnya punya akses,
hubungi Admin.

**Tombol simpan/setujui tidak bisa diklik.**
Pastikan **nama penanda tangan** sudah diisi **dan** **tanda tangan** sudah
digambar. Selama salah satu kosong, tombol memang dimatikan.

**Muncul pesan "Perlu Revisi" pada record saya.**
Record Anda ditolak di tahap 2/3. Buka record, baca **alasan revisi**, perbaiki
item terkait, lalu isi + tanda tangan ulang.

**Saya tidak bisa menyetujui, padahal saya User PIC.**
Bila Anda juga orang yang **mengisi** checklist itu, penolakan adalah
anti-konflik kepentingan yang disengaja. Minta orang lain yang mengisi, atau
minta Admin/PIC lain menyetujui.

**Periode di masa depan tidak bisa dipilih / tampil terkunci.**
Itu memang aturan: periode yang belum berjalan belum bisa diisi.

**Tanda tangan tidak muncul setelah disetujui.**
Pastikan penyimpanan berhasil (muncul notifikasi sukses). Tanda tangan
disajikan lewat tautan ber-otorisasi; buka ulang detail record.

**Saya lupa password.**
Pakai **Lupa password** di halaman login; bila tidak berhasil, minta Admin
mengubah password lewat menu **Pengguna**.

**Filter saya hilang saat pindah halaman.**
Filter Beranda sengaja diteruskan ke Form PM dan tombol Kembali. Bila Anda
membuka halaman lain dari menu, filter akan direset ke default periode berjalan.

**Stok suku cadang tidak berubah setelah transaksi.**
Pastikan transaksi tersimpan (barang keluar melebihi stok akan ditolak).
Periksa **Riwayat** suku cadang untuk memastikan transaksinya tercatat.

**Ingin melihat siapa mengubah apa.**
Hanya Admin & Manager yang dapat membuka **Activity Log**. Hubungi mereka.

**Saya tidak melihat lonceng notifikasi.**
Pastikan Anda sudah login. Lonceng hanya muncul untuk pengguna yang sudah
masuk, dan menampilkan **badge** hanya bila ada notifikasi belum dibaca.

**Saya tidak menerima notifikasi untuk unit yang saya tangani.**
Notifikasi hanya dikirim sesuai aturan penerima: teknisi → unit sesuai scope;
PIC → unit yang menjadi tanggung jawabnya; Atasan → seluruh laporan. Bila
mencurigai, minta Admin memeriksa tab **PIC Perangkat** dan **Pratinjau
penerima** di Settings → Notifications.

**Saya menerima reminder yang sama dua kali.**
Reminder dikirim **sekali per periode**. Bila Anda menerimanya lagi, kemungkinan
periodenya berbeda atau Admin mengirim manual. Baris **Kirim & Riwayat**
menunjukkan pemicunya (terjadwal / manual / real-time).

**Email notifikasi tidak masuk, padahal notifikasi ada di aplikasi.**
Cek **Kirim & Riwayat** (Settings → Notifications) — status `failed` disertai
pesan error penyedia. Periksa juga **Settings → Integrate → Kirim Email Uji**.
Notifikasi In-App tetap masuk walau email gagal.

**Saya ingin berhenti menerima email, tapi tetap ingin notifikasi di aplikasi.**
Minta Admin mematikan kanal **Email** pada baris matriks yang relevan, dan
biarkan kanal **Apps** menyala.

**Saya tidak bisa membuka Kotak Masuk.**
Kotak Masuk berada di `/notifications` dan terbuka untuk semua pengguna yang
sudah login. Bila muncul halaman error 403/419, muat ulang halaman atau
keluar-masuk kembali.

**Saya tidak bisa mengunduh laporan PDF/Excel dari email.**
Lampiran hanya disertakan bila Admin mengaktifkannya di **Settings →
Integrate → Laporan otomatis**. Tanpa lampiran, email tetap berisi ringkasan
jumlah, persentase, dan daftar terlambat.

**Bagaimana cara melihat notifikasi lama yang sudah dihapus?**
Notifikasi yang dihapus dari Kotak Masuk **tidak** bisa dipulihkan. Yang
tetap tersimpan adalah **Riwayat Pengiriman** (Settings → Notifications).

**Tiba-tiba halaman saya menampilkan halaman error beranimasi.**
Halaman error (403/404/419/500/dll) punya tombol **✕** dan tombol **Esc** untuk
menutup, serta tombol **Kembali** / **Ke Beranda**. Bila muncul 419, sesi Anda
habis — coba login ulang.

---

## 12. Glosarium

| Istilah | Penjelasan |
|---------|-----------|
| **Aset / Unit** | Mesin atau Utility yang dirawat (tabel `assets`) |
| **Checklist** | Daftar item/komponen yang diperiksa |
| **Komponen / Part** | Bagian unit yang diperiksa; sumber item checklist |
| **Form Maintenance** | Halaman pemilih Mesin/Utility/Komponen untuk masuk form PM |
| **Periode** | Rentang 2 bulanan (6 per tahun) |
| **Record PM** | Satu lembar PM per unit per periode |
| **Snapshot** | Salinan nilai yang disimpan agar riwayat tetap terbaca |
| **Soft delete** | Menghapus tanpa benar-benar membuang data; bisa dipulihkan (restore) |
| **Scope** | Batas jenis unit yang boleh diakses (Mesin vs Utility) |
| **Signer** | Orang yang menandatangani tahap tertentu |
| **Activity Log** | Catatan aktivitas penting beserta pelaku, IP, dan perangkat |
| **PIC** | *Person in Charge* — User yang bertanggung jawab atas sebuah unit |
| **Atasan** | Pemegang persetujuan tahap 3 (role `manager` / `pm.approve`) |
| **Reminder** | Notifikasi pengingat PM untuk Minggu ke-2 setiap periode |
| **Keterlambatan** | Notifikasi unit yang PM-nya melewati batas jadwal |
| **Digest** | Satu notifikasi berisi gabungan beberapa unit |
| **Nomor dokumen** | Kode unik PM, mis. `PM/2026/SEP-OKT/00042` |
| **Kan In-App / Apps** | Notifikasi yang muncul di aplikasi (Kotak Masuk) |
| **OTP** | Kode 6 digit sekali pakai untuk verifikasi login via email |

---

## 13. Dokumen Terkait

- [PRD.md](PRD.md) — kebutuhan produk, persona, aturan bisnis, kriteria penerimaan
- [Architecture.md](Architecture.md) — arsitektur teknis, skema data, otorisasi, deployment
- [Agent.md](Agent.md) — instruksi kerja untuk AI coding agent
- [README.md](../../README.md) — setup, perintah Artisan, akun demo
- [`../v.1.2/`](../v.1.2/) — arsip dokumen versi sebelumnya
- [`../data-source/`](../data-source/) — sumber data CSV
- [../../README.md](../../README.md) — setup & instalasi, perintah artisan, akun demo
- [../v.1.1/](../v.1.1/) — arsip dokumen desain awal (superseded)

> Bila perilaku aplikasi berbeda dari panduan ini, **kode adalah kebenaran** —
> laporkan agar dokumen diperbarui pada perubahan yang sama.
