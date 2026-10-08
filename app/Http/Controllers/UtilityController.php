<?php

namespace App\Http\Controllers;

use App\Enums\MachineType;
use App\Models\ChecklistTemplate;
use App\Models\Machine;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Halaman Utility: daftar unit utility (type "utility") beserta kemampuan
 * kelola (tambah/ubah/hapus) seperti halaman Mesin.
 */
class UtilityController extends Controller
{
    public function index(Request $request): Response
    {
        $utilities = Machine::with('template.items')
            ->where('type', MachineType::Utility->value)
            ->orderBy('sort_no')
            ->orderBy('code')
            ->get()
            ->map(function (Machine $machine) {
                $items = $machine->template?->items ?? collect();

                return [
                    'id' => $machine->id,
                    'code' => $machine->code,
                    'name' => $machine->name,
                    'location' => $machine->location,
                    'category' => $machine->category,
                    'sub_category' => $machine->sub_category,
                    'type' => $machine->type,
                    'week_group' => $machine->week_group,
                    'template_id' => $machine->template_id,
                    'is_active' => $machine->is_active,
                    'component_count' => $items->count(),
                    'components' => $items->map(fn ($item) => [
                        'name' => $item->name,
                        'category' => $item->category,
                    ])->values(),
                ];
            });

        // Mesin yang dihapus tetap bisa dipulihkan
        $trashed = Machine::onlyTrashed()
            ->where('type', MachineType::Utility->value)
            ->orderByDesc('deleted_at')
            ->get()
            ->map(fn ($m) => [
                'id' => $m->id,
                'code' => $m->code,
                'name' => $m->name,
                'deleted_at' => $m->deleted_at?->format('d M Y H:i'),
            ]);

        $subCategories = Machine::where('type', MachineType::Utility->value)
            ->whereNotNull('sub_category')
            ->distinct()
            ->orderBy('sub_category')
            ->pluck('sub_category')
            ->values()
            ->all();

        $user = $request->user();

        return Inertia::render('Utility/Index', [
            'utilities' => $utilities,
            'trashed' => $trashed,
            'types' => collect(MachineType::cases())
                ->filter(fn ($t) => $t === MachineType::Utility)
                ->map(fn ($t) => ['value' => $t->value, 'label' => $t->label()])
                ->values()
                ->toArray(),
            'templates' => ChecklistTemplate::orderBy('name')
                ->where('machine_type', 'LIKE', 'utility:%')
                ->get(['id', 'name', 'machine_type'])
                ->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'machine_type' => $t->machine_type]),
            'categories' => Machine::where('type', MachineType::Utility->value)
                ->whereNotNull('category')
                ->distinct()
                ->orderBy('category')
                ->pluck('category')
                ->values()
                ->all(),
            'subCategories' => $subCategories,
            'can' => [
                'fill' => $user->can('pm.fill'),
                'history' => $user->can('pm.history.view'),
            ],
        ]);
    }
}
