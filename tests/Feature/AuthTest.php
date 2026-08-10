<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_endpoint_requires_authentication(): void
    {
        $this->getJson('/api/user')->assertUnauthorized();
    }

    public function test_user_endpoint_returns_the_current_user_when_authenticated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('id', $user->id)
            ->assertJsonPath('email', $user->email);
    }

    public function test_login_with_valid_credentials_succeeds(): void
    {
        $user = User::factory()->create(['password' => bcrypt('correct-password')]);

        $response = $this->statefulPostJson('/api/login', [
            'email' => $user->email,
            'password' => 'correct-password',
        ]);

        $response->assertOk()->assertJsonPath('data.email', $user->email);
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_with_wrong_password_fails(): void
    {
        $user = User::factory()->create(['password' => bcrypt('correct-password')]);

        $response = $this->statefulPostJson('/api/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['email']);
        $this->assertGuest();
    }

    public function test_logout_clears_the_session(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->statefulPostJson('/api/logout');

        $response->assertNoContent();
        $this->assertGuest();
    }

    /**
     * Real browser requests always carry an Origin header, which is what
     * makes Sanctum's EnsureFrontendRequestsAreStateful middleware attach
     * session handling to an /api/* route in the first place — see
     * config/sanctum.php's `stateful` list. Plain postJson() doesn't send
     * one, so /login and /logout (both touch $request->session()) crash
     * with "Session store not set on request" without this. Found by an
     * actual failing test, not written defensively up front.
     */
    private function statefulPostJson(string $uri, array $data = [])
    {
        return $this->withHeader('Origin', env('FRONTEND_URL', 'http://localhost:5173'))
            ->postJson($uri, $data);
    }
}
