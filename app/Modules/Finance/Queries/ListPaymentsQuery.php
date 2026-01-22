<?php

namespace App\Modules\Finance\Queries;

use App\Models\Payment;
use Illuminate\Http\Request;

class ListPaymentsQuery
{
    public function handle(Request $request)
    {
        $query = Payment::query()
            ->with(['student']);

        // Filter by Search (External Ref, Student: Name, ID, Email)
        if ($request->filled('search')) {
            $term = $request->input('search');
            $query->where(function ($q) use ($term) {
                $q->where('external_ref', 'like', "%{$term}%")
                  ->orWhereHas('student', function ($subQ) use ($term) {
                      $subQ->where('full_name', 'like', "%{$term}%")
                           ->orWhere('student_id', 'like', "%{$term}%")
                           ->orWhere('email', 'like', "%{$term}%");
                  });
            });
        }

        // Filter by Source
        if ($request->filled('source') && $request->input('source') !== 'all') {
            $query->where('source', $request->input('source'));
        }

        // Filter by Status
        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('status', $request->input('status'));
        }

        // Filter by Date Range
        if ($request->filled('date_range')) {
            $dateRange = $request->input('date_range');
            if (is_string($dateRange) && str_contains($dateRange, ',')) {
                [$start, $end] = explode(',', $dateRange);
                $query->whereBetween('paid_at', [$start, $end]);
            } elseif (is_array($dateRange) && count($dateRange) === 2) {
                $query->whereBetween('paid_at', $dateRange);
            }
        }

        // Sorting
        $sort = $request->input('sort', 'paid_at');
        $direction = $request->input('direction', 'desc');
        
        // Allowed sort columns
        if (in_array($sort, ['amount', 'paid_at', 'status', 'created_at'])) {
            $query->orderBy($sort, $direction === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy('paid_at', 'desc');
        }

        return $query->paginate($request->input('per_page', 15))
            ->withQueryString();
    }
}
