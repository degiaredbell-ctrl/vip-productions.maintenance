<?php

namespace Tests\Feature;

use App\Enums\PmStatus;
use App\Enums\Period;
use App\Models\Machine;
use App\Models\PmRecord;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Approver (peran User PIC tahap 2 & Atasan tahap 3) harus bisa MEMBUKA form PM
 * mesin maupun utility untuk meninjau isinya, walaupun mereka tidak memegang
 * `pm.fill`. Persetujuan memang dilakukan DI form itu, jadi tanpa akses buka
 * mereka tidak akan pernah bisa menyetujui walaupun sudah menerima di antrean.
 *
 * Form dibuka read-only (`canFill=false`) dan panel tanda tangan tetap tampil
 * selama tahapnya memang menunggu approver tersebut.
 */
class PmApproverViewTest extends TestCase
{
    use RefreshDatabase;

    /** PNG 1x1 yang valid, sama dengan yang dikirim canvas SignatureStorage. */
    private const PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    private Machine $machine;
    private Machine $utility;
    private User $pic;
    private User $teknisi;
    private User $manager;
    private User $viewer;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RoleSeeder::class);

        $this->pic = User::factory()->create()->assignRole('user');
        $this->teknisi = User::factory()->create()->assignRole('technician');
        $this->manager = User::factory()->create()->assignRole('manager');
        $this->viewer = User::factory()->create()->assignRole('viewer');

        $this->machine = Machine::create([
            'code' => 'TST-APV-001',
            'name' => 'Mesin Uji Approver',
            'type' => 'mixer',
            'week_group' => 1,
            'is_active' => true,
            'sort_no' => 1,
        ]);

        $this->utility = Machine::create([
            'code' => 'UTL-APV-001',
            'name' => 'Chiller Uji Approver',
            'type' => 'utility',
            'week_group' => 1,
            'is_active' => true,
            'sort_no' => 2,
        ]);
    }

    protected function tearDown(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        parent::tearDown();
    }

    private function record(Machine $machine, PmStatus $status): PmRecord
    {
        $record = PmRecord::create([
            'machine_id' => $machine->id,
            'year' => now()->year,
            'period' => Period::current()->value,
            'technician_id' => $this->teknisi->id,
            'technician_name' => 'Budi Santoso',
            'pic_user_id' => $this->pic->id,
            'status' => $status,
        ]);

        $record->items()->create([
            'item_name' => 'Cek oli',
            'category' => 'Mekanik',
            'spec' => '5W-30',
            'actual' => 'Wajar',
        ]);

        return $record;
    }

    public function test_user_pic_bisa_membuka_form_pm_mesin_read_only(): void
    {
        $this->record($this->machine, PmStatus::Submitted);

        $this->actingAs($this->pic)
            ->get(route('machines.pm.create', $this->machine))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('canFill', false)
                ->where('existing.status', 'submitted')
                ->where('chain.awaiting', 'pic')
                ->where('chain.canSign', true)
                ->where('items.0.item_name', 'Cek oli'));
    }

    public function test_user_pic_bisa_membuka_form_pm_utility_read_only(): void
    {
        $this->record($this->utility, PmStatus::Submitted);

        $this->actingAs($this->pic)
            ->get(route('machines.pm.create', $this->utility))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('canFill', false)
                ->where('chain.awaiting', 'pic')
                ->where('chain.canSign', true));
    }

    public function test_user_pic_bisa_meninjau_dan_menyetujui_dari_form(): void
    {
        $record = $this->record($this->machine, PmStatus::Submitted);

        // 1) Bisa membuka form untuk meninjau isi checklist.
        $this->actingAs($this->pic)
            ->get(route('machines.pm.create', $this->machine))
            ->assertOk();

        // 2) Bisa menyetujui dari form yang sama.
        $this->actingAs($this->pic)
            ->post(route('pm.sign', $record), ['signature' => self::PNG])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(PmStatus::PicApproved, $record->fresh()->status);
    }

    public function test_manager_bisa_membuka_dan_menyetujui_tahap_atasan(): void
    {
        $record = $this->record($this->machine, PmStatus::PicApproved);

        $this->actingAs($this->manager)
            ->get(route('machines.pm.create', $this->machine))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('canFill', false)
                ->where('chain.awaiting', 'supervisor')
                ->where('chain.canSign', true));

        $this->actingAs($this->manager)
            ->post(route('pm.sign', $record), ['signature' => self::PNG])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(PmStatus::Approved, $record->fresh()->status);
    }

    public function test_kembali_mengarah_ke_antrean_persetujuan_bila_dibuka_dari_sana(): void
    {
        $this->record($this->machine, PmStatus::Submitted);
        $this->record($this->utility, PmStatus::Submitted);

        foreach ([$this->machine, $this->utility] as $unit) {
            $this->actingAs($this->pic)
                ->get(route('machines.pm.create', [
                    'machine' => $unit->id,
                    'return' => 'approvals',
                ]))
                ->assertOk()
                ->assertInertia(fn ($page) => $page->where('dashboardUrl', route('approvals.index')));
        }
    }

    /**
     * Tab halaman Persetujuan yang sedang dibuka ikut dibawa masuk ke form,
     * supaya tombol "Kembali" mengembalikan approver ke daftar yang sama.
     */
    public function test_tab_persetujuan_ikut_terbawa_masuk_ke_form(): void
    {
        $this->record($this->machine, PmStatus::Submitted);

        $this->actingAs($this->pic)
            ->get(route('machines.pm.create', [
                'machine' => $this->machine->id,
                'return' => 'approvals',
                'tab' => 'queue',
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('dashboardUrl', route('approvals.index', ['tab' => 'queue'])));
    }

    /**
     * Tombol "Kembali" di form tidak lagi mengikuti daftar asal, melainkan
     * tujuan redirect setelah submit: halaman Persetujuan tab "Status PM Saya".
     */
    public function test_kembali_mengarah_ke_status_pm_saya(): void
    {
        $this->actingAs($this->teknisi)
            ->get(route('machines.pm.create', $this->machine))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('approvalReturnUrl', route('approvals.index', ['tab' => 'mine'])));
    }

    public function test_halaman_persetujuan_membuka_tab_yang_diminta(): void
    {
        $this->record($this->machine, PmStatus::Submitted);

        $this->actingAs($this->pic)
            ->get(route('approvals.index', ['tab' => 'mine']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('tab', 'mine'));

        // Nilai tab di luar daftar tidak diteruskan, jadi tab default tetap
        // yang menentukan dan URL tidak bisa memaksa tampilan sembarangan.
        $this->actingAs($this->pic)
            ->get(route('approvals.index', ['tab' => 'terserah']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('tab', null));
    }

    public function test_viewer_tetap_tidak_bisa_membuka_form_pm(): void
    {
        $this->record($this->machine, PmStatus::Submitted);

        // Viewer hanya punya pm.history.view (bukan pengisi/approver), jadi
        // tidak boleh membuka form checklist.
        $this->actingAs($this->viewer)
            ->get(route('machines.pm.create', $this->machine))
            ->assertForbidden();
    }

    public function test_teknisi_tetap_bisa_membuka_form_pm_untuk_mengisi(): void
    {
        $this->actingAs($this->teknisi)
            ->get(route('machines.pm.create', $this->machine))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('canFill', true));
    }
}
