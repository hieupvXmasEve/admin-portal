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

        return ApiResponse::success([
            'id' => $updated->id,
            'confirmation_status' => $updated->confirmation_status,
        ], [], 'Response recorded.');
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
            ->first();
    }
}
