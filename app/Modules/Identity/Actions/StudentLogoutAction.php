<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use Illuminate\Http\Request;

class StudentLogoutAction
{
    public static function run(Request $request): void
    {
        $request->user()->currentAccessToken()->delete();
    }
}
