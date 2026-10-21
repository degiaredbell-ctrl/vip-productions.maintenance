<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\Machine;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Settings → Lokasi / Site: CRUD master lokasi unit.
 *
 * `assets.location` menyimpan nama lokasi, jadi mengganti nama lokasi ikut
 * memperbarui semua unit yang memakainya (termasuk unit yang dihapus, supaya
 * tetap valid bila dipulihkan). Lokasi yang masih dipakai tidak bisa dihapus.
 */
class LocationController extends Controller
{
    public function index(): Response
    {
        $unitCounts = Machine::withTrashed()
            ->whereNotNull('location')
            ->selectRaw('location, count(*) as c')
            ->groupBy('location')
            ->pluck('c', 'location');

        $items = Location::orderBy('sort_no')->orderBy('name')->get()->map(fn (Location $l) => [
            'id' => $l->id,
            'name' => $l->name,
            'description' => $l->description,
            'units_count' => (int) ($unitCounts[$l->name] ?? 0),
        ]);

        return Inertia::render('Admin/MasterData', [
            'kind' => 'locations',
            'items' => $items,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->rules(), $this->messages());

        $location = Location::create([
            ...$data,
            'sort_no' => ((int) Location::max('sort_no')) + 1,
        ]);

        AuditLogService::record('location.create', Location::class, $location->id, $location->only(['name']));

        return back()->with('success', "Lokasi \"{$location->name}\" berhasil ditambahkan.");
    }

    public function update(Request $request, Location $location): RedirectResponse
    {
        $data = $request->validate($this->rules($location), $this->messages());
        $before = $location->name;

        $renamed = DB::transaction(function () use ($location, $data, $before) {
            $location->update($data);

            return $before !== $location->name
                ? Machine::withTrashed()->where('location', $before)->update(['location' => $location->name])
                : 0;
        });

        AuditLogService::record('location.update', Location::class, $location->id, [
            'name' => ['dari' => $before, 'ke' => $location->name],
            'units_updated' => $renamed,
        ]);

        return back()->with('success', $renamed > 0
            ? "Lokasi \"{$location->name}\" diperbarui. {$renamed} unit ikut memakai nama baru."
            : "Lokasi \"{$location->name}\" berhasil diperbarui.");
    }

    public function destroy(Location $location): RedirectResponse
    {
        $units = Machine::withTrashed()->where('location', $location->name)->count();

        if ($units > 0) {
            return back()->with('error', "Lokasi \"{$location->name}\" masih dipakai {$units} unit. Pindahkan unit ke lokasi lain sebelum menghapus.");
        }

        $location->delete();

        AuditLogService::record('location.delete', Location::class, $location->id, $location->only(['name']));

        return back()->with('success', "Lokasi \"{$location->name}\" dihapus.");
    }

    private function rules(?Location $location = null): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('locations', 'name')->ignore($location?->id)],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    private function messages(): array
    {
        return ['name.unique' => 'Nama lokasi sudah dipakai.'];
    }
}
