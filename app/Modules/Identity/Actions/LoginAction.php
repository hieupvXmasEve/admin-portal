<?php

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Http\Requests\Identity\LoginRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginAction
{
    public static function run(LoginRequest $request): void
    {
        $request->ensureIsNotRateLimited();

        if (! Auth::attempt($request->only('email', 'password'), $request->boolean('remember'))) {
            $request->hitRateLimit();

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        $user = Auth::user();

        // Check if user is staff of this system
        if (!$user->isStaff()) {
            Auth::logout();
            $request->hitRateLimit();

            throw ValidationException::withMessages([
                'email' => 'Only staff members can log in to this area.',
            ]);
        }

        // Check user status
        if (!$user->isActive() && !$user->isVerified()) {
            Auth::logout();
            $request->hitRateLimit();

            throw ValidationException::withMessages([
                'email' => "Your account is {$user->status}. Please contact administrator.",
            ]);
        }

        $request->clearRateLimit();
    }
}
