<?php

namespace Tests\Feature;

use App\Enums\Period;
use App\Models\Machine;
use App\Models\PmRecord;
use App\Models\PmRecordItem;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Aksi "Ganti" pada checklist PM wajib disertai detail bagian yang diganti.
 *
 * Yang dijaga di sini:
 * - Tanpa catatan, submit ditolak (validasi per-item), supaya tidak ada
 *   riwayat "Ganti" yang tidak menjelaskan komponen apa yang diganti.
 * - Catatan tersimpan, terbaca lagi di form (untuk revisi), dan muncul di
 *   halaman riwayat mesin.
 * - Aksi lain tidak ikut dipaksa mengisi catatan.
 */
class PmReplaceNoteTest extends TestCase
{
    use RefreshDatabase;

    private const PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    private Machine $machine;

    private User $teknisi;

    private User $pic;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RoleSeeder::class);
        Storage::fake('public');

        $this->teknisi = User::factory()->create()->assignRole('technician');
        $this->pic = User::factory()->create(['name' => 'Pak PIC'])->assignRole('user');

        $this->machine = Machine::create([
            'code' => 'TST-REP-001',
            'name' => 'Mesin Uji Ganti',
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
     * @param  array<int, array<string, mixed>>|null  $items
     */
    private function submit(?array $items = null, array $overrides = [])
    {
        return $this->actingAs($this->teknisi)->post(route('machines.pm.store', $this->machine), array_merge([
            'machine_id' => $this->machine->id,
            'year' => now()->year,
            'period' => Period::current()->value,
            'technician_name' => 'Budi Santoso',
            'pic_user_id' => $this->pic->id,
            'signature' => self::PNG,
            'items' => $items ?? [
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
                    'replace_note' => 'Belt A-32',
                ],
            ],
        ], $overrides));
    }

    private function inertiaHeaders(): array
    {
        $manifest = public_path('build/manifest.json');

        return [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => is_file($manifest) ? hash_file('xxh128', $manifest) : '',
        ];
    }

    public function test_ganti_tanpa_catatan_ditolak_validasi(): void
    {
        $this->submit([
            [
                'item_name' => 'Cek belt',
                'category' => 'Mekanik',
                'spec' => 'A-32',
                'actual' => 'Perlu ganti',
                'act_replace' => true,
                'replace_note' => '',
            ],
        ])->assertSessionHasErrors('items.0.replace_note');

        $this->assertSame(0, PmRecord::count(), 'Checklist tidak boleh tersimpan saat catatan ganti kosong.');
    }

    public function test_catatan_ganti_tersimpan(): void
    {
        $this->submit()->assertRedirect();

        $item = PmRecordItem::where('item_name', 'Cek belt')->sole();

        $this->assertSame('Belt A-32', $item->replace_note);
    }

    public function test_catatan_ganti_terbaca_lagi_di_form(): void
    {
        $this->submit()->assertRedirect();

        $props = $this->actingAs($this->teknisi)
            ->withHeaders($this->inertiaHeaders())
            ->get(route('machines.pm.create', [
                'machine' => $this->machine->id,
                'period' => Period::current()->value,
                'year' => now()->year,
            ]))
            ->json('props');

        $belt = collect($props['items'])->firstWhere('item_name', 'Cek belt');

        $this->assertSame('Belt A-32', $belt['replace_note']);
    }

    public function test_catatan_ganti_muncul_di_riwayat_mesin(): void
    {
        $this->submit()->assertRedirect();

        $props = $this->actingAs($this->teknisi)
            ->withHeaders($this->inertiaHeaders())
            ->get(route('machines.history', $this->machine))
            ->json('props');

        $belt = collect($props['records'][0]['items'])->firstWhere('item_name', 'Cek belt');

        $this->assertSame('Belt A-32', $belt['replace_note']);
    }

    public function test_aksi_lain_tidak_wajib_mengisi_catatan_ganti(): void
    {
        $this->submit([
            [
                'item_name' => 'Cek oli',
                'category' => 'Mekanik',
                'spec' => '5W-30',
                'actual' => 'Wajar',
                'act_clean' => true,
            ],
        ])->assertRedirect();

        $item = PmRecordItem::sole();

        $this->assertFalse((bool) $item->act_replace);
        $this->assertNull($item->replace_note);
    }
}
