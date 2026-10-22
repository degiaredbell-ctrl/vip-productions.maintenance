<?php

namespace App\Services;

use App\Models\ChecklistTemplate;
use App\Models\Component;
use App\Models\Machine;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Impor Template komponen Mesin dari CSV (references/data-source/templates/
 * Template_Komponen_Mesin.csv): satu baris = satu unit, berisi Nama Template,
 * Code unit, dan Komponen 1..N berurutan.
 *
 * - Template dibuat/diperbarui per "Nama Template" (cakupan Mesin, tipe
 *   mengikuti tipe unitnya).
 * - Nama komponen disesuaikan ke master Komponen / Parts: beda huruf besar/
 *   kecil, spasi, dan salah ketik yang jelas dipetakan ke komponen yang sudah
 *   ada (ALIASES), sisanya dibuat sebagai komponen baru (NEW_COMPONENTS).
 * - Code di CSV memakai akhiran nomor baris ("MC.2-TOMPB-101-16"); akhiran itu
 *   dibuang untuk mencari unit ("MC.2-TOMPB-101").
 * - Template diterapkan ke unitnya lewat TemplateService (sama dengan tombol
 *   "Terapkan ke Unit").
 */
class TemplateCsvImportService
{
    /** Nama di CSV (dinormalisasi) → nama master yang dipakai. */
    private const ALIASES = [
        'kalibrasi' => 'Kalibrasi',
        'loadcell' => 'Load Cell',
        'lotcell' => 'Load Cell',
        'aki/batterai' => 'Baterai/Aki',
        'contactor' => 'Kontaktor',
        'heater' => 'Heater',
        'thermo control' => 'Thermo Control',
        'thermo couple' => 'Sensor Thermo Couple',
        'pressure gauge' => 'Pressure gauge',
        'keyped' => 'Keypad',
        'power supplay' => 'Power Supply',
        'as giude cylinder inject' => 'As Guide Cylinder Inject',
        'power transfer(gear, rantai, v-belt, timming belt)' => 'Power Transfer(gear, rantai, v-belt, timing belt)',
        'switch on/off' => 'Switch ON/OFF',
        'heater atas' => 'Heater Atas',
        'heater bawah' => 'Heater Bawah',
        'layar monitor' => 'Layar Monitor',
        'touch screen' => 'Touch Screen',
        'kondisi fisik' => 'Kondisi Fisik',
        'roda/bantalan' => 'Roda / Bantalan',
        'kawat ram ayakan' => 'Kawat Ram Ayakan',
    ];

    /**
     * Bila master punya beberapa komponen bernama sama, pilih yang Standar
     * bawaannya ini. Power Input memakai "Normal" karena tegangan berbeda per
     * mesin (220 V / 380 V); nilai pastinya diisi per unit.
     */
    private const PREFERRED_SPEC = [
        'power input' => 'Normal',
        'neutral-ground' => '0V ± 2V',
        'kondisi baut' => 'Kencang',
        'bearing' => 'Tidak panas',
        'lampu indikator' => 'Nyala',
        'sensor' => 'Normal',
    ];

    /** Komponen baru: nama => [kategori, standar bawaan]. */
    private const NEW_COMPONENTS = [
        'Oli Pneumatic' => ['Pneumatik', 'Cukup'],
        'Water Trap' => ['Pneumatik', 'Bersih'],
        'Tekanan Angin' => ['Pneumatik', '6-8 bar'],
        'Cylinder Pneumatic' => ['Pneumatik', 'Baik'],
        'Selang Angin' => ['Pneumatik', 'Tidak bocor'],
        'Valve' => ['Mekanik', 'Baik'],
        'Bearing/Bushing' => ['Mekanik', 'Baik'],
        'Power Transfer(gear, rantai, v-belt, timing belt)' => ['Mekanik', 'Baik'],
        'Power Supply' => ['Listrik', 'Berfungsi'],
        'Heater Atas' => ['Listrik', 'Berfungsi'],
        'Heater Bawah' => ['Listrik', 'Berfungsi'],
        'Switch ON/OFF' => ['Listrik', 'Berfungsi'],
        'Selenoid Valve Cooling Pump' => ['Listrik', 'Berfungsi'],
        'Keypad' => ['Instrumen', 'Berfungsi'],
        'LCD' => ['Instrumen', 'Berfungsi'],
        'HMI' => ['Instrumen', 'Berfungsi'],
        'Layar Monitor' => ['Instrumen', 'Berfungsi'],
        'Touch Screen' => ['Instrumen', 'Berfungsi'],
        'Kondisi Fisik' => ['Kondisi', 'Baik'],
        'Klakson' => ['Keselamatan', 'Berfungsi'],
        'Grease & Running' => ['Pelumasan', 'Terlumasi'],
        'Oli & Running' => ['Pelumasan', 'Cukup'],
        'Roda' => ['Mekanik', 'Baik'],
        'Roda / Bantalan' => ['Mekanik', 'Baik'],
        'Bracket' => ['Mekanik', 'Kencang'],
        'Mekanikal Seal' => ['Mekanik', 'Tidak bocor'],
        'Packing Seal' => ['Mekanik', 'Tidak bocor'],
        'Selang Hidrolik' => ['Mekanik', 'Tidak bocor'],
        'Selang Oli' => ['Mekanik', 'Tidak bocor'],
        'Cylinder Hidrolik' => ['Mekanik', 'Baik'],
        'Cylinder Mold' => ['Mekanik', 'Baik'],
        'Cylinder Striper' => ['Mekanik', 'Baik'],
        'As Guide Cylinder Inject' => ['Mekanik', 'Baik'],
        'Core' => ['Mekanik', 'Baik'],
        'Slider' => ['Mekanik', 'Baik'],
        'Moulding' => ['Mekanik', 'Baik'],
        'Spiral' => ['Mekanik', 'Baik'],
        'Vibrator' => ['Mekanik', 'Berfungsi'],
        'Kawat Ram Ayakan' => ['Mekanik', 'Tidak sobek'],
        'Tuas/Handel Penggerak' => ['Mekanik', 'Berfungsi'],
        'Bak Penampungan Air' => ['Mekanik', 'Bersih'],
    ];

    public static function defaultPath(): string
    {
        return base_path('references/data-source/templates/Template_Komponen_Mesin.csv');
    }

    public static function normalize(string $name): string
    {
        $name = mb_strtolower(trim($name));
        $name = preg_replace('/\s*\/\s*/', '/', $name);

        return preg_replace('/\s+/', ' ', $name);
    }

    /**
     * Baca CSV → template beserta komponen (nama CSV) dan kode unit.
     *
     * @return array<string, array{components: list<string>, codes: list<string>, adjustments: list<string>}>
     */
    public function parse(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("File CSV tidak ditemukan: {$path}");
        }

        $handle = fopen($path, 'r');
        $header = fgetcsv($handle);
        if ($header === false) {
            throw new RuntimeException('CSV kosong.');
        }

        $templates = [];
        while (($row = fgetcsv($handle)) !== false) {
            $name = trim($row[1] ?? '');
            $code = trim($row[3] ?? '');
            if ($name === '' || $code === '') {
                continue;
            }

            $components = array_values(array_filter(array_map('trim', array_slice($row, 4)), fn ($v) => $v !== ''));
            $templates[$name] ??= ['components' => $components, 'codes' => [], 'adjustments' => []];

            if (array_map([self::class, 'resolveName'], $components) !== array_map([self::class, 'resolveName'], $templates[$name]['components'])) {
                throw new RuntimeException("Daftar komponen \"{$name}\" berbeda antar baris (unit {$code}).");
            }

            $templates[$name]['codes'][] = $code;
            $adjust = trim(preg_replace('/^\x{FEFF}/u', '', $row[0] ?? ''));
            if ($adjust !== '' && $adjust !== 'Sesuai') {
                $templates[$name]['adjustments'][] = "{$code}: {$adjust}";
            }
        }
        fclose($handle);

        return $templates;
    }

    /** Nama komponen setelah disesuaikan (alias/salah ketik). */
    public static function resolveName(string $csvName): string
    {
        return self::ALIASES[self::normalize($csvName)] ?? trim($csvName);
    }

    public static function unitCode(string $csvCode): string
    {
        return preg_replace('/-\d+$/', '', trim($csvCode));
    }

    /**
     * @return array{templates: list<array<string, mixed>>, reused: array<string, string>, created: list<string>, missingUnits: list<string>}
     */
    public function run(string $path, bool $dryRun = true, bool $apply = true): array
    {
        $parsed = $this->parse($path);

        $report = ['templates' => [], 'reused' => [], 'created' => [], 'missingUnits' => []];

        $work = function () use ($parsed, $dryRun, $apply, &$report) {
            $cache = [];

            foreach ($parsed as $name => $tpl) {
                $codes = array_map([self::class, 'unitCode'], $tpl['codes']);
                $units = Machine::whereIn('code', $codes)->get();
                $report['missingUnits'] = [...$report['missingUnits'], ...array_diff($codes, $units->pluck('code')->all())];

                $types = $units->pluck('type')->unique()->values();
                if ($types->count() > 1) {
                    throw new RuntimeException("Unit \"{$name}\" punya tipe berbeda: ".$types->implode(', '));
                }

                $componentIds = [];
                foreach ($tpl['components'] as $csvName) {
                    $resolved = self::resolveName($csvName);
                    $key = self::normalize($resolved);
                    $cache[$key] ??= $this->component($resolved, $dryRun, $report);
                    if ($resolved !== trim($csvName)) {
                        $report['reused'][trim($csvName)] = $resolved;
                    }
                    $componentIds[] = $cache[$key];
                }

                $template = ChecklistTemplate::firstOrNew(['name' => $name, 'scope' => ChecklistTemplate::SCOPE_MACHINE]);
                $isNew = ! $template->exists;

                if (! $dryRun) {
                    $template->fill([
                        'machine_type' => $types->first() ?? 'generic',
                        'description' => 'Impor Template_Komponen_Mesin.csv · '.count($codes).' unit',
                        'is_default' => $template->is_default ?? false,
                    ])->save();

                    $sync = [];
                    foreach ($componentIds as $i => $id) {
                        $sync[$id] = ['sort_no' => $i + 1, 'standard' => null];
                    }
                    $template->components()->sync($sync);

                    if ($apply && $units->isNotEmpty()) {
                        TemplateService::apply($template->fresh(), $units);
                    }
                }

                $report['templates'][] = [
                    'name' => $name,
                    'type' => $types->first(),
                    'components' => count($componentIds),
                    'units' => $units->count(),
                    'codes' => $units->pluck('code')->all(),
                    'status' => $isNew ? 'baru' : 'diperbarui',
                    'adjustments' => $tpl['adjustments'],
                ];
            }
        };

        $dryRun ? $work() : DB::transaction($work);

        $report['created'] = array_values(array_unique($report['created']));

        return $report;
    }

    /** ID komponen master untuk nama ini; dibuat bila belum ada (kecuali dry-run). */
    private function component(string $name, bool $dryRun, array &$report): int
    {
        $key = self::normalize($name);

        /** @var Collection<int, Component> $matches */
        $matches = Component::withCount(['machines', 'templates'])->get()
            ->filter(fn (Component $c) => self::normalize($c->name) === $key);

        if ($matches->isNotEmpty()) {
            $preferred = self::PREFERRED_SPEC[$key] ?? null;
            $pick = ($preferred ? $matches->firstWhere('spec', $preferred) : null)
                ?? $matches->sortByDesc(fn ($c) => [$c->templates_count, $c->machines_count, filled($c->spec)])->first();

            return $pick->id;
        }

        $report['created'][] = $name;

        if ($dryRun) {
            return -crc32($key);
        }

        [$category, $spec] = self::NEW_COMPONENTS[$name] ?? ['Lain-lain', 'Baik'];

        return Component::create([
            'name' => $name,
            'category' => $category,
            'spec' => $spec,
            'sort_no' => (int) Component::where('category', $category)->max('sort_no') + 1,
        ])->id;
    }
}
