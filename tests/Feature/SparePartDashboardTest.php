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

    public function test_grafik_garis_harian_mingguan_bulanan(): void
    {
        $part = $this->part('A1', 20, 2);

        $this->move($part, 'in', 10, now()->toDateString());
        $this->move($part, 'out', 4, now()->toDateString());
        $this->move($part, 'out', 2, now()->toDateString());
        // Kemarin: tetap di bulan/minggu yang bisa berbeda, jadi hanya dicek di harian.
        $this->move($part, 'out', 3, now()->subDay()->toDateString());
        // Di luar 30 hari, tapi masih masuk 12 bulan.
        $this->move($part, 'in', 7, now()->subDays(40)->toDateString());

        $this->actingAs(User::factory()->create()->assignRole('viewer'))
            ->get(route('spareparts.dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('lineTrend.daily', 30)
                ->has('lineTrend.weekly', 12)
                ->has('lineTrend.monthly', 12)
                // Titik terakhir = hari ini.
                ->where('lineTrend.daily.29.key', now()->toDateString())
                ->where('lineTrend.daily.29.in', 10)
                ->where('lineTrend.daily.29.out', 6)
                ->where('lineTrend.daily.28.out', 3)
                // Total 30 hari tidak memuat transaksi 40 hari lalu.
                ->where('lineTrend.daily', fn ($rows) => collect($rows)->sum('in') === 10)
                // Minggu berjalan dimulai hari Senin.
                ->where('lineTrend.weekly.11.key', now()->startOfWeek()->toDateString())
                ->where('lineTrend.monthly.11.key', now()->startOfMonth()->toDateString())
                // Setahun memuat semua transaksi.
                ->where('lineTrend.monthly', fn ($rows) => collect($rows)->sum('in') === 17
                    && collect($rows)->sum('out') === 9)
            );
    }

    public function test_dashboard_suku_cadang_juga_tampil_di_beranda(): void
    {
        $part = $this->part('A1', 0, 2);
        $this->move($part, 'out', 5, now()->toDateString());

        $this->actingAs(User::factory()->create()->assignRole('viewer'))
            ->get(route('dashboard', ['sp_range' => 3]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard/Index')
                ->where('sparePartDashboard.range', 3)
                ->where('sparePartDashboard.kpi.out', 1)
                ->where('sparePartDashboard.lineTrend.daily.29.out', 5)
            );
    }

    public function test_beranda_tanpa_izin_sparepart_view_tidak_memuat_suku_cadang(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('dashboard.view');

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('sparePartDashboard', null));
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
