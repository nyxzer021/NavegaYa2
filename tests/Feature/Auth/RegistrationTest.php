<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'phone' => '999999999',
            'document_number' => '12345678',
            'password' => 'Password1234',
            'password_confirmation' => 'Password1234',
        ]);

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'test@example.com', 'phone' => '999999999', 'document_number' => '12345678']);
        $response->assertRedirect(route('travels.index', absolute: false));
        $this->assertDatabaseHas('roles', ['code' => 'customer']);
        $this->assertTrue(auth()->user()->roles()->where('code', 'customer')->exists());
    }
}
