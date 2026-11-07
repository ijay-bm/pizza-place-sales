<?php

namespace Tests\Feature\Api\V1\Auth\AuthController;

use App\Models\User;
use Tests\Support\ApiTestCase;

class LoginTest extends ApiTestCase
{
    private const string URI = '/api/v1/auth/login';

    public function test_valid_credentials_sign_the_user_in(): void
    {
        $user = User::factory()->create(['email' => 'demo@pizza-place-sales.test']);

        $this->fromTheSpa()
            ->postJson(self::URI, ['email' => 'demo@pizza-place-sales.test', 'password' => 'password'])
            ->assertOk()
            ->assertExactJson(['data' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email]]);

        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_a_wrong_password_is_refused_on_the_email_field(): void
    {
        User::factory()->create(['email' => 'demo@pizza-place-sales.test']);

        $this->fromTheSpa()
            ->postJson(self::URI, ['email' => 'demo@pizza-place-sales.test', 'password' => 'not-the-password'])
            ->assertUnprocessable()
            ->assertOnlyJsonValidationErrors(['email']);

        $this->assertGuest('web');
    }

    public function test_missing_credentials_are_refused_by_field(): void
    {
        $this->fromTheSpa()
            ->postJson(self::URI, [])
            ->assertUnprocessable()
            ->assertOnlyJsonValidationErrors(['email', 'password']);
    }

    public function test_the_sixth_attempt_in_a_minute_is_refused(): void
    {
        User::factory()->create(['email' => 'demo@pizza-place-sales.test']);
        $credentials = ['email' => 'demo@pizza-place-sales.test', 'password' => 'not-the-password'];

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->fromTheSpa()->postJson(self::URI, $credentials)->assertUnprocessable();
        }

        $this->fromTheSpa()->postJson(self::URI, $credentials)->assertTooManyRequests();
    }
}
