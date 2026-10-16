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

class SparePartDashboardTest extends TestCase
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

    private function part(string $code, int $stock, int $min, string $category = 'Bearing'): SparePart
    {
        return SparePart::create([
            'code' => $code,
            'name' => 'Part '.$code,
            'category' => $category,
            'unit' => 'pcs',
            'stock' => $stock,
            'min_stock' => $min,
        ]);
    }

    private function move(SparePart $part, string $type, int $qty, string $date, ?Machine $machine = null): void
    {
        SparePartMovement::create([
            'spare_part_id' => $part->id,
            'machine_id' => $machine?->id,
            'type' => $type,
            'qty' => $qty,
            'moved_at' => $date,
        ]);
    }

    public function test_dashboard_merangkum_stok_restock_dan_pemakaian(): void
    {
        $habis = $this->part('A1', 0, 2);
        $tipis = $this->part('B1', 1, 5);
        $aman = $this->part('C1', 50, 5, 'Sensor');
        $diam = $this->part('D1', 9, 0, 'Sensor');

        $mesin = Machine::create([
            'code' => 'M-1', 'name' => 'Mixer 1', 'type' => 'mixer',
            'week_group' => 1, 'is_active' => true, 'sort_no' => 1,
        ]);

        $this->move($aman, 'out', 6, now()->toDateString(), $mesin);
        $this->move($aman, 'in', 10, now()->toDateString());
        $this->move($tipis, 'out', 3, now()->subMonth()->toDateString(), $mesin);
        // Di luar rentang 3 bulan.
        $this->move($aman, 'out', 99, now()->subMonths(8)->toDateString(), $mesin);

        $user = User::factory()->create()->assignRole('viewer');

        $this->actingAs($user)
            ->get(route('spareparts.dashboard', ['range' => 3]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('SpareParts/Dashboard')
                ->where('range', 3)
                ->where('kpi.total', 4)
                ->where('kpi.out', 1)
                ->where('kpi.low', 1)
                ->where('kpi.ok', 2)
                ->where('kpi.no_min', 1)
                ->where('kpi.out_qty_range', 9)
                ->where('kpi.in_qty_range', 10)
                ->where('kpi.units_served', 1)
                // Habis didahulukan, lalu yang menipis.
                ->where('restock.0.code', 'A1')
                ->where('restock.0.shortage', 3)
                ->where('restock.1.code', 'B1')
                ->where('restock.1.shortage', 5)
                ->has('restock', 2)
                ->has('trend', 3)
                ->where('topParts.0.code', 'C1')
                ->where('topParts.0.qty', 6)
                ->where('topUnits.0.qty', 9)
                // D1 punya stok tapi tidak pernah dipakai.
                ->where('slowMoving', fn ($rows) => collect($rows)->pluck('code')->contains('D1')
                    && ! collect($rows)->pluck('code')->contains('C1'))
                ->has('recent', 4)
            );

        $this->assertNotNull($diam);
        $this->assertNotNull($habis);
    }

    public function test_rentang_tidak_valid_kembali_ke_6_bulan(): void
    {
        $user = User::factory()->create()->assignRole('viewer');

        $this->actingAs($user)
            ->get(route('spareparts.dashboard', ['range' => 99]))
            ->assertInertia(fn ($page) => $page->where('range', 6)->has('trend', 6));
    }

    public function test_tanpa_izin_sparepart_view_ditolak(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('spareparts.dashboard'))->assertForbidden();
    }
}
