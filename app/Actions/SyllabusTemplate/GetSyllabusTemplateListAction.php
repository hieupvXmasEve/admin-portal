<?php

declare(strict_types=1);

namespace App\Actions\SyllabusTemplate;

use App\Models\SyllabusTemplate;
use Illuminate\Pagination\LengthAwarePaginator;

class GetSyllabusTemplateListAction
{
    /**
     * Get a paginated list of syllabus templates.
     */
    public function execute(array $filters = []): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 10);
        $sort = $filters['sort'] ?? 'created_at';
        $direction = $filters['direction'] ?? 'desc';

        $query = SyllabusTemplate::query()->with(['unit']);

        if (! empty($filters['unit_id']) && $filters['unit_id'] !== 'all') {
            $query->where('unit_id', (int) $filters['unit_id']);
        }

        if (isset($filters['is_active']) && $filters['is_active'] !== 'all' && $filters['is_active'] !== '') {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('version', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('unit', function ($uq) use ($search) {
                        $uq->where('code', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%");
                    });
            });
        }

        // Apply custom sorting or default
        if ($sort === 'created_at' && $direction === 'desc') {
            // Default project sorting
            $query->orderByDesc('is_default')
                ->orderByDesc('is_active')
                ->orderByDesc('created_at');
        } else {
            $query->orderBy($sort, $direction);
        }

        return $query->paginate($perPage)->withQueryString();
    }
}
