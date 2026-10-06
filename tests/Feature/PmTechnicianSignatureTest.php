<?php

namespace Tests\Feature;

use App\Actions\Pm\RejectPmRecord;
use App\Actions\Pm\SignPmRecord;
use App\Enums\PmStatus;
use App\Enums\Period;
use App\Enums\SignatureStage;
use App\Models\Machine;
use App\Models\PmRecord;
use App\Models\PmRecordItem;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Aturan tahap 1: checklist teknisi hanya bisa ditutup lewat satu submit, dan
 * submit itu wajib membawa nama teknisi yang diketik manual + gambar tanda
 * tangan. Nama yang diketik inilah yang tersimpan sebagai identitas di dokumen,
 * bukan nama akun yang squeez-in.
 *
 * Isolasi database dijamin `Tests\TestCase`: koneksi dipaksa ke sqlite
 * in-memory, jadi `migrate:fresh` dari RefreshDatabase tidak mungkin menyentuh
 * database produksi meski ada variabel DB_* di shell atau .env.
 */
class PmTechnicianSignatureTest extends TestCase
{
    use RefreshDatabase;

    /** PNG 1x1 yang valid, sama dengan yang dikirim canvas SignatureStorage. */
    private const PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    private Machine $machine;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RoleSeeder::class);

        // Berkas tanda tangan test ditulis ke folder fake, tidak pernah bercampur
        // dengan tanda tangan PM yang sudah tersimpan.
        Storage::fake('public');

        $this->machine = Machine::create([
            'code' => 'TST-001',
            'name' => 'Mesin Uji Tanda Tangan',
            'location' => 'Pabrik A',
            'category' => 'Produksi',
            'sub_category' => 'Mixer',
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

    private function user(string $role): User
    {
        return User::factory()->create()->assignRole($role);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'machine_id' => $this->machine->id,
            'year' => now()->year,
            'period' => Period::current()->value,
            'technician_name' => 'Budi Santoso',
            'signature' => self::PNG,
            'general_note' => null,
            'items' => [
                ['item_name' => 'Cek oli', 'category' => 'Mekanik', 'spec' => '5W-30', 'actual' => 'Wajar'],
            ],
        ], $overrides);
    }

    private function store(User $user, array $overrides = [])
    {
        return $this->actingAs($user)->post(
            route('machines.pm.store', $this->machine),
            $this->payload($overrides),
        );
    }

    public function test_nama_teknisi_wajib_diisi(): void
    {
        $this->store($this->user('technician'), ['technician_name' => ''])
            ->assertSessionHasErrors('technician_name');

        $this->assertSame(0, PmRecord::count(), 'Checklist tidak boleh tersimpan tanpa nama teknisi.');
    }

    public function test_nama_teknisi_hanya_spasi_kosong_juga_ditolak(): void
    {
        $this->store($this->user('technician'), ['technician_name' => '   '])
            ->assertSessionHasErrors('technician_name');

        $this->assertSame(0, PmRecord::count());
    }

    public function test_tanda_tangan_wajib_diisi(): void
    {
        $this->store($this->user('technician'), ['signature' => ''])
            ->assertSessionHasErrors('signature');

        $this->assertSame(0, PmRecord::count(), 'Checklist tidak boleh tersimpan tanpa tanda tangan.');
    }

    public function test_tanda_tangan_harus_png_asli(): void
    {
        $this->store($this->user('technician'), ['signature' => 'data:text/plain;base64,aGVsbG8='])
            ->assertSessionHasErrors('signature');

        $this->assertSame(0, PmRecord::count());
    }

    public function test_submit_menyimpan_checklist_sekaligus_menandatangani_tahap_teknisi(): void
    {
        $user = $this->user('technician');

        $this->store($user)->assertRedirect();

        $record = PmRecord::sole();

        // Satu submit menutup dua hal: checklist tersimpan dan tahap teknisi
        // sudah lewat, jadi tidak ada lagi record nyangkut di draft.
        $this->assertSame(PmStatus::Submitted, $record->status);
        $this->assertNotNull($record->submitted_at);
        $this->assertSame(1, $record->items()->count());
        $this->assertSame(SignatureStage::Pic, $record->status->awaiting(), 'Tahap berikutnya adalah User PIC.');

        // Nama yang diketik manual yang tersimpan, bukan nama akun login.
        $this->assertSame('Budi Santoso', $record->technician_name);
        $this->assertSame('Budi Santoso', $record->technicianName());
        $this->assertSame($user->id, $record->technician_id);

        $signature = $record->signatureFor(SignatureStage::Technician);
        $this->assertNotNull($signature);
        $this->assertSame('Budi Santoso', $signature->signed_by_name);
        $this->assertNotNull($signature->image_path);
        Storage::disk('public')->assertExists($signature->image_path);
    }

    public function test_nama_teknisi_dipakai_di_daftar_persetujuan(): void
    {
        $this->store($this->user('technician'));

        $response = $this->actingAs($this->user('user'))
            ->withHeaders([
                'X-Inertia' => 'true',
                'X-Inertia-Version' => Inertia::getVersion(),
            ])
            ->get(route('approvals.index'));

        $this->assertSame(
            'Budi Santoso',
            $response->json('props.records.0.technician_name'),
            'Halaman Persetujuan harus menampilkan nama teknisi yang diketik manual.',
        );
    }

    public function test_revisi_setelah_ditolak_menulis_ulang_nama_baru(): void
    {
        $technician = $this->user('technician');
        $this->store($technician);

        $record = PmRecord::sole();
        app(RejectPmRecord::class)->handle($record, 'Aktual kurang detail');

        $this->assertSame(PmStatus::Rejected, $record->fresh()->status);

        $this->store($technician, ['technician_name' => 'Budi Santoso (revisi)'])->assertRedirect();

        $record->refresh();
        $this->assertSame(PmStatus::Submitted, $record->status);
        $this->assertSame('Budi Santoso (revisi)', $record->technician_name);
        $this->assertSame(1, $record->revision_count);
        $this->assertSame('Budi Santoso (revisi)', $record->signatureFor(SignatureStage::Technician)->signed_by_name);
    }

    public function test_manajer_tidak_bisa_menutup_checklist(): void
    {
        // Manager memegang pm.approve tapi tidak pm.fill/pm.sign, jadi tahap 1
        // bukan miliknya.
        $this->store($this->user('manager'))->assertForbidden();

        $this->assertSame(0, PmRecord::count());
    }

    public function test_checklist_yang_sedang_berjalan_di_approval_tidak_bisa_disimpan_ulang(): void
    {
        $this->store($this->user('technician'));

        $this->assertSame(PmStatus::Submitted, PmRecord::sole()->status);

        // Teknisi lain tidak boleh menulis ulang checklist yang sedang direview.
        $this->store($this->user('technician'))->assertForbidden();

        $this->assertSame('Budi Santoso', PmRecord::sole()->technician_name);
    }

    public function test_tahap_user_pic_menuntut_nama_penanda_tangan(): void
    {
        $record = $this->signedRecord();

        $this->actingAs($this->user('user'))
            ->post(route('pm.sign', $record), ['signature' => self::PNG, 'note' => null])
            ->assertSessionHasErrors('signer_name');

        $this->assertSame(PmStatus::Submitted, $record->fresh()->status);
    }

    public function test_tahap_user_pic_menyimpan_nama_yang_diketik(): void
    {
        $record = $this->signedRecord();

        $this->actingAs($this->user('user'))
            ->post(route('pm.sign', $record), [
                'signature' => self::PNG,
                'signer_name' => 'Siti Rahayu',
                'note' => 'Sebagai diketahui',
            ])
            ->assertRedirect();

        $record->refresh();
        $this->assertSame(PmStatus::PicApproved, $record->status);
        $this->assertSame('Siti Rahayu', $record->signatureFor(SignatureStage::Pic)->signed_by_name);

        // Nama tahap 2 tidak boleh menimpa nama teknisi di pm_records.
        $this->assertSame('Budi Santoso', $record->technician_name);
    }

    public function test_tahap_atasan_menuntut_nama_penanda_tangan(): void
    {
        $record = $this->signedRecord();
        app(SignPmRecord::class)->handle($record, self::PNG, null, 'Siti Rahayu');

        $this->actingAs($this->user('manager'))
            ->post(route('pm.sign', $record->fresh()), ['signature' => self::PNG, 'signer_name' => ''])
            ->assertSessionHasErrors('signer_name');

        $this->assertSame(PmStatus::PicApproved, $record->fresh()->status);
    }

    public function test_rantai_penuh_sampai_disetujui(): void
    {
        $this->store($this->user('technician'));
        $record = PmRecord::sole();

        $this->actingAs($this->user('user'))->post(route('pm.sign', $record), [
            'signature' => self::PNG,
            'signer_name' => 'Siti Rahayu',
        ])->assertRedirect();

        $this->actingAs($this->user('manager'))->post(route('pm.sign', $record->fresh()), [
            'signature' => self::PNG,
            'signer_name' => 'Andi Prasetyo',
        ])->assertRedirect();

        $record->refresh();
        $this->assertSame(PmStatus::Approved, $record->status);
        $this->assertSame('Andi Prasetyo', $record->signatureFor(SignatureStage::Supervisor)->signed_by_name);
        $this->assertSame('Budi Santoso', $record->signatureFor(SignatureStage::Technician)->signed_by_name);
    }

    /**
     * Record yang sudah lewat tahap teknisi, siap menunggu PIC.
     */
    private function signedRecord(): PmRecord
    {
        $this->store($this->user('technician'))->assertRedirect();

        return PmRecord::sole();
    }

    /**
     * Record draft milik sendiri: checklist sudah punya baris, tapi masih ada
     * nilai aktual yang kosong. Ini bentuk PM yang muncul di antrean teknisi.
     */
    private function incompleteRecord(User $technician): PmRecord
    {
        $record = PmRecord::create([
            'machine_id' => $this->machine->id,
            'year' => now()->year,
            'period' => Period::current()->value,
            'technician_id' => $technician->id,
            'status' => PmStatus::Draft,
        ]);

        PmRecordItem::create([
            'pm_record_id' => $record->id,
            'item_name' => 'Cek oli',
            'category' => 'Mekanik',
            'spec' => '5W-30',
            'actual' => null,
        ]);

        return $record;
    }

    public function test_daftar_persetujuan_mengirim_tahap_untuk_panel_tanda_tangan(): void
    {
        $this->store($this->user('technician'));

        $props = $this->actingAs($this->user('user'))
            ->withHeaders([
                'X-Inertia' => 'true',
                'X-Inertia-Version' => Inertia::getVersion(),
            ])
            ->get(route('approvals.index'))
            ->json('props.records.0');

        // Panel tanda tangan di dalam kartu butuh tahap utuh supaya nama
        // mandatory-nya menyebut orang yang seharusnya menandatangani.
        $this->assertSame('pic', $props['awaiting_stage']['value']);
        $this->assertSame('User PIC', $props['awaiting_stage']['short_label']);
        $this->assertTrue($props['can_sign']);
    }

    public function test_tanda_tangan_bisa_dilewati_langsung_dari_daftar_persetujuan(): void
    {
        $record = $this->signedRecord();

        $this->actingAs($this->user('user'))
            ->post(route('pm.sign', $record), [
                'signature' => self::PNG,
                'signer_name' => 'Siti Rahayu',
            ])
            ->assertRedirect();

        $record->refresh();
        $this->assertSame(PmStatus::PicApproved, $record->status);
        $this->assertSame('Siti Rahayu', $record->signatureFor(SignatureStage::Pic)->signed_by_name);
    }

    public function test_daftar_persetujuan_menampilkan_tahap_atasan_bagi_manager(): void
    {
        $record = $this->signedRecord();
        app(SignPmRecord::class)->handle($record, self::PNG, null, 'Siti Rahayu');

        $props = $this->actingAs($this->user('manager'))
            ->withHeaders([
                'X-Inertia' => 'true',
                'X-Inertia-Version' => Inertia::getVersion(),
            ])
            ->get(route('approvals.index'))
            ->json('props.records.0');

        $this->assertSame($record->id, $props['id']);
        $this->assertSame('supervisor', $props['awaiting_stage']['value']);
        $this->assertTrue($props['can_sign'], 'Manager memegang pm.approve untuk tahap Atasan.');
    }

    public function test_checklist_kosong_tidak_bisa_ditandatangani_dari_antrean(): void
    {
        $technician = $this->user('technician');

        $record = PmRecord::create([
            'machine_id' => $this->machine->id,
            'year' => now()->year,
            'period' => Period::current()->value,
            'technician_id' => $technician->id,
            'status' => PmStatus::Draft,
        ]);

        // Nol item: record ada di antrean tapi belum ada satu pun baris yang
        // dikerjakan, jadi tidak boleh bisa diteruskan ke PIC.
        $this->actingAs($technician)
            ->post(route('pm.sign', $record), [
                'signature' => self::PNG,
                'signer_name' => 'Budi Santoso',
            ])
            ->assertSessionHasErrors('signature');

        $this->assertSame(PmStatus::Draft, $record->fresh()->status);
        $this->assertNull($record->signatureFor(SignatureStage::Technician));
    }

    public function test_checklist_yang_aktualnya_kosong_tidak_bisa_ditandatangani_dari_antrean(): void
    {
        $technician = $this->user('technician');
        $record = $this->incompleteRecord($technician);

        $this->actingAs($technician)
            ->post(route('pm.sign', $record), [
                'signature' => self::PNG,
                'signer_name' => 'Budi Santoso',
            ])
            ->assertSessionHasErrors('signature');

        $this->assertSame(PmStatus::Draft, $record->fresh()->status);
        $this->assertNull($record->signatureFor(SignatureStage::Technician));
    }

    public function test_aksi_tanda_tangan_juga_menolak_checklist_tidak_lengkap(): void
    {
        // Penjaga di SignPmRecordRequest sudah menolak lewat HTTP; yang di sini
        // memastikan action-nya sendiri tidak bisa dipakai untuk hal yang sama.
        $record = $this->incompleteRecord($this->user('technician'));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Checklist PM belum lengkap.');

        $this->actingAs($record->technician);

        app(SignPmRecord::class)->handle($record, self::PNG, null, 'Budi Santoso');
    }
}