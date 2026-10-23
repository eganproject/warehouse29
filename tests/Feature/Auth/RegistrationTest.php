<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Registrasi publik dinonaktifkan: akun hanya boleh dibuat oleh admin.
 */
class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_is_not_available(): void
    {
        $this->get('/register')->assertNotFound();
    }

    public function test_guest_cannot_register_a_new_account(): void
    {
        $response = $this->post('/register', [
            'name' => 'Orang Luar',
            'email' => 'luar@example.com',
            'password' => 'Password!234',
            'password_confirmation' => 'Password!234',
        ]);

        $response->assertNotFound();
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'luar@example.com']);
        $this->assertSame(0, User::count());
    }
}
