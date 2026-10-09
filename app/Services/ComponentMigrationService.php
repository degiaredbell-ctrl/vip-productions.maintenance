<?php

namespace App\Services;

use App\Models\ChecklistTemplate;
use App\Models\Component;
use App\Models\Machine;

/**
 * Memindahkan struktur template lama ke master Komponen + penugasan per unit.
 *
 * Komponen yang sama (nama + spec + kategori) cukup dibuat sekali lalu dibagi
 * ke semua unit yang memakainya lewat pivot `component_machine`. Proses ini
 * idempoten: bisa dijalankan ulang tanpa menduplikasi komponen (firstOrCreate)
 * dan tanpa mengubah penugasan manual selama daftar sumber tidak berubah.
 */
class ComponentMigrationService
{
    /**
     * @return array{components:int, machines:int}
     */
    public function migrateFromTemplates(): array
    {
        $templates = ChecklistTemplate::with('items')->get();

        $componentIds = [];
        $templateMap = [];

        foreach ($templates as $template) {
            $order = [];

            foreach ($template->items as $item) {
                $key = $this->dedupeKey($item->name, $item->category, $item->spec);

                if (! isset($componentIds[$key])) {
                    $component = Component::firstOrCreate(
                        [
                            'name' => $item->name,
                            'category' => $item->category,
                            'spec' => $item->spec,
                        ],
                        ['sort_no' => $item->sort_no ?? 0],
                    );

                    $componentIds[$key] = $component->id;
                }

                $order[] = $componentIds[$key];
            }

            $templateMap[$template->id] = $order;
        }

        $machineCount = 0;

        Machine::withTrashed()
            ->orderBy('id')
            ->chunk(200, function ($machines) use ($templateMap, &$machineCount) {
                foreach ($machines as $machine) {
                    $templateId = $machine->template_id;

                    // Mesin tanpa template memakai template default untuk
                    // jenisnya, sama seperti sumber item di form PM selama ini.
                    if ($templateId === null) {
                        $templateId = ChecklistTemplate::query()
                            ->where('machine_type', $machine->type)
                            ->where('is_default', true)
                            ->value('id');
                    }

                    $componentIdsForMachine = $templateMap[$templateId] ?? [];

                    $sync = [];
                    $order = 1;

                    foreach (array_values(array_unique($componentIdsForMachine)) as $componentId) {
                        $sync[$componentId] = ['sort_no' => $order++];
                    }

                    $machine->components()->sync($sync);
                    $machineCount++;
                }
            });

        return [
            'components' => count($componentIds),
            'machines' => $machineCount,
        ];
    }

    private function dedupeKey(string $name, ?string $category, ?string $spec): string
    {
        return implode('|', [
            mb_strtolower(trim($name)),
            mb_strtolower(trim((string) $category)),
            mb_strtolower(trim((string) $spec)),
        ]);
    }
}