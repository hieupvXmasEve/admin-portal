<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Actions;

use App\Models\CurriculumVersion;
use App\Models\Specialization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final class ManageCurriculumVersionApiAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): CurriculumVersion
    {
        return DB::transaction(fn (): CurriculumVersion => CurriculumVersion::query()->create($data));
    }

    /**
     * @return Collection<int, Specialization>
     */
    public function specializationsForProgram(int $programId): Collection
    {
        return Specialization::query()
            ->where('program_id', $programId)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * @return Collection<int, CurriculumVersion>
     */
    public function versionsForProgram(int $programId): Collection
    {
        return CurriculumVersion::query()
            ->where('program_id', $programId)
            ->orderByDesc('created_at')
            ->get(['id', 'version_code']);
    }

    /**
     * @return array{deleted: list<string>, failed: list<array{version_code: string, reason: string}>}
     */
    public function bulkDelete(array $curriculumVersionIds): array
    {
        return DB::transaction(function () use ($curriculumVersionIds): array {
            $deleted = [];
            $failed = [];

            foreach (CurriculumVersion::query()->whereIn('id', $curriculumVersionIds)->get() as $curriculumVersion) {
                if ($curriculumVersion->curriculumUnits()->exists()) {
                    $failed[] = [
                        'version_code' => $curriculumVersion->version_code,
                        'reason' => 'Has existing curriculum units',
                    ];

                    continue;
                }

                $curriculumVersion->delete();
                $deleted[] = $curriculumVersion->version_code;
            }

            return compact('deleted', 'failed');
        });
    }

    public function deleteIfEmpty(CurriculumVersion $curriculumVersion): bool
    {
        if ($curriculumVersion->curriculumUnits()->exists()) {
            return false;
        }

        DB::transaction(fn () => $curriculumVersion->delete());

        return true;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{filename: string, total_records: int}
     */
    public function export(array $filters): array
    {
        $query = CurriculumVersion::query()
            ->with(['program', 'specialization', 'effectiveFromSemester', 'curriculumUnits.unit'])
            ->withCount('curriculumUnits');

        $this->applyFilters($query, $filters);

        return [
            'filename' => 'curriculum_versions_'.now()->format('Y_m_d_H_i_s').'.xlsx',
            'total_records' => $query->count(),
        ];
    }

    /**
     * @param  Builder<CurriculumVersion>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters($query, array $filters): void
    {
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($query) use ($search): void {
                $query->where('version_code', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhereHas('program', fn ($programQuery) => $programQuery->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('specialization', fn ($specializationQuery) => $specializationQuery->where('name', 'like', "%{$search}%"));
            });
        }

        if (isset($filters['program_id'])) {
            $query->where('program_id', $filters['program_id']);
        }

        if (isset($filters['specialization_id'])) {
            $query->where('specialization_id', $filters['specialization_id']);
        }
    }
}
