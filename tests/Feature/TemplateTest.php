<?php

namespace Tests\Feature;

use App\Models\ChecklistTemplate;
use App\Models\Component;
use App\Models\Machine;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Settings → Template: template = kumpulan Komponen untuk Mesin/Utility yang
 * bisa diterapkan ke unit.
 */
class TemplateTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Component $oli;
    private Component $baut;
    private Component $sensor;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RoleSeeder::class);

        $this->admin = User::factory()->create()->assignRole('admin');

        $this->oli = Component::create(['name' => 'Oli Gearbox', 'category' => 'Pelumasan', 'spec' => 'Penuh', 'sort_no' => 1]);
        $this->baut = Component::create(['name' => 'Baut Rangka', 'category' => 'Mekanik', 'spec' => 'Kencang', 'sort_no' => 2]);
        $this->sensor = Component::create(['name' => 'Sensor', 'category' => 'Listrik', 'spec' => 'Normal', 'sort_no' => 3]);
    }

    protected function tearDown(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        parent::tearDown();
    }

    private function machine(string $code, string $type = 'mixer', array $attrs = []): Machine
    {
        return Machine::create([
            'code' => $code,
            'name' => "Unit {$code}",
            'type' => $type,
            'week_group' => 1,
            'is_active' => true,
            'sort_no' => 1,
            ...$attrs,
        ]);
    }

    private function template(array $components, string $scope = 'machine', string $type = 'mixer'): ChecklistTemplate
    {
        $template = ChecklistTemplate::create([
            'name' => 'Template Uji',
            'scope' => $scope,
            'machine_type' => $scope === 'utility' ? 'utility' : $type,
            'is_default' => false,
        ]);

        $sync = [];
        foreach (array_values($components) as $i => [$component, $standard]) {
            $sync[$component->id] = ['sort_no' => $i + 1, 'standard' => $standard];
        }
        $template->components()->sync($sync);

        return $template;
    }

    public function test_halaman_template_menampilkan_template_dan_komponennya(): void
    {
        $this->template([[$this->oli, 'SAE 90'], [$this->baut, null]]);

        $this->actingAs($this->admin)
            ->get(route('admin.templates'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Templates')
                ->has('templates', 1)
                ->where('templates.0.scope', 'machine')
                ->where('templates.0.components.0.name', 'Oli Gearbox')
                ->where('templates.0.components.0.standard', 'SAE 90')
                ->where('templates.0.components.1.name', 'Baut Rangka')
                ->has('allComponents', 3));
    }

    public function test_tanpa_izin_template_manage_ditolak(): void
    {
        $viewer = User::factory()->create()->assignRole('viewer');

        $this->actingAs($viewer)->get(route('admin.templates'))->assertForbidden();
    }

    public function test_membuat_template_dengan_urutan_dan_standar_serta_satu_default_per_tipe(): void
    {
        $lama = ChecklistTemplate::create(['name' => 'Lama', 'scope' => 'machine', 'machine_type' => 'mixer', 'is_default' => true]);

        $this->actingAs($this->admin)
            ->post(route('admin.templates.store'), [
                'name' => 'Mixer Baru',
                'scope' => 'machine',
                'machine_type' => 'mixer',
                'is_default' => true,
                'components' => [
                    ['id' => $this->baut->id, 'standard' => ''],
                    ['id' => $this->oli->id, 'standard' => 'SAE 90'],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $baru = ChecklistTemplate::where('name', 'Mixer Baru')->sole();
        $this->assertTrue($baru->is_default);
        $this->assertFalse($lama->fresh()->is_default, 'Default lama untuk tipe yang sama harus dilepas.');
        $this->assertSame([$this->baut->id, $this->oli->id], $baru->components->pluck('id')->all());
        $this->assertNull($baru->components[0]->pivot->standard);
        $this->assertSame('SAE 90', $baru->components[1]->pivot->standard);
    }

    public function test_template_mesin_wajib_punya_tipe_mesin(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.templates.store'), ['name' => 'Tanpa Tipe', 'scope' => 'machine'])
            ->assertSessionHasErrors('machine_type');

        $this->actingAs($this->admin)
            ->post(route('admin.templates.store'), ['name' => 'Utility Pompa', 'scope' => 'utility'])
            ->assertSessionHasNoErrors();

        $this->assertSame('utility', ChecklistTemplate::where('name', 'Utility Pompa')->sole()->machine_type);
    }

    public function test_terapkan_ke_unit_mengganti_komponen_dan_mempertahankan_standar_unit(): void
    {
        $template = $this->template([[$this->oli, 'SAE 90'], [$this->baut, null]]);

        $a = $this->machine('M-A');
        // Unit punya standar sendiri untuk Baut, dan komponen Sensor yang
        // tidak ada di template.
        $a->components()->sync([
            $this->baut->id => ['sort_no' => 1, 'standard' => '25 Nm'],
            $this->sensor->id => ['sort_no' => 2, 'standard' => null],
        ]);
        $b = $this->machine('M-B');
        $utility = $this->machine('U-1', 'utility');

        $this->actingAs($this->admin)
            ->post(route('admin.templates.apply', $template), ['machine_ids' => [$a->id, $b->id, $utility->id]])
            ->assertRedirect()
            ->assertSessionHas('success', 'Template "Template Uji" diterapkan ke 2 unit.');

        $a->refresh()->load('components');
        $this->assertSame([$this->oli->id, $this->baut->id], $a->components->pluck('id')->all());
        $this->assertSame('SAE 90', $a->components[0]->pivot->standard);
        $this->assertSame('25 Nm', $a->components[1]->pivot->standard, 'Standar unit dipertahankan bila template tidak menentukan.');
        $this->assertSame($template->id, $a->template_id);

        $this->assertSame(2, $b->refresh()->components()->count());
        // Template Mesin tidak boleh mengubah unit Utility.
        $this->assertSame(0, $utility->refresh()->components()->count());
        $this->assertNull($utility->template_id);
    }

    public function test_ganti_template_di_form_unit_menerapkan_komponen_template(): void
    {
        $template = $this->template([[$this->oli, null], [$this->sensor, null]]);
        $machine = $this->machine('M-1');
        $machine->components()->sync([$this->baut->id => ['sort_no' => 1, 'standard' => null]]);

        $payload = [
            'code' => 'M-1', 'name' => 'Unit M-1', 'type' => 'mixer', 'week_group' => 1, 'is_active' => 1,
        ];

        // Simpan tanpa mengganti template: komponen tidak disentuh.
        $this->actingAs($this->admin)->put(route('machines.update', $machine), $payload + ['template_id' => null])->assertSessionHasNoErrors();
        $this->assertSame([$this->baut->id], $machine->refresh()->components()->pluck('components.id')->all());

        // Ganti template: komponen diganti isi template.
        $this->actingAs($this->admin)->put(route('machines.update', $machine), $payload + ['template_id' => $template->id])->assertSessionHasNoErrors();
        $this->assertSame([$this->oli->id, $this->sensor->id], $machine->refresh()->load('components')->components->pluck('id')->all());
    }

    public function test_unit_baru_dengan_template_langsung_berisi_komponen_template(): void
    {
        $template = $this->template([[$this->baut, 'M12'], [$this->oli, null]]);

        $this->actingAs($this->admin)
            ->post(route('machines.store'), [
                'code' => 'M-NEW', 'name' => 'Mixer Baru', 'type' => 'mixer', 'week_group' => 2,
                'is_active' => 1, 'template_id' => $template->id,
            ])
            ->assertSessionHasNoErrors();

        $machine = Machine::where('code', 'M-NEW')->sole()->load('components');
        $this->assertSame([$this->baut->id, $this->oli->id], $machine->components->pluck('id')->all());
        $this->assertSame('M12', $machine->components[0]->pivot->standard);
    }

    public function test_hapus_template_tidak_mengubah_komponen_unit(): void
    {
        $template = $this->template([[$this->oli, null]]);
        $machine = $this->machine('M-1', attrs: ['template_id' => $template->id]);
        $machine->components()->sync([$this->oli->id => ['sort_no' => 1, 'standard' => null]]);

        $this->actingAs($this->admin)->delete(route('admin.templates.destroy', $template))->assertRedirect();

        $this->assertDatabaseMissing('checklist_templates', ['id' => $template->id]);
        $this->assertNull($machine->refresh()->template_id);
        $this->assertSame(1, $machine->components()->count());
    }

    public function test_form_pm_memakai_komponen_template_bila_unit_belum_punya_komponen(): void
    {
        $template = $this->template([[$this->oli, 'SAE 90'], [$this->baut, null]]);
        $machine = $this->machine('M-1', attrs: ['template_id' => $template->id]);

        $teknisi = User::factory()->create()->assignRole('technician');

        $this->actingAs($teknisi)
            ->get(route('machines.pm.create', $machine))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('items', 2)
                ->where('items.0.item_name', 'Oli Gearbox')
                ->where('items.0.spec', 'SAE 90')
                ->where('items.1.spec', 'Kencang'));
    }

    public function test_kartu_template_tersedia_di_settings(): void
    {
        $this->actingAs($this->admin)
            ->get(route('settings'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Settings/Index'));

        $this->assertSame(url('/admin/templates'), route('admin.templates'));
    }
}
