<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Actions\ScholarshipAdjustment;

use App\Modules\Academic\Progression\Models\ScholarshipAdjustmentDossier;
use App\Shared\Contracts\Identity\CampusPermissionReader;

/**
 * Maker records a proposed decision on a dossier.
 *
 * Boundary rule: this class may depend on the Shared Finance contracts
 * namespace only — never a concrete Finance module class or model. See
 * AcademicFinanceBoundaryArchRedProofTest for the enforced regex.
 */
class DecideAdjustmentAction
{
    /** Statuses that already resolved a decision — decide() is refused once here. */
    private const TERMINAL_DECISION_STATUSES = [
        ScholarshipAdjustmentDossier::STATUS_APPROVED,
        ScholarshipAdjustmentDossier::STATUS_APPLIED,
        ScholarshipAdjustmentDossier::STATUS_FINANCE_REVIEW_REQUIRED,
        ScholarshipAdjustmentDossier::STATUS_NO_ADJUSTMENT,
        ScholarshipAdjustmentDossier::STATUS_NOT_APPLICABLE,
        ScholarshipAdjustmentDossier::STATUS_CANCELLED,
        ScholarshipAdjustmentDossier::STATUS_CLOSED,
    ];

    public function __construct(private readonly CampusPermissionReader $permissions) {}

    /**
     * Maker records a proposed decision. Blocked until the interview is
     * completed, EXCEPT the exception path (manual override reason + the
     * maker holds approve_scholarship_adjustment — the checker permission
     * doubles as the exception-override permission per the plan).
     */
    public function run(
        ScholarshipAdjustmentDossier $dossier,
        string $decisionType,
        ?float $adjustedAmount,
        string $reason,
        int $makerUserId,
        ?string $exceptionOverrideReason = null,
    ): ScholarshipAdjustmentDossier {
        if (! in_array($decisionType, ScholarshipAdjustmentDossier::DECISION_TYPES, true)) {
            throw new \InvalidArgumentException('Hình thức xử lý học bổng này không hợp lệ. Hãy chọn một mục trong danh sách.');
        }

        // 'defer' has no terminal resolution defined yet (Phase 4/5 concern) —
        // refuse rather than silently closing it out as no_adjustment.
        if ($decisionType === ScholarshipAdjustmentDossier::DECISION_DEFER) {
            throw new \InvalidArgumentException('Tính năng tạm hoãn quyết định chưa dùng được. Hãy chọn hình thức xử lý khác.');
        }

        if (in_array($dossier->status, self::TERMINAL_DECISION_STATUSES, true)) {
            throw new \DomainException('Hồ sơ này đã chốt nên không thể thay đổi quyết định nữa.');
        }

        if (! $dossier->isDecidable()) {
            if ($exceptionOverrideReason === null) {
                throw new \DomainException('Hãy ghi nhận buổi phỏng vấn trước khi ra quyết định, hoặc nêu lý do bỏ qua bước phỏng vấn.');
            }

            $makerCodes = $this->permissions->permissionCodesForUserId($makerUserId, (int) $dossier->campus_id);

            if (! in_array('approve_scholarship_adjustment', $makerCodes, true)) {
                throw new \DomainException('Chỉ người có quyền duyệt quyết định học bổng tại cơ sở này mới được bỏ qua bước phỏng vấn.');
            }
        }

        if (in_array($decisionType, ScholarshipAdjustmentDossier::MONEY_DECISION_TYPES, true) && $adjustedAmount === null) {
            throw new \InvalidArgumentException('Hãy nhập mức học bổng sinh viên còn được giữ — giảm hoặc cắt học bổng đều cần giá trị này.');
        }

        // An adjustment only ever reduces. A value above the awarded amount is
        // silently clamped by the discount resolver, so without this guard the
        // dossier records a decision that changes no money at all. Compared
        // against the dossier's own snapshot, not the live definition, which
        // may have been edited since the dossier was raised.
        if ($adjustedAmount !== null
            && $dossier->original_amount !== null
            && $adjustedAmount > (float) $dossier->original_amount) {
            throw new \InvalidArgumentException(
                "Mức học bổng còn giữ ({$adjustedAmount}) không được cao hơn mức học bổng đã cấp ({$dossier->original_amount}).",
            );
        }

        // P4 confirmation gate: a fee-increasing (money) decision requires the
        // student to have acknowledged the interview minutes, or an approver to
        // have overruled their dispute on the record (OverruleDisputeAction).
        // Overdue is overridable inline by an approver; pending/declined and an
        // UNADDRESSED dispute are hard blocks — a fee increase is never bound
        // over an objection nobody has reviewed.
        if (in_array($decisionType, ScholarshipAdjustmentDossier::MONEY_DECISION_TYPES, true)
            && ! $dossier->canProceedToMoneyDecision()) {
            $isOverrideableOverdue = $dossier->confirmation_status === ScholarshipAdjustmentDossier::CONFIRMATION_OVERDUE
                && $exceptionOverrideReason !== null;

            if (! $isOverrideableOverdue) {
                throw new \DomainException(
                    'Sinh viên chưa xác nhận biên bản phỏng vấn nên chưa thể ghi nhận quyết định làm tăng học phí.',
                );
            }

            $overrideCodes = $this->permissions->permissionCodesForUserId($makerUserId, (int) $dossier->campus_id);

            if (! in_array('approve_scholarship_adjustment', $overrideCodes, true)) {
                throw new \DomainException('Chỉ người có quyền duyệt quyết định học bổng tại cơ sở này mới được tiếp tục khi sinh viên đã quá hạn xác nhận.');
            }
        }

        // Only a money decision carries an amount. keep/cancel never reach
        // Finance (see ApproveAdjustmentAction), so a value left in the form
        // would be stored as a scholarship change that never happens — and
        // every surface reading decision_adjusted_amount (the fee-impact
        // preview above all) would show a fee movement that is not real.
        $storedAmount = in_array($decisionType, ScholarshipAdjustmentDossier::MONEY_DECISION_TYPES, true)
            ? $adjustedAmount
            : null;

        $dossier->update([
            'status' => ScholarshipAdjustmentDossier::STATUS_READY_FOR_DECISION,
            'decision_type' => $decisionType,
            'decision_adjusted_amount' => $storedAmount,
            'decision_reason' => $reason.($exceptionOverrideReason !== null ? " [Exception: {$exceptionOverrideReason}]" : ''),
            'proposed_by_user_id' => $makerUserId,
            'decided_at' => now(),
        ]);

        return $dossier->refresh();
    }
}
