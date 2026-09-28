<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class LoginLogoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_user_can_log_in_with_email_and_correct_password(): void
    {
        $user = $this->createUser();

        $this->post(route('login.store'), $this->loginData($user->email))
            ->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
    }

    public function test_user_can_log_in_with_username_and_correct_password(): void
    {
        $user = $this->createUser();

        $this->post(route('login.store'), $this->loginData($user->username))
            ->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
    }

    public function test_email_shaped_username_is_resolved_as_a_username(): void
    {
        $user = $this->createUser(
            email: 'reader@example.test',
            username: 'another@example.test',
        );

        $this->post(route('login.store'), $this->loginData('another@example.test'))
            ->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
    }

    public function test_wrong_password_returns_generic_error(): void
    {
        $user = $this->createUser();

        $this->post(route('login.store'), $this->loginData($user->email, 'wrong-password'))
            ->assertSessionHasErrors(['login' => __('site.login.failed')]);

        $this->assertGuest();
    }

    public function test_unknown_user_and_wrong_password_return_the_same_error(): void
    {
        $user = $this->createUser();

        $wrongPassword = $this->post(
            route('login.store'),
            $this->loginData($user->email, 'wrong-password'),
        );
        $unknownUser = $this->post(
            route('login.store'),
            $this->loginData('missing@example.test', 'wrong-password'),
        );

        $wrongPassword->assertSessionHasErrors(['login' => __('site.login.failed')]);
        $unknownUser->assertSessionHasErrors(['login' => __('site.login.failed')]);
    }

    public function test_disabled_account_is_rejected_before_authentication_with_generic_error(): void
    {
        $user = $this->createUser(status: 'disabled');

        $this->post(route('login.store'), $this->loginData($user->email))
            ->assertSessionHasErrors(['login' => __('site.login.failed')]);

        $this->assertGuest();
    }

    public function test_remember_me_uses_laravels_persistent_login(): void
    {
        $user = $this->createUser();

        $response = $this->post(route('login.store'), [
            ...$this->loginData($user->email),
            'remember' => '1',
        ]);

        $response->assertRedirect('/')
            ->assertCookie(Auth::guard('web')->getRecallerName());
        $this->assertAuthenticatedAs($user);
        $this->assertNotEmpty($user->fresh()->getRememberToken());
    }

    public function test_logout_invalidates_session_and_redirects_to_home(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);
        $this->withSession(['logout_probe' => 'present']);
        $oldToken = session()->token();

        $response = $this->post(route('logout'));

        $response->assertRedirect('/')
            ->assertSessionMissing('logout_probe');
        $this->assertGuest();
        $this->assertNotSame($oldToken, $response->getSession()->token());
    }

    public function test_unverified_user_can_logout_and_verification_enforcement_remains_active(): void
    {
        $user = $this->createUser(verified: false);
        $this->actingAs($user);

        $this->get('/test-layout')->assertRedirect(route('verification.notice'));

        $this->post(route('logout'))
            ->assertRedirect('/');
        $this->assertGuest();

        $this->actingAs($user->fresh());
        $this->get('/test-layout')->assertRedirect(route('verification.notice'));
    }

    public function test_unverified_user_can_log_in_then_is_redirected_by_verification_middleware(): void
    {
        $user = $this->createUser(verified: false);

        $this->post(route('login.store'), $this->loginData($user->email))
            ->assertRedirect('/');
        $this->assertAuthenticatedAs($user);

        $this->get('/')->assertRedirect(route('verification.notice'));
    }

    public function test_login_locks_out_after_five_failed_attempts(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->post(route('login.store'), $this->loginData('missing@example.test', 'wrong-password'))
                ->assertSessionHasErrors(['login' => __('site.login.failed')]);
        }

        $this->post(route('login.store'), $this->loginData('missing@example.test', 'wrong-password'))
            ->assertSessionHasErrors(['login' => __('site.login.throttled')]);
    }

    public function test_case_and_whitespace_variations_share_the_login_rate_limit(): void
    {
        $variations = [
            'Reader@Example.test',
            ' reader@example.test ',
            'READER@EXAMPLE.TEST',
            'Reader@example.TEST',
            'reader@example.test',
        ];

        foreach ($variations as $login) {
            $this->post(route('login.store'), $this->loginData($login, 'wrong-password'))
                ->assertSessionHasErrors(['login' => __('site.login.failed')]);
        }

        $this->post(route('login.store'), $this->loginData('reader@example.test', 'wrong-password'))
            ->assertSessionHasErrors(['login' => __('site.login.throttled')]);
    }

    public function test_successful_login_clears_failed_attempts_for_that_identifier_and_ip(): void
    {
        $user = $this->createUser();

        for ($attempt = 1; $attempt <= 4; $attempt++) {
            $this->post(route('login.store'), $this->loginData($user->email, 'wrong-password'))
                ->assertSessionHasErrors(['login' => __('site.login.failed')]);
        }

        $this->post(route('login.store'), $this->loginData($user->email))
            ->assertRedirect('/');
        $this->post(route('logout'))->assertRedirect('/');

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->post(route('login.store'), $this->loginData($user->email, 'wrong-password'))
                ->assertSessionHasErrors(['login' => __('site.login.failed')]);
        }

        $this->post(route('login.store'), $this->loginData($user->email, 'wrong-password'))
            ->assertSessionHasErrors(['login' => __('site.login.throttled')]);
    }

    private function createUser(
        string $email = 'reader@example.test',
        string $username = 'reader',
        string $password = 'correct-horse-battery',
        string $status = 'active',
        bool $verified = true,
    ): User {
        $user = new User;
        $user->name = 'Test Reader';
        $user->email = $email;
        $user->username = $username;
        $user->password = $password;
        $user->role = 'client';
        $user->account_status = $status;
        $user->email_verified_at = $verified ? now() : null;
        $user->save();

        return $user;
    }

    /**
     * @return array<string, string>
     */
    private function loginData(string $login, string $password = 'correct-horse-battery'): array
    {
        return [
            'login' => $login,
            'password' => $password,
        ];
    }
}
