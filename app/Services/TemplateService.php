<?php

namespace App\Services;

use App\Enums\MachineType;
use App\Models\ChecklistTemplate;
use App\Models\Machine;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Menerapkan Template ke unit Mesin/Utility.
 *
 * Komponen unit diganti utuh dengan isi template (urutan ikut template).
 * Standar: Standar default template dipakai bila diisi; bila kosong, Standar
 * yang sudah diisi di unit untuk komponen yang sama dipertahankan, supaya
 * penerapan template tidak menghapus nilai acuan per unit tanpa alasan.
 *
 * Riwayat PM tidak tersentuh: record PM menyimpan salinan itemnya sendiri.
 */
class TemplateService
{
    /**
     * @param  iterable<Machine>  $machines
     * @return int jumlah unit yang diterapkan
     */
    public static function apply(ChecklistTemplate $template, iterable $machines): int
    {
        $template->loadMissing('components');

        $count = 0;

        DB::transaction(function () use ($template, $machines, &$count) {
            foreach ($machines as $machine) {
                self::applyToMachine($template, $machine);
                $count++;
            }
        });

        return $count;
    }

    public static function applyToMachine(ChecklistTemplate $template, Machine $machine): void
    {
        $template->loadMissing('components');

        $existingStandards = $machine->components()->get()->pluck('pivot.standard', 'id');

        $sync = [];
        $order = 1;

        foreach ($template->components as $component) {
            $standard = $component->pivot->standard ?: ($existingStandards[$component->id] ?? null);

            $sync[$component->id] = [
                'sort_no' => $order++,
                'standard' => $standard !== '' ? $standard : null,
            ];
        }

        $machine->components()->sync($sync);

        if ((int) $machine->template_id !== (int) $template->id) {
            $machine->forceFill(['template_id' => $template->id])->save();
        }

        $machine->unsetRelation('components');
    }

    /**
     * Unit yang boleh menerima template ini: Mesin untuk template Mesin,
     * Utility untuk template Utility.
     *
     * @param  array<int>  $ids
     * @return Collection<int, Machine>
     */
    public static function eligibleMachines(ChecklistTemplate $template, array $ids): Collection
    {
        return Machine::query()
            ->whereIn('id', $ids)
            ->when(
                $template->scope === ChecklistTemplate::SCOPE_UTILITY,
                fn ($q) => $q->where('type', MachineType::Utility->value),
                fn ($q) => $q->where('type', '!=', MachineType::Utility->value),
            )
            ->get();
    }
}
