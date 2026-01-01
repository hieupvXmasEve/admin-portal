<?php

namespace App\Modules\Identity\Actions;

use App\Models\User;
use App\Modules\Identity\IdentityContext;
use Illuminate\Support\Facades\Session;

class SetCurrentCampusAction
{
    public static function run(User $user, int $campusId): void
    {
        // Save to session
        Session::put('current_campus_id', $campusId);
        Session::save();

        // Refresh IdentityContext
        app(IdentityContext::class)->init();
    }
}
