<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Operations;

use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Support\ExamResitDngLinkResolver;
use App\Modules\Finance\Support\ExamResitDueClassification;
use App\Modules\Finance\Support\ExamResitDueClassifier;
use App\Modules\Finance\Support\ExamResitDueRowPresenter;
use App\Shared\Contracts\Academic\AcademicFinanceChargeSourceGateway;
use App\Shared\Contracts\Academic\AcademicFinanceSourceKeys;
use App\Shared\Contracts\Academic\DTO\AcademicExamResitDueData;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Surface overdue exam-resit (thi lại / PTL) sources that still need charge/DNG
 * creation for the Finance Due Reminders worklist (ACAD-RET-002).
 *
 * These rows are visible for triage/handoff but are NOT directly remindable —
 * staff route them to the DNG worklist / Batch Studio DNG flow. The candidate
 * set is DB-scoped (campus + semester + lifecycle + a past sitting/deadline)
 * before the precise per-row overdue clock is derived in PHP, so no full-table
 * materialization happens for routine filtering.
 */
class ListExamResitHandoffQuery
{
    private const PER_PAGE = 25;

    private const PAGE_NAME = 'handoff_page';

    public function __construct(
        private readonly ExamResitDngLinkResolver $linkResolver = new ExamResitDngLinkResolver,
        private readonly ExamResitDueClassifier $classifier = new ExamResitDueClassifier,
        private readonly ?AcademicFinanceChargeSourceGateway $academicSources = null,
    ) {}

    public function handle(?int $semesterId, ?string $search): LengthAwarePaginator
    {
        $campusId = app()->bound('campus') ? app('campus')->id : null;
        $now = now();

        $candidates = collect($this->academicSources()->overdueExamResitDueSources($campusId, $semesterId, trim((string) $search)));

        $chargeIdsByAttempt = FinanceCharge::query()
            ->join('finance_obligations', 'finance_obligations.id', '=', 'finance_charges.finance_obligation_id')
            ->where('finance_obligations.source_system', AcademicFinanceSourceKeys::SOURCE_SYSTEM)
            ->where('finance_obligations.source_kind', AcademicFinanceSourceKeys::EXAM_RESIT_ATTEMPT)
            ->whereIn(
                'finance_obligations.source_ref',
                $candidates->map(fn (AcademicExamResitDueData $source): string => AcademicFinanceSourceKeys::examResitAttemptRef($source->id)),
            )
            ->pluck('finance_charges.id', 'finance_obligations.source_ref')
            ->mapWithKeys(fn (int $chargeId, string $sourceRef): array => [
                (int) (AcademicFinanceSourceKeys::sourceIdFromRef($sourceRef, 'exam-resit:') ?? 0) => $chargeId,
            ]);

        $withActiveDng = array_flip($this->linkResolver->chargeIdsWithActivePushedDng($chargeIdsByAttempt->values()->all()));

        $rows = $candidates
            ->reject(fn (AcademicExamResitDueData $attempt) => isset($withActiveDng[$chargeIdsByAttempt->get($attempt->id)]))
            ->map(fn (AcademicExamResitDueData $attempt) => [
                'attempt' => $attempt,
                'classification' => $this->classifier->classify($attempt, hasActivePushedDng: false, now: $now),
            ])
            ->filter(fn (array $pair) => $this->isOverdueHandoff($pair['classification']))
            ->map(fn (array $pair) => ExamResitDueRowPresenter::handoffRow($pair['attempt'], $pair['classification']))
            ->values();

        return $this->paginate($rows);
    }

    private function academicSources(): AcademicFinanceChargeSourceGateway
    {
        return $this->academicSources ?? app(AcademicFinanceChargeSourceGateway::class);
    }

    private function isOverdueHandoff(ExamResitDueClassification $classification): bool
    {
        return $classification->reminderState === ExamResitDueClassification::REMINDER_STATE_NEEDS_CHARGE_OR_DNG
            && ($classification->daysOverdue ?? 0) > 0;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    private function paginate(Collection $rows): LengthAwarePaginator
    {
        $page = max(1, (int) request()->get(self::PAGE_NAME, 1));

        return new LengthAwarePaginator(
            $rows->forPage($page, self::PER_PAGE)->values(),
            $rows->count(),
            self::PER_PAGE,
            $page,
            [
                'path' => request()->url(),
                'query' => request()->query(),
                'pageName' => self::PAGE_NAME,
            ],
        );
    }
}
