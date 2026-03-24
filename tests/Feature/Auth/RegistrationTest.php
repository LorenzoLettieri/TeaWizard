<?php

namespace Tests\Feature\Auth;

use App\Models\RegistrationRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get(route('register'));

        $response->assertOk();
    }

    public function test_guests_can_submit_registration_requests(): void
    {
        $response = $this->post(route('register.store'), [
            'name' => 'John Doe',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasNoErrors()
            ->assertRedirect(route('login', absolute: false));

        $this->assertDatabaseHas('registration_requests', [
            'name' => 'John Doe',
            'email' => 'test@example.com',
            'status' => RegistrationRequest::STATUS_PENDING,
        ]);

        $this->assertGuest();
    }
}
