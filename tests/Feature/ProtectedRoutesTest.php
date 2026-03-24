<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProtectedRoutesTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function guests_are_redirected_from_all_app_routes(): void
    {
        foreach (['/', '/dashboard', '/teams', '/archetypes', '/decks', '/results', '/stats'] as $uri) {
            $this->get($uri)->assertRedirect('/login');
        }
    }

    #[Test]
    public function authenticated_users_can_access_all_app_routes(): void
    {
        $this->actingAs(User::factory()->create());

        foreach (['/', '/dashboard', '/teams', '/archetypes', '/decks', '/results', '/stats'] as $uri) {
            $response = $this->get($uri);

            if ($uri === '/') {
                $response->assertRedirect('/dashboard');

                continue;
            }

            $response->assertOk();
        }
    }
}
