<?php

namespace App\Services;

use App\Models\SparePart;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Impor master suku cadang dari CSV (references/data-source/suku_cadang.csv
 * atau file yang diunggah lewat halaman Suku Cadang).
 *
 * Format CSV ini berasal dari spreadsheet dan sedikit "kotor", jadi parser di
 * sini sengaja toleran:
 *
 *  1. Baris header berupa SATU field ber-quote yang berisi DUA baris
 *     (baris pertama Code.asset…QTY, baris kedua STOK AKHIR…LOC). Header
 *     dilewati begitu terdeteksi memuat "Code.asset".
 *  2. Data punya 21 kolom nyata, sedangkan header menyebut 22 nama (ada
 *     kolom "Log.Barang Masuk" yang tidak punya data). Pemetaan memakai
 *     urutan kolom nyata (lihat COLUMNS).
 *  3. Sebagian baris punya koma tanpa tanda kutip di dalam nama barang,
 *     sehingga kolomnya bergeser satu. Baris 22 kolom diperlakukan seperti
 *     itu dan dua kolom pertama nama digabung kembali.
 *
 * Pemetaan 21 kolom (posisi -> kolom tabel) mengikuti makna tiap kolom:
 * Code.asset, CATEGORY DEVICE, Suku Cadang, MEREK, TYPE, Desc,
 * Last - Modified, Ket. Last - Modified, UNIT, QTY, STOK AKHIR,
 * BARANG KELUAR, Log.Barang Keluar, BARANG MASUK, STOK AWAL, MIN,
 * SUPLIER, Kebutuhan Untuk, Penjelasan, Code.loc, LOC.
 */
class SparePartImportService
{
    /**
     * Urutan kolom nyata pada CSV, dipetakan ke kolom tabel `spare_parts`.
     *
     * @var array<int, string>
     */
    private const COLUMNS = [
        'code', 'category', 'name', 'brand', 'type', 'description',
        'last_modified', 'last_modified_note', 'unit', 'qty',
        'stock', 'stock_out', 'stock_out_log', 'stock_in', 'stock_start',
        'min_stock', 'supplier', 'needed_for', 'note',
        'location_code', 'location',
    ];

    private const INTEGER_COLUMNS = ['qty', 'stock', 'stock_out', 'stock_in', 'stock_start', 'min_stock'];

    public function defaultPath(): string
    {
        $preferred = base_path('references/data-source/suku_cadang.csv');

        return is_file($preferred) ? $preferred : base_path('references/suku_cadang.csv');
    }

    public function defaultExists(): bool
    {
        return is_file($this->defaultPath());
    }

    /**
     * Impor dari file CSV bawaan.
     *
     * @return array{created:int, updated:int, purged:int, total:int, skipped:int, path:string}
     */
    public function importDefault(bool $purge = false): array
    {
        return $this->importFromPath($this->defaultPath(), $purge);
    }

    /**
     * @return array{created:int, updated:int, purged:int, total:int, skipped:int, path:string}
     */
    public function importFromPath(string $path, bool $purge = false): array
    {
        $rows = $this->parse($path);

        if ($rows === []) {
            throw new RuntimeException('CSV tidak berisi baris suku cadang yang bisa dibaca.');
        }

        $created = 0;
        $updated = 0;

        DB::transaction(function () use ($rows, &$created, &$updated) {
            foreach ($rows as $index => $row) {
                $row['sort_no'] = $index + 1;

                $part = SparePart::withTrashed()->where('code', $row['code'])->first();

                if ($part) {
                    $part->fill($row);
                    $part->deleted_at = null;

                    if ($part->isDirty()) {
                        $part->save();
                        $updated++;
                    }

                    continue;
                }

                SparePart::create($row);
                $created++;
            }
        });

        $purged = 0;

        if ($purge) {
            $codes = array_column($rows, 'code');
            $purged = SparePart::withTrashed()->whereNotIn('code', $codes)->forceDelete();
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'purged' => $purged,
            'total' => count($rows),
            'skipped' => max(0, $this->lastSkipped),
            'path' => $path,
        ];
    }

    /** Jumlah baris yang dilewati karena tidak bisa dipetakan pada parse terakhir. */
    private int $lastSkipped = 0;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function parse(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("File CSV tidak ditemukan: {$path}");
        }

        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new RuntimeException("File CSV tidak bisa dibaca: {$path}");
        }

        // BOM UTF-8 tepat di depan tanda kutip pembuka membuat fgetcsv tidak
        // mengenali kutip tersebut, sehingga header dua-baris yang dibungkus
        // satu tanda kutip terbelah menjadi dua record (dan baris keduanya
        // "STOK AKHIR…" ikut terbaca sebagai data). Buang BOM lebih dulu.
        if (fread($handle, 3) !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $this->lastSkipped = 0;
        $rows = [];
        $seen = [];

        try {
            while (($fields = fgetcsv($handle)) !== false) {
                // BOM UTF-8 pada kolom pertama.
                if (isset($fields[0])) {
                    $fields[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $fields[0]);
                }

                // Baris header (dua-baris ber-quote maupun satu baris biasa).
                if ($this->isHeaderRow($fields)) {
                    continue;
                }

                $row = $this->mapRow($fields);

                if ($row === null) {
                    if (count(array_filter($fields, fn ($v) => trim((string) $v) !== '')) > 0) {
                        $this->lastSkipped++;
                    }

                    continue;
                }

                // Kode harus unik; kalau CSV punya duplikat, baris pertama menang.
                if (isset($seen[$row['code']])) {
                    continue;
                }

                $seen[$row['code']] = true;

                $rows[] = $row;
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  array<int, string|null>  $fields
     */
    private function isHeaderRow(array $fields): bool
    {
        // Header dua baris ber-quote datang sebagai satu field yang memuat
        // seluruh nama kolom; header satu baris memuat "Code.asset" di kolom
        // pertama. Kalau BOM sempat memecah header, baris keduanya mulai dari
        // "STOK AKHIR" — itu pun dilewati.
        $joined = strtolower(implode(' ', array_map(fn ($v) => (string) $v, $fields)));

        return str_contains($joined, 'code.asset')
            || str_contains($joined, 'suku cadang')
            || str_contains($joined, 'stok akhir');
    }

    /**
     * @param  array<int, string|null>  $fields
     * @return array<string, mixed>|null
     */
    private function mapRow(array $fields): ?array
    {
        $fields = array_map(fn ($v) => trim((string) $v), $fields);

        // Buang kolom kosong di ujung kanan; karena tidak membawa informasi
        // dan bisa mengacaukan penghitungan kolom.
        while ($fields !== [] && end($fields) === '') {
            array_pop($fields);
        }

        if ($fields === []) {
            return null;
        }

        // Koma tanpa kutip di nama barang memecah kolom jadi dua; gabungkan
        // kembali kolom ke-3 dan ke-4 lalu teruskan sisanya.
        if (count($fields) === 22) {
            $fields = array_merge(
                array_slice($fields, 0, 2),
                [$fields[2].', '.$fields[3]],
                array_slice($fields, 4),
            );
        }

        if (count($fields) > 21) {
            return null; // tidak bisa dipetakan dengan aman
        }

        $fields = array_pad($fields, 21, '');

        $row = array_combine(self::COLUMNS, $fields);

        if ($row === false || $row['code'] === '' || $row['name'] === '') {
            return null;
        }

        foreach (self::INTEGER_COLUMNS as $column) {
            $row[$column] = $this->toInt($row[$column]);
        }

        return $row;
    }

    private function toInt(?string $value): int
    {
        $value = trim((string) $value);

        if ($value === '') {
            return 0;
        }

        // Ambil angka di depan saja (mis. "1 pcs" -> 1).
        if (preg_match('/-?\d+/', $value, $m) === 1) {
            return (int) $m[0];
        }

        return 0;
    }
}
