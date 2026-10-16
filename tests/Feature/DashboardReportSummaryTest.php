<?php

namespace Tests\Feature;

use App\Enums\Period;
use App\Models\Machine;
use App\Models\PmRecord;
use App\Models\User;
use App\Services\ReportService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Kartu "Ringkasan Tindakan" & "Komponen Paling Sering Diganti" dulu ada di
 * halaman /reports, kini dilebur ke Beranda. Test ini menjaga agar data yang
 * dikirim ke Beranda benar per periode dan ekspor tetap bisa diakses
 * (`docs`/PRD tidak lagi menyebut halaman terpisah).
 */
class DashboardReportSummaryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RoleSeeder::class);
    }

    private function record(string $period, array $items, ?int $year = null): PmRecord
    {
        $machine = Machine::create([
            'code' => 'MC-'.uniqid(),
            'name' => 'Mesin Ringkasan',
            'week_group' => 1,
            'is_active' => true,
            'sort_no' => 1,
        ]);

        $record = PmRecord::create([
            'machine_id' => $machine->id,
            'year' => $year ?? now()->year,
            'period' => $period,
            'technician_name' => 'Budi',
            'status' => 'draft',
        ]);

        foreach ($items as $item) {
            $record->items()->create(array_merge([
                'item_name' => 'Item',
                'category' => 'Mekanik',
                'parts_replaced' => 0,
            ], $item));
        }

        return $record;
    }

    public function test_summary_by_period_memakai_angka_tiap_periode_dan_agregat_setahun(): void
    {
        $year = now()->year;

        // Jan–Feb: 1 bersihkan + 1 ganti bearing (2 pcs).
        $this->record(Period::JanFeb->value, [
            ['item_name' => 'Bearing', 'act_clean' => true],
            ['item_name' => 'Bearing', 'act_replace' => true, 'parts_replaced' => 2],
        ], $year);

        // Mar–Apr: 1 perbaiki + 1 ganti bearing (1 pc).
        $this->record(Period::MarApr->value, [
            ['item_name' => 'Belt', 'act_repair' => true],
            ['item_name' => 'Bearing', 'act_replace' => true, 'parts_replaced' => 1],
        ], $year);

        // Tahun lain tidak boleh ikut terhitung.
        $this->record(Period::JanFeb->value, [
            ['item_name' => 'Bearing', 'act_replace' => true, 'parts_replaced' => 99],
        ], $year - 1);

        $summary = ReportService::summaryByPeriod($year);

        $this->assertSame(1, $summary[Period::JanFeb->value]['actions']['clean']);
        $this->assertSame(1, $summary[Period::JanFeb->value]['actions']['replace']);
        $this->assertSame(1, $summary[Period::MarApr->value]['actions']['repair']);
        $this->assertSame(0, $summary[Period::MeiJun->value]['actions']['clean']);

        // Agregat setahun menjumlahkan semua periode (99 dari tahun lain diabaikan).
        $this->assertSame(1, $summary['all']['actions']['clean']);
        $this->assertSame(1, $summary['all']['actions']['repair']);
        $this->assertSame(2, $summary['all']['actions']['replace']);

        // Top parts per periode hanya memuat komponen yang benar-benar diganti.
        $this->assertSame('Bearing', $summary[Period::JanFeb->value]['topParts'][0]['item_name']);
        $this->assertSame(2, $summary[Period::JanFeb->value]['topParts'][0]['total']);
        $this->assertSame(3, $summary['all']['topParts'][0]['total']);
        $this->assertCount(0, $summary[Period::MeiJun->value]['topParts']);
    }

    public function test_beranda_mengirim_ringkasan_laporan_untuk_pemegang_report_view(): void
    {
        $teknisi = User::factory()->create()->assignRole('technician');

        $this->record(Period::JanFeb->value, [
            ['item_name' => 'Bearing', 'act_replace' => true, 'parts_replaced' => 4],
        ]);

        $this->actingAs($teknisi)
            ->withHeaders($this->inertiaHeaders())
            ->get(route('dashboard', ['year' => now()->year]))
            ->assertOk()
            ->assertJsonPath('props.reportSummary.all.actions.replace', 1)
            ->assertJsonPath('props.reportSummary.all.topParts.0.item_name', 'Bearing')
            ->assertJsonPath('props.reportSummary.all.topParts.0.total', 4);
    }

    public function test_ekspor_laporan_dipindah_ke_beranda_dan_halaman_lama_hilang(): void
    {
        $teknisi = User::factory()->create()->assignRole('technician');

        $this->assertSame(url('/dashboard/report/export'), route('dashboard.report.export'));

        $this->actingAs($teknisi)
            ->get(route('dashboard.report.export', ['year' => now()->year, 'format' => 'xlsx']))
            ->assertOk();

        $this->actingAs($teknisi)->get('/reports')->assertNotFound();
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
