<?php

namespace Tests\Feature;

use App\Enums\Period;
use App\Enums\PmStatus;
use App\Models\Machine;
use App\Models\PmRecord;
use App\Models\User;
use App\Models\PmSignature;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Pembersihan data uji E2E.
 *
 * Semua test di sini guarding satu janji: `e2e:cleanup` tidak boleh menyentuh
 * data produksi. Test dibuat setelah folder tanda tangan milik record produksi
 * ikut terhapus karena cleanup mencocokkan id, jadi sekarang id sendirinya
 * tidak lagi cukup jadi alasan menghapus.
 */
class E2eCleanupCommandTest extends TestCase
{
    use RefreshDatabase;

    private int $machineSeq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RoleSeeder::class);
        Storage::fake('public');
    }

    /**
     * Satu mesin per record: pm_records punya unique key
     * (machine_id, year, period), jadi dua record pada mesin yang sama akan
     * bentrok kecuali period atau mesinnya dibedakan.
     */
    private function record(array $attributes = []): PmRecord
    {
        $this->machineSeq++;

        $machine = Machine::create([
            'code' => 'TST-CLN-'.str_pad((string) $this->machineSeq, 3, '0', STR_PAD_LEFT),
            'name' => 'Mesin Uji Cleanup '.$this->machineSeq,
            'location' => 'Pabrik A',
            'category' => 'Produksi',
            'sub_category' => 'Mixer',
            'type' => 'mixer',
            'week_group' => 1,
            'is_active' => true,
            'sort_no' => $this->machineSeq,
        ]);

        $technicianId = $attributes['technician_id'] ?? User::factory()->create()->assignRole('technician')->id;

        $record = PmRecord::create(array_merge([
            'machine_id' => $machine->id,
            'year' => now()->year,
            'period' => Period::current()->value,
            'technician_id' => $technicianId,
            'technician_name' => 'Teknisi Produksi',
            'status' => PmStatus::Submitted,
        ], $attributes));

        PmSignature::create([
            'pm_record_id' => $record->id,
            'stage' => \App\Enums\SignatureStage::Technician,
            'signed_by' => $technicianId,
            'signed_by_name' => 'Teknisi Produksi',
            'signed_by_role' => 'technician',
            'image_path' => "signatures/pm-record-{$record->id}/technician.png",
            'signed_at' => now(),
        ]);

        Storage::disk('public')->put("signatures/pm-record-{$record->id}/technician.png", 'PNG');

        return $record;
    }

    private function e2eUser(): User
    {
        return User::factory()->create(['email' => 'e2e.tek@a.test'])->assignRole('technician');
    }

    public function test_tanpa_force_tidak_menghapus_apa_pun(): void
    {
        $user = $this->e2eUser();
        $record = $this->record(['technician_id' => $user->id]);

        Artisan::call('e2e:cleanup');

        $this->assertNotNull(PmRecord::find($record->id));
        $this->assertNotNull(User::find($user->id));
        Storage::disk('public')->assertExists("signatures/pm-record-{$record->id}/technician.png");
    }

    public function test_force_menghapus_checklist_akun_dan_file_tanda_tangan(): void
    {
        $user = $this->e2eUser();
        $record = $this->record(['technician_id' => $user->id]);

        Artisan::call('e2e:cleanup', ['--force' => true]);

        $this->assertNull(PmRecord::find($record->id));
        $this->assertNull(User::find($user->id));
        Storage::disk('public')->assertMissing("signatures/pm-record-{$record->id}/technician.png");
    }

    public function test_checklist_produksi_tidak_bisa_ikut_terhapus(): void
    {
        // Nama teknisi sengaja sama dengan milik akun uji. Kalau cleanup
        // memakai nama, checklist produksi ikut hilang.
        $user = $this->e2eUser();
        $this->record(['technician_id' => $user->id, 'technician_name' => 'E2E Tek']);
        $produksi = $this->record([
            'technician_name' => 'E2E Tek',
            'technician_id' => User::factory()->create()->assignRole('technician')->id,
        ]);

        Artisan::call('e2e:cleanup', ['--force' => true]);

        $this->assertNotNull(
            PmRecord::find($produksi->id),
            'Checklist milik akun non-E2E tidak boleh terhapus hanya karena namanya mirip.',
        );
        Storage::disk('public')->assertExists("signatures/pm-record-{$produksi->id}/technician.png");
    }

    public function test_record_produksi_tetap_utuh_saat_record_e2e_dihapus(): void
    {
        // Tidak ada dua record dengan id sama, jadi nama folder tidak mungkin
        // bertabrakan. Yang diuji di sini adalah arah sebaliknya: satu folder
        // signature per record, dan menghapus folder E2E tidak boleh menyapu
        // folder record lain.
        $produksi = $this->record();
        $e2e = $this->record(['technician_id' => $this->e2eUser()->id]);

        Artisan::call('e2e:cleanup', ['--force' => true]);

        $this->assertNull(PmRecord::find($e2e->id));
        $this->assertNotNull(PmRecord::find($produksi->id));
        Storage::disk('public')->assertExists("signatures/pm-record-{$produksi->id}/technician.png");
    }

    public function test_record_yang_sudah_ditandatangani_tidak_dihapus(): void
    {
        $user = $this->e2eUser();
        $picApproved = $this->record([
            'technician_id' => $user->id,
            'status' => PmStatus::PicApproved,
        ]);
        $approved = $this->record([
            'technician_id' => $user->id,
            'status' => PmStatus::Approved,
        ]);

        Artisan::call('e2e:cleanup', ['--force' => true]);

        $this->assertNotNull(PmRecord::find($picApproved->id), 'Record yang sudah lewat tahap PIC tidak boleh terhapus diam-diam.');
        $this->assertNotNull(PmRecord::find($approved->id));
        Storage::disk('public')->assertExists("signatures/pm-record-{$picApproved->id}/technician.png");
    }

    public function test_pola_yang_tidak_dimatch_tidak_menyentuh_apa_pun(): void
    {
        $user = $this->e2eUser();
        $record = $this->record(['technician_id' => $user->id]);

        Artisan::call('e2e:cleanup', ['--force' => true, '--pattern' => 'lainnya%']);

        $this->assertNotNull(PmRecord::find($record->id));
        $this->assertNotNull(User::find($user->id));
    }
}