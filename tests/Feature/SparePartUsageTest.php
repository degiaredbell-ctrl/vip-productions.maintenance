<?php

namespace Tests\Feature;

use App\Models\Machine;
use App\Models\SparePart;
use App\Models\SparePartMovement;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Form Maintenance - Suku Cadang: catat pemakaian suku cadang ke unit
 * (Mesin/Utility) per periode PM. Pemakaian disimpan sebagai transaksi stok
 * keluar bertujuan unit, dan stok berkurang otomatis.
 */
class SparePartUsageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RoleSeeder::class);
    }

    private function unit(): Machine
    {
        return Machine::create([
            'code' => 'MC-'.uniqid(),
            'name' => 'Mesin Uji',
            'week_group' => 1,
            'is_active' => true,
            'sort_no' => 1,
        ]);
    }

    private function part(int $stock = 10): SparePart
    {
        return SparePart::create([
            'code' => 'BRG-'.uniqid(),
            'name' => 'Bearing',
            'unit' => 'pcs',
            'stock' => $stock,
        ]);
    }

    public function test_halaman_pemakaian_dapat_dibuka_pemegang_sparepart_view(): void
    {
        $teknisi = User::factory()->create()->assignRole('technician');

        $this->actingAs($teknisi)
            ->withHeaders($this->inertiaHeaders())
            ->get(route('form-maintenance.suku-cadang'))
            ->assertOk()
            ->assertJsonPath('component', 'FormMaintenance/SukuCadang');
    }

    public function test_catat_pemakaian_mengurangi_stok_dan_menyimpan_unit_periode(): void
    {
        $admin = User::factory()->create()->assignRole('admin');
        $unit = $this->unit();
        $part = $this->part(10);

        $this->actingAs($admin)
            ->post(route('form-maintenance.suku-cadang.store'), [
                'machine_id' => $unit->id,
                'spare_part_id' => $part->id,
                'year' => now()->year,
                'period' => 'jan-feb',
                'qty' => 3,
                'used_at' => now()->toDateString(),
                'note' => 'Ganti bearing',
            ])
            ->assertRedirect();

        $this->assertSame(7, $part->fresh()->stock);

        $this->assertDatabaseHas('spare_part_movements', [
            'spare_part_id' => $part->id,
            'machine_id' => $unit->id,
            'type' => SparePartMovement::TYPE_OUT,
            'qty' => 3,
            'year' => now()->year,
            'period' => 'jan-feb',
        ]);
    }

    public function test_jumlah_melebihi_stok_ditolak(): void
    {
        $admin = User::factory()->create()->assignRole('admin');
        $unit = $this->unit();
        $part = $this->part(2);

        $this->actingAs($admin)
            ->from(route('form-maintenance.suku-cadang'))
            ->post(route('form-maintenance.suku-cadang.store'), [
                'machine_id' => $unit->id,
                'spare_part_id' => $part->id,
                'year' => now()->year,
                'period' => 'jan-feb',
                'qty' => 5,
            ])
            ->assertSessionHasErrors('qty');

        $this->assertSame(2, $part->fresh()->stock);
        $this->assertSame(0, SparePartMovement::count());
    }

    public function test_mencatat_pemakaian_butuh_permission_sparepart_manage(): void
    {
        $teknisi = User::factory()->create()->assignRole('technician');
        $unit = $this->unit();
        $part = $this->part(5);

        $this->actingAs($teknisi)
            ->post(route('form-maintenance.suku-cadang.store'), [
                'machine_id' => $unit->id,
                'spare_part_id' => $part->id,
                'year' => now()->year,
                'period' => 'jan-feb',
                'qty' => 1,
            ])
            ->assertForbidden();

        $this->assertSame(5, $part->fresh()->stock);
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
