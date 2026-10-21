<?php

namespace Tests\Feature;

use App\Enums\PmStatus;
use App\Enums\Period;
use App\Enums\SignatureStage;
use App\Models\AuditLog;
use App\Models\Machine;
use App\Models\PmRecord;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Pembuat PM (teknisi) bisa memantau progres persetujuan PM-nya sendiri di
 * halaman Persetujuan: sudah di tahap User PIC, Atasan, ditolak, atau selesai.
 */
class ApprovalMySubmissionsTest extends TestCase
{
    use RefreshDatabase;

    private User $teknisi;
    private User $teknisiLain;
    private User $pic;
    private int $sort = 0;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RoleSeeder::class);

        $this->teknisi = User::factory()->create()->assignRole('technician');
        $this->teknisiLain = User::factory()->create()->assignRole('technician');
        $this->pic = User::factory()->create(['name' => 'Pak PIC'])->assignRole('user');
    }

    protected function tearDown(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        parent::tearDown();
    }

    private function record(User $owner, PmStatus $status, ?int $year = null, ?string $period = null): PmRecord
    {
        $this->sort++;

        $machine = Machine::create([
            'code' => 'TST-MS-'.$this->sort,
            'name' => 'Mesin '.$this->sort,
            'type' => 'mixer',
            'week_group' => 1,
            'is_active' => true,
            'sort_no' => $this->sort,
        ]);

        return PmRecord::create([
            'machine_id' => $machine->id,
            'year' => $year ?? now()->year,
            'period' => $period ?? Period::current()->value,
            'technician_id' => $owner->id,
            'pic_user_id' => $this->pic->id,
            'status' => $status,
        ]);
    }

    private function sign(PmRecord $record, SignatureStage $stage, string $name): void
    {
        $record->signatures()->create([
            'stage' => $stage,
            'signed_by_name' => $name,
            'image_path' => 'x.png',
            'signed_at' => now(),
        ]);
    }

    public function test_pembuat_melihat_tahap_approval_pm_miliknya(): void
    {
        $draft = $this->record($this->teknisi, PmStatus::Draft);
        $atPic = $this->record($this->teknisi, PmStatus::Submitted);
        $this->sign($atPic, SignatureStage::Technician, 'Teknisi A');
        $atAtasan = $this->record($this->teknisi, PmStatus::PicApproved);
        $this->sign($atAtasan, SignatureStage::Technician, 'Teknisi A');
        $this->sign($atAtasan, SignatureStage::Pic, 'Pak PIC');
        $this->record($this->teknisiLain, PmStatus::Submitted);

        $this->actingAs($this->teknisi)
            ->get(route('approvals.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('canSubmit', true)
                ->has('mySubmissions', 2)
                ->where('mySubmissions', fn ($rows) => collect($rows)->pluck('id')->sort()->values()->all()
                    === collect([$atPic->id, $atAtasan->id])->sort()->values()->all())
                ->where('mySubmissions', function ($rows) use ($atPic, $atAtasan) {
                    $byId = collect($rows)->keyBy('id');

                    return $byId[$atPic->id]['awaiting'] === 'pic'
                        && $byId[$atPic->id]['assigned_pic'] === 'Pak PIC'
                        && $byId[$atPic->id]['stages'][0]['signed'] === true
                        && $byId[$atAtasan->id]['awaiting'] === 'supervisor'
                        && $byId[$atAtasan->id]['stages'][1]['signed_by_name'] === 'Pak PIC';
                })
            );

        $this->assertNotNull($draft);
    }

    public function test_pm_ditolak_menampilkan_tahap_dan_alasan_penolakan(): void
    {
        $rejected = $this->record($this->teknisi, PmStatus::Rejected);

        AuditLog::create([
            'user_id' => $this->pic->id,
            'action' => 'pm.reject',
            'subject_type' => PmRecord::class,
            'subject_id' => $rejected->id,
            'changes' => ['stage' => 'pic', 'reason' => 'Foto kurang jelas'],
            'created_at' => now(),
        ]);

        $this->actingAs($this->teknisi)
            ->get(route('approvals.index'))
            ->assertInertia(fn ($page) => $page
                ->has('mySubmissions', 1)
                ->where('mySubmissions.0.is_rejected', true)
                ->where('mySubmissions.0.awaiting', null)
                ->where('mySubmissions.0.stages.1.rejected', true)
                ->where('mySubmissions.0.rejection.reason', 'Foto kurang jelas')
                ->where('mySubmissions.0.rejection.by', 'Pak PIC')
            );
    }

    public function test_status_pm_saya_memuat_semua_tahun_dan_periode(): void
    {
        $tahunLalu = $this->record($this->teknisi, PmStatus::Approved, now()->year - 1, 'nov-des');
        $periodeLain = $this->record($this->teknisi, PmStatus::Rejected, now()->year, 'jan-feb');
        $sekarang = $this->record($this->teknisi, PmStatus::Submitted);
        // Punya orang lain tetap tidak ikut.
        $this->record($this->teknisiLain, PmStatus::Approved, now()->year - 1, 'nov-des');

        // Filter tahun/periode hanya untuk antrean "Perlu Tindakan Saya";
        // tab PM Saya tetap memuat semua riwayat milik pembuat.
        $this->actingAs($this->teknisi)
            ->get(route('approvals.index', ['year' => now()->year, 'period' => Period::current()->value]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('mySubmissions', 3)
                ->where('mySubmissions', fn ($rows) => collect($rows)->pluck('id')->sort()->values()->all()
                    === collect([$tahunLalu->id, $periodeLain->id, $sekarang->id])->sort()->values()->all())
                // Masih berjalan didahulukan, lalu revisi, terakhir selesai.
                ->where('mySubmissions.0.id', $sekarang->id)
                ->where('mySubmissions.1.id', $periodeLain->id)
                ->where('mySubmissions.2.id', $tahunLalu->id)
                ->where('mySubmissions.0.year', now()->year)
                ->where('mySubmissions.2.year', now()->year - 1)
                ->where('mySubmissions.2.status', 'done')
            );
    }

    public function test_approver_tanpa_pm_sendiri_tidak_mendapat_daftar_pm_saya(): void
    {
        $this->record($this->teknisi, PmStatus::Submitted);

        $this->actingAs($this->pic)
            ->get(route('approvals.index'))
            ->assertInertia(fn ($page) => $page
                ->where('canSubmit', false)
                ->has('mySubmissions', 0)
                ->has('records', 1)
            );
    }
}
