# Guide.md — Panduan Pengguna PM Preventive

Panduan **pemakaian aplikasi sehari-hari** untuk seluruh pengguna **PM
Preventive**, ditulis per peran (role). Dokumen ini menjawab "apa yang saya
lakukan di aplikasi ini?" — bukan "bagaimana aplikasi ini dibangun".

**Versi dokumen:** 1.2 · Zona waktu aplikasi: **Asia/Jakarta** · Bahasa UI:
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
8. [Aturan Penting yang Perlu Diketahui](#8-aturan-penting-yang-perlu-diketahui)
9. [Tips & Penanganan Masalah (FAQ)](#9-tips--penanganan-masalah-faq)
10. [Glosarium](#10-glosarium)
11. [Dokumen Terkait](#11-dokumen-terkait)

---

## 1. Tentang Panduan Ini

- **Siapa yang perlu membaca:** semua pengguna yang punya akun — Admin, Manager,
  Teknisi, User PIC, Viewer, maupun teknisi ber-scope (`tech-machines`,
  `tech-utilities`, `tech-all`).
- **Cara membaca:** mulailah dari bab [3](#3-mulai-menggunakan-aplikasi) dan
  [4](#4-peran--hak-akses), lalu lompat ke bagian peran Anda di
  [bab 7](#7-panduan-per-peran). Bab [5](#5-fitur-umum-semua-peran) menjelaskan
  fitur yang dipakai bersama.
- **Konvensi teks:** **Tebal** = nama menu, tombol, kolom, atau status yang
  terlihat di layar. 🔵 🟠 🔴 🟢 = warna dot status periode.
- **Penting:** yang terlihat di layar tidak selalu sama dengan yang boleh
  dilakukan. Menu yang tidak relevan memang disembunyikan, dan setiap aksi tetap
  diperiksa di server. Kalau Anda tidak berhak, aksi akan ditolak.

---

## 2. Apa Itu PM Preventive

**PM Preventive** adalah aplikasi untuk mengelola **Preventive Maintenance (PM)**
mesin produksi secara digital: daftar aset, pengisian checklist lapangan,
**rantai tanda tangan 3 tahap**, laporan, dan ekspor.

Istilah inti:

| Istilah | Arti |
|---------|------|
| **PM** | Preventive Maintenance — perawatan berkala agar mesin tidak rusak mendadak |
| **Unit / Aset** | Satu **Mesin** atau satu **Utility** yang dirawat (tabel `assets`) |
| **Periode** | Rentang 2 bulanan; ada 6 periode per tahun (lihat tabel di bawah) |
| **Checklist** | Daftar item/komponen yang diperiksa saat PM, berisi nilai **Aktual** dan aksi |
| **Record PM** | Satu lembar PM untuk satu unit pada satu periode & tahun |
| **Tanda tangan** | Persetujuan bertingkat yang harus dilalui berurutan |

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

> Akun dibuatkan oleh Admin lewat menu **Pengguna**. Bila belum punya akun,
> hubungi Admin. Jangan membagikan password kepada orang lain.

### 3.2 Lupa password

1. Di halaman login klik **Lupa password?**.
2. Masukkan Email Anda, lalu klik **Kirim Tautan Reset Password**.
3. Buka email, ikuti tautan, lalu tetapkan password baru.

Bila tautan tidak sampai, hubungi Admin untuk direset lewat menu **Pengguna**
(Admin dapat mengganti password login Anda).

### 3.3 Mengenal navigasi

- **Di desktop (layar lebar):** menu utama ada di **sidebar kiri**.
- **Di ponsel / tablet:** menu berada di **bilah bawah** yang bisa digeser
  horizontal.
- Menu **Persetujuan** kadang menampilkan **badge angka** — itu jumlah record
  yang benar-benar menunggu tindakan Anda. Angka badge sama dengan isi daftar.
- Klik nama Anda di kanan atas → **Profile** atau **Keluar**.

Menu yang Anda lihat berbeda-beda sesuai peran. Rinciannya ada di
[bab 4](#4-peran--hak-akses).

### 3.4 Profil & tanda tangan tersimpan

Buka **Profile** untuk:

- Mengubah **nama** dan **email**.
- Mengganti **password** sendiri (memasukkan password lama sebagai bukti).
- Menyimpan **Tanda Tangan Tersimpan**: gambar tanda tangan sekali, lalu pakai
  ulang saat menyetujui tanpa menggambar lagi.
- Menghapus akun sendiri (dengan konfirmasi).

> **Tanda tangan tersimpan bersifat pribadi.** Menyimpannya tidak mengubah
> tanda tangan pada record yang sudah ditandatangani. Menggambar ulang tanda
> tangan saat menyetujui juga tetap bisa, dan akan menimpa tanda tangan Anda
> untuk tahap itu.

---

## 4. Peran & Hak Akses

Aplikasi mengenal **8 peran**. Lima peran umum, tiga peran teknisi ber-scope.

| Peran | Sebutan di lapangan | Inti tugas |
|-------|---------------------|-----------|
| `admin` | Administrator | Mengelola seluruh data, akun, role, komponen, PM Record, dan membaca audit log |
| `manager` | Atasan / Supervisor | Menyetujui **tahap 3**, mengelola mesin & komponen, memantau laporan |
| `technician` | Teknisi / Pemeriksa | Mengisi checklist & menandatangani **tahap 1** untuk Mesin dan Utility |
| `user` | User PIC | Menyetujui sebagai diketahui pada **tahap 2** |
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
| Audit Log | ✅ | ✅ | — | — | — | — | — | — |

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
6. **Baca Audit Log — menu Audit Log**
   - 100 aktivitas terbaru: pelaku, waktu, objek, perubahan (sebelum→sesudah),
     dan IP.
7. **Kelola Suku Cadang** (lihat [5.8](#58-suku-cadang)).
8. **Cadangan tanda tangan**: bila pihak berwenang tidak ada, Admin dapat
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
5. **Baca Audit Log**.

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
- **Tidak** memiliki menu **Audit Log**, **PM Records**, **Pengguna**, maupun
  **Role** — itu milik Admin.

---

## 8. Aturan Penting yang Perlu Diketahui

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
9. **Semua perubahan penting tercatat** di Audit Log (tanpa pernah menulis
   password).
10. **Otorisasi ditegakkan di server.** Menyembunyikan tombol hanyalah tampilan;
    URL langsung ke halaman yang bukan hak Anda tetap ditolak.

---

## 9. Tips & Penanganan Masalah (FAQ)

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
Hanya Admin & Manager yang dapat membuka **Audit Log**. Hubungi mereka.

---

## 10. Glosarium

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
| **Audit Log** | Catatan aktivitas penting beserta pelaku dan IP |

---

## 11. Dokumen Terkait

- [PRD.md](PRD.md) — kebutuhan produk, persona, aturan bisnis, kriteria penerimaan
- [Architecture.md](Architecture.md) — arsitektur teknis, skema data, otorisasi, deployment
- [Agent.md](Agent.md) — instruksi kerja untuk AI coding agent
- [../../README.md](../../README.md) — setup & instalasi, perintah artisan, akun demo
- [../v.1.1/](../v.1.1/) — arsip dokumen desain awal (superseded)

> Bila perilaku aplikasi berbeda dari panduan ini, **kode adalah kebenaran** —
> laporkan agar dokumen diperbarui pada perubahan yang sama.
