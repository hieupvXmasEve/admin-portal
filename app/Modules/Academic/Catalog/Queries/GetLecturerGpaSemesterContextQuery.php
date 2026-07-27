<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Queries;

use App\Models\Semester;

/**
 * Semester resolution for the lecturer-GPA report: active semester, falling
 * back to the most recent one, plus lookup/listing, so FacultyWorkforce
 * reaches Semester through a Catalog-owned seam instead of importing the
 * shared model directly.
 */
class GetLecturerGpaSemesterContextQuery
{
    public function resolveActiveOrLatestId(): ?string
    {
        $activeSemester = Semester::getActiveSemester();
        if ($activeSemester !== null) {
            return (string) $activeSemester->id;
        }

        $latestSemester = Semester::query()
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->first();

        return $latestSemester ? (string) $latestSemester->id : null;
    }

    /**
     * @return array{id: int, name: string, code: string|null}|null
     */
    public function find(int $semesterId): ?array
    {
        $semester = Semester::query()->find($semesterId);

        if ($semester === null) {
            return null;
        }

        return [
            'id' => $semester->id,
            'name' => $semester->name,
            'code' => $semester->code,
        ];
    }

    /**
     * @return array<int, array{id: int, name: string, code: string|null}>
     */
    public function listAll(): array
    {
        return Semester::query()
            ->select('id', 'name', 'code')
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Semester $semester): array => [
                'id' => $semester->id,
                'name' => $semester->name,
                'code' => $semester->code,
            ])
            ->all();
    }
}
