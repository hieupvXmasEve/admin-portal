<?php

declare(strict_types=1);

namespace App\Modules\Academic\FacultyWorkforce\Queries;

use App\Models\Lecture;
use Illuminate\Support\Collection;

/**
 * Lecturer picker rows for Academic scheduling screens, so those consumers
 * reach Lecture through its FacultyWorkforce owner instead of importing the
 * shared model directly.
 */
class GetLecturerReferenceOptionsQuery
{
    /**
     * Name-ordered lecturers, optionally narrowed to one campus.
     *
     * @return Collection<int, Lecture>
     */
    public function forCampus(?int $campusId): Collection
    {
        return Lecture::query()
            ->when($campusId, fn ($query) => $query->where('campus_id', $campusId))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name']);
    }
}
