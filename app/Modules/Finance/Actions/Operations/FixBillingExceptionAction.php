<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions\Operations;

use App\Models\CourseRegistration;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Services\FinanceChargeService;
use App\Modules\Finance\Support\BillingExceptionIdentifier;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class FixBillingExceptionAction
{
    public static function run(array $data): array
    {
        $decoded = BillingExceptionIdentifier::decode((int) $data['exception_id']);

        return match ($decoded['type']) {
            'missing_charge' => self::fixMissingCharge(
                $decoded['source_id'],
                isset($data['semester_id']) ? (int) $data['semester_id'] : null,
            ),
            'retake_no_charge' => self::fixRetakeNoCharge($decoded['source_id']),
            'defer_no_case' => throw new RuntimeException(
                'Defer case creation requires manual fee-policy selection'
            ),
            'zero_tuition_waived' => throw new RuntimeException(
                'Zero tuition term is waived by plan and does not require a fix'
            ),
            'deferred_enrolled' => throw new RuntimeException(
                'Deferred student with retained class registrations does not require a tuition fix'
            ),
            default => throw new RuntimeException('Unsupported billing exception type'),
        };
    }

    private static function fixMissingCharge(int $registrationId, ?int $requestedSemesterId): array
    {
        return DB::transaction(function () use ($registrationId, $requestedSemesterId): array {
            $registration = CourseRegistration::query()
                ->with(['student', 'courseOffering'])
                ->whereKey($registrationId)
                ->lockForUpdate()
                ->firstOrFail();

            $semesterId = (int) ($registration->semester_id ?: $registration->courseOffering?->semester_id);

            if ($semesterId <= 0) {
                throw new RuntimeException('Semester is required to fix a missing charge exception');
            }

            if ($requestedSemesterId !== null && $requestedSemesterId > 0 && $requestedSemesterId !== $semesterId) {
                throw new RuntimeException('Requested semester does not match the exception registration');
            }

            $studentCode = $registration->student?->student_id;

            if ($studentCode === null || $studentCode === '') {
                throw new RuntimeException('Student code is missing for this registration');
            }

            $result = GenerateBatchChargesAction::run([
                'semester_id' => $semesterId,
                'scope_type' => 'upload_list',
                'uploaded_student_ids' => [$studentCode],
                'charge_types' => [FinanceCharge::TYPE_TUITION_TERM],
                'filter_enrollment_status' => 'all',
            ]);

            if (($result['created_count'] ?? 0) === 0) {
                $error = $result['errors'][0] ?? 'Could not create missing charge';

                throw new RuntimeException(is_string($error) ? $error : 'Could not create missing charge');
            }

            return [
                'fixed' => true,
                'message' => 'Created missing tuition charge',
                'details' => $result,
            ];
        });
    }

    private static function fixRetakeNoCharge(int $registrationId): array
    {
        return DB::transaction(function () use ($registrationId): array {
            $registration = CourseRegistration::query()
                ->whereKey($registrationId)
                ->lockForUpdate()
                ->firstOrFail();

            $existingCharge = FinanceCharge::query()
                ->where('student_id', $registration->student_id)
                ->where('semester_id', $registration->semester_id)
                ->where('charge_type', FinanceCharge::TYPE_RETAKE_FEE)
                ->where('status', FinanceCharge::STATUS_ACTIVE)
                ->where('source_type', CourseRegistration::class)
                ->where('source_id', $registration->id)
                ->first();

            if ($existingCharge) {
                return [
                    'fixed' => true,
                    'message' => 'Retake fee charge already exists',
                    'charge_id' => $existingCharge->id,
                ];
            }

            $charge = app(FinanceChargeService::class)->generateRetakeCharge($registration);

            if ($charge === null) {
                throw new RuntimeException('Retake charge skipped by defer policy');
            }

            return [
                'fixed' => true,
                'message' => 'Created retake fee charge',
                'charge_id' => $charge->id,
            ];
        });
    }
}
