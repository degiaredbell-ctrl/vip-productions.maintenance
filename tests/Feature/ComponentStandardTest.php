<?php

namespace Tests\Feature;

use App\Enums\MachineType;
use App\Models\Component;
use App\Models\Machine;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Fitur "Edit Komponen" di halaman Mesin/Utility: memilih beberapa komponen
 * sekaligus (multi-select) lalu mengisi nilai Standar PER UNIT yang disimpan di
 * pivot `component_machine.standard`.
 *
 * Standar per unit menang atas `components.spec` (standar bawaan master) baik di
 * daftar komponen maupun di form PM; bila kosong, form PM memakai spec master.
 */
class ComponentStandardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RoleSeeder::class);
    }

    private function machine(array $overrides = []): Machine
    {
        return Machine::create(array_merge([
            'code' => 'MC-001',
            'name' => 'Mesin Uji',
            'type' => MachineType::Generic->value,
            'week_group' => 1,
            'is_active' => true,
            'sort_no' => 1,
        ], $overrides));
    }

    private function utility(array $overrides = []): Machine
    {
        return Machine::create(array_merge([
            'code' => 'UTL-001',
            'name' => 'Chiller',
            'type' => MachineType::Utility->value,
            'week_group' => 1,
            'is_active' => true,
            'sort_no' => 1,
        ], $overrides));
    }

    private function makeComponent(string $name = 'Bearing', ?string $spec = null): Component
    {
        return Component::create([
            'name' => $name,
            'category' => 'Umum',
            'spec' => $spec,
            'sort_no' => 1,
        ]);
    }

    public function test_admin_bisa_menyimpan_komponen_dan_standar_mesin(): void
    {
        $admin = User::factory()->create()->assignRole('admin');
        $machine = $this->machine();
        $bearing = $this->makeComponent('Bearing');
        $belt = $this->makeComponent('V-Belt');

        $this->actingAs($admin)->put(route('machines.components.update', $machine), [
            'items' => [
                ['id' => $bearing->id, 'standard' => ' 5 mm '],
                ['id' => $belt->id, 'standard' => ''],
            ],
        ])->assertRedirect();

        $pivot = $machine->fresh()->components->keyBy('id');

        $this->assertSame('5 mm', $pivot[$bearing->id]->pivot->standard);
        // Standar kosong disimpan NULL supaya form PM jatuh ke spec master.
        $this->assertNull($pivot[$belt->id]->pivot->standard);
        $this->assertSame(1, $pivot[$bearing->id]->pivot->sort_no);
        $this->assertSame(2, $pivot[$belt->id]->pivot->sort_no);
    }

    public function test_menyimpan_ulang_melepas_komponen_yang_tidak_dicentang(): void
    {
        $admin = User::factory()->create()->assignRole('admin');
        $machine = $this->machine();
        $bearing = $this->makeComponent('Bearing');
        $belt = $this->makeComponent('V-Belt');

        $machine->components()->attach($bearing->id, ['sort_no' => 1]);
        $machine->components()->attach($belt->id, ['sort_no' => 2]);

        $this->actingAs($admin)->put(route('machines.components.update', $machine), [
            'items' => [
                ['id' => $belt->id, 'standard' => 'Tension 15'],
            ],
        ])->assertRedirect();

        $machine = $machine->fresh();
        $this->assertSame([$belt->id], $machine->components->pluck('id')->all());
    }

    public function test_halaman_mesin_mengirim_master_komponen_dan_id_terpilih(): void
    {
        $admin = User::factory()->create()->assignRole('admin');
        $machine = $this->machine();
        $bearing = $this->makeComponent('Bearing', 'master spec');

        $machine->components()->attach($bearing->id, ['sort_no' => 1, 'standard' => 'per unit']);

        $this->actingAs($admin)->get(route('machines.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('allComponents', 1)
                ->where('allComponents.0.name', 'Bearing')
                ->where('machines.0.component_ids', [$bearing->id])
                ->where('machines.0.components.0.standard', 'per unit')
                ->where('machines.0.components.0.spec', 'master spec'));
    }

    public function test_form_pm_memakai_standar_per_unit(): void
    {
        $admin = User::factory()->create()->assignRole('admin');
        $machine = $this->machine();
        $bearing = $this->makeComponent('Bearing', 'master spec');

        $machine->components()->attach($bearing->id, ['sort_no' => 1, 'standard' => '380 V']);

        $this->actingAs($admin)->get(route('machines.pm.create', $machine))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('items.0.item_name', 'Bearing')
                ->where('items.0.spec', '380 V'));
    }

    public function test_form_pm_memakai_spec_master_bila_standar_unit_kosong(): void
    {
        $admin = User::factory()->create()->assignRole('admin');
        $machine = $this->machine();
        $bearing = $this->makeComponent('Bearing', 'master spec');

        $machine->components()->attach($bearing->id, ['sort_no' => 1, 'standard' => null]);

        $this->actingAs($admin)->get(route('machines.pm.create', $machine))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('items.0.spec', 'master spec'));
    }

    public function test_teknisi_tidak_boleh_menyimpan_komponen_mesin(): void
    {
        $technician = User::factory()->create()->assignRole('technician');
        $machine = $this->machine();
        $bearing = $this->makeComponent('Bearing');

        $this->actingAs($technician)->put(route('machines.components.update', $machine), [
            'items' => [['id' => $bearing->id, 'standard' => 'x']],
        ])->assertForbidden();

        $this->assertDatabaseMissing('component_machine', ['machine_id' => $machine->id]);
    }

    public function test_manager_tidak_boleh_menyimpan_komponen_utility(): void
    {
        $manager = User::factory()->create()->assignRole('manager');
        $utility = $this->utility();
        $bearing = $this->makeComponent('Bearing');

        $this->actingAs($manager)->put(route('utility.components.update', $utility), [
            'items' => [['id' => $bearing->id, 'standard' => 'x']],
        ])->assertForbidden();

        $this->assertDatabaseMissing('component_machine', ['machine_id' => $utility->id]);
    }

    public function test_tech_all_bisa_menyimpan_komponen_utility(): void
    {
        $techAll = User::factory()->create()->assignRole('tech-all');
        $utility = $this->utility();
        $bearing = $this->makeComponent('Bearing', 'master spec');

        $this->actingAs($techAll)->put(route('utility.components.update', $utility), [
            'items' => [['id' => $bearing->id, 'standard' => '2 bar']],
        ])->assertRedirect();

        $pivot = $utility->fresh()->components->first();
        $this->assertSame('2 bar', $pivot->pivot->standard);
    }

    public function test_komponen_tidak_dikenal_ditolak(): void
    {
        $admin = User::factory()->create()->assignRole('admin');
        $machine = $this->machine();

        $this->actingAs($admin)->put(route('machines.components.update', $machine), [
            'items' => [['id' => 999999, 'standard' => 'x']],
        ])->assertSessionHasErrors('items.0.id');
    }

    public function test_penugasan_dari_halaman_admin_tidak_menghapus_standar_per_unit(): void
    {
        $admin = User::factory()->create()->assignRole('admin');
        $machine = $this->machine();
        $bearing = $this->makeComponent('Bearing');
        $belt = $this->makeComponent('V-Belt');

        $machine->components()->attach($bearing->id, ['sort_no' => 1, 'standard' => '5 mm']);

        // Admin menugaskan ulang dari /admin/komponen (hanya kirim id komponen).
        $this->actingAs($admin)->post(route('admin.components.assign'), [
            'machine_id' => $machine->id,
            'component_ids' => [$bearing->id, $belt->id],
        ])->assertRedirect();

        $pivot = $machine->fresh()->components->keyBy('id');
        $this->assertSame('5 mm', $pivot[$bearing->id]->pivot->standard);
        $this->assertNull($pivot[$belt->id]->pivot->standard);
    }
}
