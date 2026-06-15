<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Lookup;

use App\Models\StudentInvoice;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

final class ListStudentInvoicesQuery
{
    /** Real columns only — appended computed totals are not sortable in SQL. */
    private const SORTABLE = ['invoice_number', 'due_date', 'created_at', 'cached_total_amount', 'cached_paid_amount'];

    /**
     * @return array{items: LengthAwarePaginator}
     */
    public function handle(Request $request): array
    {
        $campusId = $request->user()?->can('view_finance_all_campus')
            ? null
            : app('campus')?->id;

        $query = StudentInvoice::query()->with(['student', 'semester']);

        if ($campusId !== null) {
            $query->forCampus((int) $campusId);
        }

        $query->when($request->filled('semester_id'), fn ($q) => $q->forSemester((int) $request->input('semester_id')));
        $query->when($request->filled('search'), fn ($q) => $q->search((string) $request->input('search')));

        $status = $request->input('status');
        if ($request->filled('status') && $status !== 'all') {
            $query->filterByStatus((string) $status);
        }

        $sort = (string) $request->input('sort', '');
        $direction = $request->input('direction') === 'asc' ? 'asc' : 'desc';
        if (in_array($sort, self::SORTABLE, true)) {
            $query->orderBy($sort, $direction);
        } else {
            $query->latest();
        }

        $invoices = $query->paginate((int) $request->input('per_page', 50))->withQueryString();
        $invoices->getCollection()->each->append(['real_time_status', 'total_amount', 'paid_amount', 'outstanding_balance']);

        return ['items' => $invoices];
    }
}
