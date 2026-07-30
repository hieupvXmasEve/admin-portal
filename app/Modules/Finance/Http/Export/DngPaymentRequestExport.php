<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Export;

use App\Models\Campus;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Dng\Models\DngWebhookEvent;
use App\Shared\Contracts\StudentRegistry\DTO\StudentReference;
use App\Shared\Contracts\StudentRegistry\StudentReferenceReader;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Reconciliation export: one row per DngPaymentRequest with its lifecycle
 * status, the validated DNG ref bound onto the request, and the raw ref from
 * the latest webhook callback (which can exist even when the request never
 * bound, e.g. a checksum-mismatched or orphan callback).
 */
final class DngPaymentRequestExport implements FromQuery, WithHeadings, WithMapping
{
    /** @var array<int, StudentReference> */
    private array $studentReferenceMap = [];

    /** @var array<int, string> dng_payment_request_id => latest webhook's raw dng_payment_id */
    private array $latestWebhookRefByRequest = [];

    public function __construct(
        private readonly array $filters,
        private readonly StudentReferenceReader $studentReferenceReader,
    ) {}

    public function query(): Builder
    {
        $campus = app()->bound('campus') ? app('campus') : null;
        $campusId = $campus instanceof Campus && $campus->id !== null ? (int) $campus->id : null;

        $query = DngPaymentRequest::query()->with(['semester:id,name,code', 'payment:id,status,amount,paid_at,method']);

        if ($campusId !== null) {
            $query->whereIn('student_id', $this->studentReferenceReader->idsForCampus($campusId));
        }

        $search = trim((string) ($this->filters['search'] ?? ''));
        if ($search !== '') {
            $matchingStudentIds = $this->studentReferenceReader->idsMatchingSearch($search, $campusId);
            $query->where(fn (Builder $builder) => $builder
                ->where('student_code', 'like', "%{$search}%")
                ->orWhere('item_id', 'like', "%{$search}%")
                ->orWhere('dng_payment_id', 'like', "%{$search}%")
                ->orWhere('dng_transaction_id', 'like', "%{$search}%")
                ->orWhereIn('student_id', $matchingStudentIds));
        }

        if (filled($this->filters['status'] ?? null)) {
            $query->where('status', $this->filters['status']);
        }

        if (($this->filters['has_payment'] ?? 'all') === 'yes') {
            $query->whereNotNull('payment_id');
        } elseif (($this->filters['has_payment'] ?? 'all') === 'no') {
            $query->whereNull('payment_id');
        }

        if (($this->filters['has_webhook'] ?? 'all') === 'yes') {
            $query->whereHas('webhookEvents');
        } elseif (($this->filters['has_webhook'] ?? 'all') === 'no') {
            $query->whereDoesntHave('webhookEvents');
        }

        if (filled($this->filters['created_from'] ?? null)) {
            $query->whereDate('created_at', '>=', $this->filters['created_from']);
        }

        if (filled($this->filters['created_to'] ?? null)) {
            $query->whereDate('created_at', '<=', $this->filters['created_to']);
        }

        return $query->latest();
    }

    /**
     * Maatwebsite calls this once per query chunk. Batches student reference and
     * latest-webhook-ref lookups to avoid N+1 reads per row.
     */
    public function prepareRows(iterable $rows): iterable
    {
        $rows = collect($rows);

        $this->studentReferenceMap = $this->studentReferenceReader->findMany(
            $rows->pluck('student_id')->map(static fn (int|string $id): int => (int) $id)->unique()->values()->all(),
        );

        $this->latestWebhookRefByRequest = DngWebhookEvent::query()
            ->whereIn('dng_payment_request_id', $rows->pluck('id'))
            ->whereNotNull('dng_payment_id')
            ->orderByDesc('created_at')
            ->get(['dng_payment_request_id', 'dng_payment_id'])
            ->groupBy('dng_payment_request_id')
            ->map(static fn ($group): string => (string) $group->first()->dng_payment_id)
            ->all();

        return $rows;
    }

    public function headings(): array
    {
        return [
            'Student Code',
            'Student Name',
            'Campus',
            'Semester',
            'Fee Type',
            'Item ID',
            'Amount',
            'Request Status',
            'DNG Payment Ref',
            'DNG Transaction ID',
            'Latest Webhook Ref',
            'Bridged Payment ID',
            'Payment Method',
            'Payment Amount',
            'Paid At',
            'Invoice Serial Number',
            'Due Date',
            'Created At',
        ];
    }

    public function map($paymentRequest): array
    {
        $student = $this->studentReferenceMap[(int) $paymentRequest->student_id] ?? null;
        $payment = $paymentRequest->payment;

        return [
            $paymentRequest->student_code,
            $student?->fullName,
            $paymentRequest->campus_code,
            $paymentRequest->semester?->name,
            $paymentRequest->fee_type,
            $paymentRequest->item_id,
            (float) $paymentRequest->amount,
            $paymentRequest->status,
            $paymentRequest->dng_payment_id,
            $paymentRequest->dng_transaction_id,
            $this->latestWebhookRefByRequest[(int) $paymentRequest->id] ?? null,
            $payment?->id,
            $payment?->method,
            $payment ? (float) $payment->amount : null,
            $payment?->paid_at?->toIso8601String(),
            $paymentRequest->invoice_serial_number,
            $paymentRequest->due_date?->format('Y-m-d'),
            $paymentRequest->created_at?->toIso8601String(),
        ];
    }
}
