<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\PasswordChangedNotification;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_request_response_does_not_reveal_if_an_email_exists(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'wali@example.test']);

        $known = $this->post(route('password.email'), ['email' => $user->email])
            ->assertRedirect()
            ->assertSessionHas('status', 'Jika email tersebut terdaftar, tautan reset akan dikirim. Periksa kotak masuk Anda.');

        Notification::assertSentTo($user, ResetPassword::class);

        $unknown = $this->post(route('password.email'), ['email' => 'unknown@example.test'])
            ->assertRedirect()
            ->assertSessionHas('status', 'Jika email tersebut terdaftar, tautan reset akan dikirim. Periksa kotak masuk Anda.');

        $this->assertSame($known->getSession()->get('status'), $unknown->getSession()->get('status'));
    }

    public function test_valid_token_changes_password_once_and_sends_a_notice(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'wali@example.test']);
        $token = Password::broker()->createToken($user);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'AmanBanget-2026!',
            'password_confirmation' => 'AmanBanget-2026!',
        ])->assertRedirect(route('login'))
            ->assertSessionHas('status', 'Kata sandi berhasil diubah. Silakan masuk kembali.');

        $this->assertTrue(Hash::check('AmanBanget-2026!', $user->fresh()->password));
        Notification::assertSentTo($user, PasswordChangedNotification::class);

        $this->from(route('password.request'))
            ->post(route('password.update'), [
                'token' => $token,
                'email' => $user->email,
                'password' => 'KataSandiCadangan-2026!',
                'password_confirmation' => 'KataSandiCadangan-2026!',
            ])->assertRedirect(route('password.request'))
                ->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('AmanBanget-2026!', $user->fresh()->password));
    }

    public function test_password_reset_requires_a_long_confirmed_password(): void
    {
        $this->post(route('password.update'), [
            'token' => 'invalid',
            'email' => 'wali@example.test',
            'password' => 'short',
            'password_confirmation' => 'different',
        ])->assertSessionHasErrors(['password']);
    }
}
