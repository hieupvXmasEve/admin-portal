<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Policies;

use App\Models\Program;
use Illuminate\Contracts\Auth\Access\Authorizable;

class ProgramPolicy
{
    public function view(Authorizable $user, Program $program): bool
    {
        return $user->can('view_program');
    }

    public function update(Authorizable $user, Program $program): bool
    {
        return $user->can('edit_program');
    }

    public function delete(Authorizable $user, Program $program): bool
    {
        return $user->can('delete_program');
    }
}
