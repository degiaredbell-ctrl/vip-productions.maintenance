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
 * tangan. Nama yang diketik inilah yang tersimpan sebagai identitas di dokumen.
 *
 * Tahap 2 dan 3 (User PIC dan Atasan) berbeda: tidak ada field nama sama
 * sekali. Nama approver diambil dari akun yang login, dan `signer_name` yang
 * dikirim client diabaikan supaya nama di dokumen tidak bisa dipalsukan.
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
    private User $pic;
    private User $picLain;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RoleSeeder::class);

        // Berkas tanda tangan test ditulis ke folder fake, tidak pernah bercampur
        // dengan tanda tangan PM yang sudah tersimpan.
        Storage::fake('public');

        // User PIC yang jadi pilihan bawaan di payload().PIC kedua
        // dipakai test yang memastikan User PIC lain tidak ikut bisa menyetujui.
        $this->pic = User::factory()->create()->assignRole('user');
        $this->picLain = User::factory()->create()->assignRole('user');

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
            'pic_user_id' => $this->pic->id,
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

        $response = $this->actingAs($this->pic)
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

    public function test_tahap_user_pic_tidak_menuntut_nama_dan_memakai_nama_akun(): void
    {
        $record = $this->signedRecord();

        // Tidak ada `signer_name` sama sekali: panel persetujuan di form PM
        // tidak punya field nama, jadi payload frontend memang begitu.
        $this->actingAs($this->pic)
            ->post(route('pm.sign', $record), ['signature' => self::PNG, 'note' => null])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $record->refresh();
        $this->assertSame(PmStatus::PicApproved, $record->status);
        $this->assertSame($this->pic->name, $record->signatureFor(SignatureStage::Pic)->signed_by_name);
    }

    public function test_tahap_user_pic_mengabaikan_nama_yang_dikirim_client(): void
    {
        $record = $this->signedRecord();

        // Nama approver bukan input user, jadi nilai yang dikirim client
        // diabaikan: kalau tidak, dokumen bisa menyatakan nama orang lain.
        $this->actingAs($this->pic)
            ->post(route('pm.sign', $record), [
                'signature' => self::PNG,
                'signer_name' => 'Orang Lain',
                'note' => 'Sebagai diketahui',
            ])
            ->assertRedirect();

        $record->refresh();
        $this->assertSame(PmStatus::PicApproved, $record->status);
        $this->assertSame($this->pic->name, $record->signatureFor(SignatureStage::Pic)->signed_by_name);

        // Nama tahap 2 tidak boleh menimpa nama teknisi di pm_records.
        $this->assertSame('Budi Santoso', $record->technician_name);
    }

    public function test_tahap_atasan_tidak_menuntut_nama_dan_memakai_nama_akun(): void
    {
        $record = $this->signedRecord();
        app(SignPmRecord::class)->handle($record, self::PNG, null, 'Siti Rahayu');

        $manager = $this->user('manager');

        $this->actingAs($manager)
            ->post(route('pm.sign', $record->fresh()), ['signature' => self::PNG])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $record->refresh();
        $this->assertSame(PmStatus::Approved, $record->status);
        $this->assertSame(
            $manager->name,
            $record->signatureFor(SignatureStage::Supervisor)->signed_by_name
        );
    }

    public function test_rantai_penuh_sampai_disetujui(): void
    {
        $this->store($this->user('technician'));
        $record = PmRecord::sole();

        $this->actingAs($this->pic)->post(route('pm.sign', $record), [
            'signature' => self::PNG,
        ])->assertRedirect();

        $manager = $this->user('manager');

        $this->actingAs($manager)->post(route('pm.sign', $record->fresh()), [
            'signature' => self::PNG,
        ])->assertRedirect();

        $record->refresh();
        $this->assertSame(PmStatus::Approved, $record->status);

        // Dua tahap persetujuan memakai nama akun masing-masing, tahap teknisi
        // tetap memakai nama yang diketik teknisi di form checklist.
        $this->assertSame(
            $manager->name,
            $record->signatureFor(SignatureStage::Supervisor)->signed_by_name
        );
        $this->assertSame($this->pic->name, $record->signatureFor(SignatureStage::Pic)->signed_by_name);
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

        $props = $this->actingAs($this->pic)
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

    public function test_halaman_persetujuan_menampilkan_data_kolom_nama_dan_tanda_tangan(): void
    {
        $record = $this->signedRecord();

        $props = $this->actingAs($this->pic)
            ->withHeaders([
                'X-Inertia' => 'true',
                'X-Inertia-Version' => Inertia::getVersion(),
            ])
            ->get(route('approvals.index'))
            ->json('props.records.0');

        // Antrean dirender sebagai tabel: kolom "Nama Penanda Tangan" dan
        // "Tanda Tangan" berdiri sendiri, jadi frontend butuh id unik per record
        // untuk input nama dan canvas-nya. Tanpa `id` yang unik, label di semua
        // baris akan menempel ke baris pertama saja.
        $this->assertSame($record->id, $props['id']);
        $this->assertTrue($props['can_sign']);
        $this->assertTrue($props['can_reject']);
        $this->assertSame('User PIC', $props['awaiting_stage']['short_label']);
        $this->assertSame('Persetujuan User PIC', $props['awaiting_stage']['label']);

        // Nama teknisi manual harus ikut tampil supaya yang sudah mengisi
        // checklist bisa dibandingkan dengan nama yang akan menandatangani.
        $this->assertSame('Budi Santoso', $props['technician_name']);
    }

    public function test_tahap_pic_tetap_berjalan_kalau_endpoint_dipanggil_langsung(): void
    {
        // Daftar Persetuian tidak punya kolom tanda tangan lagi, tapi endpointnya
        // masih bisa dipanggil langsung, jadi penjaga backend tidak boleh ikut
        // dilonggarkan: approve tetap butuh gambar tanda tangan.
        $record = $this->signedRecord();

        $this->actingAs($this->pic)
            ->post(route('pm.sign', $record), ['note' => 'Tanpa tanda tangan'])
            ->assertSessionHasErrors('signature');

        $this->assertSame(PmStatus::Submitted, $record->fresh()->status);

        $this->actingAs($this->pic)
            ->post(route('pm.sign', $record), ['signature' => self::PNG])
            ->assertRedirect();

        $record->refresh();
        $this->assertSame(PmStatus::PicApproved, $record->status);
        $this->assertSame($this->pic->name, $record->signatureFor(SignatureStage::Pic)->signed_by_name);
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

    public function test_tahap_teknisi_masih_menuntut_nama_yang_diketik(): void
    {
        // Kebalikan dari tahap PIC/Atasan: nama teknisi tetap wajib, karena
        // yang menandatangani pekerjaannya sendiri dan nama di dokumen boleh
        // berbeda dengan nama akunnya.
        $technician = $this->user('technician');
        $this->store($technician)->assertRedirect();
        $record = PmRecord::sole();
        $record->update(['status' => PmStatus::Draft]);

        $this->actingAs($technician)
            ->post(route('pm.sign', $record), [
                'signature' => self::PNG,
                'signer_name' => '',
            ])
            ->assertSessionHasErrors('signer_name');

        // Request yang ditolak tidak boleh menambah tanda tangan kedua maupun
        // melepas kunci: status masih draft.
        $this->assertSame(PmStatus::Draft, $record->fresh()->status);
        $this->assertSame(1, $record->fresh()->signatures()->count());
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