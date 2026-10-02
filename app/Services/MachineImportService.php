<?php

namespace App\Services;

use App\Enums\MachineType;
use App\Models\ChecklistTemplate;
use App\Models\Machine;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Impor daftar mesin dari references/data.csv.
 *
 * CSV tidak punya kolom "jenis mesin" (MachineType) maupun "minggu", jadi
 * keduanya diturunkan:
 *  - type        <- kata kunci pada Machine Name (lihat TYPE_KEYWORDS)
 *  - week_group  <- angka pada Sub-Category ("C.7" -> 7)
 *  - sort_no     <- kolom NO
 */
class MachineImportService
{
    /**
     * Kata kunci ke jenis mesin. Dicek berurutan, keyword yang cocok
     * pertama menang, jadi urutannya penting:
     *  - "Timbangan - Mixer 1" harus jadi Timbangan, bukan Mixer
     *  - "TOM - Filling Otomatis" harus jadi TOM, bukan Filling
     */
    private const TYPE_KEYWORDS = [
        ['reach truck', MachineType::Vehicle],
        ['stacker', MachineType::Vehicle],
        ['coding', MachineType::Coding],
        ['inject blow', MachineType::InjectBlow],
        ['timbangan', MachineType::Timbangan],
        ['mixer', MachineType::Mixer],
        ['homogenizer', MachineType::Mixer],
        ['oilmix', MachineType::Mixer],
        ['multimix', MachineType::Mixer],
        ['plough', MachineType::Mixer],
        ['plow', MachineType::Mixer],
        ['tom', MachineType::Tom],
        ['labeling', MachineType::Labeling],
        ['label', MachineType::Labeling],
        ['filling', MachineType::Filling],
    ];

    public function csvPath(): string
    {
        return base_path('references/data.csv');
    }

    public function exists(): bool
    {
        return is_file($this->csvPath());
    }

    /**
     * @return array<int, array<string, mixed>> atribut siap simpan per mesin
     */
    public function buildRows(): array
    {
        $path = $this->csvPath();

        if (!is_file($path)) {
            throw new RuntimeException("File CSV tidak ditemukan: {$path}");
        }

        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new RuntimeException("File CSV tidak bisa dibaca: {$path}");
        }

        try {
            $header = fgetcsv($handle);

            if ($header === false) {
                return [];
            }

            // data.csv disimpan dengan BOM UTF-8, jadi nama kolom pertama
            // akan diawaliEF BB BF kalau tidak dibersihkan.
            $header = array_map(
                fn ($column) => trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $column)),
                $header
            );

            $rows = [];
            $seen = [];

            while (($line = fgetcsv($handle)) !== false) {
                if (count(array_filter($line, fn ($v) => trim((string) $v) !== '')) === 0) {
                    continue; // baris kosong di akhir file
                }

                $record = array_combine($header, array_pad($line, count($header), ''));

                if ($record === false) {
                    continue;
                }

                $record = array_map(fn ($v) => trim((string) $v), $record);

                $code = $record['Code.asset'] ?? '';

                if ($code === '') {
                    continue;
                }

                // Kode aset harus unik; kalau CSV punya duplikat, baris
                // pertama yang menang supaya tidak diam-diam menimpa.
                if (isset($seen[$code])) {
                    continue;
                }

                $seen[$code] = true;

                $subCategory = $record['Sub-Category'] ?? null;
                $name = $record['Machine Name'] ?? '';

                $rows[] = [
                    'code' => $code,
                    'name' => $name,
                    'location' => $record['LOC'] ?? null,
                    'category' => $record['Category'] ?? null,
                    'sub_category' => $subCategory,
                    'type' => self::guessType($name)->value,
                    'week_group' => self::guessWeekGroup($subCategory),
                    'sort_no' => (int) ($record['NO'] ?? 0),
                    'is_active' => true,
                ];
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    /**
     * @return array{created:int, updated:int, purged:int, total:int, types:array<string,int>, weeks:array<int,int>}
     */
    public function import(bool $purge = false): array
    {
        $rows = $this->buildRows();

        if ($rows === []) {
            throw new RuntimeException('CSV tidak berisi baris mesin.');
        }

        $templates = ChecklistTemplate::where('is_default', true)
            ->pluck('id', 'machine_type');

        $created = 0;
        $updated = 0;

        DB::transaction(function () use ($rows, $templates, &$created, &$updated) {
            foreach ($rows as $row) {
                $row['template_id'] = $templates[$row['type']] ?? null;

                // Urutan kolom harus sesuai dengan fillable Machine.
                $machine = Machine::withTrashed()
                    ->where('code', $row['code'])
                    ->first();

                if ($machine) {
                    $machine->fill($row);
                    // fill() tidak menyentuh deleted_at, jadi diisi manual
                    // supaya mesin yang pernah dinonaktifkan bisa hidup lagi.
                    $machine->deleted_at = null;

                    if ($machine->isDirty()) {
                        $machine->save();
                        $updated++;
                    }

                    continue;
                }

                Machine::create($row);
                $created++;
            }
        });

        $purged = 0;

        if ($purge) {
            // Sengaja dilakukan setelah import: kalau CSV ternyata tidak bisa
            // dibaca, daftar mesin lama tidak ikut hilang.
            $codes = array_column($rows, 'code');

            $purged = Machine::withTrashed()
                ->whereNotIn('code', $codes)
                ->forceDelete();
        }

        $types = array_count_values(array_column($rows, 'type'));
        ksort($types);

        $weeks = array_count_values(array_column($rows, 'week_group'));
        ksort($weeks);

        return [
            'created' => $created,
            'updated' => $updated,
            'purged' => $purged,
            'total' => count($rows),
            'types' => $types,
            'weeks' => $weeks,
        ];
    }

    public static function guessType(string $name): MachineType
    {
        // Keyword disimpan dalam huruf kecil, jadi nama mesin ikut diturunkan
        // lebih dulu daripada mengandalkan flag regex "i".
        $needle = mb_strtolower($name);

        foreach (self::TYPE_KEYWORDS as [$keyword, $type]) {
            // Cocok per kata, bukan substring. Tanpa \b, "tom" ikut cocok di
            // dalam "Au-tom-atic" dan "O-tom-atis" sehingga mesin Labeling
            // salah diklasifikasikan sebagai TOM.
            if (preg_match('/\b' . preg_quote($keyword) . '\b/u', $needle) === 1) {
                return $type;
            }
        }

        return MachineType::Generic;
    }

    /**
     * "C.7" -> 7, "0" / kosong / bukan angka -> 1 (nilai default skema).
     * Dijepit ke 1..9 karena week_group berupa tinyint dan form mesin
     * memvalidasi max 9.
     */
    public static function guessWeekGroup(?string $subCategory): int
    {
        if ($subCategory === null || !preg_match('/\d+/', $subCategory, $m)) {
            return 1;
        }

        return max(1, min(9, (int) $m[0]));
    }
}
