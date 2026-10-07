<?php

namespace App\Services;

use App\Enums\MachineType;
use App\Models\ChecklistTemplate;
use App\Models\ChecklistTemplateItem;
use App\Models\Machine;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Impor daftar mesin + utility dari references/.
 *
 * Mesin datang dari references/data.csv dan utility dari
 * references/utility.csv. Tidak ada kolom "jenis" maupun "minggu" di kedua
 * CSV, jadi keduanya diturunkan:
 *  - type        <- kata kunci pada Machine Name (lihat TYPE_KEYWORDS),
 *                   kecuali baris utility yang sudah jelas type-nya
 *  - week_group  <- angka pada Sub-Category ("C.7" / "W.9" -> 7 / 9)
 *  - sort_no     <- kolom NO
 *
 * Komponen maintenance per unit utility dibaca dari
 * references/utility_components.csv. Tiap unit mendapat satu ChecklistTemplate
 * tersendiri (machine_type = "utility:{code}") supaya isi form PM mengikuti
 * daftar komponen unit tersebut, bukan template generik.
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

    /**
     * Kata kunci komponen utility -> kategori. Dicek berurutan, yang pertama
     * cocok menang. Urutan sengaja dibuat seperti ini supaya komponen majemuk
     * ("Refrigerant Pressure", "Baut/Kabel", "Sensor Safety") jatuh ke kategori
     * yang paling bermakna, bukan ke kata yang kebetulan ketemu lebih dulu.
     *
     * @var array<int, array{0: string, 1: string}>
     */
    private const COMPONENT_KEYWORDS = [
        ['limit switch', 'Keselamatan'],
        ['sensor safety', 'Keselamatan'],
        ['sensor kaki', 'Keselamatan'],
        ['emergency', 'Keselamatan'],
        ['grease', 'Pelumasan'],
        ['pelumasan', 'Pelumasan'],
        ['lubrika', 'Pelumasan'],
        ['oli', 'Pelumasan'],
        ['bahan bakar', 'Pelumasan'],
        ['air radiator', 'Pelumasan'],
        ['pressure', 'Instrumen'],
        ['pessure', 'Instrumen'],
        ['gauge', 'Instrumen'],
        ['ph', 'Instrumen'],
        ['conductiv', 'Instrumen'],
        ['resistansi', 'Instrumen'],
        ['monitor', 'Instrumen'],
        ['radar pelampung', 'Instrumen'],
        ['level air', 'Instrumen'],
        ['layar', 'Instrumen'],
        ['status', 'Kondisi'],
        ['visual', 'Kondisi'],
        ['suara', 'Kondisi'],
        ['noise', 'Kondisi'],
        ['auto drain', 'Filter'],
        ['refrigerant', 'Filter'],
        ['filter', 'Filter'],
        ['bearing', 'Mekanik'],
        ['motor', 'Mekanik'],
        ['kipas', 'Mekanik'],
        ['kompressor', 'Mekanik'],
        ['compressor', 'Mekanik'],
        ['cooling fan', 'Mekanik'],
        ['baling', 'Mekanik'],
        ['sprinkler', 'Mekanik'],
        ['pompa', 'Mekanik'],
        ['pump', 'Mekanik'],
        ['tali', 'Mekanik'],
        ['seling', 'Mekanik'],
        ['kondensor', 'Mekanik'],
        ['condensor', 'Mekanik'],
        ['gasket', 'Mekanik'],
        ['seal', 'Mekanik'],
        ['membran', 'Mekanik'],
        ['selang', 'Mekanik'],
        ['sambungan', 'Mekanik'],
        ['baut', 'Mekanik'],
        ['power input', 'Listrik'],
        ['neutral', 'Listrik'],
        ['panel', 'Listrik'],
        ['konektor', 'Listrik'],
        ['kabel', 'Listrik'],
        ['mccb', 'Listrik'],
        ['mcb', 'Listrik'],
        ['kontaktor', 'Listrik'],
        ['relay', 'Listrik'],
        ['rellay', 'Listrik'],
        ['fuse', 'Listrik'],
        ['timer', 'Listrik'],
        ['thermal', 'Listrik'],
        ['breaker', 'Listrik'],
        ['inverter', 'Listrik'],
        ['pilot lamp', 'Listrik'],
        ['lampu', 'Listrik'],
        ['switch', 'Listrik'],
        ['push button', 'Listrik'],
        ['potentiometer', 'Listrik'],
        ['baterai', 'Listrik'],
        ['terminal', 'Listrik'],
        ['terminasi', 'Listrik'],
        ['kutub', 'Listrik'],
        ['kapasitor', 'Listrik'],
        ['selenoid', 'Listrik'],
        ['solenoid', 'Listrik'],
        ['phasa', 'Listrik'],
        ['phase', 'Listrik'],
    ];

    public function csvPath(): string
    {
        return base_path('references/data.csv');
    }

    public function utilityCsvPath(): string
    {
        return base_path('references/utility.csv');
    }

    public function utilityComponentsCsvPath(): string
    {
        return base_path('references/utility_components.csv');
    }

    public function exists(): bool
    {
        return is_file($this->csvPath())
            && is_file($this->utilityCsvPath())
            && is_file($this->utilityComponentsCsvPath());
    }

    /**
     * @return array<int, array<string, mixed>> atribut siap simpan per mesin
     */
    public function buildRows(): array
    {
        $rows = [];
        $seen = [];

        // Mesin dulu, seperti urutan visual di UI; utility menyusul dengan
        // type yang sudah terkunci (bukan ditebak dari nama).
        foreach ([
            $this->csvPath() => null,
            $this->utilityCsvPath() => MachineType::Utility,
        ] as $path => $fixedType) {
            array_push($rows, ...$this->parseFile($path, $fixedType, $seen));
        }

        return $rows;
    }

    /**
     * @return array{created:int, updated:int, purged:int, total:int, types:array<string,int>, weeks:array<int,int>}
     */
    public function import(bool $purge = false): array
    {
        $rows = $this->buildRows();

        if ($rows === []) {
            throw new RuntimeException('CSV tidak berisi baris mesin/utility.');
        }

        $templates = ChecklistTemplate::where('is_default', true)
            ->pluck('id', 'machine_type');

        $components = $this->buildComponentsMap();

        $created = 0;
        $updated = 0;

        DB::transaction(function () use ($rows, $templates, $components, &$created, &$updated) {
            foreach ($rows as $row) {
                if ($row['type'] === MachineType::Utility->value) {
                    $row['template_id'] = $this->syncUtilityTemplate(
                        $row['code'],
                        $row['name'],
                        $components[$row['code']] ?? [],
                    );
                } else {
                    $row['template_id'] = $templates[$row['type']] ?? null;
                }

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

    /**
     * Baris pengenal sheet ("MASTER") dari CSV, bukan mesin sungguhan.
     */
    public static function isSentinelRow(?string $name): bool
    {
        return mb_strtolower(trim((string) $name)) === 'master';
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
            if (preg_match('/\b'.preg_quote($keyword).'\b/u', $needle) === 1) {
                return $type;
            }
        }

        return MachineType::Generic;
    }

    /**
     * "C.7" -> 7, "W.9" -> 9, "0" / kosong / bukan angka -> 1 (nilai default
     * skema). Dijepit ke 1..9 karena week_group berupa tinyint dan form mesin
     * memvalidasi max 9.
     */
    public static function guessWeekGroup(?string $subCategory): int
    {
        if ($subCategory === null || ! preg_match('/\d+/', $subCategory, $m)) {
            return 1;
        }

        return max(1, min(9, (int) $m[0]));
    }

    /**
     * Satu CSV utama + satu CSV utility, formatnya sama:
     * NO,Code.asset,Machine Name,LOC,Category,Sub-Category.
     *
     * @param  array<string, bool>  $seen  kode yang sudah dipakai, dibagi antar file
     * @return array<int, array<string, mixed>>
     */
    private function parseFile(string $path, ?MachineType $fixedType, array &$seen): array
    {
        if (! is_file($path)) {
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

            // CSV disimpan dengan BOM UTF-8, jadi nama kolom pertama
            // akan diawali EF BB BF kalau tidak dibersihkan.
            $header = array_map(
                fn ($column) => trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $column)),
                $header
            );

            $rows = [];

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

                // Kode aset harus unik di seluruh daftar; kalau CSV punya
                // duplikat, baris pertama yang menang supaya tidak diam-diam
                // menimpa.
                if (isset($seen[$code])) {
                    continue;
                }

                $seen[$code] = true;

                $subCategory = $record['Sub-Category'] ?? null;
                $name = $record['Machine Name'] ?? '';

                // Baris sentinel hanya ada di CSV mesin: namanya persis
                // "MASTER" dengan Sub-Category "0", bukan "C.<n>". Ini
                // pengenal sheet, bukan alat.
                if ($fixedType === null && self::isSentinelRow($name)) {
                    continue;
                }

                $rows[] = [
                    'code' => $code,
                    'name' => $name,
                    'location' => $record['LOC'] ?? null,
                    'category' => $record['Category'] ?? null,
                    'sub_category' => $subCategory,
                    'type' => $fixedType?->value ?? self::guessType($name)->value,
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
     * Komponen maintenance utility. File helaian (TSV) berformat lebar:
     * kolom pertama kode unit, lalu nama, lalu grup ("U"), sisanya komponen.
     * Dikembalikan sebagai [kode => array{0: nama, 1: kategori}].
     *
     * @return array<string, array<int, array{0: string, 1: string}>>
     */
    private function buildComponentsMap(): array
    {
        $path = $this->utilityComponentsCsvPath();

        if (! is_file($path)) {
            return [];
        }

        $handle = fopen($path, 'r');

        if ($handle === false) {
            return [];
        }

        $map = [];

        try {
            while (($line = fgetcsv($handle, 0, "\t")) !== false) {
                $code = trim((string) ($line[0] ?? ''));

                // Baris komentar (#) dan baris kosong diabaikan.
                if ($code === '' || str_starts_with($code, '#')) {
                    continue;
                }

                $map[$code] = [];

                foreach (array_slice($line, 3) as $component) {
                    $name = trim((string) $component);

                    if ($name === '') {
                        continue;
                    }

                    $map[$code][] = [$name, self::categorizeComponent($name)];
                }
            }

            return $map;
        } finally {
            fclose($handle);
        }
    }

    /**
     * Template checklist khusus satu unit utility. Per unit mendapat templatenya
     * sendiri dengan isi sesuai komponen CSV; bukan template per jenis seperti
     * mesin, karena komponen utility berbeda tiap unit.
     */
    private function syncUtilityTemplate(string $code, string $name, array $components): int
    {
        $template = ChecklistTemplate::updateOrCreate(
            ['machine_type' => 'utility:'.$code],
            ['name' => "Utility {$name} ({$code})", 'is_default' => false],
        );

        $template->items()->delete();

        foreach ($components as $i => [$component, $category]) {
            ChecklistTemplateItem::create([
                'template_id' => $template->id,
                'category' => $category,
                'name' => $component,
                'spec' => null,
                'sort_no' => $i + 1,
            ]);
        }

        return (int) $template->id;
    }

    /**
     * Kategorikan nama komponen menjadi grup yang sama dengan kategori
     * checklist di form PM (dipakai untuk pengelompokan tampilan).
     */
    public static function categorizeComponent(string $component): string
    {
        $needle = mb_strtolower(trim($component));

        if ($needle === '') {
            return 'Umum';
        }

        foreach (self::COMPONENT_KEYWORDS as [$keyword, $category]) {
            if (str_contains($needle, $keyword)) {
                return $category;
            }
        }

        return 'Umum';
    }
}
