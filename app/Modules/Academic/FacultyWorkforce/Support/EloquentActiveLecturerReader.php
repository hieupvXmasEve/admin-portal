<?php

declare(strict_types=1);

namespace App\Modules\Academic\FacultyWorkforce\Support;

use App\Models\Lecture;
use App\Shared\Contracts\Identity\ActiveLecturerReader;
use App\Shared\Contracts\Identity\LecturerReferenceReader;

final class EloquentActiveLecturerReader implements ActiveLecturerReader, LecturerReferenceReader
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
                'display_name' => trim($lecturer->first_name.' '.$lecturer->last_name),
            ])
            ->all();
    }

    public function find(int $lecturerId): ?array
    {
        return Lecture::query()->find($lecturerId)?->toArray();
    }

    public function findMany(array $lecturerIds): array
    {
        return Lecture::query()
            ->whereIn('id', array_values(array_unique($lecturerIds)))
            ->get()
            ->mapWithKeys(static fn (Lecture $lecturer): array => [(int) $lecturer->id => $lecturer->toArray()])
            ->all();
    }
}
