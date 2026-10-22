<?php

namespace Tests\Feature;

use App\Models\ChecklistTemplate;
use App\Models\Component;
use App\Models\Machine;
use App\Services\TemplateCsvImportService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class TemplateCsvImportTest extends TestCase
{
    use RefreshDatabase;

    private string $csv;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RoleSeeder::class);

        $this->csv = tempnam(sys_get_temp_dir(), 'tpl').'.csv';
        file_put_contents($this->csv, "\u{FEFF}Penyesuaian,Nama Template,,Code,Komponen 1,Komponen 2,Komponen 3,Komponen 4,Komponen 5\n"
            ."Sesuai,Template Timbangan Digital,,MC.7-LCS30-101-74,Power Input,Switch ON / OFF,Keyped,Loadcell,kalibrasi\n"
            ."Penyesuaian (Lotcell -> Loadcell),Template Timbangan Digital,,MC.7-LCS30-102-75,Power Input,switch ON/OFF,Keyped,Lotcell,kalibrasi\n"
            ."Sesuai,Template TOM Basic,,MC.2-TOMRT-101-20,Power Input,Kondisi Baut,\"Power Transfer(gear, rantai, v-belt)\",Grease,\n");

        foreach (['MC.7-LCS30-101' => 'generic', 'MC.7-LCS30-102' => 'generic', 'MC.2-TOMRT-101' => 'tom'] as $code => $type) {
            Machine::create(['code' => $code, 'name' => $code, 'type' => $type, 'week_group' => 1, 'is_active' => true, 'sort_no' => 1]);
        }

        Component::create(['name' => 'Power Input', 'category' => 'Listrik', 'spec' => '220-240V']);
        Component::create(['name' => 'Power Input', 'category' => 'Listrik', 'spec' => 'Normal']);
        Component::create(['name' => 'Load Cell', 'category' => 'Listrik', 'spec' => 'Normal']);
        Component::create(['name' => 'Kalibrasi', 'category' => 'Kalibrasi', 'spec' => 'Akurat']);
    }

    protected function tearDown(): void
    {
        @unlink($this->csv);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        parent::tearDown();
    }

    public function test_dry_run_tidak_mengubah_database(): void
    {
        $this->artisan('templates:import', ['file' => $this->csv, '--dry-run' => true])->assertSuccessful();

        $this->assertSame(0, ChecklistTemplate::count());
        $this->assertSame(4, Component::count());
    }

    public function test_impor_membuat_template_menyesuaikan_komponen_dan_menerapkan_ke_unit(): void
    {
        $this->artisan('templates:import', ['file' => $this->csv])->assertSuccessful();

        $timbangan = ChecklistTemplate::where('name', 'Template Timbangan Digital')->sole();
        $this->assertSame('machine', $timbangan->scope);
        $this->assertSame('generic', $timbangan->machine_type);
        $this->assertSame(
            ['Power Input', 'Switch ON/OFF', 'Keypad', 'Load Cell', 'Kalibrasi'],
            $timbangan->components->pluck('name')->all(),
        );
        // Power Input dengan Standar "Normal" dipilih, bukan duplikat 220-240V.
        $this->assertSame('Normal', $timbangan->components->first()->spec);

        // Alias tidak membuat duplikat master komponen.
        $this->assertSame(1, Component::where('name', 'Load Cell')->count());
        $this->assertSame(1, Component::where('name', 'Switch ON/OFF')->count());

        foreach (['MC.7-LCS30-101', 'MC.7-LCS30-102'] as $code) {
            $unit = Machine::where('code', $code)->sole();
            $this->assertSame($timbangan->id, $unit->template_id);
            $this->assertSame($timbangan->components->pluck('id')->all(), $unit->components->pluck('id')->all());
        }

        $tom = Machine::where('code', 'MC.2-TOMRT-101')->sole();
        $this->assertSame('Template TOM Basic', $tom->template->name);
        $this->assertSame('tom', $tom->template->machine_type);
        $this->assertCount(4, $tom->components);

        // Dijalankan ulang: idempoten.
        $this->artisan('templates:import', ['file' => $this->csv])->assertSuccessful();
        $this->assertSame(2, ChecklistTemplate::count());
        $this->assertSame(1, Component::where('name', 'Keypad')->count());
    }

    public function test_kode_unit_membuang_akhiran_nomor_baris(): void
    {
        $this->assertSame('MC.2-TOMPB-101', TemplateCsvImportService::unitCode('MC.2-TOMPB-101-16'));
        $this->assertSame('MC.9-TM25M-102', TemplateCsvImportService::unitCode('MC.9-TM25M-102-108'));
    }
}
