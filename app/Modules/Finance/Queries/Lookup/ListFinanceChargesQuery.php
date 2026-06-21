<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Lookup;

use App\Models\FinanceCharge;
use App\Modules\Finance\Support\FinanceSemesterContextResolver;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

final class ListFinanceChargesQuery
{
    /** Columns the client may sort by (anything else falls back to the default). */
    private const SORTABLE = ['amount', 'effective_at', 'status', 'charge_type', 'created_at'];

    /**
     * @return array{items: LengthAwarePaginator}
     */
    public function handle(Request $request, ?int $studentId = null): array
    {
        $query = FinanceCharge::query()->with(['student', 'semester', 'createdBy']);

        $resolvedStudentId = $studentId ?? ($request->filled('student_id') ? (int) $request->input('student_id') : null);
        if ($resolvedStudentId !== null) {
            $query->where('student_id', $resolvedStudentId);
        }

        if ($request->filled('search')) {
            $term = (string) $request->input('search');
            $query->where(function ($q) use ($term) {
                $q->where('description', 'like', "%{$term}%")
                    ->orWhereHas('student', fn ($s) => $s->where('full_name', 'like', "%{$term}%")
                        ->orWhere('student_id', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%"));
            });
        }

        $semesterId = $request->filled('semester_id')
            ? (int) $request->input('semester_id')
            : FinanceSemesterContextResolver::selectedId();

        if ($semesterId !== null) {
            $query->where('semester_id', $semesterId);
        }

        $chargeType = $request->input('charge_type');
        if ($request->filled('charge_type') && $chargeType !== 'all') {
            $query->where('charge_type', $chargeType);
        }

        $status = $request->input('status');
        if ($request->filled('status') && $status !== 'all') {
            $query->where('status', $status);
        }

        $sort = (string) $request->input('sort', '');
        $direction = $request->input('direction') === 'asc' ? 'asc' : 'desc';
        if (in_array($sort, self::SORTABLE, true)) {
            $query->orderBy($sort, $direction);
        } else {
            $query->orderByDesc('effective_at');
        }

        $perPage = (int) $request->input('per_page', 20);

        return ['items' => $query->paginate($perPage)->withQueryString()];
    }
}
