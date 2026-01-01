<?php

namespace App\Modules\Identity\Actions;

use App\Models\User;
use App\Shared\DTO\SocialUserData;
use Illuminate\Support\Facades\Auth;
use Exception;

class AuthenticateSocialUserAction
{
    /**
     * @throws Exception
     */
    public static function run(SocialUserData $data): User
    {
        $user = User::where('email', $data->email)->first();

        if (!$user) {
            throw new Exception('Email is not registered');
        }

        // Check if user is staff of this system
        if (!$user->isStaff()) {
            throw new Exception('Only staff members can log in using Google.');
        }

        // Check user status
        if (!$user->isActive() && !$user->isVerified()) {
            throw new Exception("Your account is {$user->status}. Please contact administrator.");
        }

        Auth::login($user);
        
        return $user;
    }
}
