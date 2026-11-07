<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Resources\Api\V1\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Signs the user in and answers with the user; the session cookie carries the sign-in from then on.
     */
    public function login(LoginRequest $request): UserResource
    {
        if (! Auth::attempt($request->validated())) {
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        // A new session id, so an id planted in the browser before signing in is not the signed-in one.
        $request->session()->regenerate();

        return new UserResource($request->user());
    }

    /**
     * Signs the user out and ends the session.
     */
    public function logout(Request $request): Response
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }

    /**
     * Returns the signed-in user.
     */
    public function user(Request $request): UserResource
    {
        return new UserResource($request->user());
    }
}
