<?php

namespace Tests\Feature;

use App\Mail\EmailVerificationCodeMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\PendingMail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Mail::fake();
    }

    public function test_registration_sends_a_six_digit_code_and_stores_only_its_hash(): void
    {
        $response = $this->post(route('register.store'), $this->registrationData());
        $user = User::where('email', 'reader@example.test')->firstOrFail();
        $code = $this->sentCodeFor($user->email);

        $response->assertRedirect(route('verification.notice'));
        $response->assertSessionMissing('verification_code');
        $this->assertMatchesRegularExpression('/\A[0-9]{6}\z/', $code);
        $this->assertNotSame($code, $user->verification_code);
        $this->assertTrue(Hash::check($code, $user->verification_code));
        Mail::assertSent(EmailVerificationCodeMail::class, fn (EmailVerificationCodeMail $mail): bool => $mail->hasTo($user->email));
    }

    public function test_unverified_user_is_redirected_from_normal_application_pages(): void
    {
        $this->registerUser();

        $this->get('/')->assertRedirect(route('verification.notice'));
    }

    public function test_unverified_user_can_access_the_verification_page(): void
    {
        $this->registerUser();

        $this->get(route('verification.notice'))
            ->assertOk()
            ->assertSee(__('site.verification.instructions'))
            ->assertDontSee('verification_expiry');
    }

    public function test_valid_code_verifies_user_and_clears_verification_data(): void
    {
        $user = $this->registerUser();
        $code = $this->sentCodeFor($user->email);

        $this->post(route('verification.verify'), ['verification_code' => $code])
            ->assertRedirect('/');

        $user->refresh();
        $this->assertNotNull($user->email_verified_at);
        $this->assertNull($user->verification_code);
        $this->assertNull($user->verification_expiry);
    }

    public function test_invalid_code_is_rejected(): void
    {
        $this->registerUser();

        $this->post(route('verification.verify'), ['verification_code' => '000000'])
            ->assertSessionHasErrors('verification_code')
            ->assertRedirect(route('verification.notice'));
    }

    public function test_expired_code_is_rejected_with_an_expiry_message(): void
    {
        $user = $this->registerUser();
        $user->verification_expiry = now()->subSecond();
        $user->save();
        $this->actingAs($user->refresh());

        $this->post(route('verification.verify'), ['verification_code' => $this->sentCodeFor($user->email)])
            ->assertSessionHasErrors('verification_code')
            ->assertRedirect(route('verification.notice'));

        $this->assertSame(
            __('site.verification.expired_code'),
            session('errors')->first('verification_code'),
        );
    }

    public function test_resend_after_cooldown_sends_and_stores_a_new_code(): void
    {
        $user = $this->registerUser();
        $oldCode = $this->sentCodeFor($user->email);
        $oldHash = $user->verification_code;

        $this->travel(61)->seconds();

        $this->post(route('verification.resend'))
            ->assertRedirect(route('verification.notice'))
            ->assertSessionHas('status', __('site.verification.sent'));

        $user->refresh();
        $newCode = $this->sentCodeFor($user->email);

        $this->assertNotSame($oldCode, $newCode);
        $this->assertNotSame($oldHash, $user->verification_code);
        $this->assertTrue(Hash::check($newCode, $user->verification_code));
        $this->assertFalse(Hash::check($oldCode, $user->verification_code));
        $this->assertSame(now()->addMinutes(10)->timestamp, $user->verification_expiry->timestamp);
        Mail::assertSent(EmailVerificationCodeMail::class, 2);
    }

    public function test_resend_is_blocked_during_the_one_minute_cooldown(): void
    {
        $user = $this->registerUser();
        $originalHash = $user->verification_code;
        $originalExpiry = $user->verification_expiry->toDateTimeString();

        $this->post(route('verification.resend'))
            ->assertRedirect(route('verification.notice'))
            ->assertSessionHasErrors('resend');

        $user->refresh();
        $this->assertSame($originalHash, $user->verification_code);
        $this->assertSame($originalExpiry, $user->verification_expiry->toDateTimeString());
        Mail::assertSent(EmailVerificationCodeMail::class, 1);
    }

    public function test_verified_user_is_not_forced_back_to_verification(): void
    {
        $user = $this->registerUser();
        $user->email_verified_at = now();
        $user->save();
        $this->actingAs($user->refresh());

        $this->get('/')->assertOk();
        $this->get(route('verification.notice'))->assertRedirect('/');
    }

    public function test_seventh_verification_post_within_a_minute_is_throttled(): void
    {
        $this->registerUser();

        for ($attempt = 1; $attempt <= 6; $attempt++) {
            $this->post(route('verification.verify'), ['verification_code' => '000000'])
                ->assertRedirect(route('verification.notice'));
        }

        $this->post(route('verification.verify'), ['verification_code' => '000000'])
            ->assertTooManyRequests();
    }

    public function test_fourth_resend_post_within_a_minute_is_throttled(): void
    {
        $user = $this->registerUser();
        $user->verification_expiry = now()->subMinutes(9);
        $user->save();
        $this->actingAs($user->refresh());

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $this->post(route('verification.resend'))
                ->assertRedirect(route('verification.notice'));

            $user->verification_expiry = now()->subMinutes(9);
            $user->save();
            $this->actingAs($user->refresh());
        }

        $this->post(route('verification.resend'))
            ->assertTooManyRequests();
    }

    public function test_unverified_user_cannot_use_livewire_update_route_to_bypass_enforcement(): void
    {
        $this->registerUser();

        $this->post(route('default-livewire.update'))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_registration_mail_failure_keeps_user_authenticated_and_shows_generic_error(): void
    {
        $mail = $this->failingPendingMail();
        $verificationCode = null;
        $mail->shouldReceive('send')
            ->once()
            ->withArgs(function (EmailVerificationCodeMail $mailable) use (&$verificationCode): bool {
                $verificationCode = $mailable->verificationCode;

                return true;
            })
            ->andThrow(new RuntimeException('Synthetic transport failure detail.'));

        Mail::shouldReceive('to')
            ->once()
            ->with('reader@example.test')
            ->andReturn($mail);

        $response = $this->post(route('register.store'), $this->registrationData());

        $response->assertRedirect(route('verification.notice'))
            ->assertSessionHasErrors(['email' => __('site.verification.delivery_failed')]);
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'reader@example.test']);
        $this->assertIsString($verificationCode);
        $this->assertStringNotContainsString($verificationCode, $response->getContent());
        $this->assertStringNotContainsString('Synthetic transport failure detail.', $response->getContent());
    }

    public function test_resend_mail_failure_preserves_previous_hash_expiry_and_code(): void
    {
        $user = $this->registerUser();
        $previousCode = $this->sentCodeFor($user->email);
        $previousHash = $user->verification_code;
        $this->travel(61)->seconds();
        $user->refresh();
        $previousExpiry = $user->verification_expiry->toDateTimeString();

        $mail = $this->failingPendingMail();
        $mail->shouldReceive('send')
            ->once()
            ->with(Mockery::type(EmailVerificationCodeMail::class))
            ->andThrow(new RuntimeException('Synthetic transport failure detail.'));

        Mail::shouldReceive('to')
            ->once()
            ->with($user->email)
            ->andReturn($mail);

        $response = $this->post(route('verification.resend'));

        $response->assertRedirect(route('verification.notice'))
            ->assertSessionHasErrors(['resend' => __('site.verification.delivery_failed')]);
        $this->assertStringNotContainsString('Synthetic transport failure detail.', $response->getContent());

        $user->refresh();
        $this->assertSame($previousHash, $user->verification_code);
        $this->assertSame($previousExpiry, $user->verification_expiry->toDateTimeString());

        $this->actingAs($user);
        $this->post(route('verification.verify'), ['verification_code' => $previousCode])
            ->assertRedirect('/');
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    private function registerUser(): User
    {
        $this->post(route('register.store'), $this->registrationData())
            ->assertRedirect(route('verification.notice'));

        return User::where('email', 'reader@example.test')->firstOrFail();
    }

    /**
     * @return array<string, string>
     */
    private function registrationData(): array
    {
        return [
            'name' => 'Test Reader',
            'username' => 'test-reader',
            'email' => 'reader@example.test',
            'password' => 'correct-horse-battery',
            'password_confirmation' => 'correct-horse-battery',
        ];
    }

    private function sentCodeFor(string $email): string
    {
        $code = null;

        Mail::assertSent(EmailVerificationCodeMail::class, function (EmailVerificationCodeMail $mail) use ($email, &$code): bool {
            if (! $mail->hasTo($email)) {
                return false;
            }

            $code = $mail->verificationCode;

            return true;
        });

        $this->assertIsString($code);

        return $code;
    }

    private function failingPendingMail(): PendingMail
    {
        return Mockery::mock(PendingMail::class);
    }
}
