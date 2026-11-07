<?php

namespace Tests\Feature\Api\V1\Auth\AuthController;

use App\Models\User;
use Tests\Support\ApiTestCase;

class UserTest extends ApiTestCase
{
    private const string URI = '/api/v1/auth/user';

    public function test_an_anonymous_request_is_refused(): void
    {
        $this->fromTheSpa()->getJson(self::URI)->assertUnauthorized();
    }

    public function test_the_signed_in_user_is_returned_without_secrets(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'web')
            ->fromTheSpa()
            ->getJson(self::URI)
            ->assertOk()
            ->assertExactJson(['data' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email]]);
    }
}
