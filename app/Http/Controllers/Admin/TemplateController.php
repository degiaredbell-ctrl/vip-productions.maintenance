<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MachineType;
use App\Http\Controllers\Controller;
use App\Models\ChecklistTemplate;
use App\Models\Component;
use App\Models\Machine;
use App\Services\AuditLogService;
use App\Services\TemplateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Halaman Settings → Template: kelola template komponen untuk Mesin & Utility
 * dan terapkan ke banyak unit sekaligus.
 */
class TemplateController extends Controller
{
    public function index(): Response
    {
        $templates = ChecklistTemplate::with('components:id,name,category,spec')
            ->withCount('machines')
            ->orderByRaw("CASE WHEN machine_type LIKE 'utility:%' THEN 1 ELSE 0 END")
            ->orderBy('scope')
            ->orderBy('name')
            ->get()
            ->map(fn (ChecklistTemplate $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'description' => $t->description,
                'scope' => $t->scope,
                'machine_type' => $t->machine_type,
                'type_label' => $t->typeLabel(),
                'is_default' => $t->is_default,
                'is_unit_specific' => $t->isUnitSpecific(),
                'units_count' => $t->machines_count,
                'components' => $t->components->map(fn (Component $c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'category' => $c->category,
                    'spec' => $c->spec,
                    'standard' => $c->pivot->standard,
                ])->values(),
            ]);

        $units = Machine::withCount('components')
            ->orderByRaw('CASE WHEN type = ? THEN 1 ELSE 0 END', [MachineType::Utility->value])
            ->orderBy('sort_no')
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type', 'template_id', 'is_active'])
            ->map(fn (Machine $m) => [
                'id' => $m->id,
                'code' => $m->code,
                'name' => $m->name,
                'type' => $m->type,
                'type_label' => MachineType::tryFrom($m->type)?->label() ?? $m->type,
                'is_utility' => $m->type === MachineType::Utility->value,
                'is_active' => (bool) $m->is_active,
                'template_id' => $m->template_id,
                'components_count' => $m->components_count,
            ]);

        return Inertia::render('Admin/Templates', [
            'templates' => $templates,
            'units' => $units,
            'allComponents' => Component::options(),
            'machineTypes' => collect(MachineType::cases())
                ->reject(fn ($t) => $t === MachineType::Utility)
                ->map(fn ($t) => ['value' => $t->value, 'label' => $t->label()])
                ->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $template = DB::transaction(function () use ($data) {
            $template = ChecklistTemplate::create($this->attributes($data));
            $this->syncComponents($template, $data['components'] ?? []);
            $this->ensureSingleDefault($template);

            return $template;
        });

        AuditLogService::record('template.create', ChecklistTemplate::class, $template->id, [
            'name' => $template->name,
            'scope' => $template->scope,
            'components' => count($data['components'] ?? []),
        ]);

        return back()->with('success', "Template \"{$template->name}\" berhasil dibuat.");
    }

    public function update(Request $request, ChecklistTemplate $template): RedirectResponse
    {
        $data = $this->validated($request, $template);

        DB::transaction(function () use ($template, $data) {
            $attributes = $this->attributes($data);

            // Template per-unit (impor CSV) tetap terikat ke unitnya: tipe
            // dan cakupannya tidak diubah dari sini.
            if ($template->isUnitSpecific()) {
                unset($attributes['machine_type'], $attributes['scope']);
            }

            $template->update($attributes);
            $this->syncComponents($template, $data['components'] ?? []);
            $this->ensureSingleDefault($template);
        });

        AuditLogService::record('template.update', ChecklistTemplate::class, $template->id, [
            'name' => $template->name,
            'components' => count($data['components'] ?? []),
        ]);

        return back()->with('success', "Template \"{$template->name}\" berhasil disimpan. Terapkan ke unit agar perubahan komponen berlaku di unit.");
    }

    public function destroy(ChecklistTemplate $template): RedirectResponse
    {
        $units = $template->machines()->count();
        $name = $template->name;

        // machines.template_id = NULL lewat FK nullOnDelete; komponen unit
        // tidak ikut terhapus karena tersimpan di unit itu sendiri.
        $template->delete();

        AuditLogService::record('template.delete', ChecklistTemplate::class, $template->id, [
            'name' => $name,
            'units' => $units,
        ]);

        return back()->with('success', $units > 0
            ? "Template \"{$name}\" dihapus. {$units} unit tetap memakai komponennya saat ini."
            : "Template \"{$name}\" dihapus.");
    }

    public function apply(Request $request, ChecklistTemplate $template): RedirectResponse
    {
        $validated = $request->validate([
            'machine_ids' => ['required', 'array', 'min:1'],
            'machine_ids.*' => ['integer', 'exists:assets,id'],
        ], [
            'machine_ids.required' => 'Pilih minimal satu unit.',
            'machine_ids.min' => 'Pilih minimal satu unit.',
        ]);

        $machines = TemplateService::eligibleMachines($template, $validated['machine_ids']);

        if ($machines->isEmpty()) {
            return back()->with('error', 'Tidak ada unit yang cocok untuk template ini.');
        }

        $count = TemplateService::apply($template, $machines);

        AuditLogService::record('template.apply', ChecklistTemplate::class, $template->id, [
            'name' => $template->name,
            'units' => $machines->pluck('code')->all(),
        ]);

        return back()->with('success', "Template \"{$template->name}\" diterapkan ke {$count} unit.");
    }

    private function validated(Request $request, ?ChecklistTemplate $template = null): array
    {
        $unitSpecific = $template?->isUnitSpecific() ?? false;

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'scope' => $unitSpecific ? ['nullable'] : ['required', Rule::in([ChecklistTemplate::SCOPE_MACHINE, ChecklistTemplate::SCOPE_UTILITY])],
            'machine_type' => $unitSpecific ? ['nullable'] : [
                Rule::requiredIf($request->input('scope') === ChecklistTemplate::SCOPE_MACHINE),
                'nullable',
                Rule::in(collect(MachineType::cases())->reject(fn ($t) => $t === MachineType::Utility)->map->value->all()),
            ],
            'is_default' => ['boolean'],
            'components' => ['array'],
            'components.*.id' => ['required', 'integer', 'distinct', 'exists:components,id'],
            'components.*.standard' => ['nullable', 'string', 'max:255'],
        ], [
            'machine_type.required' => 'Pilih tipe mesin untuk template Mesin.',
        ]);
    }

    private function attributes(array $data): array
    {
        $scope = $data['scope'] ?? null;

        return [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'scope' => $scope,
            'machine_type' => $scope === ChecklistTemplate::SCOPE_UTILITY ? MachineType::Utility->value : ($data['machine_type'] ?? null),
            'is_default' => (bool) ($data['is_default'] ?? false),
        ];
    }

    private function syncComponents(ChecklistTemplate $template, array $components): void
    {
        $sync = [];
        $order = 1;

        foreach ($components as $row) {
            $standard = trim((string) ($row['standard'] ?? ''));
            $sync[(int) $row['id']] = [
                'sort_no' => $order++,
                'standard' => $standard !== '' ? $standard : null,
            ];
        }

        $template->components()->sync($sync);
    }

    /** Satu tipe unit hanya punya satu template default. */
    private function ensureSingleDefault(ChecklistTemplate $template): void
    {
        if (! $template->is_default) {
            return;
        }

        ChecklistTemplate::where('machine_type', $template->machine_type)
            ->whereKeyNot($template->id)
            ->where('is_default', true)
            ->update(['is_default' => false]);
    }
}
