<?php

namespace Tests\Feature;

use App\Mail\PasswordResetCodeMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\PendingMail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Mail::fake();
    }

    public function test_guests_can_view_both_password_reset_pages(): void
    {
        $this->get(route('password.request'))
            ->assertOk()
            ->assertSee(__('site.password_reset.forgot_title'));

        $this->get(route('password.reset'))
            ->assertOk()
            ->assertSee(__('site.password_reset.reset_title'));
    }

    public function test_authenticated_users_are_redirected_away_from_both_password_reset_pages(): void
    {
        $this->actingAs($this->createUser());

        $this->get(route('password.request'))->assertRedirect('/');
        $this->get(route('password.reset'))->assertRedirect('/');
    }

    public function test_active_user_request_sends_mail_and_stores_only_a_hash(): void
    {
        $user = $this->createUser();

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertRedirect(route('password.reset'))
            ->assertSessionHas('status', __('site.password_reset.request_status'))
            ->assertSessionMissing('reset_code');

        $code = $this->sentCodeFor($user->email);
        $row = $this->resetRow($user->email);

        $this->assertMatchesRegularExpression('/\A[0-9]{6}\z/', $code);
        $this->assertNotSame($code, $row->token);
        $this->assertTrue(Hash::check($code, $row->token));
        $this->assertNotNull($row->created_at);
    }

    public function test_unknown_email_sends_no_mail_and_gets_the_generic_response(): void
    {
        $this->post(route('password.email'), ['email' => 'missing@example.test'])
            ->assertRedirect(route('password.reset'))
            ->assertSessionHas('status', __('site.password_reset.request_status'));

        Mail::assertNothingSent();
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'missing@example.test']);
    }

    public function test_disabled_account_sends_no_mail_and_gets_the_generic_response(): void
    {
        $user = $this->createUser(status: 'disabled');

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertRedirect(route('password.reset'))
            ->assertSessionHas('status', __('site.password_reset.request_status'));

        Mail::assertNothingSent();
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
    }

    public function test_request_during_cooldown_does_not_send_or_replace_existing_code(): void
    {
        $user = $this->createUser();
        $this->storeCode($user->email, '123456');
        $before = $this->resetRow($user->email);

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertSessionHas('status', __('site.password_reset.request_status'));

        $after = $this->resetRow($user->email);
        Mail::assertNothingSent();
        $this->assertSame($before->token, $after->token);
        $this->assertSame($before->created_at, $after->created_at);
    }

    public function test_request_after_cooldown_replaces_and_invalidates_previous_code(): void
    {
        $user = $this->createUser();
        $this->storeCode($user->email, '123456', now()->subSeconds(61));

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertSessionHas('status', __('site.password_reset.request_status'));

        $newCode = $this->sentCodeFor($user->email);
        $row = $this->resetRow($user->email);
        $this->assertFalse(Hash::check('123456', $row->token));
        $this->assertTrue(Hash::check($newCode, $row->token));

        $this->post(route('password.update'), $this->resetData($user->email, '123456'))
            ->assertSessionHasErrors(['code' => __('site.password_reset.invalid_code')]);
    }

    public function test_initial_mail_failure_is_hidden_and_persists_nothing(): void
    {
        $user = $this->createUser();
        $this->fakeMailFailure($user->email);

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertRedirect(route('password.reset'))
            ->assertSessionHas('status', __('site.password_reset.request_status'));

        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
    }

    public function test_replacement_mail_failure_preserves_previous_hash_created_at_and_usable_code(): void
    {
        $user = $this->createUser();
        $this->storeCode($user->email, '123456', now()->subSeconds(61));
        $before = $this->resetRow($user->email);
        $this->fakeMailFailure($user->email);

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertSessionHas('status', __('site.password_reset.request_status'));

        $after = $this->resetRow($user->email);
        $this->assertSame($before->token, $after->token);
        $this->assertSame($before->created_at, $after->created_at);

        $this->post(route('password.update'), $this->resetData($user->email, '123456'))
            ->assertRedirect(route('login'));
    }

    public function test_forgot_password_request_is_throttled_after_three_requests(): void
    {
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $this->post(route('password.email'), ['email' => 'missing@example.test'])
                ->assertRedirect(route('password.reset'));
        }

        $this->post(route('password.email'), ['email' => 'missing@example.test'])
            ->assertTooManyRequests();
    }

    public function test_email_case_and_whitespace_cannot_bypass_request_throttle(): void
    {
        foreach (['Reader@Example.test', ' reader@example.test ', 'READER@EXAMPLE.TEST'] as $email) {
            $this->post(route('password.email'), ['email' => $email])
                ->assertRedirect(route('password.reset'));
        }

        $this->post(route('password.email'), ['email' => 'reader@example.test'])
            ->assertTooManyRequests();
    }

    public function test_valid_code_resets_password_rotates_token_deletes_code_and_does_not_log_in(): void
    {
        $user = $this->createUser(password: 'old-password', rememberToken: 'old-remember-token');
        $this->storeCode($user->email, '123456');

        $this->post(route('password.update'), $this->resetData($user->email, '123456', 'new-password'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', __('site.password_reset.success'));

        $user->refresh();
        $this->assertFalse(Hash::check('old-password', $user->password));
        $this->assertTrue(Hash::check('new-password', $user->password));
        $this->assertNotSame('old-remember-token', $user->remember_token);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
        $this->assertGuest();
    }

    public function test_new_password_authenticates_and_old_password_does_not(): void
    {
        $user = $this->createUser(password: 'old-password');
        $this->storeCode($user->email, '123456');
        $this->post(route('password.update'), $this->resetData($user->email, '123456', 'new-password'));

        $this->post(route('login.store'), ['login' => $user->email, 'password' => 'old-password'])
            ->assertSessionHasErrors(['login' => __('site.login.failed')]);
        $this->post(route('login.store'), ['login' => $user->email, 'password' => 'new-password'])
            ->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
    }

    public function test_password_reset_does_not_change_email_verification_fields(): void
    {
        $user = $this->createUser();
        $user->email_verified_at = now()->subDay();
        $user->verification_code = Hash::make('654321');
        $user->verification_expiry = now()->addMinutes(5);
        $user->save();
        $expectedVerifiedAt = $user->email_verified_at->toDateTimeString();
        $expectedVerificationCode = $user->verification_code;
        $expectedVerificationExpiry = $user->verification_expiry->toDateTimeString();
        $this->storeCode($user->email, '123456');

        $this->post(route('password.update'), $this->resetData($user->email, '123456'));

        $user->refresh();
        $this->assertSame($expectedVerifiedAt, $user->email_verified_at->toDateTimeString());
        $this->assertSame($expectedVerificationCode, $user->verification_code);
        $this->assertSame($expectedVerificationExpiry, $user->verification_expiry->toDateTimeString());
    }

    public function test_wrong_expired_unknown_and_disabled_attempts_share_the_generic_error(): void
    {
        $active = $this->createUser(email: 'active@example.test', username: 'active');
        $expired = $this->createUser(email: 'expired@example.test', username: 'expired');
        $disabled = $this->createUser(email: 'disabled@example.test', username: 'disabled', status: 'disabled');
        $this->storeCode($active->email, '123456');
        $this->storeCode($expired->email, '123456', now()->subMinutes(11));
        $this->storeCode($disabled->email, '123456');

        foreach ([
            [$active->email, '000000'],
            [$expired->email, '123456'],
            ['missing@example.test', '123456'],
            [$disabled->email, '123456'],
        ] as [$email, $code]) {
            $this->post(route('password.update'), $this->resetData($email, $code))
                ->assertRedirect(route('password.reset'))
                ->assertSessionHasErrors(['code' => __('site.password_reset.invalid_code')]);
        }
    }

    public function test_used_code_cannot_be_reused(): void
    {
        $user = $this->createUser();
        $this->storeCode($user->email, '123456');

        $this->post(route('password.update'), $this->resetData($user->email, '123456'))
            ->assertRedirect(route('login'));

        $this->post(route('password.update'), $this->resetData($user->email, '123456', 'another-password'))
            ->assertSessionHasErrors(['code' => __('site.password_reset.invalid_code')]);
    }

    public function test_five_failed_attempts_delete_code_and_correct_code_then_fails(): void
    {
        $user = $this->createUser();
        $this->storeCode($user->email, '123456');

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->post(route('password.update'), $this->resetData($user->email, '000000'))
                ->assertSessionHasErrors(['code' => __('site.password_reset.invalid_code')]);
        }

        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
        $this->post(route('password.update'), $this->resetData($user->email, '123456'))
            ->assertSessionHasErrors(['code' => __('site.password_reset.invalid_code')]);
    }

    public function test_newly_issued_code_clears_previous_failed_attempt_counter(): void
    {
        $user = $this->createUser();
        $this->storeCode($user->email, '123456', now()->subSeconds(61));

        for ($attempt = 1; $attempt <= 4; $attempt++) {
            $this->post(route('password.update'), $this->resetData($user->email, '000000'))
                ->assertSessionHasErrors('code');
        }

        $this->assertSame(4, RateLimiter::attempts($this->attemptKey($user->email)));
        $this->post(route('password.email'), ['email' => $user->email]);
        $this->assertSame(0, RateLimiter::attempts($this->attemptKey($user->email)));

        $newCode = $this->sentCodeFor($user->email);
        $this->post(route('password.update'), $this->resetData($user->email, $newCode))
            ->assertRedirect(route('login'));
    }

    public function test_password_rules_require_eight_characters_and_confirmation(): void
    {
        $user = $this->createUser();
        $this->storeCode($user->email, '123456');

        $this->post(route('password.update'), $this->resetData($user->email, '123456', 'short'))
            ->assertSessionHasErrors('password');

        $data = $this->resetData($user->email, '123456');
        $data['password_confirmation'] = 'different-password';
        $this->post(route('password.update'), $data)->assertSessionHasErrors('password');
    }

    public function test_reset_post_is_throttled_after_ten_requests(): void
    {
        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $this->post(route('password.update'), $this->resetData('missing@example.test', '123456'))
                ->assertRedirect(route('password.reset'));
        }

        $this->post(route('password.update'), $this->resetData('missing@example.test', '123456'))
            ->assertTooManyRequests();
    }

    public function test_reset_form_never_repopulates_code_or_passwords(): void
    {
        $this->from(route('password.reset'))->post(route('password.update'), [
            'email' => 'reader@example.test',
            'code' => '123456',
            'password' => 'new-password',
            'password_confirmation' => 'different-password',
        ]);

        $this->get(route('password.reset'))
            ->assertSee('value="reader@example.test"', false)
            ->assertDontSee('value="123456"', false)
            ->assertDontSee('value="new-password"', false);
    }

    private function createUser(
        string $email = 'reader@example.test',
        string $username = 'reader',
        string $password = 'old-password',
        string $status = 'active',
        string $rememberToken = 'remember-token',
    ): User {
        $user = new User;
        $user->name = 'Test Reader';
        $user->username = $username;
        $user->email = $email;
        $user->password = $password;
        $user->role = 'client';
        $user->account_status = $status;
        $user->email_verified_at = now();
        $user->remember_token = $rememberToken;
        $user->save();

        return $user;
    }

    private function storeCode(string $email, string $code, mixed $createdAt = null): void
    {
        $this->app['db']->table('password_reset_tokens')->insert([
            'email' => $email,
            'token' => Hash::make($code),
            'created_at' => $createdAt ?? now(),
        ]);
    }

    private function resetRow(string $email): object
    {
        return $this->app['db']->table('password_reset_tokens')->where('email', $email)->firstOrFail();
    }

    /** @return array<string, string> */
    private function resetData(string $email, string $code, string $password = 'new-password'): array
    {
        return [
            'email' => $email,
            'code' => $code,
            'password' => $password,
            'password_confirmation' => $password,
        ];
    }

    private function sentCodeFor(string $email): string
    {
        $code = null;

        Mail::assertSent(PasswordResetCodeMail::class, function (PasswordResetCodeMail $mail) use ($email, &$code): bool {
            if (! $mail->hasTo($email)) {
                return false;
            }

            $code = $mail->resetCode;

            return true;
        });

        $this->assertIsString($code);

        return $code;
    }

    private function fakeMailFailure(string $email): void
    {
        $pendingMail = Mockery::mock(PendingMail::class);
        $pendingMail->shouldReceive('send')
            ->once()
            ->with(Mockery::type(PasswordResetCodeMail::class))
            ->andThrow(new RuntimeException('Synthetic transport failure containing private details.'));

        Mail::shouldReceive('to')->once()->with($email)->andReturn($pendingMail);
    }

    private function attemptKey(string $email): string
    {
        return 'password-reset-code:'.hash('sha256', strtolower(trim($email)));
    }
}
