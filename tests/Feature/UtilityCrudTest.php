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
 * CRUD unit utility dibatasi role Admin. Manager yang memang memegang
 * permission `machine.manage` (dipakai halaman Mesin) tetap tidak boleh
 * menambah/mengubah/menghapus utility dari halaman Utility.
 */
class UtilityCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RoleSeeder::class);
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

    public function test_admin_bisa_menambah_utility(): void
    {
        $admin = User::factory()->create()->assignRole('admin');

        $this->actingAs($admin)->post(route('utility.store'), [
            'code' => 'UTL-NEW',
            'name' => 'Kompresor Baru',
            'location' => 'Ruang Utility',
            'type' => MachineType::Utility->value,
            'week_group' => 2,
            'is_active' => 1,
        ])->assertRedirect();

        $this->assertDatabaseHas('assets', ['code' => 'UTL-NEW', 'type' => 'utility']);
    }

    public function test_manager_tidak_boleh_menambah_utility(): void
    {
        $manager = User::factory()->create()->assignRole('manager');

        $this->actingAs($manager)->post(route('utility.store'), [
            'code' => 'UTL-XYZ',
            'name' => 'Tidak Boleh',
            'type' => MachineType::Utility->value,
            'week_group' => 1,
            'is_active' => 1,
        ])->assertForbidden();

        $this->assertDatabaseMissing('assets', ['code' => 'UTL-XYZ']);
    }

    public function test_admin_bisa_mengubah_menghapus_dan_memulihkan_utility(): void
    {
        $admin = User::factory()->create()->assignRole('admin');
        $utility = $this->utility();

        $this->actingAs($admin)->put(route('utility.update', $utility), [
            'code' => 'UTL-001',
            'name' => 'Chiller Diubah',
            'type' => MachineType::Utility->value,
            'week_group' => 3,
            'is_active' => 1,
        ])->assertRedirect();

        $this->assertDatabaseHas('assets', ['id' => $utility->id, 'name' => 'Chiller Diubah']);

        $this->actingAs($admin)->delete(route('utility.destroy', $utility))->assertRedirect();
        $this->assertSoftDeleted('assets', ['id' => $utility->id]);

        $this->actingAs($admin)->patch(route('utility.restore', $utility))->assertRedirect();
        $this->assertDatabaseHas('assets', ['id' => $utility->id, 'deleted_at' => null]);
    }

    public function test_manager_tidak_boleh_mengubah_utility(): void
    {
        $manager = User::factory()->create()->assignRole('manager');
        $utility = $this->utility();

        $this->actingAs($manager)->put(route('utility.update', $utility), [
            'code' => 'UTL-001',
            'name' => 'Diubah',
            'type' => MachineType::Utility->value,
            'week_group' => 1,
            'is_active' => 1,
        ])->assertForbidden();
    }

    public function test_flag_manage_hanya_untuk_admin(): void
    {
        $admin = User::factory()->create()->assignRole('admin');
        $manager = User::factory()->create()->assignRole('manager');

        $this->actingAs($admin)->get(route('utility.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('can.manage', true));

        $this->actingAs($manager)->get(route('utility.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('can.manage', false));
    }
}
