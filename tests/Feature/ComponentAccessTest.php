<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Master Komponen / Parts hanya untuk role Admin dan tech-all
 * (permission `component.manage`), terpisah dari halaman Template.
 */
class ComponentAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RoleSeeder::class);
    }

    protected function tearDown(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        parent::tearDown();
    }

    private function as(string $role): User
    {
        return User::factory()->create()->assignRole($role);
    }

    public function test_admin_dan_tech_all_bisa_membuka_komponen(): void
    {
        foreach (['admin', 'tech-all'] as $role) {
            $this->actingAs($this->as($role))
                ->get(route('admin.components'))
                ->assertOk();
        }
    }

    public function test_role_lain_tidak_bisa_membuka_maupun_mengubah_komponen(): void
    {
        foreach (['manager', 'technician', 'user', 'viewer', 'tech-machines', 'tech-utilities'] as $role) {
            $user = $this->as($role);

            $this->actingAs($user)->get(route('admin.components'))->assertForbidden();
            $this->actingAs($user)
                ->post(route('admin.components.store'), ['name' => 'X', 'category' => 'Umum'])
                ->assertForbidden();
        }
    }

    public function test_pemegang_template_manage_tanpa_component_manage_tetap_bisa_kelola_template(): void
    {
        // Manager memegang template.manage tetapi bukan component.manage.
        $manager = $this->as('manager');

        $this->assertFalse($manager->can('component.manage'));
        $this->actingAs($manager)->get(route('admin.templates'))->assertOk();
        $this->actingAs($manager)->get(route('admin.components'))->assertForbidden();
    }
}
