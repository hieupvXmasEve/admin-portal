<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Operations;

use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Support\LifecycleDueItemPredicate;
use Illuminate\Pagination\LengthAwarePaginator;

class ListDueItemsQuery
{
    public function handle(?int $semesterId, ?string $status, ?string $search): LengthAwarePaginator
    {
        $campusId = app()->bound('campus') ? app('campus')->id : null;
        $today = now()->startOfDay();

        $dngQuery = DngPaymentRequest::query()
            ->with(['student:id,student_id,full_name,email,status'])
            ->when($semesterId, fn ($q) => $q->where('semester_id', $semesterId))
            ->where('dng_payment_requests.status', 'pushed_to_dng')
            ->whereNotNull('dng_payment_requests.due_date');

        LifecycleDueItemPredicate::applyActiveCollectionScope($dngQuery, $campusId);

        if (! empty($status)) {
            switch ($status) {
                case 'upcoming':
                    $dngQuery->whereBetween('dng_payment_requests.due_date', [$today->copy()->addDay(), $today->copy()->addDays(7)]);
                    break;
                case 'due_today':
                    $dngQuery->whereDate('dng_payment_requests.due_date', $today);
                    break;
                case 'overdue':
                    $dngQuery->where('dng_payment_requests.due_date', '<', $today);
                    break;
            }
        }

        if (! empty($search)) {
            $dngQuery->where(function ($q) use ($search) {
                $q->where('dng_payment_requests.student_code', 'like', "%{$search}%")
                    ->orWhere('students.full_name', 'like', "%{$search}%")
                    ->orWhere('students.student_id', 'like', "%{$search}%");
            });
        }

        $page = (int) request()->get('page', 1);
        $perPage = 20;

        return $dngQuery
            ->orderBy('dng_payment_requests.due_date')
            ->paginate($perPage, ['*'], 'page', $page)
            ->through(function (DngPaymentRequest $request) use ($today) {
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
    }
}
