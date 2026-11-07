<?php

namespace Tests\Feature\Api\V1\Auth\AuthController;

use App\Models\User;
use Tests\Support\ApiTestCase;

class LogoutTest extends ApiTestCase
{
    private const string URI = '/api/v1/auth/logout';

    public function test_an_anonymous_request_is_refused(): void
    {
        $this->fromTheSpa()->postJson(self::URI)->assertUnauthorized();
    }

    public function test_signing_out_ends_the_session(): void
    {
        $this->actingAs(User::factory()->create(), 'web');

        $this->fromTheSpa()->postJson(self::URI)->assertNoContent();

        $this->assertGuest('web');
    }
}
