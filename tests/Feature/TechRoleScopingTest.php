<?php

namespace Tests\Feature;

use App\Enums\MachineType;
use App\Models\Machine;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Scope role teknisi: tech-machines hanya mesin, tech-utilities hanya utility,
 * tech-all keduanya + CRUD + komponen. Batasan harus konsisten di menu,
 * halaman /form-maintenance, dan akses URL langsung (form PM mesin/utility).
 */
class TechRoleScopingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RoleSeeder::class);
    }

    private function unit(string $type, string $code): Machine
    {
        return Machine::create([
            'code' => $code,
            'name' => 'Unit '.$code,
            'type' => $type,
            'week_group' => 1,
            'is_active' => true,
            'sort_no' => 1,
        ]);
    }

    private function user(string $role): User
    {
        return User::factory()->create()->assignRole($role);
    }

    public function test_tech_machines_hanya_bisa_akses_scope_mesin(): void
    {
        $user = $this->user('tech-machines');

        // Halaman mesin terbuka, tetapi read-only (tanpa tombol CRUD).
        $this->actingAs($user)->get(route('machines.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('can.manage', false)
                ->where('can.fill', true));

        // Utility tertutup di semua pintu.
        $this->actingAs($user)->get(route('utility.index'))->assertForbidden();
        $this->actingAs($user)->get(route('form-maintenance.mesin'))->assertOk();
        $this->actingAs($user)->get(route('form-maintenance.utility'))->assertForbidden();
    }

    public function test_tech_machines_tidak_bisa_membuka_form_pm_utility(): void
    {
        $user = $this->user('tech-machines');
        $machine = $this->unit(MachineType::Generic->value, 'MC-100');
        $utility = $this->unit(MachineType::Utility->value, 'UTL-100');

        $this->actingAs($user)->get(route('machines.pm.create', $machine))->assertOk();
        $this->actingAs($user)->get(route('machines.pm.create', $utility))->assertForbidden();
    }

    public function test_tech_machines_tidak_bisa_crud_mesin(): void
    {
        $user = $this->user('tech-machines');

        $this->actingAs($user)->post(route('machines.store'), [
            'code' => 'MC-XYZ',
            'name' => 'Tidak Boleh',
            'type' => MachineType::Generic->value,
            'week_group' => 1,
            'is_active' => 1,
        ])->assertForbidden();

        $this->assertDatabaseMissing('assets', ['code' => 'MC-XYZ']);
    }

    public function test_tech_utilities_hanya_bisa_akses_scope_utility(): void
    {
        $user = $this->user('tech-utilities');

        $this->actingAs($user)->get(route('utility.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('can.manage', false)
                ->where('can.fill', true));

        $this->actingAs($user)->get(route('machines.index'))->assertForbidden();
        $this->actingAs($user)->get(route('form-maintenance.utility'))->assertOk();
        $this->actingAs($user)->get(route('form-maintenance.mesin'))->assertForbidden();
    }

    public function test_tech_utilities_tidak_bisa_membuka_form_pm_mesin_dan_crud_utility(): void
    {
        $user = $this->user('tech-utilities');
        $machine = $this->unit(MachineType::Generic->value, 'MC-200');
        $utility = $this->unit(MachineType::Utility->value, 'UTL-200');

        $this->actingAs($user)->get(route('machines.pm.create', $utility))->assertOk();
        $this->actingAs($user)->get(route('machines.pm.create', $machine))->assertForbidden();

        $this->actingAs($user)->post(route('utility.store'), [
            'code' => 'UTL-XYZ',
            'name' => 'Tidak Boleh',
            'type' => MachineType::Utility->value,
            'week_group' => 1,
            'is_active' => 1,
        ])->assertForbidden();

        $this->assertDatabaseMissing('assets', ['code' => 'UTL-XYZ']);
    }

    public function test_tech_all_bisa_akses_dan_crud_mesin_dan_utility(): void
    {
        $user = $this->user('tech-all');

        $this->actingAs($user)->get(route('machines.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('can.manage', true));

        $this->actingAs($user)->get(route('utility.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('can.manage', true));

        // Form maintenance lengkap, termasuk kartu Komponen.
        $this->actingAs($user)->get(route('form-maintenance.mesin'))->assertOk();
        $this->actingAs($user)->get(route('form-maintenance.utility'))->assertOk();
        $this->actingAs($user)->get(route('admin.components'))->assertOk();

        $this->actingAs($user)->post(route('machines.store'), [
            'code' => 'MC-NEW',
            'name' => 'Mesin Baru',
            'type' => MachineType::Generic->value,
            'week_group' => 1,
            'is_active' => 1,
        ])->assertRedirect();

        $this->actingAs($user)->post(route('utility.store'), [
            'code' => 'UTL-NEW',
            'name' => 'Utility Baru',
            'type' => MachineType::Utility->value,
            'week_group' => 1,
            'is_active' => 1,
        ])->assertRedirect();

        $this->assertDatabaseHas('assets', ['code' => 'MC-NEW']);
        $this->assertDatabaseHas('assets', ['code' => 'UTL-NEW']);
    }

    public function test_role_dengan_satu_scope_tidak_bisa_tembus_endpoint_lain(): void
    {
        // Manager memegang machine.manage tetapi bukan utility.manage, jadi
        // tidak boleh membuat unit utility lewat endpoint mesin — bukti izin
        // mengikuti tipe aset, bukan hanya route yang dipakai.
        $manager = $this->user('manager');

        $this->actingAs($manager)->post(route('machines.store'), [
            'code' => 'UTL-VIA-MESIN',
            'name' => 'Utility lewat endpoint mesin',
            'type' => MachineType::Utility->value,
            'week_group' => 1,
            'is_active' => 1,
        ])->assertForbidden();

        $this->assertDatabaseMissing('assets', ['code' => 'UTL-VIA-MESIN']);

        // Mesin biasa tetap boleh (machine.manage).
        $this->actingAs($manager)->post(route('machines.store'), [
            'code' => 'MC-MGR-OK',
            'name' => 'Mesin Manager',
            'type' => MachineType::Generic->value,
            'week_group' => 1,
            'is_active' => 1,
        ])->assertRedirect();

        $this->assertDatabaseHas('assets', ['code' => 'MC-MGR-OK']);
    }
}
