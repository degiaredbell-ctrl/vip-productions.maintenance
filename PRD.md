# PRD — PM Preventive System

**Produk:** Preventive Maintenance System
**Judul aplikasi:** PM Preventive
**Client:** Verra Inter Pangan / Redbell Group
**Versi dokumen:** 1.0
**Status:** Implementasi berjalan (MVP selesai)

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
| **Admin** | Administrator sistem | Mengelola seluruh data & akun | CRUD mesin, template, role, pengguna, ganti password user, baca audit log |
| **Manager / Atasan** | Atasan unit | Memastikan PM dikerjakan & menyetujui di tahap akhir | Lihat Beranda, isi mesin & template, baca riwayat, laporan + ekspor, audit log, persetujuan tahap 3 |
| **Technician / Pemeriksa** | Teknisi lapangan | Melakukan & mendokumentasikan pemeriksaan | Isi checklist, tanda tangan tahap 1, riwayat, laporan + ekspor |
| **User PIC** | Penanggung jawab unit | Mengetahui hasil pemeriksaan tim | Persetujuan tahap 2, riwayat, lihat laporan |
| **Viewer** | Auditor /_management | Hanya melihat | Lihat Beranda, riwayat, laporan + ekspor |

---

## 5. Ruang Lingkup

### Termasuk (v1)
- Autentikasi, profil, ganti password sendiri
- Dashboard dengan filter periode/tahun/area/status/pencarian + notifikasi tunggakan
- CRUD mesin (soft delete + restore)
- Template checklist per jenis mesin
- Pengisian checklist PM dengan aksi per item
- Rantai tanda tangan 3 tahap (gambar) + penolakan + revisi
- Halaman Persetujuan dengan badge antrean
- Manajemen pengguna (CRUD + ganti password) oleh Admin
- Manajemen role & permission oleh Admin
- Audit log
- Laporan kepatuhan, top part, ringkasan aksi
- Ekspor XLSX & PDF
- Impor mesin dari CSV

### Tidak Termasuk (v1)
- Notifikasi email/WhatsApp otomatis
- Aplikasi mobile native
- Integrasi dengan sistem ERP / sensor IoT
- Perhitungan downtime, spare part inventory, atau biaya maintenance

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

### 6.4 Template Checklist (REQ-TMPL)

| ID | Kebutuhan |
|----|-----------|
| REQ-TMPL-01 | Template checklist dapat dibuat untuk suatu jenis mesin |
| REQ-TMPL-02 | Setiap item template memiliki kategori, nama, spesifikasi, dan urutan |
| REQ-TMPL-03 | Satu template per jenis mesin ditandai sebagai default |
| REQ-TMPL-04 | Template beserta itemnya dapat dihapus |
| REQ-TMPL-05 | Form PM me-generate item checklist dari template default sesuai jenis mesin |

### 6.5 Pengisian PM (REQ-PM)

| ID | Kebutuhan |
|----|-----------|
| REQ-PM-01 | Technisi dapat membuka form PM untuk mesin + periode + tahun tertentu |
| REQ-PM-02 | Item checklist memuat nilai aktual, aksi (bersihkan/perbaiki/lumasi/ganti), kondisi akhir, dan jumlah part |
| REQ-PM-03 | Record dapat menyimpan catatan umum |
| REQ-PM-04 | Form yang sudah memiliki record menampilkan data sebelumnya, bukan template kosong |
| REQ-PM-05 | Periode terkunci/masa depan tidak dapat diisi |
| REQ-PM-06 | Checklist hanya dapat diubah saat status `draft` atau `rejected` |
| REQ-PM-07 | Setiap record PM unik per kombinasi mesin + tahun + periode |

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

### 6.7 Persetujuan (REQ-APPR)

| ID | Kebutuhan |
|----|-----------|
| REQ-APPR-01 | Halaman Persetujuan menampilkan record yang menunggu tindakan user sesuai permission-nya |
| REQ-APPR-02 | Badge antrean pada sidebar menggunakan query identik dengan daftar halaman |
| REQ-APPR-03 | Detail record menampilkan stepper 3 tahap dengan status tercentang |
| REQ-APPR-04 | User tanpa permission untuk suatu tahap tidak melihat aksi tanda tangan pada tahap tersebut |

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

| ID | Kebutuhan |
|----|-----------|
| REQ-RPT-01 | Laporan menampilkan kepatuhan (total, selesai, sedang approval, %) per periode |
| REQ-RPT-02 | Laporan menampilkan 5 part yang paling sering diganti |
| REQ-RPT-03 | Laporan menampilkan ringkasan aksi (bersihkan/perbaiki/lumasi/ganti) |
| REQ-RPT-04 | Laporan dapat difilter tahun dan periode |
| REQ-RPT-05 | Laporan dapat diekspor ke XLSX dan PDF |

### 6.11 Audit Log (REQ-AUD)

| ID | Kebutuhan |
|----|-----------|
| REQ-AUD-01 | Aksi penting pada mesin (CRUD + restore), siklus PM (submit/sign/reject), dan pengguna tercatat di audit log |
| REQ-AUD-02 | Audit log menyimpan pelaku, waktu, subject, perubahan, dan IP |
| REQ-AUD-03 | Perubahan nilai.before-after dicatat dalam bentuk JSON |
| REQ-AUD-04 | Halaman audit log menampilkan 100 log terbaru |
| REQ-AUD-05 | Hanya permission `audit.view` dapat membuka halaman audit log |

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

Prinsip:
- Menu sidebar hanya menampilkan item sesuai permission, **tetapi** otorisasi sesungguhnya ditegakkan di server oleh Policy.
- Role `admin` memegang seluruh permission.
- Role `manager` tidak memegang `pm.fill` (tidak melakukan pekerjaan lapangan).

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
users ──< pm_records (technician_id) ──< pm_record_items
                    │                ──< pm_record_revisions
                    │                ──< pm_signatures
                    └ (approved_by)

machines ──< pm_records            checklist_templates ──< checklist_template_items
machines ──> checklist_templates   (template_id, nullOnDelete)

users >── roles >── permissions     (Spatie: model_has_roles, roles, permissions)
audit_logs ──> users (user_id, nullOnDelete)
```

Constraint kunci:
- `pm_records`: **unique** (`machine_id`, `year`, `period`) — satu record PM per mesin per periode.
- `pm_signatures`: **unique** (`pm_record_id`, `stage`) — satu tanda tangan per tahap.
- `machines.code`: **unique**.
- `machines`: soft delete (`deleted_at`).

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

### Umum
- [ ] Tidak ada halaman yang tampil blank (React error) setelah login pada 9 route utama.
- [ ] Tidak ada error console atau respons HTTP 5xx pada seluruh halaman.
- [ ] Tidak ada halaman yang dapat diakses tanpa login.

---

## 12. Di Luar Ruang Lingkup

- Notifikasi otomatis (email/WhatsApp) untuk pengingat persetujuan atau PM jatuh tempo.
- Aplikasi mobile native.
- Integrasi ERP, sistem aset, atau sensor IoT.
- Manajemen spare part & inventaris.
- Perhitungan downtime, biaya, atau ROI maintenance.
- Multi-tenancy / multi-cabang.
- SSO / LDAP.
- Workflow approval yang dapat dikonfigurasi bebas (rantai 3 tahap bersifat tetap).

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
| REQ-TMPL-* | `Admin/TemplateController`, `Admin/Templates.jsx` |
| REQ-PM-* | `PmRecordController`, `StorePmRecordRequest`, `Actions/Pm/SubmitPmRecord` |
| REQ-SIGN-* | `Actions/Pm/SignPmRecord`, `Actions/Pm/RejectPmRecord`, `SignatureChain`, `SignatureStorage`, `PmSignatureController` |
| REQ-APPR-* | `ApprovalController`, `Pages/Approvals/Index.jsx` |
| REQ-USER-* | `Admin/UserController`, `UserPolicy`, `Pages/Admin/Users.jsx` |
| REQ-ROLE-* | `Admin/RoleController`, `RolePolicy`, `Pages/Admin/Roles.jsx` |
| REQ-RPT-* | `ReportController`, `ReportService`, `Exports/*`, `views/reports/pdf.blade.php` |
| REQ-AUD-* | `AuditLogService`, `Admin/AuditLogController`, `Pages/Admin/AuditLogs.jsx` |

---

## Related Documents

- [README.md](README.md) — panduan setup &uso
- [Architecture.md](Architecture.md) — arsitektur teknis