<?php

namespace Tests\Feature;

use App\Models\SparePart;
use App\Models\SparePartMovement;
use App\Models\User;
use App\Services\SparePartImportService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Modul Suku Cadang: impor CSV, CRUD, transaksi stok, dan pembatasan akses.
 */
class SparePartTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RoleSeeder::class);
    }

    private function user(string $role): User
    {
        return User::factory()->create()->assignRole($role);
    }

    public function test_impor_membaca_csv_referensi_yang_header_dua_baris(): void
    {
        $report = app(SparePartImportService::class)->importDefault();

        // File referensi berisi 22 baris data; semuanya harus terbaca.
        $this->assertSame(22, $report['total']);
        $this->assertSame(22, $report['created']);
        $this->assertSame(22, SparePart::count());

        $sensor = SparePart::where('code', 'SM381')->firstOrFail();

        $this->assertSame('Sensor', $sensor->category);
        $this->assertSame('Sensor - Magnetik CMSE-020 AIRTAC', $sensor->name);
        $this->assertSame('AIRTAC', $sensor->brand);
        $this->assertSame('CMSE-020 AIRTAC', $sensor->type);
        $this->assertSame('pcs', $sensor->unit);
        $this->assertSame(1, $sensor->stock);
        $this->assertSame(1, $sensor->min_stock);
        $this->assertSame('Air Tac Official - (Tokopedia)', $sensor->supplier);
        $this->assertSame('Factory Milenium', $sensor->location);
    }

    public function test_impor_menangani_baris_dengan_koma_tanpa_kutip_di_nama(): void
    {
        app(SparePartImportService::class)->importDefault();

        // Baris 22 kolom karena koma di dalam nama harus digabung kembali.
        $part = SparePart::where('code', 'SM400')->firstOrFail();

        $this->assertSame('Sensor - Limit switch roller 380VAC/220VDC, 10A', $part->name);
        $this->assertSame('CHINT', $part->brand);
        $this->assertSame('YBLXW-6/11HL', $part->type);
        $this->assertSame('Mesin Carton Sealer Line Manual', $part->description);
        $this->assertSame('Listrik Nusantara (Tokopedia)', $part->supplier);
        $this->assertSame('Factory Milenium', $part->location);
    }

    public function test_impor_ulang_memperbarui_tanpa_duplikat(): void
    {
        $service = app(SparePartImportService::class);

        $service->importDefault();
        $report = $service->importDefault();

        $this->assertSame(0, $report['created']);
        $this->assertSame(0, $report['updated']);
        $this->assertSame(22, SparePart::count());
    }

    public function test_admin_dapat_crud_suku_cadang(): void
    {
        $admin = $this->user('admin');

        $this->actingAs($admin)->post(route('spareparts.store'), [
            'code' => 'SP900',
            'name' => 'Bearing 6204',
            'category' => 'Bearing',
            'unit' => 'pcs',
            'stock' => 5,
            'min_stock' => 2,
        ])->assertRedirect();

        $part = SparePart::where('code', 'SP900')->firstOrFail();
        $this->assertSame(5, $part->stock);
        $this->assertSame(2, $part->min_stock);
        $this->assertTrue($part->isLowStock() === false);

        $this->actingAs($admin)->put(route('spareparts.update', $part->id), [
            'code' => 'SP900',
            'name' => 'Bearing 6204 ZZ',
            'category' => 'Bearing',
            'unit' => 'pcs',
            'stock' => 1,
            'min_stock' => 2,
        ])->assertRedirect();

        $part->refresh();
        $this->assertSame('Bearing 6204 ZZ', $part->name);
        $this->assertTrue($part->isLowStock());

        $this->actingAs($admin)->delete(route('spareparts.destroy', $part->id))->assertRedirect();
        $this->assertSoftDeleted('spare_parts', ['id' => $part->id]);
    }

    public function test_kode_harus_unik(): void
    {
        $admin = $this->user('admin');

        SparePart::create(['code' => 'SP901', 'name' => 'Ada', 'stock' => 0]);

        $this->actingAs($admin)->post(route('spareparts.store'), [
            'code' => 'SP901',
            'name' => 'Duplikat',
        ])->assertSessionHasErrors('code');
    }

    public function test_admin_dapat_hapus_massal(): void
    {
        $admin = $this->user('admin');

        $a = SparePart::create(['code' => 'SPA', 'name' => 'A', 'stock' => 0]);
        $b = SparePart::create(['code' => 'SPB', 'name' => 'B', 'stock' => 0]);
        $c = SparePart::create(['code' => 'SPC', 'name' => 'C', 'stock' => 0]);

        $this->actingAs($admin)
            ->delete(route('spareparts.bulk-destroy'), ['ids' => [$a->id, $b->id]])
            ->assertRedirect();

        $this->assertSoftDeleted('spare_parts', ['id' => $a->id]);
        $this->assertSoftDeleted('spare_parts', ['id' => $b->id]);
        $this->assertNotSoftDeleted('spare_parts', ['id' => $c->id]);
    }

    public function test_hapus_massal_mengabaikan_id_yang_tidak_ada(): void
    {
        $admin = $this->user('admin');

        $a = SparePart::create(['code' => 'SPA', 'name' => 'A', 'stock' => 0]);

        $this->actingAs($admin)
            ->delete(route('spareparts.bulk-destroy'), ['ids' => [$a->id, 999999]])
            ->assertRedirect();

        $this->assertSoftDeleted('spare_parts', ['id' => $a->id]);
    }

    public function test_hapus_massal_tanpa_id_ditolak(): void
    {
        $admin = $this->user('admin');

        $this->actingAs($admin)
            ->delete(route('spareparts.bulk-destroy'), ['ids' => []])
            ->assertSessionHasErrors('ids');
    }

    public function test_tech_machines_tidak_bisa_hapus_massal(): void
    {
        $technician = $this->user('tech-machines');

        $a = SparePart::create(['code' => 'SPA', 'name' => 'A', 'stock' => 0]);

        $this->actingAs($technician)
            ->delete(route('spareparts.bulk-destroy'), ['ids' => [$a->id]])
            ->assertForbidden();

        $this->assertNotSoftDeleted('spare_parts', ['id' => $a->id]);
    }

    public function test_viewer_bisa_melihat_tetapi_tidak_bisa_mengelola(): void
    {
        $viewer = $this->user('viewer');

        SparePart::create(['code' => 'SP902', 'name' => 'Filter', 'stock' => 3]);

        $this->actingAs($viewer)->get(route('spareparts.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('SpareParts/Index')
                ->where('can.manage', false)
                ->has('parts', 1));

        $this->actingAs($viewer)->post(route('spareparts.store'), [
            'code' => 'SP903',
            'name' => 'Tidak boleh',
        ])->assertForbidden();
    }

    public function test_tech_all_dapat_mengelola(): void
    {
        $techAll = $this->user('tech-all');

        $this->actingAs($techAll)->get(route('spareparts.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('can.manage', true));
    }

    public function test_transaksi_stok_masuk_dan_keluar_mengubah_stok(): void
    {
        $admin = $this->user('admin');
        $part = SparePart::create(['code' => 'SP904', 'name' => 'Selang', 'stock' => 5, 'unit' => 'pcs']);

        $this->actingAs($admin)->post(route('spareparts.movements.store', $part->id), [
            'type' => 'in',
            'qty' => 3,
        ])->assertRedirect();

        $this->assertSame(8, $part->fresh()->stock);

        $this->actingAs($admin)->post(route('spareparts.movements.store', $part->id), [
            'type' => 'out',
            'qty' => 2,
        ])->assertRedirect();

        $this->assertSame(6, $part->fresh()->stock);
        $this->assertSame(2, SparePartMovement::where('spare_part_id', $part->id)->count());
    }

    public function test_barang_keluar_melebihi_stok_ditolak(): void
    {
        $admin = $this->user('admin');
        $part = SparePart::create(['code' => 'SP905', 'name' => 'Kabel', 'stock' => 2]);

        $this->actingAs($admin)->post(route('spareparts.movements.store', $part->id), [
            'type' => 'out',
            'qty' => 5,
        ])->assertSessionHasErrors('qty');

        $this->assertSame(2, $part->fresh()->stock);
        $this->assertSame(0, SparePartMovement::where('spare_part_id', $part->id)->count());
    }

    public function test_riwayat_transaksi_dikembalikan_sebagai_json(): void
    {
        $admin = $this->user('admin');
        $part = SparePart::create(['code' => 'SP906', 'name' => 'Pompa', 'stock' => 0]);

        SparePartMovement::create([
            'spare_part_id' => $part->id,
            'user_id' => $admin->id,
            'type' => 'in',
            'qty' => 4,
            'moved_at' => now()->toDateString(),
        ]);

        $this->actingAs($admin)->get(route('spareparts.movements', $part->id))
            ->assertOk()
            ->assertJsonPath('part.code', 'SP906')
            ->assertJsonPath('movements.0.qty', 4)
            ->assertJsonPath('movements.0.type_label', 'Masuk');
    }

    public function test_unggal_csv_mengimpor_berkas_terkirim(): void
    {
        $admin = $this->user('admin');

        $csv = "\"Code.asset,CATEGORY DEVICE,Suku Cadang,MEREK,TYPE,Desc,Last - Modified,Ket. Last - Modified,UNIT,QTY\n"
            ."STOK AKHIR,BARANG KELUAR,Log.Barang Keluar,BARANG MASUK,Log.Barang Masuk,STOK AWAL,MIN,SUPLIER,Kebutuhan Untuk,Penjelasan,Code.loc,LOC\"\n"
            ."UP001,Filter,Filter Oli,BOSCH,F-01,Desc filter,,,pcs,1,2,,1,,2,1,Supplier X,,,1,Gudang A\n";

        $path = tempnam(sys_get_temp_dir(), 'spare').'.csv';
        file_put_contents($path, $csv);

        $file = new \Illuminate\Http\UploadedFile($path, 'suku_cadang.csv', 'text/csv', null, true);

        $this->actingAs($admin)->post(route('spareparts.import'), ['file' => $file])
            ->assertRedirect();

        @unlink($path);

        $part = SparePart::where('code', 'UP001')->firstOrFail();
        $this->assertSame('Filter Oli', $part->name);
        $this->assertSame('BOSCH', $part->brand);
    }

    public function test_template_csv_dapat_diunduh_oleh_pengelola(): void
    {
        $admin = $this->user('admin');

        $response = $this->actingAs($admin)->get(route('spareparts.template'));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();

        // Nama berkas memakai urutan 21 kolom nyata dan baris header harus bisa
        // dikenali parser (memuat "Code.asset").
        $this->assertStringContainsString('Code.asset', $content);
        $this->assertStringContainsString('Suku Cadang', $content);
        $this->assertStringContainsString('LOC', $content);
    }

    public function test_template_csv_dapat_diisi_dan_diunggah_kembali(): void
    {
        $admin = $this->user('admin');

        // Ambil template, isi satu baris data, lalu unggah lagi — persis alur
        // yang dipakai user. Baris header template harus dilewati otomatis.
        $content = $this->actingAs($admin)->get(route('spareparts.template'))->streamedContent();
        $content .= "TPL001,Filter,Filter Udara,BOSCH,F-99,Desc,,,pcs,1,4,,,,\n";

        $path = tempnam(sys_get_temp_dir(), 'spare-template').'.csv';
        file_put_contents($path, $content);

        $file = new \Illuminate\Http\UploadedFile($path, 'template-suku-cadang.csv', 'text/csv', null, true);

        $this->actingAs($admin)->post(route('spareparts.import'), ['file' => $file])
            ->assertRedirect();

        @unlink($path);

        $part = SparePart::where('code', 'TPL001')->firstOrFail();
        $this->assertSame('Filter Udara', $part->name);
        $this->assertSame('BOSCH', $part->brand);
        $this->assertSame(4, $part->stock);
        $this->assertSame('pcs', $part->unit);
    }

    public function test_template_tidak_bisa_diunduh_tanpa_izin_kelola(): void
    {
        $viewer = $this->user('viewer');

        $this->actingAs($viewer)->get(route('spareparts.template'))->assertForbidden();
    }

    public function test_admin_reset_dashboard_saja_menghapus_transaksi_dan_kembalikan_stok(): void
    {
        $admin = $this->user('admin');
        $part = SparePart::create([
            'code' => 'SP906', 'name' => 'Bearing', 'stock' => 10,
            'stock_start' => 5, 'stock_in' => 7, 'stock_out' => 2,
        ]);
        SparePartMovement::create([
            'spare_part_id' => $part->id, 'type' => 'in', 'qty' => 5, 'moved_at' => now(),
        ]);

        $this->actingAs($admin)->post(route('spareparts.reset'), [
            'scope' => 'dashboard',
            'confirm' => 'RESET',
        ])->assertRedirect();

        $this->assertSame(0, SparePartMovement::count());
        $this->assertNotSoftDeleted('spare_parts', ['id' => $part->id]);

        $part->refresh();
        $this->assertSame(5, $part->stock);
        $this->assertSame(0, $part->stock_in);
        $this->assertSame(0, $part->stock_out);
    }

    public function test_admin_reset_semua_menghapus_master_dan_transaksi(): void
    {
        $admin = $this->user('admin');
        $part = SparePart::create(['code' => 'SP907', 'name' => 'Seal', 'stock' => 3]);
        SparePartMovement::create([
            'spare_part_id' => $part->id, 'type' => 'out', 'qty' => 1, 'moved_at' => now(),
        ]);

        $this->actingAs($admin)->post(route('spareparts.reset'), [
            'scope' => 'all',
            'confirm' => 'RESET',
        ])->assertRedirect();

        $this->assertSame(0, SparePartMovement::count());
        $this->assertSame(0, SparePart::withTrashed()->count());
    }

    public function test_reset_ditolak_tanpa_konfirmasi_yang_benar(): void
    {
        $admin = $this->user('admin');
        $part = SparePart::create(['code' => 'SP908', 'name' => 'Oli', 'stock' => 3]);

        $this->actingAs($admin)->post(route('spareparts.reset'), [
            'scope' => 'all',
            'confirm' => 'salah',
        ])->assertSessionHasErrors('confirm');

        $this->assertNotSoftDeleted('spare_parts', ['id' => $part->id]);
    }

    public function test_tech_all_tidak_bisa_reset_meski_punya_sparepart_manage(): void
    {
        $techAll = $this->user('tech-all');

        $this->actingAs($techAll)->post(route('spareparts.reset'), [
            'scope' => 'dashboard',
            'confirm' => 'RESET',
        ])->assertForbidden();
    }
}
