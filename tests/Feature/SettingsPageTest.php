<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Halaman /settings menggantikan item menu terpisah (Mesin, Utility, Suku
 * Cadang, Komponen / Parts, Form Maintenance) di sidebar dengan satu pusat
 * kartu. Route lama tetap ada, jadi test ini hanya mengunci keberadaan route
 * baru dan tujuannya.
 */
class SettingsPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RoleSeeder::class);
    }

    public function test_route_settings_berada_di_path_settings(): void
    {
        $this->assertSame(url('/settings'), route('settings'));
    }

    public function test_teknisi_bisa_membuka_halaman_settings(): void
    {
        $teknisi = User::factory()->create()->assignRole('technician');

        $this->actingAs($teknisi)
            ->withHeaders($this->inertiaHeaders())
            ->get(route('settings'))
            ->assertOk()
            ->assertJsonPath('component', 'Settings/Index');
    }

    public function test_tamu_diarahkan_ke_login(): void
    {
        $this->get(route('settings'))->assertRedirect(route('login'));
    }

    public function test_halaman_kelola_lama_masih_dapat_diakses(): void
    {
        // Kartu Mesin & Utility mengarah ke halaman kelola (CRUD) yang lama,
        // jadi route-nya harus tetap hidup meski item menunya dihapus.
        $this->assertSame(url('/machines'), route('machines.index'));
        $this->assertSame(url('/utility'), route('utility.index'));
    }

    private function inertiaHeaders(): array
    {
        $manifest = public_path('build/manifest.json');

        return [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => is_file($manifest) ? hash_file('xxh128', $manifest) : '',
        ];
    }
}
