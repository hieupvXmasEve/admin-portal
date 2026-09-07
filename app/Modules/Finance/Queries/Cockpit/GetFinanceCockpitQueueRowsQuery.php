<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Cockpit;

use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Dng\Models\DngWebhookEvent;
use App\Modules\Finance\Models\FinanceCancellationOperation;
use App\Modules\Finance\Models\FinanceChargeInstallment;
use App\Modules\Finance\Queries\Operations\ListSettlementWorklistQuery;
use App\Modules\Finance\Queries\Operations\ListUnresolvedSurplusQuery;
use App\Modules\Finance\Support\LifecycleDueItemPredicate;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Top-N rows for a single cockpit queue, fetched on demand when the Action Panel
 * opens. Bounded by $limit; reuses existing models/queries. Read-only.
 * Omits rows when the user lacks the queue's source permission.
 */
class GetFinanceCockpitQueueRowsQuery
{
    private const LIMIT = 15;

    public function __construct(
        private ListSettlementWorklistQuery $settlementWorklist,
        private ListUnresolvedSurplusQuery $unresolvedSurplus,
    ) {}

    /** @return list<array<string,mixed>> */
    public function handle(string $queueKey, ?int $semesterId): array
    {
        $permission = GetFinanceCockpitOverviewQuery::QUEUE_PERMISSIONS[$queueKey] ?? null;
        $user = Auth::user();
        if ($permission === null || $user === null || $user->cannot($permission)) {
            return [];
        }

        return match ($queueKey) {
            'webhook_errors' => $this->webhookRows(),
            'installment_failures' => $this->installmentRows(),
            'settlement_exceptions' => $this->settlementExceptionRows(),
            'unallocated' => $this->unallocatedRows(),
            'dng_due' => $this->dueRows($semesterId),
            'lifecycle' => $this->lifecycleRows($semesterId),
            'cancellations' => $this->cancellationRows(),
            'unresolved_surplus' => $this->surplusRows(),
            default => [],
        };
    }

    /** @return list<array<string,mixed>> */
    private function webhookRows(): array
    {
        return DngWebhookEvent::query()
            ->whereIn('processing_status', [
                DngWebhookEvent::STATUS_FAILED_RETRYABLE,
                DngWebhookEvent::STATUS_FAILED_TERMINAL,
                DngWebhookEvent::STATUS_MISMATCH,
                DngWebhookEvent::STATUS_SKIPPED,
            ])
            ->orderBy('created_at')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (DngWebhookEvent $e) => $this->row(
                (int) $e->id,
                'Webhook #'.$e->id.' · '.$e->processing_status,
                (string) ($e->error_message ?? ''),
                $e->created_at?->diffForHumans(),
                null,
                'retry_webhook',
                route('finance.dng.webhook-events.retry', ['dngWebhookEvent' => $e->id]),
                'Retry',
            ))->all();
    }

    /** @return list<array<string,mixed>> */
    private function installmentRows(): array
    {
        return FinanceChargeInstallment::query()
            ->where('status', FinanceChargeInstallment::STATUS_PENDING)
            ->whereNotNull('last_push_error')
            ->orderBy('last_push_attempted_at')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (FinanceChargeInstallment $i) => $this->row(
                (int) $i->id,
                'Kỳ '.$i->installment_no.' · charge #'.$i->finance_charge_id,
                (string) ($i->last_push_error ?? ''),
                $i->last_push_attempted_at?->diffForHumans(),
                null,
                'retry_installment',
                route('finance.charges.installments.retry-push', ['charge' => $i->finance_charge_id, 'installment' => $i->id]),
                'Đẩy lại',
            ))->all();
    }

    /** @return list<array<string,mixed>> */
    private function settlementExceptionRows(): array
    {
        $worklist = $this->settlementWorklist->handle(Request::create(
            route('finance.operations.settlement.index'),
            'GET',
            ['readiness' => 'all', 'per_page' => 1],
        ));

        return collect($worklist['exceptions'] ?? [])
            ->take(self::LIMIT)
            ->map(fn (array $student) => $this->row(
                (int) $student['student_id'],
                (string) ($student['student_code'] ?? '#'.$student['student_id']).' · settlement cần kiểm tra',
                (string) ($student['student_name'] ?? ''),
                null,
                (int) $student['student_id'],
                'open_exceptions',
                route('finance.operations.exceptions'),
                'Mở ngoại lệ',
            ))
            ->values()
            ->all();
    }

    /** @return list<array<string,mixed>> */
    private function unallocatedRows(): array
    {
        $worklist = $this->settlementWorklist->handle(Request::create(
            route('finance.operations.settlement.index'),
            'GET',
            ['readiness' => 'ready', 'per_page' => self::LIMIT],
        ));

        $rows = [];
        foreach ($worklist['students'] as $student) {
            $rows[] = $this->row(
                (int) $student['student_id'],
                (string) ($student['student_code'] ?? '#'.$student['student_id']).' · tiền chờ phân bổ',
                (string) ($student['student_name'] ?? ''),
                null,
                (int) $student['student_id'],
                'open_settlement',
                route('finance.operations.settlement.index', ['readiness' => 'ready']),
                'Mở phân bổ',
            );
        }

        return $rows;
    }

    /** @return list<array<string,mixed>> */
    private function dueRows(?int $semesterId): array
    {
        $today = now()->startOfDay();
        $campusId = app()->bound('campus') ? app('campus')?->id : null;
        $query = DngPaymentRequest::query()
            ->where('dng_payment_requests.status', DngPaymentRequest::STATUS_PUSHED_TO_DNG)
            ->whereNotNull('dng_payment_requests.due_date')
            ->when($semesterId, fn ($q) => $q->where('dng_payment_requests.semester_id', $semesterId))
            ->where('dng_payment_requests.due_date', '<=', $today->toDateString());
        LifecycleDueItemPredicate::applyActiveCollectionScope($query, $campusId === null ? null : (int) $campusId);

        return $query
            ->orderBy('dng_payment_requests.due_date')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (DngPaymentRequest $request) => $this->row(
                (int) $request->id,
                ($request->student_code ?? 'DNG #'.$request->id).' · hạn '.$request->due_date?->toDateString(),
                (string) ($request->description ?? $request->fee_type),
                $request->due_date?->diffForHumans(),
                $request->student_id === null ? null : (int) $request->student_id,
                'open_due_calendar',
                route('finance.operations.due-calendar'),
                'Mở nhắc nợ',
            ))->all();
    }

    /** @return list<array<string,mixed>> */
    private function lifecycleRows(?int $semesterId): array
    {
        $campusId = app()->bound('campus') ? app('campus')?->id : null;
        $query = DngPaymentRequest::query()
            ->where('status', DngPaymentRequest::STATUS_PUSHED_TO_DNG)
            ->whereNotNull('due_date')
            ->when($semesterId, fn ($q) => $q->where('semester_id', $semesterId))
            ->when($campusId, fn ($q) => $q->whereHas('student', fn ($student) => $student->where('campus_id', $campusId)));
        LifecycleDueItemPredicate::applyLifecycleExceptionScope($query);

        return $query
            ->orderBy('due_date')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (DngPaymentRequest $request) => $this->row(
                (int) $request->id,
                ($request->student_code ?? 'DNG #'.$request->id).' · ngoại lệ lifecycle',
                (string) ($request->description ?? $request->fee_type),
                $request->due_date?->diffForHumans(),
                $request->student_id === null ? null : (int) $request->student_id,
                'open_lifecycle',
                route('finance.operations.lifecycle-exceptions'),
                'Mở lifecycle',
            ))->all();
    }

    /** @return list<array<string,mixed>> */
    private function cancellationRows(): array
    {
        return FinanceCancellationOperation::query()
            ->where('status', FinanceCancellationOperation::STATUS_REQUIRES_REVIEW)
            ->orderByDesc('updated_at')
            ->limit(self::LIMIT)
            ->get()
            ->map(function (FinanceCancellationOperation $operation) {
                $payload = is_array($operation->source_payload) ? $operation->source_payload : [];
                $result = is_array($operation->result_payload) ? $operation->result_payload : [];
                $studentId = isset($payload['student_id']) ? (int) $payload['student_id'] : null;
                $reason = (string) ($result['review_reason'] ?? $operation->source_kind.' '.$operation->source_ref);

                return $this->row(
                    (int) $operation->id,
                    'Huỷ #'.$operation->id.' · '.$operation->source_kind,
                    $reason,
                    $operation->updated_at?->diffForHumans(),
                    $studentId,
                    'open_student',
                    $studentId !== null
                        ? route('finance.students.overview', $studentId)
                        : route('finance.search'),
                    'Mở hồ sơ',
                );
            })->all();
    }

    /** @return list<array<string,mixed>> */
    private function surplusRows(): array
    {
        $campusId = app()->bound('campus') ? app('campus')?->id : null;
        $canViewAll = Auth::user()?->can('view_finance_all_campus') ?? false;

        try {
            $rows = $this->unresolvedSurplus->handle(
                $campusId === null ? null : (int) $campusId,
                $canViewAll,
            );
        } catch (AuthorizationException) {
            return [];
        }

        return collect($rows)
            ->take(self::LIMIT)
            ->map(fn (array $row) => $this->row(
                (int) $row['student_id'],
                'SV #'.$row['student_id'].' · số dư cần quyết',
                $row['enrollment_status'].' · '.number_format((float) $row['unapplied'], 0, ',', '.').' ₫',
                null,
                (int) $row['student_id'],
                'open_student',
                route('finance.students.overview', $row['student_id']),
                'Mở hồ sơ SV',
            ))
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function row(
        int $id,
        string $title,
        string $subtitle,
        ?string $age,
        ?int $studentId,
        string $kind,
        string $url,
        string $label,
    ): array {
        return [
            'id' => $id,
            'title' => $title,
            'subtitle' => $subtitle,
            'age' => $age,
            'student_id' => $studentId,
            'primary_action' => [
                'kind' => $kind,
                'url' => $url,
                'label' => $label,
            ],
        ];
    }
}
