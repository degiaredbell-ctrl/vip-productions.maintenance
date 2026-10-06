<?php

namespace Tests\Feature;

use App\Actions\Pm\RejectPmRecord;
use App\Enums\PmStatus;
use App\Enums\Period;
use App\Models\Machine;
use App\Models\PmRecord;
use App\Models\User;
use App\Services\SignatureChain;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Penugasan User PIC oleh teknisi: siapa yang harus menyetujui tahap 2.
 *
 * Dua hal diuji terpisah supaya tidak saling menutupi:
 *
 * 1. Bentuk request — User PIC wajib dipilih, dan yang boleh dipilih hanya user
 *    dengan izin `pm.acknowledge` (role User). Test ini memanggil endpoint
 *    langsung, jadi dropdown frontend yang disembunyikan tidak jadi satu-satunya
 *    pertahanan.
 * 2. Akibat penugasan — setelah PIC ditunjuk, User PIC lain tidak boleh
 *    menyetujui maupun menolak record itu dan tidak menerimanya di antrean.
 */
class PmPicAssignmentTest extends TestCase
{
    use RefreshDatabase;

    /** PNG 1x1 yang valid, sama dengan yang dikirim canvas SignatureStorage. */
    private const PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    private Machine $machine;
    private User $pic;
    private User $picLain;
    private User $teknisi;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RoleSeeder::class);
        Storage::fake('public');

        $this->pic = User::factory()->create()->assignRole('user');
        $this->picLain = User::factory()->create()->assignRole('user');
        $this->teknisi = User::factory()->create()->assignRole('technician');

        $this->machine = Machine::create([
            'code' => 'TST-PIC-001',
            'name' => 'Mesin Uji Penugasan PIC',
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

    /**
     * Header untuk menyalin respons Inertia sebagai JSON.
     *
     * Middleware Inertia membandingkan `X-Inertia-Version` dengan hash manifest
     * yang dibaca saat request berjalan. Pemanggil `Inertia::getVersion()` belum
     * berisi apa-apa kalau belum ada satu pun request yang lewat middleware, jadi
     * test yang langsung membuka halaman Inertia akan mendapat 409. Hash-nya
     * dihitung di sini supaya test tidak bergantung pada request sebelumnya.
     */
    private function inertiaHeaders(): array
    {
        $manifest = public_path('build/manifest.json');

        return [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => is_file($manifest) ? hash_file('xxh128', $manifest) : '',
        ];
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
            'items' => [
                ['item_name' => 'Cek oli', 'category' => 'Mekanik', 'spec' => '5W-30', 'actual' => 'Wajar'],
            ],
        ], $overrides);
    }

    private function store(array $overrides = [])
    {
        return $this->actingAs($this->teknisi)->post(
            route('machines.pm.store', $this->machine),
            $this->payload($overrides),
        );
    }

    private function signedRecord(): PmRecord
    {
        $this->store()->assertRedirect();

        return PmRecord::sole();
    }

    // ------------------------------------------------------------------
    // Bentuk request
    // ------------------------------------------------------------------

    public function test_user_pic_wajib_dipilih_oleh_teknisi(): void
    {
        $this->store(['pic_user_id' => null])
            ->assertSessionHasErrors('pic_user_id');

        $this->assertSame(0, PmRecord::count(), 'Checklist tidak boleh tersimpan tanpa User PIC yang ditunjuk.');
    }

    public function test_user_pic_kosong_ditolak(): void
    {
        $this->store(['pic_user_id' => ''])->assertSessionHasErrors('pic_user_id');

        $this->assertSame(0, PmRecord::count());
    }

    public function test_user_pic_tidak_boleh_memilih_teknisi_sendiri(): void
    {
        $this->store(['pic_user_id' => $this->teknisi->id])
            ->assertSessionHasErrors('pic_user_id');

        $this->assertSame(0, PmRecord::count());
    }

    public function test_user_pic_tidak_boleh_memilih_akun_tanpa_izin_persetujuan(): void
    {
        // Manager dan admin punya izin lain; mereka bukan kandidat PIC tahap 2
        // karena daftar pilihan sengaja dibatasi role User.
        $this->store(['pic_user_id' => User::factory()->create()->assignRole('manager')->id])
            ->assertSessionHasErrors('pic_user_id');

        $this->store(['pic_user_id' => User::factory()->create()->assignRole('admin')->id])
            ->assertSessionHasErrors('pic_user_id');

        $this->store(['pic_user_id' => User::factory()->create()->assignRole('viewer')->id])
            ->assertSessionHasErrors('pic_user_id');

        $this->assertSame(0, PmRecord::count());
    }

    public function test_user_pic_id_yang_tidak_ada_ditolak(): void
    {
        $this->store(['pic_user_id' => 999999])->assertSessionHasErrors('pic_user_id');

        $this->assertSame(0, PmRecord::count());
    }

    public function test_penugasan_tersimpan_bersama_checklist(): void
    {
        $record = $this->signedRecord();

        $this->assertSame($this->pic->id, $record->pic_user_id);
        $this->assertTrue($record->hasAssignedPic());
        $this->assertSame(PmStatus::Submitted, $record->status);
    }

    public function test_form_pm_menampilkan_hanya_user_pic_yang_boleh_dipilih(): void
    {
        $manager = User::factory()->create()->assignRole('manager');
        $teknisiLain = User::factory()->create()->assignRole('technician');

        $props = $this->actingAs($this->teknisi)
            ->withHeaders($this->inertiaHeaders())
            ->get(route('machines.pm.create', $this->machine))
            ->json('props');

        $ids = collect($props['picCandidates'])->pluck('id');

        $this->assertTrue($ids->contains($this->pic->id));
        $this->assertTrue($ids->contains($this->picLain->id));
        $this->assertFalse($ids->contains($manager->id), 'Manager tidak boleh jadi pilihan User PIC.');
        $this->assertFalse($ids->contains($teknisiLain->id), 'Teknisi tidak boleh menunjuk teknisi lain.');
        $this->assertFalse($ids->contains($this->teknisi->id));
    }

    public function test_revisi_mempertahankan_penugasan_sebelumnya(): void
    {
        $record = $this->signedRecord();
        app(RejectPmRecord::class)->handle($record, 'Aktual kurang detail');

        $this->store(['technician_name' => 'Budi Santoso (revisi)'])->assertRedirect();

        $record->refresh();
        $this->assertSame(
            $this->pic->id,
            $record->pic_user_id,
            'Penugasan PIC tidak boleh hilang saat teknisi mengirim revisi.',
        );
        $this->assertSame(PmStatus::Submitted, $record->status);
    }

    public function test_teknisi_bisa_mengganti_pic_saat_revisi(): void
    {
        $record = $this->signedRecord();
        app(RejectPmRecord::class)->handle($record, 'Aktual kurang detail');

        $this->store(['pic_user_id' => $this->picLain->id])->assertRedirect();

        $this->assertSame($this->picLain->id, $record->fresh()->pic_user_id);
    }

    // ------------------------------------------------------------------
    // Akibat penugasan
    // ------------------------------------------------------------------

    public function test_pic_terpilih_boleh_menandatangani(): void
    {
        $record = $this->signedRecord();

        $this->assertTrue(SignatureChain::canSign($this->pic, $record));

        $this->actingAs($this->pic)->post(route('pm.sign', $record), [
            'signature' => self::PNG,
            'signer_name' => 'Siti Rahayu',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(PmStatus::PicApproved, $record->fresh()->status);
    }

    public function test_pic_lain_tidak_boleh_menandatangani(): void
    {
        $record = $this->signedRecord();

        $this->assertFalse(SignatureChain::canSign($this->picLain, $record));

        $this->actingAs($this->picLain)->post(route('pm.sign', $record), [
            'signature' => self::PNG,
            'signer_name' => 'Orang Lain',
        ])->assertForbidden();

        $this->assertSame(PmStatus::Submitted, $record->fresh()->status);
        $this->assertNull($record->fresh()->signatureFor(\App\Enums\SignatureStage::Pic));
    }

    public function test_pic_lain_tidak_boleh_menolak(): void
    {
        $record = $this->signedRecord();

        $this->assertFalse(SignatureChain::canReject($this->picLain, $record));

        $this->actingAs($this->picLain)->post(route('pm.reject', $record), [
            'note' => 'Saya tidak menerima penugasan ini',
        ])->assertForbidden();

        $this->assertSame(PmStatus::Submitted, $record->fresh()->status);
    }

    public function test_pic_lain_tidak_menerima_record_di_antrean(): void
    {
        $this->signedRecord();

        $idsForAssigned = $this->approvalIdsFor($this->pic);
        $idsForOther = $this->approvalIdsFor($this->picLain);

        $this->assertCount(1, $idsForAssigned);
        $this->assertCount(0, $idsForOther, 'User PIC yang tidak ditunjuk tidak boleh menerima record ini.');
    }

    public function test_badge_menampilkan_hanya_record_miliknya(): void
    {
        $this->signedRecord();

        $this->assertSame(1, SignatureChain::pendingCountFor($this->pic));
        $this->assertSame(0, SignatureChain::pendingCountFor($this->picLain));
    }

    public function test_tahap_atasan_tidak_terkunci_penugasan_pic(): void
    {
        $record = $this->signedRecord();

        $this->actingAs($this->pic)->post(route('pm.sign', $record), [
            'signature' => self::PNG,
            'signer_name' => 'Siti Rahayu',
        ])->assertRedirect();

        $record->refresh();
        $this->assertSame(PmStatus::PicApproved, $record->status);

        // Penugasan hanya berlaku untuk tahap User PIC; tahap Atasan tetap
        // terbuka untuk manager sesuai alurnya.
        $manager = User::factory()->create()->assignRole('manager');
        $this->assertTrue(SignatureChain::canSign($manager, $record));
    }

    public function test_record_lama_tanpa_penugasan_tetap_bisa_ditandatangani_pic_manapun(): void
    {
        // Record yang dibuat sebelum kolom pic_user_id ada: tidak ada yang
        // menugaskan siapa pun, jadi tidak boleh ada antrean yang mandek.
        $record = PmRecord::create([
            'machine_id' => $this->machine->id,
            'year' => now()->year,
            'period' => Period::current()->value,
            'technician_id' => $this->teknisi->id,
            'technician_name' => 'Budi Santoso',
            'status' => PmStatus::Submitted,
            'pic_user_id' => null,
        ]);

        $this->assertFalse($record->hasAssignedPic());
        $this->assertTrue(SignatureChain::canSign($this->pic, $record));
        $this->assertTrue(SignatureChain::canSign($this->picLain, $record));
        $this->assertCount(1, $this->approvalIdsFor($this->pic));
        $this->assertCount(1, $this->approvalIdsFor($this->picLain));
    }

    public function test_admin_tetap_bisa_menandatangani_sebagai_cadangan(): void
    {
        $record = $this->signedRecord();

        $admin = User::factory()->create()->assignRole('admin');

        $this->assertTrue(
            SignatureChain::canSign($admin, $record),
            'Admin harus tetap bisa membuka antrean yang PIC-nya tidak bisa.',
        );
    }

    /**
     * Id record yang tampil di halaman Persetujuan untuk user ini.
     *
     * @return array<int, int>
     */
    private function approvalIdsFor(User $user): array
    {
        $records = $this->actingAs($user)
            ->withHeaders($this->inertiaHeaders())
            ->get(route('approvals.index'))
            ->json('props.records');

        return array_column($records, 'id');
    }
}