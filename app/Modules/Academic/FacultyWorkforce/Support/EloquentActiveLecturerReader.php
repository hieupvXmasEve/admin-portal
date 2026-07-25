<?php

declare(strict_types=1);

namespace App\Modules\Academic\FacultyWorkforce\Support;

use App\Models\Lecture;
use App\Shared\Contracts\Identity\ActiveLecturerReader;

final class EloquentActiveLecturerReader implements ActiveLecturerReader
{
    public function all(): array
    {
        return Lecture::query()
            ->active()
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name'])
            ->map(static fn (Lecture $lecturer): array => [
                'id' => (int) $lecturer->id,
                'first_name' => (string) $lecturer->first_name,
                'last_name' => (string) $lecturer->last_name,
            ])
            ->all();
    }
}
