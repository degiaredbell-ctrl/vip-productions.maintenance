<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AssetType;
use App\Models\ChecklistTemplate;
use App\Models\Machine;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Settings → Tipe Mesin: CRUD master tipe unit.
 *
 * Kode tipe disimpan di `assets.type` dan `checklist_templates.machine_type`,
 * jadi kode tidak bisa diubah setelah dibuat; yang diubah hanya nama tampil.
 * Tipe yang masih dipakai unit/template, dan tipe sistem, tidak bisa dihapus.
 */
class MachineTypeController extends Controller
{
    public function index(): Response
    {
        $unitCounts = Machine::withTrashed()->selectRaw('type, count(*) as c')->groupBy('type')->pluck('c', 'type');
        $templateCounts = ChecklistTemplate::selectRaw('machine_type, count(*) as c')->groupBy('machine_type')->pluck('c', 'machine_type');

        $items = AssetType::orderBy('sort_no')->orderBy('name')->get()->map(fn (AssetType $t) => [
            'id' => $t->id,
            'code' => $t->code,
            'name' => $t->name,
            'description' => $t->description,
            'is_system' => $t->is_system,
            'units_count' => (int) ($unitCounts[$t->code] ?? 0),
            'templates_count' => (int) ($templateCounts[$t->code] ?? 0),
        ]);

        return Inertia::render('Admin/MasterData', [
            'kind' => 'types',
            'items' => $items,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['code' => Str::of($request->input('code') ?: $request->input('name'))->lower()->slug('_')->limit(30, '')->toString()]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:50', Rule::unique('machine_types', 'name')],
            'code' => ['required', 'string', 'max:30', 'regex:/^[a-z0-9_]+$/', Rule::unique('machine_types', 'code'), 'not_in:utility'],
            'description' => ['nullable', 'string', 'max:255'],
        ], [
            'name.unique' => 'Nama tipe sudah dipakai.',
            'code.unique' => 'Kode tipe sudah dipakai.',
            'code.regex' => 'Kode hanya boleh huruf kecil, angka, dan garis bawah.',
            'code.not_in' => 'Kode "utility" dipakai sistem.',
        ]);

        $type = AssetType::create([
            ...$data,
            'is_system' => false,
            'sort_no' => ((int) AssetType::max('sort_no')) + 1,
        ]);

        AuditLogService::record('machine_type.create', AssetType::class, $type->id, $type->only(['code', 'name']));

        return back()->with('success', "Tipe mesin \"{$type->name}\" berhasil ditambahkan.");
    }

    public function update(Request $request, AssetType $type): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:50', Rule::unique('machine_types', 'name')->ignore($type->id)],
            'description' => ['nullable', 'string', 'max:255'],
        ], [
            'name.unique' => 'Nama tipe sudah dipakai.',
        ]);

        $before = $type->name;
        $type->update($data);

        AuditLogService::record('machine_type.update', AssetType::class, $type->id, [
            'code' => $type->code,
            'name' => ['dari' => $before, 'ke' => $type->name],
        ]);

        return back()->with('success', "Tipe mesin \"{$type->name}\" berhasil diperbarui.");
    }

    public function destroy(AssetType $type): RedirectResponse
    {
        if ($type->is_system) {
            return back()->with('error', "Tipe \"{$type->name}\" adalah tipe sistem dan tidak bisa dihapus.");
        }

        $units = Machine::withTrashed()->where('type', $type->code)->count();
        $templates = ChecklistTemplate::where('machine_type', $type->code)->count();

        if ($units > 0 || $templates > 0) {
            $usage = collect([$units ? "{$units} unit" : null, $templates ? "{$templates} template" : null])->filter()->implode(' dan ');

            return back()->with('error', "Tipe \"{$type->name}\" masih dipakai {$usage}. Pindahkan dulu ke tipe lain sebelum menghapus.");
        }

        $type->delete();

        AuditLogService::record('machine_type.delete', AssetType::class, $type->id, $type->only(['code', 'name']));

        return back()->with('success', "Tipe mesin \"{$type->name}\" dihapus.");
    }
}
