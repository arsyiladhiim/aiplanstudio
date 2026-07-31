<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_sends_reset_link_to_registered_email(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'reset@example.com']);

        $response = $this->postJson('/api/forgot-password', [
            'email' => 'reset@example.com',
        ]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Jika email terdaftar, tautan reset password telah dikirim.']);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_forgot_password_returns_same_message_for_unknown_email(): void
    {
        $response = $this->postJson('/api/forgot-password', [
            'email' => 'nobody@example.com',
        ]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Jika email terdaftar, tautan reset password telah dikirim.']);
    }

    public function test_forgot_password_validates_email(): void
    {
        $response = $this->postJson('/api/forgot-password', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_user_can_reset_password_with_valid_token(): void
    {
        $user = User::factory()->create([
            'email' => 'reset@example.com',
            'password' => bcrypt('oldpassword'),
        ]);

        $token = Password::createToken($user);

        $response = $this->postJson('/api/reset-password', [
            'token' => $token,
            'email' => 'reset@example.com',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Password berhasil direset. Silakan login.']);

        $user->refresh();
        $this->assertTrue(password_verify('newpassword123', $user->password));
    }

    public function test_reset_password_rejects_invalid_token(): void
    {
        User::factory()->create([
            'email' => 'reset@example.com',
            'password' => bcrypt('oldpassword'),
        ]);

        $response = $this->postJson('/api/reset-password', [
            'token' => 'invalid-token',
            'email' => 'reset@example.com',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(422);
    }

    public function test_reset_password_validates_required_fields(): void
    {
        $response = $this->postJson('/api/reset-password', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['token', 'email', 'password']);
    }
}
