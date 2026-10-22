<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Halaman error beranimasi (Errors/Error) menggantikan halaman error bawaan.
 */
class ErrorPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RoleSeeder::class);
    }

    public function test_404_menampilkan_halaman_error_dengan_status_asli(): void
    {
        $this->get('/halaman-yang-tidak-ada')
            ->assertNotFound()
            ->assertInertia(fn ($page) => $page->component('Errors/Error')
                ->where('status', 404)
                ->where('message', null)
                ->where('homeUrl', url('/')));
    }

    public function test_403_dari_permission_menampilkan_halaman_error_tanpa_pesan_inggris(): void
    {
        $user = User::factory()->create()->assignRole('viewer');

        $this->actingAs($user)->get(route('admin.users'))
            ->assertForbidden()
            ->assertInertia(fn ($page) => $page->component('Errors/Error')
                ->where('status', 403)
                ->where('message', null));
    }

    public function test_pesan_abort_aplikasi_ikut_ditampilkan(): void
    {
        Route::middleware('web')->get('/_uji-abort', fn () => abort(403, 'Akun Admin hanya dapat diubah oleh Admin.'));

        $this->get('/_uji-abort')
            ->assertForbidden()
            ->assertInertia(fn ($page) => $page->where('message', 'Akun Admin hanya dapat diubah oleh Admin.'));
    }

    public function test_500_tidak_membocorkan_detail_error(): void
    {
        Route::middleware('web')->get('/_uji-500', fn () => throw new \RuntimeException('SQLSTATE rahasia'));

        $response = $this->get('/_uji-500')->assertStatus(500);
        $response->assertInertia(fn ($page) => $page->where('status', 500)->where('message', null));
        $this->assertStringNotContainsString('SQLSTATE rahasia', $response->getContent());
    }

    public function test_permintaan_json_tetap_mendapat_json(): void
    {
        $this->getJson('/halaman-yang-tidak-ada')
            ->assertNotFound()
            ->assertJsonStructure(['message']);
    }

    public function test_sesi_kedaluwarsa_pada_kunjungan_inertia_kembali_dengan_pesan(): void
    {
        Route::middleware('web')->post('/_uji-419', fn () => abort(419));

        $this->from('/dashboard')
            ->withHeaders(['X-Inertia' => 'true'])
            ->post('/_uji-419')
            ->assertRedirect('/dashboard')
            ->assertSessionHas('error');
    }
}
