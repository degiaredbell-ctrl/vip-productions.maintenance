# PRD — PM Preventive System

**Produk:** Preventive Maintenance System
**Judul aplikasi:** PM Preventive
**Client:** Verra Inter Pangan / Redbell Group
**Versi dokumen:** 1.3
**Status:** Implementasi berjalan (MVP + Modul Notifikasi 2 Saluran)

> **Versi 1.3 — snapshot terkini.** Dokumen ini menggantikan
> [`references/v.1.2/PRD.md`](references/v.1.2/PRD.md) dan [`references/v.1.1/PRD.md`](references/v.1.1/PRD.md) sebagai arsip. Arsitektur teknis ada di
> [Architecture.md](Architecture.md); instruksi kerja untuk AI agent ada di
> [references/v.1.3/Agent.md](references/v.1.3/Agent.md); panduan pemakaian per peran ada di
> [references/v.1.3/Guide.md](references/v.1.3/Guide.md).
>
> **What's new di v1.3:**
> - **Modul Notifikasi dua saluran** (In-App + Email) — matriks 4 baris (§6.14,
>   §7 BR-11…BR-16).
> - **Kotak Masuk** berdiri sendiri di `/notifications`, lonceng notifikasi di
>   header, dan halaman **Settings → Notifications** (3 tab).
> - **Integrasi email Microsoft 365** (Graph/SMTP) + login OTP + lupa password
>   yang benar-benar terkirim (§6.15).
> - **Master data** Tipe Mesin & Lokasi/Site (§6.16).
> - **Halaman error beranimasi** 403/404/405/419/429/500/503 dengan tombol X
>   (§6.17).
> - **Impor Template Komponen Mesin dari CSV** + penerapan ke unit (§6.18).
> - Zona waktu **WIB (Asia/Jakarta)** untuk seluruh aplikasi.

---

## Daftar Isi

1. [Ringkasan Eksekutif](#1-ringkasan-eksekutif)
2. [Masalah yang Diselesaikan](#2-masalah-yang-diselesaikan)
3. [Tujuan & Sasaran](#3-tujuan--sasaran)
4. [Persona](#4-persona)
5. [Ruang Lingkup](#5-ruang-lingkup)
6. [Kebutuhan Fungsional](#6-kebutuhan-fungsional)
7. [Aturan Bisnis](#7-aturan-bisnis)
8. [Model Peran dan Permission](#8-model-peran-dan-permission)
9. [Kebutuhan Non-Fungsional](#9-kebutuhan-non-fungsional)
10. [Model Data](#10-model-data)
11. [Kriteria Penerimaan](#11-kriteria-penerimaan)
12. [Di Luar Ruang Lingkup](#12-di-luar-ruang-lingkup)
13. [Risiko & Mitigasi](#13-risiko--mitigasi)
14. [Metrik Sukses](#14-metrik-sukses)

---

## 1. Ringkasan Eksekutif

Aplikasi web untuk mengelola **Preventive Maintenance (PM)** mesin produksi secara end-to-end: data mesin, pengisian checklist lapangan, **rantai persetujuan bertingkat 3 tahap**, laporan kepatuhan, dan ekspor.

Nilai utama yang dibawa produk ini:

1. **Checklist PM tidak bisa dimanipulasi setelah ditandatangani** — isi yang sudah ditandatangani terkunci sehingga persetujuan di hilir selalu sesuai dengan data yang direview.
2. **Rantai persetujuan tidak bisa dilompati** — tahap aktif ditentukan oleh status record, bukan oleh pilihan pengguna.
3. **Ada jejak audit** — setiap aksi penting tercatat lengkap dengan nilai sebelum/sesudah dan IP pelaku.

---

## 2. Masalah yang Diselesaikan

| Masalah | Dampak | Solusi |
|---------|--------|--------|
| Checklist PM kertas, mudah hilang atau berubah isinya | Riwayat tidak bisa dipercaya | Checklists digital, terkunci setelah tanda tangan, ada snapshot revisi |
| Persetujuan hanya lisan atau via pesan | Sulit membuktikan siapa menyetujui apa | Rantai tanda tangan 3 tahap dengan tanda tangan gambar |
| Tidak ada yang tahu tunggakan PM periode lalu | Mesin telat dirawat, risiko breakdown | Dot notifikasi 4 warna + kepatuhan per periode |
| Orang bisa menyetujui karyawannya sendiri | Persetujuan jadi formalitas | Anti konflik kepentingan di tahap 2 & 3 |
| Sulit melacak siapa mengubah apa | Akun tidak bisa dipertanggungjawabkan | Audit log dengan diff dan IP |
| Daftar mesin tersebar di spreadsheet | Sumber data tidak konsisten | Impor CSV idempoten + CRUD mesin |

---

## 3. Tujuan & Sasaran

### Tujuan
- Mengganti pencatatan PM kertas dengan sistem digital yang **terbukti sah**.
- Memastikan setiap mesin punya PM tepat waktu sesuai periode 2 bulannya.
- Memberi jejak audit yang tidak bisa dimanipulasi.

### Sasaran (measurable)
- 100% mesin aktif punya PM record per periode berjalan.
- Rata-rata waktu persetujuan < 3 hari kerja per tahap.
- 0 kasus persetujuan yang melewati tahap.
- Laporan kepatuhan tersedia tanpa menyusun ulang data manual.

---

## 4. Persona

| Persona | Peran | Tujuan utama | Kebutuhan harian |
|---------|-------|--------------|-------------------|
| **Admin** | Administrator sistem | Mengelola seluruh data & akun | CRUD mesin, komponen, role, pengguna, ganti password user, kelola & hapus PM Record, baca audit log, **atur matriks notifikasi & PIC perangkat**, **hubungkan email**, **backup & restore** |
| **Manager / Atasan** | Atasan unit | Memastikan PM dikerjakan & menyetujui di tahap akhir | Lihat Beranda, isi mesin & komponen, baca riwayat, laporan + ekspor, audit log, persetujuan tahap 3, **terima laporan mingguan/bulanan** |
| **Technician / Pemeriksa** | Teknisi lapangan | Melakukan & mendokumentasikan pemeriksaan | Isi checklist, tanda tangan tahap 1, riwayat, laporan + ekspor, **terima reminder & pengingat keterlambatan** |
| **User PIC** | Penanggung jawab unit | Mengetahui hasil pemeriksaan tim | Persetujuan tahap 2, riwayat, lihat laporan, **terima pengingat PM unitnya** |
| **Viewer** | Auditor / management | Hanya melihat | Lihat Beranda, riwayat, laporan + ekspor |

> Matriks menu per peran, langkah pemakaian (isi checklist, tanda tangan,
> persetujuan, ekspor), dan penanganan masalah ada di [Guide.md](Guide.md).
> Aplikasi juga mengenal tiga peran teknisi ber-scope (`tech-machines`,
> `tech-utilities`, `tech-all`) yang dijelaskan pada §8.

---

## 5. Ruang Lingkup

### Termasuk (v1)
- Autentikasi, profil, ganti password sendiri
- Dashboard dengan filter periode/tahun/area/status/pencarian + notifikasi tunggakan
- CRUD mesin (soft delete + restore) + CRUD unit Utility
- Master Komponen + penugasan ke Mesin/Utility (pengganti template per jenis mesin)
- **Master data Tipe Mesin & Lokasi/Site** (dikelola dari Settings)
- **Template Komponen** (umum & per unit) + penerapan massal ke unit + **impor dari CSV**
- Halaman Form Maintenance (kartu Mesin/Utility/Suku Cadang) untuk masuk ke form PM
- Pengisian checklist PM dengan aksi per item
- Rantai tanda tangan 3 tahap (gambar) + penolakan + revisi
- Tanda tangan tersimpan per akun (diisi sekali di Profil, dipakai ulang saat persetujuan)
- Halaman Persetujuan dengan badge antrean
- Manajemen pengguna (CRUD + ganti password) oleh Admin
- Manajemen role & permission oleh Admin
- Manajemen PM Record oleh Admin (lihat semua + hapus permanen)
- Audit log
- Laporan kepatuhan, top part, ringkasan aksi
- Ekspor XLSX & PDF
- Impor mesin & suku cadang dari CSV
- Suku Cadang (global): master, transaksi stok masuk/keluar, riwayat transaksi, impor/unggah CSV, **dashboard monitoring stok**
- **Modul Notifikasi dua saluran (In-App + Email)** — reminder mingguan, keterlambatan, approval real-time, laporan mingguan/bulanan
- **Kotak Masuk notifikasi** berdiri sendiri + lonceng notifikasi di header
- **Settings → Notifications**: matriks & jadwal, PIC perangkat, kirim manual & riwayat pengiriman
- **Integrasi email** (Microsoft 365 Graph / SMTP), **login OTP**, **lupa password** yang benar-benar terkirim
- **Backup & Restore** database (manual + terjadwal 02:00 WIB)
- **Halaman error beranimasi** (403/404/405/419/429/500/503) dengan tombol tutup

### Tidak Termasuk (v1.3)
- WhatsApp / SMS / Telegram sebagai saluran notifikasi
- Aplikasi mobile native
- Integrasi dengan sistem ERP / sensor IoT
- Perhitungan downtime atau biaya maintenance

---

## 6. Kebutuhan Fungsional

REQ bernomor dipakai sebagai acuan kriteria penerimaan.

### 6.1 Autentikasi (REQ-AUTH)

| ID | Kebutuhan |
|----|-----------|
| REQ-AUTH-01 | Pengguna dapat register dengan nama, email, dan password terkonfirmasi |
| REQ-AUTH-02 | Pengguna dapat login dan logout, dengan opsi remember me |
| REQ-AUTH-03 | Pengguna dapat meminta tautan reset password dan melakukan reset |
| REQ-AUTH-04 | Aksi sensitif memerlukan konfirmasi password |
| REQ-AUTH-05 | Pengguna dapat mengubah profil (nama, email) |
| REQ-AUTH-06 | Pengguna dapat mengganti password sendiri dengan memasukkan password lama |
| REQ-AUTH-07 | Pengguna dapat menghapus akunnya sendiri (soft, dengan konfirmasi) |
| REQ-AUTH-08 | Seluruh halaman di luar auth wajib memerlukan sesi login |

### 6.2 Dashboard (REQ-DASH)

| ID | Kebutuhan |
|----|-----------|
| REQ-DASH-01 | Dashboard menampilkan seluruh mesin aktif beserta status PM untuk periode & tahun terpilih |
| REQ-DASH-02 | Filter periode (6 periode), tahun, area/sub-kategori, status, dan pencarian teks dapat dikombinasikan |
| REQ-DASH-03 | Statistik ringkasan (total, selesai, approval, perlu revisi) dihitung dari seluruh mesin aktif pada periode tersebut — **tidak** terpengaruh filter yang sedang aktif |
| REQ-DASH-04 | Setiap periode menampilkan dot status: biru / oranye / merah / hijau (lihat aturan bisnis) |
| REQ-DASH-05 | Periode di masa depan tampil terkunci dan tidak dapat dipilih untuk pengisian |
| REQ-DASH-06 | Filter yang sedang aktif diteruskan ke halaman form PM dan tombol Kembali |
| REQ-DASH-07 | Dashboard dapat diekspor ke XLSX dan PDF mengikuti filter yang sedang aktif |

### 6.3 Mesin (REQ-MCHN)

| ID | Kebutuhan |
|----|-----------|
| REQ-MCHN-01 | Admin/Manager dapat membuat mesin dengan kode unik, nama, lokasi, kategori, sub-kategori, jenis, template, dan status aktif |
| REQ-MCHN-02 | Mesin dapat diubah dan dihapus (soft delete) |
| REQ-MCHN-03 | Mesin yang dihapus dapat dipulihkan (restore) |
| REQ-MCHN-04 | Daftar mesin dapat dicari dan difilter |
| REQ-MCHN-05 | Setiap mesin dapat menampilkan riwayat seluruh PM-nya |
| REQ-MCHN-06 | Daftar mesin dapat diimpor massal dari CSV secara idempoten |
| REQ-MCHN-07 | Kolom `type` dan `week_group` diturunkan otomatis dari CSV karena tidak tersedia di sumber data |

### 6.4 Komponen / Parts (REQ-COMP)

| ID | Kebutuhan |
|----|-----------|
| REQ-COMP-01 | Master komponen memiliki nama, kategori, spesifikasi, dan urutan |
| REQ-COMP-02 | Satu komponen dapat ditugaskan ke banyak Mesin/Utility (many-to-many) |
| REQ-COMP-03 | Penugasan diatur per unit dan tiap unit punya urutan komponen sendiri |
| REQ-COMP-04 | Komponen dapat diubah/dihapus; menghapus komponen otomatis melepasnya dari unit terkait |
| REQ-COMP-05 | Form PM me-generate item dari komponen yang ditugaskan ke unit; item template lama dipakai hanya bila unit belum punya komponen |
| REQ-COMP-06 | Data item template lama dimigrasikan ke master komponen oleh `ComponentMigrationService` |
| REQ-COMP-07 | Hanya pemegang `template.manage` yang dapat mengelola Komponen (URL lama `/admin/templates` dialihkan ke `/admin/komponen`) |
| REQ-COMP-08 | Dari halaman **Mesin** maupun **Utility**, tombol **Komponen → Edit Komponen** membuka modal multi-select: centang beberapa komponen sekaligus lalu isi nilai **Standar** untuk tiap komponen |
| REQ-COMP-09 | Nilai Standar disimpan **per unit** (kolom `standard` pada pivot `component_machine`), sehingga komponen yang sama dapat punya standar berbeda di unit berbeda |
| REQ-COMP-10 | Bila Standar per unit kosong, form PM dan daftar komponen memakai `components.spec` (standar bawaan master) sebagai nilai cadangan |
| REQ-COMP-11 | Edit Komponen di halaman Mesin butuh `machine.manage`; di halaman Utility butuh `utility.manage` |

### 6.5 Pengisian PM (REQ-PM)

| ID | Kebutuhan |
|----|-----------|
| REQ-PM-01 | Technisi dapat membuka form PM untuk mesin + periode + tahun tertentu |
| REQ-PM-02 | Item checklist memuat nilai aktual, aksi (bersihkan/perbaiki/lumasi/ganti), kondisi akhir, dan jumlah part; memilih aksi "ganti" mewajibkan catatan detail bagian yang diganti |
| REQ-PM-03 | Record dapat menyimpan catatan umum |
| REQ-PM-04 | Form yang sudah memiliki record menampilkan data sebelumnya, bukan template kosong |
| REQ-PM-05 | Periode terkunci/masa depan tidak dapat diisi |
| REQ-PM-06 | Checklist hanya dapat diubah saat status `draft` atau `rejected` |
| REQ-PM-07 | Setiap record PM unik per kombinasi mesin + tahun + periode |
| REQ-PM-08 | Saat menyimpan, teknisi memilih User PIC yang akan menyetujui tahap 2 (`pic_user_id`), dipilih dari kandidat User PIC yang sah |

### 6.6 Rantai Tanda Tangan (REQ-SIGN)

| ID | Kebutuhan |
|----|-----------|
| REQ-SIGN-01 | Rantai persetujuan terdiri atas 3 tahap berurutan: Teknisi → User PIC → Atasan |
| REQ-SIGN-02 | Tahap aktif ditentukan oleh status record, sehingga rantai tidak dapat dilompati |
| REQ-SIGN-03 | Penanda tangan menggambar tanda tangan pada canvas dan mengisinya sebagai catatan opsional |
| REQ-SIGN-04 | Nama dan peran penanda tangan disimpan sebagai snapshot terpisah dari akun |
| REQ-SIGN-05 | Tahap 2 dan 3 tidak boleh ditandatangani oleh orang yang sama dengan mengisi checklist |
| REQ-SIGN-06 | Record berstatus `approved` bersifat final dan tidak dapat diubah |
| REQ-SIGN-07 | Penolakan hanya tersedia di tahap User PIC dan Atasan |
| REQ-SIGN-08 | Penolakan wajib disertai alasan dan mengembalikan record ke teknisi untuk revisi |
| REQ-SIGN-09 | Revisi menyimpan snapshot isi checklist sebelumnya |
| REQ-SIGN-10 | Menandatangani ulang pada tahap yang sama menimpa tanda tangan sebelumnya |
| REQ-SIGN-11 | Pengguna dapat menyimpan tanda tangannya sekali di halaman Profil (`signature_path`) untuk dipakai ulang saat menyetujui, tanpa menggambar ulang |

### 6.7 Persetujuan (REQ-APPR)

| ID | Kebutuhan |
|----|-----------|
| REQ-APPR-01 | Halaman Persetujuan menampilkan record yang menunggu tindakan user sesuai permission-nya |
| REQ-APPR-02 | Badge antrean pada sidebar menggunakan query identik dengan daftar halaman |
| REQ-APPR-03 | Detail record menampilkan stepper 3 tahap dengan status tercentang |
| REQ-APPR-04 | User tanpa permission untuk suatu tahap tidak melihat aksi tanda tangan pada tahap tersebut |
| REQ-APPR-05 | Approver (`pm.acknowledge` / `pm.approve`) dapat **membuka form PM mesin maupun utility** untuk meninjau isi checklist, walaupun tidak memegang `pm.fill`; form tampil **read-only** dan panel tanda tangan tetap tersedia |
| REQ-APPR-06 | Form yang dibuka dari halaman Persetujuan mengembalikan tombol "Kembali" ke antrean Persetujuan |

### 6.8 Manajemen Pengguna (REQ-USER)

| ID | Kebutuhan |
|----|-----------|
| REQ-USER-01 | Admin dapat membuat pengguna baru (nama, email, password terkonfirmasi, role) |
| REQ-USER-02 | Admin dapat mengubah nama, email, dan role pengguna |
| REQ-USER-03 | Admin dapat mengubah password login pengguna lain dengan konfirmasi password |
| REQ-USER-04 | Admin dapat menghapus pengguna |
| REQ-USER-05 | Daftar role pada form berasal dari database sehingga role baru langsung dapat dipilih |
| REQ-USER-06 | Admin tidak dapat mengubah role akunnya sendiri |
| REQ-USER-07 | Admin tidak dapat menghapus akunnya sendiri |
| REQ-USER-08 | Pengguna dapat dicari dari daftar pengguna |
| REQ-USER-09 | Password tidak pernah ditulis ke audit log |

### 6.9 Role & Permission (REQ-ROLE)

| ID | Kebutuhan |
|----|-----------|
| REQ-ROLE-01 | Admin dapat membuat role custom |
| REQ-ROLE-02 | Admin dapat mengubah permission suatu role |
| REQ-ROLE-03 | Lima role sistem tidak dapat dihapus |
| REQ-ROLE-04 | Role baru otomatis dapat dipilih pada form pengguna |

### 6.10 Laporan (REQ-RPT)

> Halaman **`/reports`** dan item menu **Laporan** dihapus. Kartu laporan kini
> menyatu di **Beranda (`/dashboard`)** dan ikut filter tahun/periode di sana.
> Ekspor dipindah ke `GET /dashboard/report/export`.

| ID | Kebutuhan |
|----|-----------|
| REQ-RPT-01 | Beranda dapat menampilkan kepatuhan per periode (tercermin di grafik status/progress) |
| REQ-RPT-02 | Beranda menampilkan 5 part yang paling sering diganti pada periode terpilih |
| REQ-RPT-03 | Beranda menampilkan ringkasan aksi (bersihkan/perbaiki/lumasi/ganti) |
| REQ-RPT-04 | Data laporan mengikuti filter tahun dan periode Beranda |
| REQ-RPT-05 | Laporan dapat diekspor ke XLSX dan PDF (termasuk kepatuhan & detail komponen diganti) |

### 6.11 Activity Logs (REQ-AUD)

> Backing store tetap tabel `audit_logs` (prefix `REQ-AUD` dipertahankan), tetapi permukaan
> fiturnya kini bernama **Activity Logs** dengan permission `activity.view`.

| ID | Kebutuhan |
|----|-----------|
| REQ-AUD-01 | Aksi penting pada mesin (CRUD + restore), siklus PM (submit/sign/reject), pengguna, suku cadang, dan backup database tercatat di activity log |
| REQ-AUD-02 | Activity log menyimpan pelaku, waktu, subject, perubahan, dan IP |
| REQ-AUD-03 | Perubahan nilai before-after dicatat dalam bentuk JSON |
| REQ-AUD-04 | Halaman Activity Logs dapat **dicari** (aksi/pelaku/subjek/IP) dan **difilter** (aksi, pelaku, rentang tanggal), dipaginasi 25 log per halaman |
| REQ-AUD-05 | Hanya permission `activity.view` dapat membuka halaman Activity Logs |
| REQ-AUD-06 | Halaman menampilkan statistik ringkas (total, hari ini, pelaku aktif) dan **detail perubahan** per baris yang bisa dibuka-tutup |

### 6.12 Suku Cadang (REQ-SPARE)

| ID | Kebutuhan |
|----|-----------|
| REQ-SPARE-01 | Sistem menyediakan master suku cadang global (tidak terikat unit Mesin/Utility) berisi kode, kategori, nama, merek, type, deskripsi, satuan, stok, stok awal/minimum, supplier, kebutuhan untuk, dan lokasi |
| REQ-SPARE-02 | Pemegang `sparepart.manage` dapat menambah, mengubah, dan menghapus (soft delete) suku cadang |
| REQ-SPARE-02a | Pemegang `sparepart.manage` dapat **menghapus massal** beberapa suku cadang sekaligus lewat pilihan checkbox pada tabel |
| REQ-SPARE-03 | Pemegang `sparepart.manage` dapat mencatat transaksi **barang masuk** dan **barang keluar**; stok terkini menyesuaikan otomatis |
| REQ-SPARE-04 | Barang keluar yang melebihi stok tersedia ditolak dengan pesan validasi |
| REQ-SPARE-05 | Riwayat transaksi per suku cadang menampilkan tipe, jumlah, tanggal, catatan, dan pelaku |
| REQ-SPARE-06 | Daftar dapat dicari dan difilter berdasarkan kategori, lokasi, serta status (stok menipis / habis) |
| REQ-SPARE-07 | Master dapat diimpor massal dari CSV (`spare-parts:import`) dan lewat **unggah CSV** di halaman; impor bersifat idempoten berdasarkan kode |
| REQ-SPARE-08 | Semua user login dapat melihat daftar; hanya pemegang `sparepart.manage` dapat mengelola |
| REQ-SPARE-09 | Aksi create/update/delete/restore/transaksi/impor suku cadang tercatat di audit log |

### 6.13 Manajemen PM Record oleh Admin (REQ-PMR)

| ID | Kebutuhan |
|----|-----------|
| REQ-PMR-01 | Pemegang `pm.manage` dapat melihat seluruh PM Record lintas mesin, status, periode, dan tahun |
| REQ-PMR-02 | Daftar dapat difilter (status, periode, tahun, tipe mesin) dan dicari (nama teknisi, catatan, kode/nama/lokasi mesin) |
| REQ-PMR-03 | Pemegang `pm.manage` dapat menghapus PM Record secara permanen beserta item, tanda tangan, revisi, dan audit log terkait |

### 6.14 Modul Notifikasi Dua Saluran (REQ-NOTIF)

Notifikasi berjalan di **dua saluran**: **In-App** (database, tampil di Kotak Masuk
& lonceng header) dan **Email**. Setiap matriks mengatur **kanal** (Apps / Email)
dan **penerima** secara terpisah, dan dapat dimatikan dari
**Settings → Notifications**.

#### 6.14.1 Matriks notifikasi (4 bagian)

| ID | Notifikasi | Pemicu / Jadwal (WIB) | Apps | Email | Penerima | Isi |
|----|------------|----------------------|:---:|:-----:|----------|-----|
| **1** | **Reminder/info masuk Minggu ke-2** | Terjadwal, sekali per periode (default 07:00) | ✅ | ✅ | Teknisi, User/PIC, Atasan | Nama periode dinamis, minggu ke-2, rentang tanggal, daftar unit yang harus di-PM |
| **2** | **Keterlambatan PM** | Terjadwal, ulang tiap hari pada jam yang dipilih (default **07:00 & 14:00**) | ✅ | ✅ | Teknisi, Atasan | Periode, minggu, daftar unit terlambat + jumlah hari terlambat |
| **3.1** | **PM dibuat — menunggu persetujuan** | Real-time saat teknisi submit | ✅ | ✅ | **User/PIC** yang ditunjuk | Nomor dokumen, nama perangkat, pengirim, waktu, tautan |
| **3.2** | **PIC menyetujui — menunggu Atasan** | Real-time saat PIC menyetujui | ✅ | ✅ | **Atasan** | Nomor dokumen, nama perangkat, pengirim, waktu, tautan |
| **3.3** | **Atasan menyetujui — PM selesai** | Real-time saat Atasan menyetujui | ✅ | ✅ | **Teknisi** pembuat | Nomor dokumen, nama perangkat, pengirim, waktu, tautan |
| **3.4** | **Perlu revisi** | Real-time saat PIC/Atasan menolak | ✅ | **❌** | **Teknisi** pembuat | Nomor dokumen, nama perangkat, **alasan**, **peminta revisi**, waktu, tautan |
| **4.1** | **Laporan PM Mingguan** | Terjadwal, hari + jam (default **Senin 07:00**) | ❌ | ✅ | **Atasan** (+ penerima tambahan) | Ringkasan jumlah, persentase pencapaian, daftar terlambat, lampiran PDF/Excel |
| **4.2** | **Laporan PM Bulanan** | Terjadwal, tanggal + jam (default **tanggal 1, 07:00**) | ❌ | ✅ | **Atasan** (+ penerima tambahan) | Ringkasan jumlah, persentase pencapaian, daftar terlambat, lampiran PDF/Excel |

> Baris 3 dan 4 **tidak bisa** diubah ke penerima lain — penerima ditentukan
> oleh aturan bisnis (BR-12). Yang bisa diubah hanya **channel** (Apps/Email),
> jam/tanggal jadwal, dan (untuk baris 1–2) penerima.

#### 6.14.2 Persyaratan umum

| ID | Kebutuhan |
|----|-----------|
| REQ-NOTIF-01 | Notifikasi In-App tersimpan per pengguna (tabel `notifications`) dan hanya terlihat oleh pemiliknya |
| REQ-NOTIF-02 | Notifikasi Email hanya dikirim bila **koneksi email aktif** (`mail.enabled` + transport terisi) dan penerima punya alamat email valid |
| REQ-NOTIF-03 | Satu orang yang punya beberapa peran (mis. teknisi **dan** PIC) menerima **satu notifikasi gabungan**, bukan notifikasi terpisah per peran |
| REQ-NOTIF-04 | Notifikasi **Reminder Minggu ke-2** dikirim **sekali per periode** — jadi notifikasi tidak terkirim ulang di hari yang sama |
| REQ-NOTIF-05 | Notifikasi **Keterlambatan** dikirim **satu kali per slot jam** (mis. `07:00` dan `14:00` terpisah) |
| REQ-NOTIF-06 | Notifikasi keterlambatan **berhenti otomatis** bila unit concerned sudah selesai di-PM (unit tak lagi masuk daftar terlambat) |
| REQ-NOTIF-07 | Setiap pengiriman tercatat di **Riwayat Pengiriman**: jenis, pemicu (terjadwal/manual/real-time), ringkasan, jumlah penerima, jumlah App & Email, status, dan pesan error bila gagal |
| REQ-NOTIF-08 | Admin dapat melihat **pratinjau penerima** (tanpa mengirim) sebelum mengaktifkan jadwal |
| REQ-NOTIF-09 | Admin dapat **mengirim manual** reminder, keterlambatan, dan laporan mingguan/bulanan dari halaman yang sama |
| REQ-NOTIF-10 | Notifikasi approval **tidak boleh menggagalkan** proses persetujuan — kegagalan pengiriman hanya dicatat di log |
| REQ-NOTIF-11 | Perubahan matriks notifikasi & penetapan PIC perangkat tercatat di Activity Log |
| REQ-NOTIF-12 | Email laporan memuat **ringkasan jumlah + persentase pencapaian + daftar unit terlambat**, dan melampirkan **PDF ringkasan** serta/atau **Excel rekap** bila diaktifkan |

#### 6.14.3 Kotak Masuk & Lonceng Header

| ID | Kebutuhan |
|----|-----------|
| REQ-INBOX-01 | **Kotak Masuk** berada di halaman mandiri `/notifications`, **tidak** ada di dalam menu Settings |
| REQ-INBOX-02 | Kotak Masuk dapat difilter: **Semua**, **Belum dibaca**, **Jadwal PM**, **Terlambat**, **Approval** |
| REQ-INBOX-03 | Pengguna dapat menandai satu notifikasi terbaca, menandai semua terbaca, menghapus satu, dan menghapus semua yang sudah dibaca |
| REQ-INBOX-04 | Notifikasi approval menampilkan rincian: perangkat, periode, **oleh / diminta oleh**, waktu (WIB), dan **catatan revisi** |
| REQ-INBOX-05 | Notifikasi jadwal/terlambat menampilkan **daftar perangkat** yang expandable (perangkat, lokasi, minggu, batas, hari terlambat) |
| REQ-INBOX-06 | **Lonceng notifikasi** di header menampilkan jumlah belum dibaca (badge) dan **8 notifikasi terbaru**; klik item membuka dokumen terkait |
| REQ-INBOX-07 | Lonceng menyegarkan secara berkala (polling) dan saat tab browser kembali aktif |
| REQ-INBOX-08 | Notifikasi **approval hanya** sampai ke tangan penerima yang berhak (PIC dokumen, Atasan, atau teknisi pembuat) |

#### 6.14.4 PIC Perangkat

| ID | Kebutuhan |
|----|-----------|
| REQ-PIC-01 | Setiap unit (Mesin/Utility) dapat ditetapkan **User/PIC** bertanggung jawab |
| REQ-PIC-02 | Admin dapat menetapkan PIC **massal** untuk beberapa unit sekaligus, maupun mengosongkan |
| REQ-PIC-03 | Paddingatan perangkat memakai **User PIC dari riwayat PM terakhir** sebagai cadangan, dan ditandai jelas di antarmuka |
| REQ-PIC-04 | Reminder Mingguan (baris 1) hanya dikirim ke PIC **unit yang menjadi tanggung jawabnya** |

### 6.15 Integrasi Email & Fitur Akun (REQ-INTEG)

| ID | Kebutuhan |
|----|-----------|
| REQ-INTEG-01 | Admin dapat menghubungkan email melalui **Microsoft Graph API (OAuth)** sebagai cara utama, atau **SMTP Office 365** sebagai cadangan |
| REQ-INTEG-02 | Client secret & password SMTP disimpan **terenkripsi** dan tidak pernah ditampilkan lagi setelah disimpan |
| REQ-INTEG-03 | Admin dapat menguji koneksi email ("Kirim Email Uji") dan melihat pesan error dari penyedia secara langsung |
| REQ-INTEG-04 | **Lupa password** mengirim tautan reset ke email terdaftar, berlaku **60 menit**, dan pesan identik untuk email terdaftar maupun tidak (tidak bisa dipakai menebak akun) |
| REQ-INTEG-05 | **Verifikasi login (OTP)** — kode 6 digit via email, berlaku **10 menit**, maksimal **5 kali** salah, kirim ulang tiap **60 detik**; ada opsi **"Ingat perangkat ini"** (default 30 hari) yang otomatis batal bila password diganti |
| REQ-INTEG-06 | Akun tanpa email valid **tidak** diminta kode OTP |
| REQ-INTEG-07 | **Peringatan stok menipis/habis** dikirim **sekali** saat stok melewati batas minimum (bukan setiap transaksi), ke penerima yang dapat diatur |
| REQ-INTEG-08 | **Laporan otomatis** mingguan & bulanan terjadwal, dengan tombol "Kirim sekarang" untuk uji coba |
| REQ-INTEG-09 | Tombol **matikan OTP darurat** tersedia (perintah `integration:disable-otp`) agar Admin tidak terkunci bila email bermasalah |

### 6.16 Master Data (REQ-MASTER)

| ID | Kebutuhan |
|----|-----------|
| REQ-MASTER-01 | **Tipe Mesin** dikelola dari Settings: nama tampilan + kode (kode immutable setelah dipakai unit/template); tipe sistem `Umum` & `Utility` tidak dapat dihapus |
| REQ-MASTER-02 | Tipe Mesin yang masih dipakai unit atau template **tidak dapat dihapus** |
| REQ-MASTER-03 | **Lokasi / Site** dikelola dari Settings; nama unit yang diubah ikut menyesuaikan, dan lokasi yang masih dipakai unit tidak dapat dihapus |
| REQ-MASTER-04 | Form Mesin/Utility memakai **dropdown** Lokasi dari master, bukan teks bebas; nilai di luar daftar ditolak |
| REQ-MASTER-05 | Impor CSV unit otomatis menambahkan lokasi baru ke master, agar unit hasil impor tetap bisa diedit |
| REQ-MASTER-06 | Penambahan, perubahan, dan penghapusan master data tercatat di Activity Log |

### 6.17 Halaman Error (REQ-ERR)

| ID | Kebutuhan |
|----|-----------|
| REQ-ERR-01 | Status **403, 404, 405, 419, 429, 500, 503** menampilkan halaman error khusus, bukan halaman bawaan Laravel |
| REQ-ERR-02 | Halaman error memiliki **ilustrasi beranimasi** per status (gambar pembesar untuk 404, gembok untuk 403, roda gigi untuk 500, dll.) |
| REQ-ERR-03 | Halaman error memiliki **tombol X** untuk menutup pesan, dan dapat ditutup dengan tombol **Esc** |
| REQ-ERR-04 | Menutup halaman error mengembalikan pengguna ke **halaman sebelumnya**; bila halaman dibuka langsung, ke **Beranda** |
| REQ-ERR-05 | Teks error **berbahasa Indonesia**; pesan bawaan framework (bahasa Inggris) disembunyikan, pesan milik aplikasi ditampilkan |
| REQ-ERR-06 | **Error 500 tidak membocorkan detail teknis** (pesan exception, stack trace) kepada pengguna |
| REQ-ERR-07 | Permintaan **JSON/axios** tetap mendapat respons JSON, bukan halaman HTML |
| REQ-ERR-08 | **Sesi kedaluwarsa (419)** saat menekan tombol di dalam aplikasi kembali ke halaman sebelumnya dengan pesan, bukan halaman error |
| REQ-ERR-09 | Perangkat dengan pengaturan "kurangi gerakan" menampilkan halaman error **tanpa animasi** |

### 6.18 Template Komponen dari CSV (REQ-TMPL)

| ID | Kebutuhan |
|----|-----------|
| REQ-TMPL-01 | Template komponen dapat diimpor dari CSV: satu baris = satu unit, berisi **Nama Template**, **Code unit**, dan **Komponen 1..N** berurutan |
| REQ-TMPL-02 | Impor membuat/memperbarui template per **Nama Template** (cakupan Mesin, tipe mengikuti tipe unitnya) |
| REQ-TMPL-03 | **Nama komponen di CSV dipetakan ke master Komponen/Parts** yang sudah ada (perbedaan huruf besar-kecil, spasi, dan salah ketik yang jelas); sisanya dibuat sebagai komponen baru |
| REQ-TMPL-04 | Akhiran nomor baris pada kolom Code di CSV dibuang saat pencocokan unit (mis. `MC.2-TOMPB-101-16` → `MC.2-TOMPB-101`) |
| REQ-TMPL-05 | Impor bersifat **idempoten** — dijalankan ulang tidak membuat template atau komponen ganda; tersedia mode `--dry-run` (rencana saja) dan `--no-apply` (tanpa menerapkan) |
| REQ-TMPL-06 | **Terapkan ke Unit** memakai komponen template beserta **standar bawaan** per unit, dan Replacing komponen lama unit tersebut |

### 6.19 Activity Log & Backup (REQ-OPS)

| ID | Kebutuhan |
|----|-----------|
| REQ-OPS-01 | Activity Log menyimpan **pelaku, waktu, objek, perubahan, IP, dan perangkat/browseryang dipakai** |
| REQ-OPS-02 | Setiap IP diberi **label** (mis. "LAN kantor", "Gateway VPN / NAT") yang dapat dikonfigurasi; `X-Forwarded-For` dari browser **diabaikan** kecuali proxy tepercaya terdaftar |
| REQ-OPS-03 | Aksi otomatis (backup terjadwal, laporan, email background) tampil sebagai "Sistem (otomatis, tanpa perangkat)" |
| REQ-OPS-04 | Halaman Activity Log dapat mencari & memfilter aksi/pelaku/tanggal |
| REQ-OPS-05 | Admin dapat membuat, mengunduh, memulihkan, dan menghapus **backup database**; restore wajib konfirmasi nama file |
| REQ-OPS-06 | **Backup otomatis** terjadwal tiap hari pukul **02:00 WIB** dengan retensi configurable |
| REQ-OPS-07 | Seluruh waktu aplikasi memakai **WIB (UTC+7)** — server, database, dan tampilan browser |

---

## 7. Aturan Bisnis

### BR-01 — Periode PM 2 bulanan
Sistem operate dalam **6 periode**: Jan–Feb, Mar–Apr, Mei–Jun, Jul–Ags, Sep–Okt, Nov–Des.
- Periode berjalan ditentukan dari bulan saat ini.
- Periode yang dimulai setelah periode berjalan pada tahun yang sama bersifat **future** → terkunci.
- Tahun sebelum tahun berjalan tidak punya konsep "periode berjalan".

### BR-02 — Rantai persetujuan berurutan
Tahap aktif dibaca dari `PmStatus::awaiting()`:

| Status | Menunggu tahap |
|--------|----------------|
| `draft` | Technician |
| `submitted` | Pic |
| `pic_approved` | Supervisor |
| `approved` | — (final) |
| `rejected` | Technician (untuk revisi) |

Konsekuensi: tidak ada endpoint untuk "langsung setuju di tahap 3", dan urutan tahap tidak bisa diacak.

### BR-03 — Anti konflik kepentingan
- Tahap 1 (Teknisi): boleh ditandatangani oleh teknisi yang mengisi checklist itu.
- Tahap 2 & 3 (PIC/Supervisor): **dilarang** bila orang yang sama mengisi checklist.
- Admin dikecualikan di semua tahap karena tidak melakukan pekerjaan lapangan, sekaligus menjadi cadangan bila tidak ada pihak lain.

### BR-04 — Penolakan
- Hanya di tahap 2 dan 3.
- Wajib menyertakan alasan.
- Menghasilkan status `rejected` dan menaikkan `revision_count`.

### BR-05 — Kunci setelah tanda tangan
`PmStatus::isEditable()` hanya `true` untuk `draft` dan `rejected`. Setelah ditandatangani, isi checklist tidak dapat diubah sehingga persetujuan hilir selalu sesuai dengan isi yang direview.

### BR-06 — Dot notifikasi periode
| Warna | Makna |
|-------|-------|
| 🔵 biru | Periode berjalan dan masih ada mesin belum selesai |
| 🟠 oranye | Periode berjalan sudah beres tetapi periode sebelumnya belum; atau periode sebelumnya masih ada tunggakan |
| 🔴 merah | Periode lebih lama masih ada tunggakan |
| 🟢 hijau | Tidak ada tunggakan |

Pada **tahun selain tahun berjalan**, tidak ada notion "periode ini": tunggakan = merah, beres = hijau.

### BR-07 — Statistik tidak terpengaruh filter
Angka ringkasan dashboard dihitung dari seluruh mesin aktif pada periode terpilih. Jika ikut terfilter, pengguna dapat melihat angka yang berubah-ubah hanya karena sedang mencari, sehingga tidak bisa dipercaya sebagai indikator.

### BR-08 — Jejak audit tetap terbaca
`pm_signatures` menyimpan `signed_by_name` dan `signed_by_role` sebagai snapshot. `pm_records.technician_id`, `approved_by`, dan `audit_logs.user_id` memakai `nullOnDelete`. History tetap utuh meski akun dihapus.

### BR-09 — Admin tidak mengunci dirinya
Admin tidak boleh mengubah role sendiri maupun menghapus akunnya sendiri. Alasannya: satu-satunya akun admin yang kehilangan `user.manage` akan terkunci dari halaman yang mengatur role, tanpa jalur pemulihan.

### BR-10 — Permission per tahap
`pm.sign`, `pm.acknowledge`, `pm.approve` adalah tiga permission terpisah. Satu permission generik membuat role bisa melewati tahap.

---

## 8. Model Peran dan Permission

Daftar permission berasal dari `database/seeders/RoleSeeder.php`.

| Permission | Admin | Manager | Technician | User | Viewer | tech-machines | tech-utilities | tech-all |
|---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| `dashboard.view` | ✅ | ✅ | ✅ | ✅ | ✅ | — | — | — |
| `pm.fill` | ✅ | — | ✅ | — | — | ✅ | ✅ | ✅ |
| `pm.sign` | ✅ | — | ✅ | — | — | ✅ | ✅ | ✅ |
| `pm.acknowledge` | ✅ | — | — | ✅ | — | — | — | — |
| `pm.approve` | ✅ | ✅ | — | — | — | — | — | — |
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
| `pm.manage` | ✅ | — | — | — | — | — | — | — |
| `user.manage` | ✅ | — | — | — | — | — | — | — |
| `audit.view` | ✅ | ✅ | — | — | — | — | — | — |

Prinsip:
- Menu sidebar hanya menampilkan item sesuai permission, **tetapi** otorisasi sesungguhnya ditegakkan di server oleh Policy/middleware (`can_any` + `AssetScope` untuk scope mesin vs utility).
- Role `admin` memegang seluruh permission.
- Role `manager` tidak memegang `pm.fill` (tidak melakukan pekerjaan lapangan).
- `machine.view` / `utility.view` adalah **scope** halaman/daftar aset; role general
  membukanya lewat baseline `dashboard.view`.
- Role teknisi **tech-machines** / **tech-utilities** tanpa `dashboard.view` → hanya
  satu scope terbuka; kartu di luar scope pada `/form-maintenance` tampil abu-abu
  dan URL langsungnya diblokir. **tech-all** memegang `machine.manage` +
  `utility.manage` + `template.manage` + `component.manage` + `masterdata.manage`.
- **Suku Cadang** berdiri di luar scope Mesin/Utility: `sparepart.view` untuk
  **semua** role, `sparepart.manage` (CRUD + transaksi + impor) hanya Admin &
  **tech-all**.
- **Notifikasi**: `notification.manage` (matriks, jadwal, PIC perangkat, kirim
  manual) hanya **Admin**. Semua user **tetap** menerima notifikasi di Kotak Masuk
  miliknya sendiri — permission ini mengatur *pengaturan*, bukan *penerimaan*.
- **Integrate** (`integration.manage`) hanya **Admin** — karena memuat client
  secret & password SMTP.
- **Activity Log** (`activity.view`) untuk Admin & Manager. Nama permission
  sebelumnya `audit.view` sudah diganti menjadi `activity.view`.
- **Admin** dan role lain: akun Admin **tidak bisa** diubah/dihapus oleh role
  non-Admin, dan option role `admin` disembunyikan dari dropdown (ditegakkan di
  server, bukan hanya di UI).

---

## 9. Kebutuhan Non-Fungsional

| Kategori | Kebutuhan |
|----------|-----------|
| Keamanan | Hash password via cast `hashed`; CSRF pada seluruh request; session terenkripsi; otorisasi server-side (Policy + middleware); gambar tanda tangan disajikan lewat route ber-otorisasi, bukan path mentah |
| Audit | Semua aksi administratif tercatat permanen |
| Bahasa | Antarmuka dan validasi berbahasa Indonesia |
| Performa | Halaman dashboard memuat hingga ~121 mesin per periode; agregasi memakai query terindeks |
| Kompatibilitas | Chrome/Edge/Firefox versi terkini, desktop & mobile (sidebar responsif) |
| Deployment | Docker (PHP-FPM + Nginx + Vite), reproducible via single Dockerfile multi-stage |
| Skalabilitas | Skema terindeks pada kombinasi kolom yang sering difilter (`is_active`, `week_group`, `year`, `period`, `status`) |
| Observabilitas | `storage/logs` ter-bind mount dari host |

---

## 10. Model Data

Entitas utama:

```
users ──< pm_records (technician_id, pic_user_id) ──< pm_record_items
                    │                          ──< pm_record_revisions
                    │                          ──< pm_signatures
                    └ (approved_by)

assets (model Machine; Mesin + Utility) ──< pm_records
assets ──< component_machine >── components     (many-to-many, urutan per unit)
assets ──> users (pic_user_id, nullOnDelete)    ← PIC perangkat
assets ──> checklist_templates (template_id, nullOnDelete) — cadangan data lama
checklist_templates ──< checklist_template_items

users >── roles >── permissions     (Spatie: model_has_roles, roles, permissions)
users.signature_path                (PNG tanda tangan tersimpan)
audit_logs ──> users (user_id, nullOnDelete)

spare_parts ──< spare_part_movements ──> users (user_id, nullOnDelete)
```

Constraint kunci:
- `pm_records`: **unique** (`machine_id`, `year`, `period`) — satu record PM per mesin per periode.
- `pm_signatures`: **unique** (`pm_record_id`, `stage`) — satu tanda tangan per tahap.
- `assets.code`: **unique** (tabel `assets` menampung Mesin dan Utility).
- `assets`: soft delete (`deleted_at`).
- `component_machine`: **unique** (`component_id`, `machine_id`) — penugasan komponen per unit. Kolom `standard` (nullable) menyimpan nilai acuan **per unit**; bila NULL, form PM/daftar memakai `components.spec`.
- `spare_parts.code`: **unique**; soft delete (`deleted_at`).
- `spare_part_movements`: mencatat setiap transaksi stok dan mengubah `spare_parts.stock` secara atomik (`type` = `in`/`out`); `spare_parts` bersifat global, tidak ada relasi ke `assets`.

Rincian lengkap ada di [Architecture.md](Architecture.md).

---

## 11. Kriteria Penerimaan

Fitur dianggap selesai bila seluruh butir di bawah ini terpenuhi dan **diverifikasi di server**, bukan hanya di antarmuka.

### Rantai tanda tangan
- [ ] Record baru berstatus `draft`, `revision_count` = 0.
- [ ] Menandatangani tahap 1 mengubah status menjadi `submitted` dan membuat baris `pm_signatures` untuk tahap `technician`.
- [ ] Menandatangani tahap 2 → `pic_approved`; tahap 3 → `approved`.
- [ ] Percobaan melewati tahap ditolak dengan 403.
- [ ] `approved` tidak dapat diedit maupun ditandatangani ulang.
- [ ] Orang yang mengisi checklist ditolak pada tahap 2 dan 3.
- [ ] Penolakan pada tahap 2/3 menghasilkan `rejected` dan mewajibkan alasan.
- [ ] Penolakan pada tahap 1 tidak tersedia.
- [ ] Nama & peran penanda tangan tetap tampil meski akunnya dihapus.

### Manajemen pengguna
- [ ] Admin dapat membuat pengguna dan pengguna baru dapat login.
- [ ] Admin dapat mengubah role; permission efeknya langsung berlaku.
- [ ] Admin dapat mengganti password pengguna lain; password lama tidak bisa dipakai lagi.
- [ ] Role baru dari halaman Role langsung muncul di dropdown pada halaman Pengguna.
- [ ] Admin tidak dapat mengubah role sendiri maupun menghapus dirinya (ditolak di backend, bukan hanya UI).
- [ ] Manajer tanpa `user.manage` mendapat 403 di `/admin/users`.
- [ ] Audit log mencatat `user.create/update/password/delete`, **tanpa memuat nilai password**.

### Dashboard & laporan
- [ ] Setiap filter (periode, tahun, area, status, pencarian) bekerja dan dapat dikombinasikan.
- [ ] Filter diteruskan ke form PM dan tombol Kembali.
- [ ] Dot notifikasi sesuai BR-06.
- [ ] Ekspor XLSX dan PDF berhasil dan isinya sesuai filter.

### Impor & mesin
- [ ] `machines:import` idempoten — dijalankan dua kali tidak menduplikasi data.
- [ ] Baris sentinel CSV tidak diimpor.
- [ ] Mesin dapat dihapus dan dipulihkan.
- [ ] Riwayat PM tetap ada setelah mesin di-soft-delete.

### Suku cadang
- [ ] `spare-parts:import` dan unggah CSV sama-sama idempoten berdasarkan kode (baris dengan kode sama diperbarui, bukan diduplikasi).
- [ ] Transaksi barang masuk menambah stok, barang keluar mengurangi stok.
- [ ] Barang keluar melebihi stok ditolak dan stok tidak berubah.
- [ ] Filter stok menipis/habis menampilkan baris yang sesuai.
- [ ] Role tanpa `sparepart.manage` hanya bisa melihat; aksi kelola ditolak server (403).
- [ ] Hapus massal menghapus semua baris yang dipilih dan mengabaikan id yang tidak ada.

### Umum
- [ ] Tidak ada halaman yang tampil blank (React error) setelah login pada 9 route utama.
- [ ] Tidak ada error console atau respons HTTP 5xx pada seluruh halaman.
- [ ] Tidak ada halaman yang dapat diakses tanpa login.

---

## 12. Di Luar Ruang Lingkup

- Notifikasi otomatis (email/WhatsApp) untuk pengingat persetujuan atau PM jatuh tempo.
- Aplikasi mobile native.
- Integrasi ERP, sistem aset, atau sensor IoT.
- Perhitungan downtime, biaya, atau ROI maintenance.
- Multi-tenancy / multi-cabang.
- SSO / LDAP.
- Workflow approval yang dapat dikonfigurasi bebas (rantai 3 tahap bersifat tetap).

> **Catatan revisi:** manajemen suku cadang/inventaris yang semula di luar
> lingkup v1 kini **termasuk** (lihat §5 Termasuk & §6.12 REQ-SPARE). Ruang
> lingkupnya dibatasi pada master + transaksi stok masuk/keluar; perhitungan
> biaya, depresiasi, dan integrasi ERP tetap di luar lingkup.

> **Catatan revisi (v1.2):** item checklist tidak lagi bersumber dari template
> per jenis mesin, melainkan dari **master Komponen** yang ditugaskan ke tiap
> unit (§6.4 REQ-COMP); template lama hanya dipakai sebagai cadangan data yang
> belum termigrasi. Ditambahkan pula halaman **Admin PM Records** (`pm.manage`,
> §6.13) dan **tanda tangan tersimpan per akun** (§6.6 REQ-SIGN-11).

---

## 13. Risiko & Mitigasi

| Risiko | Dampak | Mitigasi |
|--------|--------|----------|
| Admin mengunci dirinya sendiri (menghapus satu-satunya akun admin) | Sistem tidak bisa dikelola | Guard berlapis: UI nonaktif + validasi backend (`BR-09`) |
| Persetujuan dianggap formalitas | Kualitas data rendah | Anti konflik kepentingan (`BR-03`) + snapshot tanda tangan |
| Checklist diubah setelah ditandatangani | Persetujuan tidak sesuai isi | `isEditable()` hanya `draft`/`rejected` (`BR-05`) |
| Tunggakan PM tidak terlihat | Mesin telat dirawat | Dot 4 warna + laporan kepatuhan |
| Penyebab data hilang | Riwayat tidak terbaca | Soft delete + snapshot audit + snapshot nama penanda tangan |
| Asset frontend tidak ter-deploy karena named volume | Halaman blank | Build + sinkronisasi asset terdokumentasi di README |
| Role atau permission berubah tanpa rencana | Akses tidak sesuai | Permission per tahap; verifikasi dengan akun uji tiap role |

---

## 14. Metrik Sukses

| Metrik | Target |
|--------|--------|
| % mesin aktif dengan PM pada periode berjalan | ≥ 95% |
| Rata-rata waktu persetujuan per tahap | ≤ 3 hari kerja |
| Record yang melewati tahap | 0 |
| Aksi admin tanpa jejak audit | 0 |
| Waktu buka halaman Beranda | ≤ 2 detik |

---

## Lampiran — Peta Requirement ke Implementasi

| Requirement | Implementasi |
|-------------|--------------|
| REQ-AUTH-* | `app/Http/Controllers/Auth/*`, `routes/auth.php` |
| REQ-DASH-* | `DashboardController`, `DashboardService`, `PeriodService` |
| REQ-MCHN-* | `MachineController`, `MachineImportService`, `Console/Commands/ImportMachinesCommand` |
| REQ-COMP-* | `Admin/ComponentController`, `Admin/Components.jsx`, `Component`, `ComponentMigrationService` |
| REQ-PM-* | `PmRecordController`, `StorePmRecordRequest`, `Actions/Pm/SubmitPmRecord` |
| REQ-SIGN-* | `Actions/Pm/SignPmRecord`, `Actions/Pm/RejectPmRecord`, `SignatureChain`, `SignatureStorage`, `PmSignatureController`, `ProfileController` (tanda tangan tersimpan) |
| REQ-APPR-* | `ApprovalController`, `Pages/Approvals/Index.jsx` |
| REQ-USER-* | `Admin/UserController`, `UserPolicy`, `Pages/Admin/Users.jsx` |
| REQ-ROLE-* | `Admin/RoleController`, `RolePolicy`, `Pages/Admin/Roles.jsx` |
| REQ-RPT-* | `ReportController`, `ReportService`, `Exports/*`, `views/reports/pdf.blade.php` |
| REQ-AUD-* | `AuditLogService`, `Admin/AuditLogController`, `Pages/Admin/AuditLogs.jsx` |
| REQ-SPARE-* | `SparePartController`, `SparePartImportService`, `Console/Commands/ImportSparePartsCommand`, `Pages/SpareParts/Index.jsx` |
| REQ-PMR-* | `PmRecordController@adminIndex/adminDestroy`, `Pages/Admin/PmRecords/Index.jsx`, permission `pm.manage` |

---

## Related Documents

- [README.md](../../README.md) — panduan setup & penggunaan
- [Architecture.md](Architecture.md) — arsitektur teknis
- [Guide.md](Guide.md) — panduan pengguna per peran
- [Agent.md](Agent.md) — instruksi kerja untuk AI coding agent
- [`references/v.1.2/`](references/v.1.2/) — arsip dokumen versi sebelumnya (superseded)
