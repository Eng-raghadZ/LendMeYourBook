<?php

namespace Tests\Feature;

use App\Mail\EmailVerificationCodeMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class UsernameValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Mail::fake();
    }

    public function test_username_rejects_disallowed_characters_and_formats(): void
    {
        $invalidUsernames = [
            'reader@site',
            'reader@example.test',
            'reader name',
            'reader.name',
            'reader+name',
            "\u{0430}dmin", // Cyrillic small a, not ASCII a.
            "user\u{0661}\u{0662}", // Arabic-Indic digits.
            "reader\u{0640}name", // Tatweel.
            "رغد\u{064E}", // Fatha diacritic.
            "رغد\u{061F}", // Arabic question mark.
            "reader\u{200B}name", // Zero-width space.
            "رغد\u{060C}اسم", // Arabic comma.
            "رغد\u{061B}اسم", // Arabic semicolon.
            'readerΩ', // Greek character.
        ];

        foreach ($invalidUsernames as $username) {
            $response = $this->post(route('register.store'), $this->registrationData($username));

            $response->assertSessionHasErrors([
                'username' => __('site.registration.username_format'),
            ]);
        }

        $this->assertSame(0, User::count());
    }

    public function test_username_accepts_english_arabic_and_supported_ascii_punctuation(): void
    {
        foreach ([
            'raghad',
            'raghad123',
            'raghad_123',
            'raghad-z',
            'رغد_123',
            'رغد-user_123',
        ] as $index => $username) {
            $this->post(route('register.store'), $this->registrationData($username, $index))
                ->assertRedirect(route('verification.notice'));

            if ($index < 5) {
                $this->post(route('logout'))->assertRedirect('/');
            }
        }

        $this->assertSame(6, User::count());
        Mail::assertSent(EmailVerificationCodeMail::class, 6);
    }

    /** @return array<string, string> */
    private function registrationData(string $username, int $index = 0): array
    {
        return [
            'name' => 'Test Reader',
            'username' => $username,
            'email' => "reader{$index}@example.test",
            'password' => 'correct-horse-battery',
            'password_confirmation' => 'correct-horse-battery',
        ];
    }
}
