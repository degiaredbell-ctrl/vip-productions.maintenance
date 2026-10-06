<?php

namespace Tests\Feature;

use App\Actions\Pm\RejectPmRecord;
use App\Enums\Period;
use App\Enums\PmStatus;
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
 * Alur revisi setelah PIC menolak: "salah pilih User PIC".
 *
 * Skenario yang dijaga di sini: teknisi menunjuk PIC yang keliru, PIC itu
 * menolak dengan catatan, lalu teknisi membuka lagi checklist. Yang diuji
 * adalah apa yang TIDAK boleh terjadi — isian lama hilang, catatan penolakan
 * tidak sampai ke teknisi, atau PIC lama terkunci sehingga teknisi tidak bisa
 * menggantiUBLEk pilihan yang salah.
 */
class PmRejectionRevisionTest extends TestCase
{
    use RefreshDatabase;

    private const PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    private Machine $machine;
    private User $teknisi;
    private User $picSalah;
    private User $picBenar;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RoleSeeder::class);
        Storage::fake('public');

        $this->teknisi = User::factory()->create()->assignRole('technician');
        $this->picSalah = User::factory()->create(['name' => 'Pak Salah'])->assignRole('user');
        $this->picBenar = User::factory()->create(['name' => 'Pak Benar'])->assignRole('user');

        $this->machine = Machine::create([
            'code' => 'TST-REJ-001',
            'name' => 'Mesin Uji Revisi',
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

    private function submit(array $overrides = [])
    {
        return $this->actingAs($this->teknisi)->post(route('machines.pm.store', $this->machine), array_merge([
            'machine_id' => $this->machine->id,
            'year' => now()->year,
            'period' => Period::current()->value,
            'technician_name' => 'Budi Santoso',
            'pic_user_id' => $this->picSalah->id,
            'signature' => self::PNG,
            'general_note' => 'Catatan umum awal',
            'items' => [
                [
                    'item_name' => 'Cek oli',
                    'category' => 'Mekanik',
                    'spec' => '5W-30',
                    'actual' => 'Wajar',
                    'act_clean' => true,
                ],
                [
                    'item_name' => 'Cek belt',
                    'category' => 'Mekanik',
                    'spec' => 'A-32',
                    'actual' => 'Perlu ganti',
                    'act_replace' => true,
                ],
            ],
        ], $overrides));
    }

    /**
     * Header untuk menyalin respons Inertia sebagai JSON.
     *
     * `Inertia::getVersion()` hanya terisi setelah ada request yang lewat
     * middleware; test yang langsung membuka halaman Inertia tanpa request
     * sebelumnya akan mendapat 409. Hash manifest dihitung langsung di sini.
     */
    private function inertiaHeaders(): array
    {
        $manifest = public_path('build/manifest.json');

        return [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => is_file($manifest) ? hash_file('xxh128', $manifest) : '',
        ];
    }

    /** Form PM yang dibuka teknisi, dengan props yang sudah di-resolve. */
    private function openForm(): array
    {
        $response = $this->actingAs($this->teknisi)
            ->withHeaders($this->inertiaHeaders())
            ->get(route('machines.pm.create', [
                'machine' => $this->machine->id,
                'period' => Period::current()->value,
                'year' => now()->year,
            ]));

        return $response->json('props');
    }

    private function rejectedRecord(string $reason = 'User PIC salah pilih, mohon diganti ke Pak Benar'): PmRecord
    {
        $this->submit()->assertRedirect();
        $record = PmRecord::sole();

        $this->actingAs($this->picSalah)->post(route('pm.reject', $record), ['note' => $reason])
            ->assertRedirect();

        return $record->fresh();
    }

    // ------------------------------------------------------------------
    // Stempel waktu dokumen (toggle "Riwayat dokumen" di form)
    // ------------------------------------------------------------------

    public function test_riwayat_dokumen_tidak_muncul_sebelum_pernah_submit(): void
    {
        // Record draft: pernah dibuka formnya, tapi belum pernah dikirim.
        // props `created_at`/`updated_at` harus null supaya baris riwayat
        // tidak dirender sama sekali.
        PmRecord::create([
            'machine_id' => $this->machine->id,
            'year' => now()->year,
            'period' => Period::current()->value,
            'technician_id' => $this->teknisi->id,
            'status' => PmStatus::Draft,
        ]);

        $existing = $this->openForm()['existing'];

        $this->assertNotNull($existing, 'Draft tetap terbuka sebagai existing di form.');
        $this->assertNull($existing['created_at']);
        $this->assertNull($existing['updated_at']);
    }

    public function test_riwayat_dokumen_muncul_setelah_submit(): void
    {
        $this->submit()->assertRedirect();

        $existing = $this->openForm()['existing'];

        $this->assertNotNull($existing['created_at']);
        $this->assertNotNull($existing['updated_at']);
        $this->assertMatchesRegularExpression(
            '/\d{2} \w{3} \d{4} \d{2}:\d{2}/',
            $existing['created_at'],
            'Waktu harus terbaca sebagai "d M Y H:i".'
        );
    }

    // ------------------------------------------------------------------
    // PIC yang dipilih memang berhak menolak
    // ------------------------------------------------------------------

    public function test_pic_yang_ditunjuk_boleh_menolak(): void
    {
        $record = $this->rejectedRecord();

        $this->assertSame(PmStatus::Rejected, $record->status);
        $this->assertTrue(
            $this->picSalah->can('reject', $record) === false,
            'Setelah ditolak, record menunggu teknisi, bukan PIC yang sama.',
        );
    }

    public function test_pic_lain_tidak_boleh_menolak_record_milik_pic_terpilih(): void
    {
        $this->submit()->assertRedirect();
        $record = PmRecord::sole();

        $this->actingAs($this->picBenar)->post(route('pm.reject', $record), ['note' => 'Saya tidak tasked'])
            ->assertForbidden();

        $this->assertSame(PmStatus::Submitted, $record->fresh()->status);
    }

    public function test_penolakan_wajib_punya_catatan(): void
    {
        $this->submit()->assertRedirect();
        $record = PmRecord::sole();

        $this->actingAs($this->picSalah)->post(route('pm.reject', $record), ['note' => ''])
            ->assertSessionHasErrors('note');

        $this->assertSame(PmStatus::Submitted, $record->fresh()->status);
    }

    // ------------------------------------------------------------------
    // Form revisi: terbuka, terisi, dan bisa diubah
    // ------------------------------------------------------------------

    public function test_form_revisi_terbuka_untuk_teknisi_pemilik(): void
    {
        $this->rejectedRecord();

        $props = $this->openForm();

        $this->assertTrue($props['canFill'], 'Checklist yang ditolak harus bisa diisi ulang.');
        $this->assertSame('rejected', $props['existing']['status']);
    }

    public function test_form_revisi_menampilkan_catatan_penolakan(): void
    {
        $this->rejectedRecord('PIC salah pilih, ganti ke Pak Benar');

        $props = $this->openForm();

        $this->assertSame(
            'PIC salah pilih, ganti ke Pak Benar',
            $props['existing']['reject_reason'],
            'Catatan penolakan harus sampai ke form supaya teknisi tahu apa yang diperbaiki.',
        );
        $this->assertSame('Pak Salah', $props['existing']['rejected_by']);
        $this->assertNotNull($props['existing']['rejected_at']);
    }

    public function test_form_baru_tidak_menampilkan_catatan_penolakan(): void
    {
        $props = $this->openForm();

        $this->assertNull($props['existing'], 'Checklist yang belum pernah ditolak tidak punya catatan penolakan.');
        $this->assertSame('', $props['items'] === [] ? '' : 'baris checklist dari template');
    }

    public function test_semua_isian_lama_tetap_ada_di_form_revisi(): void
    {
        $this->rejectedRecord();
        $record = PmRecord::sole();

        $props = $this->openForm();

        // Nama teknisi tidak boleh hilang; kalau kosong, teknisi dipaksa
        // mengetik ulang identitas yang sebenarnya sudah tercatat.
        $this->assertSame('Budi Santoso', $props['existing']['technician_name']);
        $this->assertSame('Budi Santoso', $record->technician_name);
        $this->assertSame('Catatan umum awal', $props['existing']['general_note']);

        // Setiap baris checklist dan centang aksinya ikut terbawa.
        $actuals = array_column($props['items'], 'actual');
        $this->assertSame(['Wajar', 'Perlu ganti'], $actuals);
        $this->assertTrue((bool) $props['items'][0]['act_clean']);
        $this->assertTrue((bool) $props['items'][1]['act_replace']);
        $this->assertFalse((bool) $props['items'][0]['act_replace']);
    }

    public function test_pic_terpilih_lama_tetap_terpilih_tapi_bisa_diganti(): void
    {
        $this->rejectedRecord();

        $props = $this->openForm();

        // Preselect mencegah pergantian diam-diam, tapi dropdown tetap memuat
        // semua kandidat supaya teknisi bisa memperbaiki pilihannya.
        $this->assertSame(
            $this->picSalah->id,
            $props['existing']['pic_user_id'],
            'PIC lama sebaiknya tetap terpilih supaya tidak berganti tanpa sengaja.',
        );

        $ids = array_column($props['picCandidates'], 'id');
        $this->assertContains($this->picSalah->id, $ids);
        $this->assertContains(
            $this->picBenar->id,
            $ids,
            'Teknisi harus bisa memilih User PIC lain saat memperbaiki penugasan.',
        );
    }

    public function test_teknisi_bisa_mengganti_pic_kirim_ulang_dan_mengulang_tahapan(): void
    {
        $this->rejectedRecord();
        $record = PmRecord::sole();

        $this->submit([
            'pic_user_id' => $this->picBenar->id,
            'technician_name' => 'Budi Santoso',
        ])->assertRedirect();

        $record->refresh();
        $this->assertSame($this->picBenar->id, $record->pic_user_id, 'PIC baru harus menggantikan PIC yang salah.');
        $this->assertSame(PmStatus::Submitted, $record->status, 'Setelah revisi, antrean kembali ke tahap PIC.');
        $this->assertSame(1, $record->revision_count);

        // Tahap yang sama sekali replaced, bukan ditumpuk.
        $this->assertCount(1, $record->signatures()->where('stage', SignatureStage::Technician)->get());

        // PIC yang salah sudah tidak memegang antrean ini.
        $this->assertFalse($this->picSalah->can('sign', $record));
        $this->assertTrue($this->picBenar->can('sign', $record));
    }

    public function test_revisi_mempertahankan_catatan_umum_dan_isi_checklist(): void
    {
        $this->rejectedRecord();
        $record = PmRecord::sole();

        $this->submit([
            'pic_user_id' => $this->picBenar->id,
            'general_note' => 'Catatan umum awal',
            'items' => [
                ['item_name' => 'Cek oli', 'category' => 'Mekanik', 'spec' => '5W-30', 'actual' => 'Sudah diganti', 'act_clean' => true],
                ['item_name' => 'Cek belt', 'category' => 'Mekanik', 'spec' => 'A-32', 'actual' => 'Sudah diganti', 'act_replace' => true],
            ],
        ])->assertRedirect();

        $record->refresh();
        $this->assertSame('Catatan umum awal', $record->general_note);
        $this->assertSame(2, $record->items()->count());
        $this->assertSame(
            ['Sudah diganti', 'Sudah diganti'],
            $record->items()->pluck('actual')->all(),
        );
    }

    public function test_checklist_yang_ditolak_tidak_bisa_disunting_teknisi_lain(): void
    {
        $this->rejectedRecord();

        $teknisiLain = User::factory()->create()->assignRole('technician');

        $this->actingAs($teknisiLain)->post(route('machines.pm.store', $this->machine), [
            'machine_id' => $this->machine->id,
            'year' => now()->year,
            'period' => Period::current()->value,
            'technician_name' => 'Orang Lain',
            'pic_user_id' => $this->picBenar->id,
            'signature' => self::PNG,
            'items' => [['item_name' => 'X', 'category' => 'Mekanik', 'spec' => 'x', 'actual' => 'x']],
        ])->assertForbidden();

        // Dua baris asli tetap utuh; tidak ada baris baru yang masuk dari
        // teknisi lain.
        $this->assertSame(2, PmRecordItem::count());
        $this->assertSame('Budi Santoso', PmRecord::sole()->technician_name);
    }

    public function test_riwayat_penolakan_tersimpan_di_audit_log(): void
    {
        $record = $this->rejectedRecord('PIC salah pilih');

        $this->assertSame('PIC salah pilih', $record->rejectReason());

        $log = $record->lastRejection();
        $this->assertNotNull($log);
        $this->assertSame($this->picSalah->id, $log->user_id, 'Penolakan harus tercatat atas nama PIC yang menolak.');
        $this->assertSame('pic', $log->changes['stage']);
    }
}