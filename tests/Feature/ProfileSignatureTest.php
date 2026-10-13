<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\SignatureStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Tanda tangan tersimpan di halaman Profile.
 *
 * User menggambar tanda tangannya sekali, disimpan sebagai PNG di disk publik,
 * lalu dipakai langsung di panel persetujuan PM. File lama dibuang saat
 * diganti, tombol hapus membersihkan kolom + file, dan preview disajikan lewat
 * route ber-otorisasi milik akun sendiri saja.
 *
 * Isolasi database dijamin `Tests\TestCase`: koneksi dipaksa ke sqlite
 * in-memory, jadi `migrate:fresh` dari RefreshDatabase tidak mungkin menyentuh
 * database produksi.
 */
class ProfileSignatureTest extends TestCase
{
    use RefreshDatabase;

    /** PNG 1x1 yang valid, sama dengan yang dikirim canvas SignatureStorage. */
    private const PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    protected function setUp(): void
    {
        parent::setUp();

        // Berkas tanda tangan test ditulis ke folder fake, tidak pernah bercampur
        // dengan folder publik aplikasi.
        Storage::fake('public');
    }

    public function test_user_bisa_menyimpan_tanda_tangan_di_profil(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('profile.signature.store'), ['signature' => self::PNG])
            ->assertRedirect(route('profile.edit'));

        $user->refresh();
        $this->assertNotNull($user->signature_path);
        Storage::disk('public')->assertExists($user->signature_path);
    }

    public function test_menyimpang_tanda_tangan_baru_menghapus_gambar_lama(): void
    {
        $user = User::factory()->create();
        $first = SignatureStorage::storeUserSignature($user, self::PNG);
        $user->forceFill(['signature_path' => $first])->save();

        $this->actingAs($user)
            ->post(route('profile.signature.store'), ['signature' => self::PNG])
            ->assertRedirect();
        // PNG yang sama tetap menghasilkan file baru, jadi path-nya harus beda.
        $user->refresh();
        $this->assertNotSame($first, $user->signature_path);
        Storage::disk('public')->assertExists($user->signature_path);
        Storage::disk('public')->assertMissing($first);
    }

    public function test_hapus_tanda_tangan_membersihkan_kolom_dan_file(): void
    {
        $user = User::factory()->create();
        $path = SignatureStorage::storeUserSignature($user, self::PNG);
        $user->forceFill(['signature_path' => $path])->save();

        $this->actingAs($user)
            ->delete(route('profile.signature.destroy'))
            ->assertRedirect(route('profile.edit'));

        $user->refresh();
        $this->assertNull($user->signature_path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_gambar_yang_bukan_png_ditolak(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('profile.signature.store'), ['signature' => 'data:text/plain;base64,aGVsbG8='])
            ->assertSessionHasErrors('signature');

        $this->assertNull($user->fresh()->signature_path);
    }

    public function test_preview_menyajikan_png_untuk_pemilik_akun(): void
    {
        $user = User::factory()->create();
        $path = SignatureStorage::storeUserSignature($user, self::PNG);
        $user->forceFill(['signature_path' => $path])->save();

        $response = $this->actingAs($user)
            ->get(route('profile.signature.show'))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');

        // Signature bersifat pribadi: Cache-Control wajib berisi `private` dan
        // tidak boleh berisi `public` (nilai yang disuntik BinaryFileResponse
        // sendiri). Symfony bisa menggabungkannya dengan `max-age`, jadi tidak
        // diuji sebagai string utuh.
        $cacheControl = $response->headers->get('Cache-Control');
        $this->assertFalse(
            stripos((string) $cacheControl, 'public'),
            "Cache-Control harus private, ternyata: {$cacheControl}",
        );
        $this->assertNotFalse(stripos((string) $cacheControl, 'private'));
    }

    public function test_preview_404_saat_user_belum_menyimpan(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('profile.signature.show'))
            ->assertNotFound();
    }
}