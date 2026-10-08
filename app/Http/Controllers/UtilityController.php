<?php

namespace App\Http\Controllers;

use App\Enums\MachineType;
use App\Models\Machine;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Halaman Utility: daftar unit utility (type "utility") beserta komponen yang
 * wajib dicek pada maintenance. Data bersumber dari CSV (references/utility.csv
 * + utility_components.csv) lewat machines:import, jadi halaman ini read-only;
 * untuk mengubah unit, edit CSV lalu jalankan kembali impor.
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
                    'week_group' => $machine->week_group,
                    'is_active' => $machine->is_active,
                    'component_count' => $items->count(),
                    'components' => $items->map(fn ($item) => [
                        'name' => $item->name,
                        'category' => $item->category,
                    ])->values(),
                ];
            });

        $subCategories = Machine::where('type', MachineType::Utility->value)
            ->where('is_active', true)
            ->whereNotNull('sub_category')
            ->distinct()
            ->orderBy('sub_category')
            ->pluck('sub_category')
            ->values()
            ->all();

        $user = $request->user();

        return Inertia::render('Utility/Index', [
            'utilities' => $utilities,
            'subCategories' => $subCategories,
            'can' => [
                'fill' => $user->can('pm.fill'),
                'history' => $user->can('pm.history.view'),
            ],
        ]);
    }
}
