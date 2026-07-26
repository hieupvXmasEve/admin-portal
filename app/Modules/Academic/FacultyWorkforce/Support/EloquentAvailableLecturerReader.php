<?php

declare(strict_types=1);

namespace App\Modules\Academic\FacultyWorkforce\Support;

use App\Models\Lecture;
use App\Shared\Contracts\Identity\AvailableLecturerReader;

final class EloquentAvailableLecturerReader implements AvailableLecturerReader
{
    public function all(): array
    {
        return Lecture::query()
            ->active()
            ->availableForAssignment()
            ->orderByName()
            ->get(['id', 'first_name', 'last_name', 'email', 'academic_rank'])
            ->map(static fn (Lecture $lecturer): array => [
                'id' => (int) $lecturer->id,
                'first_name' => (string) $lecturer->first_name,
                'last_name' => (string) $lecturer->last_name,
                'email' => (string) $lecturer->email,
                'academic_rank' => $lecturer->academic_rank,
            ])
            ->all();
    }
}
