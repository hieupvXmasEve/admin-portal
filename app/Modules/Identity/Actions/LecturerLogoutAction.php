<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use Illuminate\Http\Request;

class LecturerLogoutAction
{
    public static function run(Request $request): void
    {
        if ($request->user()) {
            $request->user()->currentAccessToken()->delete();
        }
    }
}
