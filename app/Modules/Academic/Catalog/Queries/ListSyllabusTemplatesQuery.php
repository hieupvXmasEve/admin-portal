<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Queries;

use App\Models\SyllabusTemplate;
use Illuminate\Pagination\LengthAwarePaginator;

class ListSyllabusTemplatesQuery
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, SyllabusTemplate>
     */
    public function handle(array $filters): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 10);
        $sort = $filters['sort'] ?? 'created_at';
        $direction = $filters['direction'] ?? 'desc';

        $query = SyllabusTemplate::query()
            ->with(['unit'])
            ->withExists([
                'courseOfferings as is_locked_for_editing' => static function ($query): void {
                    $query->whereHas('classSessions', static function ($sessionQuery): void {
                        $sessionQuery->where('status', 'completed');
                    });
                },
            ]);

        if (($filters['unit_id'] ?? null) !== null && $filters['unit_id'] !== 'all') {
            $query->where('unit_id', (int) $filters['unit_id']);
        }

        if (isset($filters['is_active']) && ! in_array($filters['is_active'], ['all', ''], true)) {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(static function ($query) use ($search): void {
                $query->where('title', 'like', "%{$search}%")
                    ->orWhere('version', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('unit', static function ($unitQuery) use ($search): void {
                        $unitQuery->where('code', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%");
                    });
            });
        }

        if ($sort === 'created_at' && $direction === 'desc') {
            $query->orderByDesc('is_default')
                ->orderByDesc('is_active')
                ->orderByDesc('created_at');
        } else {
            $query->orderBy($sort, $direction);
        }

        return $query->paginate($perPage)->withQueryString();
    }
}
