<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Queries;

use App\Models\CurriculumVersion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class ListCurriculumVersionsQuery
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array{items: LengthAwarePaginator, statistics: array<string, mixed>}
     */
    public function handle(array $filters): array
    {
        $query = $this->applyFilters(
            CurriculumVersion::query()
                ->with(['program', 'specialization', 'effectiveFromSemester'])
                ->withCount('curriculumUnits'),
            $filters,
        );

        $sort = $filters['sort'] ?? 'created_at';
        $direction = $filters['direction'] ?? 'desc';
        match ($sort) {
            'program_name' => $query->join('programs', 'curriculum_versions.program_id', '=', 'programs.id')
                ->orderBy('programs.name', $direction)
                ->select('curriculum_versions.*'),
            'specialization_name' => $query->leftJoin('specializations', 'curriculum_versions.specialization_id', '=', 'specializations.id')
                ->orderBy('specializations.name', $direction)
                ->select('curriculum_versions.*'),
            'units_count' => $query->orderBy('curriculum_units_count', $direction),
            default => $query->orderBy($sort, $direction),
        };

        return [
            'items' => $query->paginate((int) ($filters['per_page'] ?? 15))->withQueryString(),
            'statistics' => $this->statistics($filters),
        ];
    }

    /** @param array<string, mixed> $filters */
    private function statistics(array $filters): array
    {
        $query = $this->applyFilters(CurriculumVersion::query(), $filters);
        $total = (clone $query)->count();
        $active = (clone $query)->whereHas('curriculumUnits')->count();

        return [
            'total_curriculum_versions' => $total,
            'active_versions' => $active,
            'inactive_versions' => $total - $active,
            'by_year' => (clone $query)->selectRaw('YEAR(created_at) as year, COUNT(*) as count')
                ->groupBy('year')->orderByDesc('year')->pluck('count', 'year')->toArray(),
            'by_program' => (clone $query)->with('program')->get()->groupBy('program.name')
                ->map(static fn ($versions): int => $versions->count())->toArray(),
        ];
    }

    /**
     * @param  Builder<CurriculumVersion>  $query
     * @param  array<string, mixed>  $filters
     * @return Builder<CurriculumVersion>
     */
    private function applyFilters(Builder $query, array $filters): Builder
    {
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(static function ($query) use ($search): void {
                $query->where('version_code', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhereHas('program', static fn ($programQuery) => $programQuery->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('specialization', static fn ($specializationQuery) => $specializationQuery->where('name', 'like', "%{$search}%"));
            });
        }

        foreach (['program_id', 'specialization_id'] as $field) {
            if (($filters[$field] ?? null) !== null) {
                $query->where($field, $filters[$field]);
            }
        }

        return $query;
    }
}
