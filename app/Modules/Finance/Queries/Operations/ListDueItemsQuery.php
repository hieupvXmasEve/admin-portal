<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Operations;

use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Support\ExamResitDngLinkResolver;
use App\Modules\Finance\Support\ExamResitDueClassification;
use App\Modules\Finance\Support\ExamResitDueClassifier;
use App\Modules\Finance\Support\ExamResitDueRowPresenter;
use App\Modules\Finance\Support\LifecycleDueItemPredicate;
use App\Shared\Contracts\Academic\DTO\AcademicExamResitDueData;
use App\Shared\Contracts\Academic\StudentLifecycleStatusReader;
use App\Shared\Support\Academic\StudentLifecycleStatusPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ListDueItemsQuery
{
    public const SOURCE_DNG_REQUEST = 'dng_request';

    public const SOURCE_EXAM_RESIT = 'exam_resit';

    public function __construct(
        private readonly StudentLifecycleStatusReader $lifecycleStatusReader,
        private readonly ExamResitDngLinkResolver $linkResolver = new ExamResitDngLinkResolver,
        private readonly ExamResitDueClassifier $classifier = new ExamResitDueClassifier,
    ) {}

    public function handle(
        ?int $semesterId,
        ?string $status,
        ?string $search,
        ?string $source = null,
        ?string $feeType = null,
    ): LengthAwarePaginator {
        $campusId = app()->bound('campus') ? app('campus')->id : null;
        $today = now()->startOfDay();

        $dngQuery = DngPaymentRequest::query()
            ->with(['student:id,student_id,full_name,email,status'])
            ->when($semesterId, fn ($q) => $q->where('semester_id', $semesterId))
            ->where('dng_payment_requests.status', 'pushed_to_dng')
            ->whereNotNull('dng_payment_requests.due_date');

        LifecycleDueItemPredicate::applyActiveCollectionScope($dngQuery, $campusId);

        $this->applySourceFilter($dngQuery, $source);

        if (! empty($feeType)) {
            $dngQuery->where('dng_payment_requests.fee_type', $feeType);
        }

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
        $perPage = min(500, max(1, (int) request()->get('per_page', 20)));

        $paginator = $dngQuery
            ->orderBy('dng_payment_requests.due_date')
            ->paginate($perPage, ['*'], 'page', $page);

        // Resolve exam-resit (PTL) linkage for just this page, batched.
        $paginator->getCollection()->loadMissing(['chargeLinks']);
        $attemptsByRequest = $this->linkResolver->attemptsByDngRequest($paginator->getCollection());
        $lifecycleStatuses = $this->lifecycleStatuses($paginator->getCollection());

        return $paginator->through(
            fn (DngPaymentRequest $request) => $this->toRow(
                $request,
                $today,
                $attemptsByRequest[$request->id] ?? null,
                $lifecycleStatuses[(int) $request->student_id] ?? null,
            ),
        );
    }

    /**
     * `students.status` is legacy and is not written back on program-enrollment
     * transitions, so the displayed lifecycle badge is read from the
     * Progression-owned projection instead.
     *
     * @param  Collection<int, DngPaymentRequest>  $requests
     * @return array<int, string>
     */
    private function lifecycleStatuses($requests): array
    {
        $studentIds = $requests
            ->filter(fn (DngPaymentRequest $request): bool => $request->student !== null)
            ->pluck('student_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($studentIds === []) {
            return [];
        }

        return $this->lifecycleStatusReader->statusesFor($studentIds);
    }

    /**
     * @param  Builder<DngPaymentRequest>  $query
     */
    private function applySourceFilter($query, ?string $source): void
    {
        if ($source === self::SOURCE_EXAM_RESIT) {
            $query->where('dng_payment_requests.fee_type', ExamResitDueRowPresenter::FEE_TYPE_PTL);
        } elseif ($source === self::SOURCE_DNG_REQUEST) {
            $query->where('dng_payment_requests.fee_type', '!=', ExamResitDueRowPresenter::FEE_TYPE_PTL);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function toRow(DngPaymentRequest $request, $today, ?AcademicExamResitDueData $attempt, ?string $lifecycleStatus = null): array
    {
        $dueDate = $request->due_date;
        $daysUntilDue = $today->diffInDays($dueDate, false);

        $requestStatus = 'upcoming';
        if ($daysUntilDue < 0) {
            $requestStatus = 'overdue';
        } elseif ($daysUntilDue === 0) {
            $requestStatus = 'due_today';
        }

        $row = [
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
            'fee_type' => $request->fee_type,
            'source_type' => self::SOURCE_DNG_REQUEST,
            'source_id' => null,
            // Plain DNG rows scoped here are active pushed requests for active
            // students, so they remain remindable as before.
            'reminder_state' => ExamResitDueClassification::REMINDER_STATE_REMINDABLE,
            'student_status_label' => $request->student === null ? null : StudentLifecycleStatusPresenter::label($lifecycleStatus ?? $request->student->status),
            'student_status_color' => $request->student === null ? null : StudentLifecycleStatusPresenter::color($lifecycleStatus ?? $request->student->status),
            'last_reminder_at' => $request->last_reminder_at,
        ];

        if ($attempt !== null) {
            $classification = $this->classifier->classify($attempt, hasActivePushedDng: true);
            $row = array_merge($row, ExamResitDueRowPresenter::examResitContext($attempt, $classification));
        }

        return $row;
    }
}
