<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Operations;

use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use Illuminate\Pagination\LengthAwarePaginator;

class ListDueItemsQuery
{
    // No dependencies needed for DNG-only query

    public function handle(?int $semesterId, ?string $status, ?string $search): LengthAwarePaginator
    {
        $campusId = app()->bound('campus') ? app('campus')->id : null;
        $today = now()->startOfDay();

        // Get DNG Payment Requests
        $dngQuery = DngPaymentRequest::query()
            ->with(['student:id,student_id,full_name,email,status'])
            ->when($campusId, fn ($q) => $q->whereHas('student', fn ($sq) => $sq->where('campus_id', $campusId)))
            ->when($semesterId, fn ($q) => $q->where('semester_id', $semesterId))
            ->where('status', 'pushed_to_dng')
            ->whereNotNull('due_date');

        // Apply status filter to DNG
        if (! empty($status)) {
            switch ($status) {
                case 'upcoming':
                    $dngQuery->whereBetween('due_date', [$today->copy()->addDay(), $today->copy()->addDays(7)]);
                    break;
                case 'due_today':
                    $dngQuery->whereDate('due_date', $today);
                    break;
                case 'overdue':
                    $dngQuery->where('due_date', '<', $today);
                    break;
            }
        }

        // Apply search filter to DNG
        if (! empty($search)) {
            $dngQuery->where(function ($q) use ($search) {
                $q->where('student_code', 'like', "%{$search}%")
                    ->orWhereHas('student', function ($q) use ($search) {
                        $q->where('full_name', 'like', "%{$search}%")
                            ->orWhere('student_id', 'like', "%{$search}%");
                    });
            });
        }

        $dngRequests = $dngQuery->get()->map(function ($request) use ($today) {
            $dueDate = $request->due_date;
            $daysUntilDue = $today->diffInDays($dueDate, false);

            $requestStatus = 'upcoming';
            if ($daysUntilDue < 0) {
                $requestStatus = 'overdue';
            } elseif ($daysUntilDue === 0) {
                $requestStatus = 'due_today';
            }

            return [
                'id' => $request->id,
                'type' => 'dng_request',
                'invoice_number' => 'DNG-'.$request->id,
                'student_id' => $request->student_id,
                'student_code' => $request->student?->student_id,
                'student_name' => $request->student?->full_name,
                'student_email' => $request->student?->email,
                'total_amount' => (float) $request->amount,
                'paid_amount' => 0.0,
                'balance' => (float) $request->amount,
                'due_date' => $dueDate->toDateString(),
                'days_until_due' => $daysUntilDue,
                'status' => $requestStatus,
                'student_status_label' => $request->student?->status_label,
                'student_status_color' => $request->student?->status_color,
                'last_reminder_at' => $request->last_reminder_at,
            ];
        });

        // Only DNG requests - no invoices
        $allItems = $dngRequests->sortBy('due_date')->values();

        // Manual pagination
        $page = request()->get('page', 1);
        $perPage = 20;
        $total = $allItems->count();
        $items = $allItems->forPage($page, $perPage);

        return new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'pageName' => 'page',
            ]
        );
    }
}
