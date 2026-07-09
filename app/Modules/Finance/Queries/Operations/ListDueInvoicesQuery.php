<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Operations;

use App\Modules\Finance\Models\StudentInvoice;
use App\Modules\Finance\Services\SettlementService;
use Illuminate\Pagination\LengthAwarePaginator;

class ListDueInvoicesQuery
{
    public function __construct(
        protected SettlementService $settlementService,
    ) {}

    public function handle(?int $semesterId, ?string $status, ?string $search): LengthAwarePaginator
    {
        $campusId = app('campus')?->id;
        $today = now()->startOfDay();

        $invoicesQuery = StudentInvoice::query()
            ->with(['student:id,student_id,full_name,email'])
            ->when($campusId, fn ($q) => $q->whereHas('student', fn ($sq) => $sq->where('campus_id', $campusId)))
            ->when($semesterId, fn ($q) => $q->where('semester_id', $semesterId))
            ->whereNotIn('status', ['paid', 'cancelled'])
            ->whereNotNull('due_date');

        // Apply status filter
        if (! empty($status)) {
            switch ($status) {
                case 'upcoming':
                    $invoicesQuery->whereBetween('due_date', [$today->copy()->addDay(), $today->copy()->addDays(7)]);
                    break;
                case 'due_today':
                    $invoicesQuery->whereDate('due_date', $today);
                    break;
                case 'overdue':
                    $invoicesQuery->where('due_date', '<', $today);
                    break;
            }
        }

        // Apply search filter
        if (! empty($search)) {
            $invoicesQuery->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhereHas('student', function ($q) use ($search) {
                        $q->where('full_name', 'like', "%{$search}%")
                            ->orWhere('student_id', 'like', "%{$search}%");
                    });
            });
        }

        return $invoicesQuery->orderBy('due_date')
            ->paginate(20)
            ->through(function ($invoice) use ($today) {
                $snapshot = $this->settlementService->deriveInvoiceSnapshot($invoice);
                $dueDate = $invoice->due_date;
                $daysUntilDue = $today->diffInDays($dueDate, false);

                $invoiceStatus = 'upcoming';
                if ($daysUntilDue < 0) {
                    $invoiceStatus = 'overdue';
                } elseif ($daysUntilDue === 0) {
                    $invoiceStatus = 'due_today';
                }

                return [
                    'id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'student_id' => $invoice->student_id,
                    'student_code' => $invoice->student?->student_id,
                    'student_name' => $invoice->student?->full_name,
                    'student_email' => $invoice->student?->email,
                    'total_amount' => $snapshot['net'],
                    'paid_amount' => $snapshot['paid'],
                    'balance' => $snapshot['remaining'],
                    'due_date' => $dueDate->toDateString(),
                    'days_until_due' => $daysUntilDue,
                    'status' => $invoiceStatus,
                    'last_reminder_at' => $invoice->last_reminder_at?->toISOString(),
                ];
            });
    }
}
