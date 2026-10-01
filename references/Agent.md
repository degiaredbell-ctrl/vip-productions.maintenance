# Agent.md — Panduan untuk AI Coding Agent

Dokumen ini adalah instruksi kerja untuk agent (Claude Code, Cursor, Copilot, dll.) yang mengerjakan repo **PM Preventive**. Baca `PRD.md` (apa yang dibangun) dan `Architecture.md` (bagaimana dibangun) sebelum mengubah kode.

## 1. Konteks Proyek

Aplikasi web maintenance mesin pabrik (PM berkala per 2 bulan, checklist per tipe mesin, riwayat, laporan). Pengguna utama teknisi di ponsel. Bahasa UI: **Bahasa Indonesia**. Zona waktu: **Asia/Jakarta**.

Stack: Laravel 11 · Inertia.js + React 18 · Tailwind CSS · MySQL (Laragon) · Vite · Pest.

Role: `admin`, `manager`, `technician`, `user`, `viewer`.

## 2. Perintah Penting

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed          # reset total: php artisan migrate:fresh --seed
npm run dev                         # Vite dev server
php artisan serve                   # atau pm-preventive.test via Laragon
php artisan test                    # Pest
./vendor/bin/pint                   # format PHP
npm run build                       # cek build produksi sebelum selesai
```

Jalankan `php artisan test` dan `npm run build` sebelum menyatakan tugas selesai.

## 3. Aturan Arsitektur

1. **Controller tipis.** Validasi di `FormRequest`, otorisasi di `Policy`, logika bisnis di `app/Actions` atau `app/Services`.
2. **Otorisasi selalu di server.** Menyembunyikan tombol di React bukan pengganti `authorize()`/Policy/middleware `can:`.
3. **Jangan menulis SQL mentah** kecuali perlu; pakai Eloquent, hindari N+1 (`with()`, `withCount()`).
4. **Operasi tulis multi-tabel** dibungkus `DB::transaction`.
5. **Data riwayat tidak boleh berubah** karena edit template: `pm_record_items` menyimpan salinan nama/spesifikasi.
6. **Satu record per (machine_id, year, period)** — jaga unique key; revisi = update + `pm_record_revisions`.
7. **Periode masa depan tidak boleh diisi** (validasi server lewat `PeriodService`), sama seperti prototipe.
8. Gunakan **Enum** (`Role`, `PmStatus`, `MachineType`, `Period`) alih-alih string literal tersebar.
9. Props Inertia dibentuk lewat **API Resource**, jangan mengirim model mentah (bocor kolom sensitif).
10. Jangan menyimpan rahasia di repo. Seeder akun demo hanya untuk lingkungan lokal.

## 4. Konvensi Kode

**PHP / Laravel**
- PSR-12 via Pint. Tipe return dan parameter eksplisit.
- Nama: `StorePmRecordRequest`, `PmRecordPolicy`, `SubmitPmRecord` (Action, satu method `handle`).
- Migration kecil dan reversible; jangan edit migration yang sudah dipakai di produksi — buat baru.
- Pesan validasi dalam Bahasa Indonesia (`lang/id`).

**React / JS**
- Functional component + hooks. Satu komponen per file, PascalCase.
- Halaman di `resources/js/Pages`, komponen UI reusable di `resources/js/Components/ui`.
- Form pakai `useForm` Inertia. Navigasi pakai `<Link>`/`router`, bukan `<a>`/`fetch` manual.
- Cek izin di UI dengan helper `can('pm.fill')` yang dibaca dari `usePage().props.auth.can`.
- Jangan memakai `localStorage` untuk data bisnis; hanya preferensi UI ringan dan antrean offline (IndexedDB).

**Tailwind**
- Gunakan token di `tailwind.config.js` (`bg-neu-bg`, `shadow-neu-up`, `shadow-neu-in`, `rounded-neu`). Jangan menulis hex atau box-shadow ad-hoc di komponen.
- Mobile-first: tulis gaya dasar untuk ponsel, tambahkan `lg:` untuk desktop.

## 5. Panduan Desain — Minimalist Neumorphism Light

Referensi visual: `pm-preview.html`. Patuhi aturan berikut:

| Aturan | Detail |
|---|---|
| Latar & permukaan | Satu warna dasar `#E8ECF1`; kartu, tombol, dan input memakai warna yang sama. Kedalaman hanya dari bayangan |
| Timbul vs tenggelam | Timbul = kartu/tombol/chip (`shadow-neu-up`/`-sm`). Tenggelam = input, track progres, item aktif/ditekan (`shadow-neu-in`) |
| Aksen | Satu warna: hijau `#0F6E56` (aksi utama, ring progres). Tombol primer boleh solid aksen dengan teks putih |
| Status | Selesai = hijau, Belum = amber, Kendala = merah — **selalu disertai label teks** |
| Tipografi | Plus Jakarta Sans; isi 14–15px, label kecil ≥ 11–12px; teks sekunder `#5A6475` |
| Radius | Kartu 22px, kontrol 14px; konsisten |
| Target sentuh | ≥ 44px tinggi untuk semua elemen interaktif |
| Navigasi | `< lg`: bottom nav fixed + safe-area. `≥ lg`: sidebar |
| Gerak | Seminimal mungkin, hormati `prefers-reduced-motion` |

Hindari: bayangan berlebihan di daftar panjang (berat di Android murah), teks abu-abu terang di atas latar terang, informasi yang hanya dibedakan lewat bayangan.

## 6. Hak Akses (acuan cepat)

| Aksi | admin | manager | technician | user | viewer |
|---|:-:|:-:|:-:|:-:|:-:|
| Lihat dashboard/riwayat/laporan | ✔ | ✔ | ✔ | ✔ | ✔ |
| Isi/revisi checklist | ✔ | – | ✔ (miliknya) | – | – |
| Setujui/tolak PM | ✔ | ✔ | – | – | – |
| Ekspor | ✔ | ✔ | ✔ | – | ✔ |
| CRUD mesin & template | ✔ | ✔ | – | – | – |
| CRUD pengguna | ✔ | – | – | – | – |

Setiap fitur baru **wajib** menambah permission, Policy, dan feature test per role.

## 7. Alur Kerja Agent

1. Baca file terkait (`PRD.md`, `Architecture.md`, kode yang akan diubah). Jangan menebak struktur.
2. Rencanakan perubahan kecil dan terfokus; satu tugas = satu perubahan logis.
3. Implementasi: migration → model → request/policy → action/service → controller → halaman React.
4. Tulis/ubah test (Pest) untuk perilaku baru, termasuk kasus **ditolak** untuk role tanpa izin.
5. Jalankan `./vendor/bin/pint`, `php artisan test`, `npm run build`.
6. Ringkas perubahan: file yang disentuh, keputusan penting, hal yang belum selesai.

## 8. Larangan

- Jangan mengubah `.env`, kredensial, atau data produksi.
- Jangan menghapus atau melemahkan Policy/middleware demi membuat test lulus.
- Jangan menambah dependency besar tanpa alasan; sebutkan alasannya di ringkasan.
- Jangan menjalankan `migrate:fresh` di lingkungan selain lokal.
- Jangan menghapus data riwayat PM; gunakan soft delete/arsip.
- Jangan memakai `dd()`, `console.log` sisa debug pada hasil akhir.

## 9. Definition of Done

- [ ] Sesuai PRD dan aturan di dokumen ini
- [ ] Otorisasi server + test per role lulus
- [ ] Validasi dan pesan error berbahasa Indonesia
- [ ] Tampil benar di lebar 360px dan ≥ 1024px; kontras dan fokus keyboard terjaga
- [ ] `pint`, `php artisan test`, `npm run build` bersih
- [ ] Migration dapat dijalankan dari nol (`migrate:fresh --seed`)

## 10. Backlog Awal (urutan disarankan)

1. Setup Laravel 11 + Breeze React + spatie permission + token Tailwind neumorphism
2. Enum, migration, seeder role/permission/mesin/template (porting dari `DEFAULT_MACHINES` & `DEFAULT_TPL`)
3. `AppLayout` (sidebar + bottom nav) dan komponen `Neu*`
4. Dashboard (periode, tahun, cari, filter, ring progres)
5. Form checklist + simpan/revisi + `PeriodService` (kunci periode masa depan)
6. Persetujuan PM, riwayat mesin
7. Laporan + ekspor Excel/PDF
8. Admin: mesin, template, pengguna, audit log
9. PWA + offline queue, UAT di perangkat nyata
