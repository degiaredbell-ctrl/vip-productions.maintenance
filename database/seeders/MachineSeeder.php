<?php

namespace Database\Seeders;

use App\Models\ChecklistTemplate;
use App\Models\ChecklistTemplateItem;
use App\Models\Machine;
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
            $template = ChecklistTemplate::create([
                'machine_type' => $type,
                'name' => $data['name'],
                'is_default' => true,
            ]);

            foreach ($data['items'] as $i => $item) {
                ChecklistTemplateItem::create([
                    'template_id' => $template->id,
                    'category' => $item[2],
                    'name' => $item[0],
                    'spec' => $item[1],
                    'sort_no' => $i + 1,
                ]);
            }
        }

        $machines = [
            ['M2', 'Filling LFM [1]', 'filling', 1],
            ['M3', 'Filling LFM [2]', 'filling', 1],
            ['M5', 'Labeling Automatic [1]', 'labeling', 1],
            ['M7', 'Shrink Tunnel [1]', 'generic', 1],
            ['M12', 'Carton Sealer [1]', 'generic', 1],
            ['M17', 'TOM – Penyusun Botol', 'tom', 2],
            ['M33', 'Coding DJ45HE [1]', 'coding', 4],
            ['M50', 'Mixer 1', 'mixer', 5],
            ['M58', 'Timbangan Mixer 1', 'timbangan', 6],
            ['M75', 'MTC Inject Blow 1', 'inject_blow', 8],
            ['M98', '[LIFTCO] Reach Truck', 'vehicle', 6],
            ['M72', 'Compressor 55', 'generic', 3],
        ];

        foreach ($machines as $i => $m) {
            Machine::create([
                'code' => $m[0],
                'name' => $m[1],
                'type' => $m[2],
                'week_group' => $m[3],
                'template_id' => ChecklistTemplate::where('machine_type', $m[2])->where('is_default', true)->first()?->id,
                'sort_no' => $i + 1,
            ]);
        }
    }
}
