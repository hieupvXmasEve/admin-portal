<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Egc;

use App\Models\EgcBlock;
use Illuminate\Pagination\LengthAwarePaginator;

class ListEgcBlockResultsQuery
{
    public function handle(int $semesterId, array $filters = [], ?int $campusId = null): LengthAwarePaginator
    {
        return EgcBlock::where('semester_id', $semesterId)
            ->when($campusId !== null, fn ($query) => $query->whereHas('student', fn ($studentQuery) => $studentQuery->where('campus_id', $campusId)))
            ->with(['student:id,full_name,student_id', 'semester:id,name'])
            ->when(($filters['search'] ?? '') !== '', function ($q) use ($filters) {
                $q->whereHas('student', fn ($sq) => $sq->where('full_name', 'like', "%{$filters['search']}%")
                    ->orWhere('student_id', 'like', "%{$filters['search']}%"));
            })
            ->when(($filters['result'] ?? 'all') !== 'all', fn ($q) => $q->where('result', $filters['result']))
            ->orderBy('student_id')
            ->orderBy('block_number')
            ->paginate($filters['per_page'] ?? 50)
            ->withQueryString();
    }
}
