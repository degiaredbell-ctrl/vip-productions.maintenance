<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PinLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_bisa_login_dengan_pin(): void
    {
        $user = User::factory()->create();
        $user->setPin('482915');

        $response = $this->post(route('login.pin'), ['pin' => '482915']);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_pin_salah_ditolak(): void
    {
        User::factory()->create()->setPin('482915');

        $this->post(route('login.pin'), ['pin' => '111222'])->assertSessionHasErrors('pin');

        $this->assertGuest();
    }

    public function test_pin_disimpan_sebagai_hash_bukan_teks_asli(): void
    {
        $user = User::factory()->create();
        $user->setPin('482915');

        $this->assertNotSame('482915', $user->fresh()->pin_hash);
        $this->assertArrayNotHasKey('pin_hash', $user->fresh()->toArray());
    }

    public function test_login_pin_dibatasi_setelah_5_percobaan_gagal(): void
    {
        $user = User::factory()->create();
        $user->setPin('482915');

        foreach (range(1, 5) as $i) {
            $this->post(route('login.pin'), ['pin' => '00000'.$i]);
        }

        $this->post(route('login.pin'), ['pin' => '482915'])->assertSessionHasErrors('pin');
        $this->assertGuest();
    }

    public function test_user_mengatur_pin_sendiri_dengan_konfirmasi_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put(route('profile.pin.update'), [
            'current_password' => 'salah',
            'pin' => '482915',
            'pin_confirmation' => '482915',
        ])->assertSessionHasErrors('current_password');
        $this->assertFalse($user->fresh()->hasPin());

        $this->actingAs($user)->put(route('profile.pin.update'), [
            'current_password' => 'password',
            'pin' => '482915',
            'pin_confirmation' => '482915',
        ])->assertSessionHasNoErrors();
        $this->assertSame(User::pinDigest('482915'), $user->fresh()->pin_hash);

        $this->actingAs($user)->delete(route('profile.pin.destroy'), [
            'current_password' => 'password',
        ])->assertSessionHasNoErrors();
        $this->assertFalse($user->fresh()->hasPin());
    }

    public function test_pin_lemah_tidak_valid_dan_pin_harus_unik(): void
    {
        User::factory()->create()->setPin('482915');
        $user = User::factory()->create();

        foreach (['111111', '123456', '654321', '12345', 'abcdef', '482915'] as $pin) {
            $this->actingAs($user)->put(route('profile.pin.update'), [
                'current_password' => 'password',
                'pin' => $pin,
                'pin_confirmation' => $pin,
            ])->assertSessionHasErrors('pin');
        }

        $this->assertFalse($user->fresh()->hasPin());
    }

    public function test_admin_bisa_mengatur_dan_menghapus_pin_user_lain(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $target = User::factory()->create();

        $this->actingAs($admin)->put(route('admin.users.update-pin', $target), [
            'pin' => '739104',
            'pin_confirmation' => '739104',
        ])->assertSessionHasNoErrors();
        $this->assertSame(User::pinDigest('739104'), $target->fresh()->pin_hash);

        $this->actingAs($admin)->delete(route('admin.users.destroy-pin', $target));
        $this->assertFalse($target->fresh()->hasPin());
    }

    public function test_non_admin_tidak_bisa_mengatur_pin_user_lain(): void
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('technician');
        $target = User::factory()->create();

        $this->actingAs($user)->put(route('admin.users.update-pin', $target), [
            'pin' => '739104',
            'pin_confirmation' => '739104',
        ])->assertForbidden();
    }
}
