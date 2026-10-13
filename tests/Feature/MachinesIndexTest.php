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
 * Halaman Mesin kini sejajar dengan halaman Utility: tombol "Tambah Mesin"
 * plus aksi Isi PM / Riwayat / Ubah / Hapus / Komponen. Test ini memastikan
 * prop yang dibutuhkan UI terkirim dan izinnya benar.
 */
class MachinesIndexTest extends TestCase
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

    public function test_admin_bisa_membuka_mesin_dan_menambah_mesin_baru(): void
    {
        $admin = User::factory()->create()->assignRole('admin');

        $this->actingAs($admin)->get(route('machines.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('can.manage', true)
                ->where('can.fill', true));

        $this->actingAs($admin)->post(route('machines.store'), [
            'code' => 'MC-NEW',
            'name' => 'Mesin Baru',
            'type' => MachineType::Generic->value,
            'week_group' => 2,
            'is_active' => 1,
        ])->assertRedirect();

        $this->assertDatabaseHas('assets', ['code' => 'MC-NEW']);
    }

    public function test_manager_bisa_mengelola_mesin_tapi_tidak_melihat_tombol_isi_pm(): void
    {
        $manager = User::factory()->create()->assignRole('manager');

        // Manager memegang machine.manage (boleh CRUD) tetapi tidak pm.fill,
        // jadi "Isi PM" tidak dirender.
        $this->actingAs($manager)->get(route('machines.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('can.manage', true)
                ->where('can.fill', false));

        $this->actingAs($manager)->post(route('machines.store'), [
            'code' => 'MC-MGR',
            'name' => 'Mesin Manager',
            'type' => MachineType::Generic->value,
            'week_group' => 1,
            'is_active' => 1,
        ])->assertRedirect();

        $this->assertDatabaseHas('assets', ['code' => 'MC-MGR']);
    }

    public function test_halaman_mesin_mengirim_daftar_komponen_efektif(): void
    {
        $admin = User::factory()->create()->assignRole('admin');
        $machine = $this->machine();

        $component = Component::create([
            'name' => 'Bearing',
            'category' => 'Bearing',
            'spec' => null,
            'sort_no' => 1,
        ]);

        $machine->components()->attach($component->id, ['sort_no' => 1]);

        $this->actingAs($admin)->get(route('machines.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('machines', 1)
                ->where('machines.0.component_count', 1)
                ->where('machines.0.components.0.name', 'Bearing')
                ->where('machines.0.components.0.category', 'Bearing'));
    }

    public function test_teknisi_tidak_boleh_membuka_halaman_mesin(): void
    {
        $teknisi = User::factory()->create()->assignRole('technician');

        $this->actingAs($teknisi)->get(route('machines.index'))->assertForbidden();
    }
}
