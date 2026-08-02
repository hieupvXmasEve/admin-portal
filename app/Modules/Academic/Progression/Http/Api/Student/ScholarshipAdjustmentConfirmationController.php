<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Http\Api\Student;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Student;
use App\Modules\Academic\Progression\Actions\ScholarshipAdjustment\RecordStudentResponseAction;
use App\Modules\Academic\Progression\Exceptions\StaleMinutesVersionException;
use App\Modules\Academic\Progression\Http\Requests\ScholarshipAdjustment\ConfirmScholarshipAdjustmentRequest;
use App\Modules\Academic\Progression\Models\ScholarshipAdjustmentDossier;
use App\Modules\Academic\Progression\Support\ScholarshipStaffNotificationPublisher;
use App\Shared\Contracts\Finance\ScholarshipAdjustmentPreviewReader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Student-portal endpoints for acknowledging scholarship-adjustment interview
 * minutes. Runs under `student.api.auth` ALONE (no `either`/parent) so only a
 * Student actor — never a guardian token — can bind this legally-relevant
 * acknowledgement. Ownership is derived strictly from the authenticated
 * student id; the dossier id in the URL is never trusted for authorization.
 */
class ScholarshipAdjustmentConfirmationController extends Controller
{
    /**
     * Every review that concerns the authenticated student, newest first —
     * backs the portal's sidebar entry, where the student has no dossier id to
     * navigate with. Dossiers with no confirmation window opened yet are
     * excluded: there is nothing for the student to act on or see.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var Student $student */
        $student = $request->user();

        $dossiers = ScholarshipAdjustmentDossier::query()
            ->where('student_id', $student->id)
            ->whereNotNull('confirmation_status')
            ->with(['sourceSemester:id,name', 'targetSemester:id,name'])
            ->orderByDesc('confirmation_requested_at')
            ->get()
            ->map(fn (ScholarshipAdjustmentDossier $record) => [
                'id' => $record->id,
                'confirmation_status' => $record->confirmation_status,
                'confirmation_requested_at' => $record->confirmation_requested_at?->toIso8601String(),
                'confirmed_at' => $record->confirmed_at?->toIso8601String(),
                'source_semester_name' => $record->sourceSemester?->name,
                'target_semester_name' => $record->targetSemester?->name,
                'awaiting_response' => $record->confirmation_status === ScholarshipAdjustmentDossier::CONFIRMATION_PENDING
                    || $record->confirmation_status === ScholarshipAdjustmentDossier::CONFIRMATION_OVERDUE,
            ])
            ->values();

        return ApiResponse::success($dossiers);
    }

    public function minutes(Request $request, int $dossier): JsonResponse
    {
        $record = $this->ownedDossierOrNull($request, $dossier);

        if ($record === null) {
            return ApiResponse::notFound('Dossier not found.');
        }

        return ApiResponse::success([
            'id' => $record->id,
            'minutes' => $record->minutes,
            'minutes_version' => (int) $record->minutes_version,
            'confirmation_status' => $record->confirmation_status,
            'confirmation_requested_at' => $record->confirmation_requested_at?->toIso8601String(),
            'source_semester_id' => $record->source_semester_id,
            'target_semester_id' => $record->target_semester_id,
            'target_semester_name' => $record->targetSemester?->name,
            'dispute_overrule_reason' => $record->dispute_overrule_reason,
            'decision_type' => $this->settledDecisionType($record),
            'fee_impact' => $this->settledFeeImpact($record),
        ]);
    }

    public function confirm(ConfirmScholarshipAdjustmentRequest $request, int $dossier): JsonResponse
    {
        $record = $this->ownedDossierOrNull($request, $dossier);

        if ($record === null) {
            return ApiResponse::notFound('Dossier not found.');
        }

        /** @var Student $student */
        $student = $request->user();

        try {
            $updated = RecordStudentResponseAction::run(
                $record,
                (int) $request->integer('minutes_version'),
                $request->boolean('agree'),
                $request->input('comment'),
                $student->user_id !== null ? (int) $student->user_id : null,
            );
        } catch (StaleMinutesVersionException $e) {
            return ApiResponse::conflict($e->getMessage());
        } catch (\DomainException $e) {
            return ApiResponse::businessLogicError($e->getMessage());
        }

        // Either answer changes what staff can do next and neither surfaces
        // anywhere else: a rejection blocks the money decision, an acceptance
        // unblocks it.
        $publisher = app(ScholarshipStaffNotificationPublisher::class);

        match ($updated->confirmation_status) {
            ScholarshipAdjustmentDossier::CONFIRMATION_DISPUTED => $publisher->disputed($updated, $updated->student_comment),
            ScholarshipAdjustmentDossier::CONFIRMATION_CONFIRMED => $publisher->confirmed($updated, $updated->student_comment),
            default => null,
        };

        return ApiResponse::success([
            'id' => $updated->id,
            'confirmation_status' => $updated->confirmation_status,
        ], [], 'Response recorded.');
    }

    /**
     * Statuses where the decision is settled and may be shown to the student.
     * A merely proposed decision is withheld — it still needs a second
     * approver and showing it would announce a fee change that may never
     * happen.
     */
    private const SETTLED_STATUSES = [
        ScholarshipAdjustmentDossier::STATUS_APPROVED,
        ScholarshipAdjustmentDossier::STATUS_APPLIED,
        ScholarshipAdjustmentDossier::STATUS_CLOSED,
    ];

    private function settledDecisionType(ScholarshipAdjustmentDossier $record): ?string
    {
        return in_array($record->status, self::SETTLED_STATUSES, true) ? $record->decision_type : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function settledFeeImpact(ScholarshipAdjustmentDossier $record): ?array
    {
        if (! in_array($record->status, self::SETTLED_STATUSES, true) || $record->decision_adjusted_amount === null) {
            return null;
        }

        $preview = app(ScholarshipAdjustmentPreviewReader::class)->preview(
            (int) $record->student_id,
            (int) $record->target_semester_id,
            (float) $record->decision_adjusted_amount,
        );

        if (! $preview->has_invoice) {
            return null;
        }

        return [
            'tuition_base' => $preview->tuition_base,
            'scholarship_before' => $preview->current_discount,
            'scholarship_after' => $preview->adjusted_discount,
            'payable_before' => $preview->payable_before,
            'payable_after' => $preview->payable_after,
            'extra_to_pay' => $preview->delta(),
        ];
    }

    /**
     * Resolve the dossier only if it belongs to the authenticated student.
     * Returns null (→ 404) otherwise so existence is never leaked cross-student.
     */
    private function ownedDossierOrNull(Request $request, int $dossierId): ?ScholarshipAdjustmentDossier
    {
        /** @var Student $student */
        $student = $request->user();

        return ScholarshipAdjustmentDossier::query()
            ->whereKey($dossierId)
            ->where('student_id', $student->id)
            ->with('targetSemester:id,name')
            ->first();
    }
}
