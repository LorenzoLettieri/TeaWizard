<?php

namespace Tests\Feature\Auth;

use App\Models\RegistrationRequest;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RegistrationRequestApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_access_registration_requests_page(): void
    {
        Role::findOrCreate(Roles::ADMIN, 'web');

        $admin = User::factory()->create();
        $admin->syncRoles([Roles::ADMIN]);

        $this->actingAs($admin)
            ->get(route('admin.registration-requests'))
            ->assertOk();
    }

    public function test_non_admin_cannot_access_registration_requests_page(): void
    {
        Role::findOrCreate(Roles::USER, 'web');

        $user = User::factory()->create();
        $user->syncRoles([Roles::USER]);

        $this->actingAs($user)
            ->get(route('admin.registration-requests'))
            ->assertForbidden();
    }

    public function test_registration_request_can_be_created(): void
    {
        $this->post(route('register.store'), [
            'name' => 'John Doe',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('login', absolute: false));

        $this->assertDatabaseHas('registration_requests', [
            'email' => 'test@example.com',
            'status' => RegistrationRequest::STATUS_PENDING,
        ]);
    }
}
