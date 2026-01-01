<?php

namespace App\Modules\Identity\Actions;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LogoutAction
{
    public static function run(Request $request): void
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();
    }
}
