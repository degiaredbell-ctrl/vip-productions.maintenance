<?php

namespace Database\Seeders;

use App\Models\ChecklistTemplate;
use App\Models\Component;
use App\Models\Machine;
use App\Services\MachineImportService;
use App\Services\TemplateService;
use Illuminate\Database\Seeder;

class MachineSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            'filling' => [
                'name' => 'Template Filling',
                'items' => [
                    ['Power Input', '220-240V', 'Listrik'],
                    ['Neutral-Ground', '0V ± 2V', 'Listrik'],
                    ['Lampu Indikator', 'Nyala', 'Listrik'],
                    ['Kondisi Baut', 'Kencang', 'Mekanik'],
                    ['Rantai Conveyor', 'Teralumasi', 'Pelumasan'],
                    ['Emergency Stop', 'Berfungsi', 'Keselamatan'],
                ],
            ],
            'labeling' => [
                'name' => 'Template Labeling',
                'items' => [
                    ['Sensor Label', 'Tepat posisi', 'Listrik'],
                    ['Roller Label', 'Tidak aus', 'Mekanik'],
                    ['Tegangan Label', 'Sesuai spec', 'Mekanik'],
                    ['Lampu Indikator', 'Nyala', 'Listrik'],
                    ['Emergency Stop', 'Berfungsi', 'Keselamatan'],
                ],
            ],
            'inject_blow' => [
                'name' => 'Template Inject Blow',
                'items' => [
                    ['Tekanan Udara', '6-8 bar', 'Pneumatik'],
                    ['Suhu Mold', 'Sesuai spec', 'Mekanik'],
                    ['Lubrikasi Mold', 'Terlumasi', 'Pelumasan'],
                    ['Emergency Stop', 'Berfungsi', 'Keselamatan'],
                    ['Power Input', '380V', 'Listrik'],
                ],
            ],
            'tom' => [
                'name' => 'Template TOM',
                'items' => [
                    ['Konveyor Botol', 'Berfungsi', 'Mekanik'],
                    ['Sensor Botol', 'Tepat posisi', 'Listrik'],
                    ['Lubrikasi', 'Terlumasi', 'Pelumasan'],
                    ['Emergency Stop', 'Berfungsi', 'Keselamatan'],
                ],
            ],
            'mixer' => [
                'name' => 'Template Mixer',
                'items' => [
                    ['Motor Mixer', 'Normal', 'Listrik'],
                    ['Bearing', 'Tidak panas', 'Mekanik'],
                    ['Lubrikasi', 'Terlumasi', 'Pelumasan'],
                    ['Emergency Stop', 'Berfungsi', 'Keselamatan'],
                ],
            ],
            'timbangan' => [
                'name' => 'Template Timbangan',
                'items' => [
                    ['Kalibrasi', 'Akurat', 'Kalibrasi'],
                    ['Load Cell', 'Normal', 'Listrik'],
                    ['Tara', 'Nol', 'Kalibrasi'],
                    ['Emergency Stop', 'Berfungsi', 'Keselamatan'],
                ],
            ],
            'coding' => [
                'name' => 'Template Coding',
                'items' => [
                    ['Print Head', 'Bersih', 'Mekanik'],
                    ['Ink Level', 'Cukup', 'Mekanik'],
                    ['Sensor', 'Normal', 'Listrik'],
                    ['Emergency Stop', 'Berfungsi', 'Keselamatan'],
                ],
            ],
            'vehicle' => [
                'name' => 'Template Kendaraan',
                'items' => [
                    ['Oli Mesin', 'Cukup', 'Pelumasan'],
                    ['Ban', 'Tekanan normal', 'Mekanik'],
                    ['Rem', 'Berfungsi', 'Mekanik'],
                    ['Lampu', 'Nyala', 'Listrik'],
                    ['Emergency Stop', 'Berfungsi', 'Keselamatan'],
                ],
            ],
            'generic' => [
                'name' => 'Template Umum',
                'items' => [
                    ['Power Input', 'Normal', 'Listrik'],
                    ['Kondisi Baut', 'Kencang', 'Mekanik'],
                    ['Lubrikasi', 'Terlumasi', 'Pelumasan'],
                    ['Emergency Stop', 'Berfungsi', 'Keselamatan'],
                ],
            ],
        ];

        foreach ($templates as $type => $data) {
            // Idempoten: db:seed sering dijalankan ulang saat menambah data
            // baru. Tanpa updateOrCreate, tiap run menambah satu template
            // duplikat karena machine_type tidak punya unique index, dan
            // mesin lalu menunjuk ke template yang salah.
            $template = ChecklistTemplate::updateOrCreate(
                ['machine_type' => $type],
                ['name' => $data['name'], 'scope' => ChecklistTemplate::SCOPE_MACHINE, 'is_default' => true],
            );

            // Isi template = komponen dari master Komponen / Parts.
            $sync = [];
            foreach ($data['items'] as $i => $item) {
                $component = Component::firstOrCreate(
                    ['name' => $item[0], 'category' => $item[2], 'spec' => $item[1]],
                    ['sort_no' => $i + 1],
                );
                $sync[$component->id] = ['sort_no' => $i + 1, 'standard' => null];
            }
            $template->components()->sync($sync);
        }

        $importer = app(MachineImportService::class);

        $importer->import(purge: true);

        // Unit yang belum punya komponen (instalasi baru) diisi dari
        // templatenya. Unit yang sudah punya komponen tidak diutak-atik.
        Machine::with('template')
            ->doesntHave('components')
            ->whereNotNull('template_id')
            ->get()
            ->each(fn (Machine $machine) => TemplateService::applyToMachine($machine->template, $machine));
    }
}
