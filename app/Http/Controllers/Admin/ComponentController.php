<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MachineType;
use App\Http\Controllers\Controller;
use App\Models\Component;
use App\Models\Machine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Halaman "Komponen / Parts": master daftar komponen yang diperiksa saat PM,
 * lengkap dengan penugasan (link) ke Mesin dan unit Utility.
 *
 * Komponen adalah master tersendiri (one-to-many ke penampilannya di tiap
 * unit), sehingga mengubah nama/spesifikasi sebuah komponen cukup sekali dan
 * semua unit yang memakainya ikut terbarui.
 */
class ComponentController extends Controller
{
    public function index(Request $request): Response
    {
        $components = Component::with('machines:id,code,name,type')
            ->orderBy('category')
            ->orderBy('sort_no')
            ->orderBy('name')
            ->get()
            ->map(fn (Component $component) => [
                'id' => $component->id,
                'name' => $component->name,
                'category' => $component->category,
                'spec' => $component->spec,
                'sort_no' => $component->sort_no,
                'machine_ids' => $component->machines->pluck('id')->all(),
                'machines_count' => $component->machines->count(),
            ]);

        $machines = Machine::with('components:id')
            ->orderByRaw('CASE WHEN type = ? THEN 1 ELSE 0 END', [MachineType::Utility->value])
            ->orderBy('type')
            ->orderBy('sort_no')
            ->orderBy('code')
            ->get()
            ->map(fn (Machine $machine) => [
                'id' => $machine->id,
                'code' => $machine->code,
                'name' => $machine->name,
                'type' => $machine->type,
                'type_label' => MachineType::tryFrom($machine->type)?->label() ?? $machine->type,
                'is_utility' => $machine->type === MachineType::Utility->value,
                'component_ids' => $machine->components->pluck('id')->all(),
            ]);

        return Inertia::render('Admin/Components', [
            'components' => $components,
            'machines' => $machines,
            'categories' => Component::query()
                ->orderBy('category')
                ->distinct()
                ->pluck('category')
                ->values()
                ->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:50'],
            'spec' => ['nullable', 'string', 'max:255'],
        ]);

        Component::create([
            'name' => $validated['name'],
            'category' => $validated['category'],
            'spec' => $validated['spec'] ?? null,
            'sort_no' => ((int) Component::query()->max('sort_no')) + 1,
        ]);

        return back()->with('success', 'Komponen berhasil ditambahkan.');
    }

    public function update(Request $request, Component $component): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:50'],
            'spec' => ['nullable', 'string', 'max:255'],
        ]);

        $component->update([
            'name' => $validated['name'],
            'category' => $validated['category'],
            'spec' => $validated['spec'] ?? null,
        ]);

        return back()->with('success', 'Komponen berhasil diperbarui.');
    }

    public function destroy(Request $request, Component $component): RedirectResponse
    {
        $usedBy = $component->machines()->count();

        $component->delete();

        return back()->with(
            'success',
            $usedBy > 0
                ? "Komponen \"{$component->name}\" dihapus dan dilepas dari {$usedBy} unit."
                : 'Komponen berhasil dihapus.'
        );
    }

    /**
     * Simpan daftar komponen untuk satu unit (Mesin/Utility). Penugasan
     * ditulis utuh (sync), jadi menghapus centang otomatis melepas komponen.
     */
    public function assign(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'machine_id' => ['required', 'exists:machines,id'],
            'component_ids' => ['array'],
            'component_ids.*' => ['integer', 'exists:components,id'],
        ]);

        $machine = Machine::findOrFail($validated['machine_id']);

        $sync = [];
        $order = 1;

        foreach (array_values(array_unique($validated['component_ids'] ?? [])) as $componentId) {
            $sync[$componentId] = ['sort_no' => $order++];
        }

        $machine->components()->sync($sync);

        return back()->with('success', "Komponen untuk {$machine->code} — {$machine->name} berhasil disimpan.");
    }
}