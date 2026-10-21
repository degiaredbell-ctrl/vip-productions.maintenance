<?php

namespace Tests\Feature;

use App\Enums\PmStatus;
use App\Enums\Period;
use App\Models\AuditLog;
use App\Models\Machine;
use App\Models\PmRecord;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Endpoint JSON yang dipakai form PM dan antrean offline di perangkat teknisi.
 */
class PmOfflineSyncTest extends TestCase
{
    use RefreshDatabase;

    private const PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    private Machine $machine;
    private User $pic;
    private User $teknisi;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RoleSeeder::class);
        Storage::fake('public');

        $this->pic = User::factory()->create()->assignRole('user');
        $this->teknisi = User::factory()->create()->assignRole('technician');

        $this->machine = Machine::create([
            'code' => 'TST-OFF-001',
            'name' => 'Mesin Uji Offline',
            'type' => 'mixer',
            'week_group' => 1,
            'is_active' => true,
            'sort_no' => 1,
        ]);
    }

    protected function tearDown(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        parent::tearDown();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'client_id' => '3f1c2a9e-1111-4a2b-9c3d-000000000001',
            'machine_id' => $this->machine->id,
            'year' => now()->year,
            'period' => Period::current()->value,
            'technician_name' => 'Budi Santoso',
            'pic_user_id' => $this->pic->id,
            'signature' => self::PNG,
            'items' => [
                ['item_name' => 'Cek oli', 'category' => 'Mekanik', 'spec' => '5W-30', 'actual' => 'Wajar'],
            ],
        ], $overrides);
    }

    public function test_checklist_terkirim_dan_langsung_menunggu_user_pic(): void
    {
        $this->actingAs($this->teknisi)
            ->postJson(route('pm.sync'), $this->payload())
            ->assertOk()
            ->assertJson(['ok' => true, 'signed' => true, 'duplicate' => false]);

        $record = PmRecord::sole();
        $this->assertSame(PmStatus::Submitted, $record->status);
        $this->assertSame($this->pic->id, $record->pic_user_id);
    }

    /**
     * Setelah submit, teknisi tidak lagi kembali ke daftar asal form. Yang
     * ditunggunya adalah perjalanan PM-nya di rantai approval, jadi kedua
     * jalur (form JSON maupun submit HTML biasa) mendarat di halaman
     * Persetujuan dengan tab "Status PM Saya" terbuka.
     */
    public function test_submit_mengarah_ke_halaman_persetujuan_tab_pm_saya(): void
    {
        $expected = route('approvals.index', ['tab' => 'mine']);

        $this->actingAs($this->teknisi)
            ->postJson(route('pm.sync'), $this->payload())
            ->assertOk()
            ->assertJsonPath('redirect', $expected);

        // Kiriman ulang dari perangkat harus membawa tujuan yang sama; kalau
        // tidak, antrean offline yang baru tersinkron mendarat di tempat lain.
        $this->actingAs($this->teknisi)
            ->postJson(route('pm.sync'), $this->payload())
            ->assertOk()
            ->assertJsonPath('redirect', $expected);
    }

    public function test_kiriman_ulang_dengan_client_id_sama_tidak_dobel(): void
    {
        $this->actingAs($this->teknisi)->postJson(route('pm.sync'), $this->payload())->assertOk();

        // Respons pertama "hilang" di jaringan, perangkat mengirim ulang.
        $this->actingAs($this->teknisi)
            ->postJson(route('pm.sync'), $this->payload())
            ->assertOk()
            ->assertJson(['ok' => true, 'duplicate' => true]);

        $this->assertSame(1, PmRecord::count());
        $this->assertSame(1, PmRecord::sole()->signatures()->count());
    }

    public function test_client_id_berbeda_untuk_record_yang_sudah_terkunci_ditolak(): void
    {
        $this->actingAs($this->teknisi)->postJson(route('pm.sync'), $this->payload())->assertOk();

        $this->actingAs($this->teknisi)
            ->postJson(route('pm.sync'), $this->payload(['client_id' => '3f1c2a9e-2222-4a2b-9c3d-000000000002']))
            ->assertForbidden();
    }

    public function test_validasi_dikembalikan_sebagai_json_422(): void
    {
        $this->actingAs($this->teknisi)
            ->postJson(route('pm.sync'), $this->payload(['pic_user_id' => null]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('pic_user_id');

        $this->assertSame(0, PmRecord::count());
    }

    public function test_tanpa_login_mendapat_401(): void
    {
        $this->postJson(route('pm.sync'), $this->payload())->assertUnauthorized();
    }

    public function test_pengisian_offline_yang_tertunda_dicatat_di_audit_log(): void
    {
        $this->actingAs($this->teknisi)
            ->postJson(route('pm.sync'), $this->payload(['queued_at' => now()->subHours(3)->toIso8601String()]))
            ->assertOk();

        $this->assertSame(1, AuditLog::where('action', 'pm.offline_sync')->count());
    }

    public function test_ping_mengembalikan_akun_yang_login(): void
    {
        $this->actingAs($this->teknisi)
            ->getJson(route('pm.sync.ping'))
            ->assertOk()
            ->assertJson(['ok' => true, 'user_id' => $this->teknisi->id]);
    }

    // ------------------------------------------------------------------
    // Opsi penanda tangan tahap teknisi: "Data Saya" vs "Isi Manual"
    // ------------------------------------------------------------------

    private function giveProfileSignature(User $user): void
    {
        $path = \App\Services\SignatureStorage::storeUserSignature($user, self::PNG);
        $user->forceFill(['signature_path' => $path])->save();
    }

    public function test_data_saya_memakai_nama_akun_dan_tanda_tangan_tersimpan(): void
    {
        $this->giveProfileSignature($this->teknisi);

        $this->actingAs($this->teknisi)
            ->postJson(route('pm.sync'), $this->payload([
                'signer_mode' => 'self',
                'use_saved_signature' => true,
                'signature' => null,
                // Nama dari client diabaikan di mode Data Saya.
                'technician_name' => 'Orang Lain',
            ]))
            ->assertOk()
            ->assertJson(['signed' => true]);

        $record = PmRecord::sole();
        $this->assertSame(PmStatus::Submitted, $record->status);
        $this->assertSame($this->teknisi->name, $record->technician_name);
        $signature = $record->signatures()->sole();
        $this->assertSame($this->teknisi->name, $signature->signed_by_name);
        Storage::disk('public')->assertExists($signature->image_path);
    }

    public function test_data_saya_tanpa_tanda_tangan_di_profile_ditolak(): void
    {
        $this->actingAs($this->teknisi)
            ->postJson(route('pm.sync'), $this->payload([
                'signer_mode' => 'self',
                'use_saved_signature' => true,
                'signature' => null,
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('use_saved_signature');

        $this->assertSame(0, PmRecord::count());
    }

    public function test_isi_manual_tidak_boleh_memakai_tanda_tangan_tersimpan(): void
    {
        $this->giveProfileSignature($this->teknisi);

        $this->actingAs($this->teknisi)
            ->postJson(route('pm.sync'), $this->payload([
                'signer_mode' => 'manual',
                'use_saved_signature' => true,
                'signature' => null,
                'technician_name' => 'Budi Santoso',
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('use_saved_signature');
    }

    public function test_isi_manual_tetap_wajib_nama_dan_tanda_tangan(): void
    {
        $this->actingAs($this->teknisi)
            ->postJson(route('pm.sync'), $this->payload([
                'signer_mode' => 'manual',
                'technician_name' => '',
                'signature' => null,
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['technician_name', 'signature']);
    }

    public function test_isi_manual_menyimpan_nama_yang_diketik(): void
    {
        $this->actingAs($this->teknisi)
            ->postJson(route('pm.sync'), $this->payload([
                'signer_mode' => 'manual',
                'technician_name' => 'Budi Santoso',
            ]))
            ->assertOk();

        $this->assertSame('Budi Santoso', PmRecord::sole()->signatures()->sole()->signed_by_name);
    }

    public function test_data_saya_gambar_baru_bisa_disimpan_ke_profile(): void
    {
        $this->actingAs($this->teknisi)
            ->postJson(route('pm.sync'), $this->payload([
                'signer_mode' => 'self',
                'save_signature_to_profile' => true,
            ]))
            ->assertOk();

        $this->assertNotNull($this->teknisi->fresh()->signature_path);
    }

    public function test_isi_manual_tidak_menyimpan_tanda_tangan_ke_profile(): void
    {
        $this->actingAs($this->teknisi)
            ->postJson(route('pm.sync'), $this->payload([
                'signer_mode' => 'manual',
                'technician_name' => 'Budi Santoso',
                'save_signature_to_profile' => true,
            ]))
            ->assertOk();

        $this->assertNull($this->teknisi->fresh()->signature_path);
    }
}
