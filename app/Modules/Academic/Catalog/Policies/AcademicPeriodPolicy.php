<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Policies;

use App\Models\Semester;
use Illuminate\Contracts\Auth\Access\Authorizable;

class AcademicPeriodPolicy
{
    public function update(Authorizable $user, Semester $academicPeriod): bool
    {
        return ! $academicPeriod->isArchived() && $user->can('edit_semester');
    }

    public function delete(Authorizable $user, Semester $academicPeriod): bool
    {
        return $academicPeriod->canDelete() && $user->can('delete_semester');
    }
}
