<?php

namespace Tests\Feature;

use App\Models\AssetType;
use App\Models\ChecklistTemplate;
use App\Models\Location;
use App\Models\Machine;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Settings → Tipe Mesin & Lokasi / Site (CRUD), hanya Admin & tech-all.
 */
class MasterDataTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RoleSeeder::class);

        $this->admin = User::factory()->create()->assignRole('admin');
    }

    protected function tearDown(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        parent::tearDown();
    }

    private function machine(string $code, array $attrs = []): Machine
    {
        return Machine::create([
            'code' => $code, 'name' => "Unit {$code}", 'type' => 'mixer',
            'week_group' => 1, 'is_active' => true, 'sort_no' => 1, ...$attrs,
        ]);
    }

    public function test_hanya_admin_dan_tech_all(): void
    {
        foreach (['admin', 'tech-all'] as $role) {
            $user = User::factory()->create()->assignRole($role);
            $this->actingAs($user)->get(route('admin.machine-types'))->assertOk();
            $this->actingAs($user)->get(route('admin.locations'))->assertOk();
        }

        foreach (['manager', 'technician', 'viewer', 'tech-machines'] as $role) {
            $user = User::factory()->create()->assignRole($role);
            $this->actingAs($user)->get(route('admin.machine-types'))->assertForbidden();
            $this->actingAs($user)->post(route('admin.locations.store'), ['name' => 'X'])->assertForbidden();
        }
    }

    public function test_tipe_bawaan_tersedia_dengan_tipe_sistem(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.machine-types'))
            ->assertInertia(fn ($page) => $page
                ->component('Admin/MasterData')
                ->where('kind', 'types')
                ->where('items', fn ($items) => collect($items)->firstWhere('code', 'utility')['is_system'] === true
                    && collect($items)->firstWhere('code', 'mixer')['name'] === 'Mixer'));
    }

    public function test_tambah_tipe_kode_otomatis_lalu_dipakai_unit_dan_labelnya_tampil(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.machine-types.store'), ['name' => 'Packing Line', 'code' => ''])
            ->assertSessionHasNoErrors();

        $type = AssetType::where('code', 'packing_line')->sole();
        $this->assertSame('Packing Line', $type->name);

        // Unit baru bisa memakai tipe ini.
        $this->actingAs($this->admin)
            ->post(route('machines.store'), [
                'code' => 'PK-1', 'name' => 'Packing 1', 'type' => 'packing_line', 'week_group' => 1, 'is_active' => 1,
            ])
            ->assertSessionHasNoErrors();

        // Tipe tidak terdaftar ditolak.
        $this->actingAs($this->admin)
            ->post(route('machines.store'), [
                'code' => 'PK-2', 'name' => 'Packing 2', 'type' => 'tidak_ada', 'week_group' => 1, 'is_active' => 1,
            ])
            ->assertSessionHasErrors('type');

        // Mengganti nama tampil langsung berlaku di daftar Mesin.
        $this->actingAs($this->admin)->put(route('admin.machine-types.update', $type), ['name' => 'Packing'])->assertSessionHasNoErrors();

        $this->actingAs($this->admin)
            ->get(route('machines.index'))
            ->assertInertia(fn ($page) => $page->where('types', fn ($types) => collect($types)->contains(fn ($t) => $t['value'] === 'packing_line' && $t['label'] === 'Packing')
                && ! collect($types)->contains('value', 'utility')));
    }

    public function test_kode_utility_dan_duplikat_ditolak(): void
    {
        $this->actingAs($this->admin)->post(route('admin.machine-types.store'), ['name' => 'Utility Baru', 'code' => 'utility'])->assertSessionHasErrors('code');
        $this->actingAs($this->admin)->post(route('admin.machine-types.store'), ['name' => 'Mixer'])->assertSessionHasErrors('name');
    }

    public function test_hapus_tipe_diblokir_bila_sistem_atau_dipakai(): void
    {
        $generic = AssetType::where('code', 'generic')->sole();
        $this->actingAs($this->admin)->delete(route('admin.machine-types.destroy', $generic))->assertSessionHas('error');
        $this->assertModelExists($generic);

        $this->machine('M-1');
        $mixer = AssetType::where('code', 'mixer')->sole();
        $this->actingAs($this->admin)->delete(route('admin.machine-types.destroy', $mixer))->assertSessionHas('error');
        $this->assertModelExists($mixer);

        ChecklistTemplate::create(['name' => 'T', 'scope' => 'machine', 'machine_type' => 'tom']);
        $tom = AssetType::where('code', 'tom')->sole();
        $this->actingAs($this->admin)->delete(route('admin.machine-types.destroy', $tom))->assertSessionHas('error');

        $coding = AssetType::where('code', 'coding')->sole();
        $this->actingAs($this->admin)->delete(route('admin.machine-types.destroy', $coding))->assertSessionHas('success');
        $this->assertModelMissing($coding);
    }

    public function test_lokasi_crud_dan_ganti_nama_memperbarui_unit(): void
    {
        $this->actingAs($this->admin)->post(route('admin.locations.store'), ['name' => 'Factory A'])->assertSessionHasNoErrors();
        $location = Location::where('name', 'Factory A')->sole();

        $unit = $this->machine('M-1', ['location' => 'Factory A']);
        $trashed = $this->machine('M-2', ['location' => 'Factory A']);
        $trashed->delete();

        $this->actingAs($this->admin)
            ->put(route('admin.locations.update', $location), ['name' => 'Factory Milenium 2'])
            ->assertSessionHas('success', 'Lokasi "Factory Milenium 2" diperbarui. 2 unit ikut memakai nama baru.');

        $this->assertSame('Factory Milenium 2', $unit->fresh()->location);
        $this->assertSame('Factory Milenium 2', Machine::withTrashed()->find($trashed->id)->location);

        // Masih dipakai: tidak bisa dihapus.
        $this->actingAs($this->admin)->delete(route('admin.locations.destroy', $location))->assertSessionHas('error');

        $kosong = Location::create(['name' => 'Gudang Kosong']);
        $this->actingAs($this->admin)->delete(route('admin.locations.destroy', $kosong))->assertSessionHas('success');
        $this->assertModelMissing($kosong);
    }

    public function test_form_unit_hanya_menerima_lokasi_dari_master(): void
    {
        Location::create(['name' => 'Factory Milenium']);

        $payload = ['code' => 'M-9', 'name' => 'Mesin 9', 'type' => 'mixer', 'week_group' => 1, 'is_active' => 1];

        $this->actingAs($this->admin)->post(route('machines.store'), $payload + ['location' => 'Tidak Terdaftar'])
            ->assertSessionHasErrors('location');

        $this->actingAs($this->admin)->post(route('machines.store'), $payload + ['location' => 'Factory Milenium'])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->admin)
            ->get(route('machines.index'))
            ->assertInertia(fn ($page) => $page->where('locations', ['Factory Milenium']));
    }
}
