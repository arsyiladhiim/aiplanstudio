<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Tests\TestCase;

class SocialiteControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.frontend_url' => 'http://localhost:3000']);
    }

    private function fakeGoogle(mixed $user, bool $throw = false): void
    {
        Socialite::extend('google', function () use ($user, $throw) {
            return new class($user, $throw)
            {
                public function __construct(private mixed $user, private bool $throw = false) {}

                public function redirect()
                {
                    return redirect('https://accounts.google.com/o/oauth2/auth');
                }

                public function user(): mixed
                {
                    if ($this->throw) {
                        throw new \RuntimeException('Google auth failed');
                    }

                    return $this->user;
                }
            };
        });
    }

    private function socialUser(string $email, ?string $name = null): object
    {
        return new class($email, $name)
        {
            public function __construct(private string $email, private ?string $name) {}

            public function getEmail(): string
            {
                return $this->email;
            }

            public function getName(): ?string
            {
                return $this->name;
            }
        };
    }

    public function test_google_redirect_hits_google(): void
    {
        $this->fakeGoogle($this->socialUser('google@example.com'));

        $response = $this->get('/api/auth/google/redirect');

        $response->assertStatus(302)
            ->assertRedirect('https://accounts.google.com/o/oauth2/auth');
    }

    public function test_first_google_login_creates_admin_and_logs_in(): void
    {
        $this->fakeGoogle($this->socialUser('google@example.com', 'Google User'));

        $response = $this->get('/api/auth/google/callback');

        $response->assertStatus(302)
            ->assertRedirect('http://localhost:3000/dashboard');

        $this->assertDatabaseHas('users', [
            'email' => 'google@example.com',
            'role' => 'admin',
            'status' => 'active',
        ]);
        $this->assertAuthenticated('web');
    }

    public function test_google_login_for_new_user_creates_pending_member(): void
    {
        User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->fakeGoogle($this->socialUser('new@example.com'));

        $response = $this->get('/api/auth/google/callback');

        $response->assertStatus(302)
            ->assertRedirect('http://localhost:3000/login?status=pending');

        $this->assertDatabaseHas('users', [
            'email' => 'new@example.com',
            'role' => 'member',
            'status' => 'pending',
        ]);
        $this->assertGuest('web');
    }

    public function test_google_callback_redirects_pending_user_to_login(): void
    {
        User::factory()->create(['email' => 'pending@example.com', 'status' => 'pending']);
        $this->fakeGoogle($this->socialUser('pending@example.com'));

        $response = $this->get('/api/auth/google/callback');

        $response->assertStatus(302)
            ->assertRedirect('http://localhost:3000/login?status=pending');
        $this->assertGuest('web');
    }

    public function test_google_callback_logs_in_existing_active_user(): void
    {
        User::factory()->create(['email' => 'google@example.com', 'status' => 'active']);
        $this->fakeGoogle($this->socialUser('google@example.com'));

        $response = $this->get('/api/auth/google/callback');

        $response->assertStatus(302)
            ->assertRedirect('http://localhost:3000/dashboard');
        $this->assertAuthenticated('web');
    }

    public function test_google_callback_fills_missing_name(): void
    {
        $user = User::factory()->create([
            'email' => 'google@example.com',
            'name' => '',
            'status' => 'active',
        ]);
        $this->fakeGoogle($this->socialUser('google@example.com', 'Google Person'));

        $response = $this->get('/api/auth/google/callback');

        $response->assertStatus(302)
            ->assertRedirect('http://localhost:3000/dashboard');
        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Google Person']);
        $this->assertAuthenticated('web');
    }

    public function test_google_callback_failure_redirects_to_login_with_error(): void
    {
        $this->fakeGoogle(null, throw: true);

        $response = $this->get('/api/auth/google/callback');

        $response->assertStatus(302)
            ->assertRedirect('http://localhost:3000/login?error=google');
        $this->assertGuest('web');
    }
}
