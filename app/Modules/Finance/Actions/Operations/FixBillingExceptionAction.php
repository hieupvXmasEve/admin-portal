<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions\Operations;

use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceObligation;
use App\Modules\Finance\Support\BillingExceptionIdentifier;
use App\Shared\Contracts\Academic\AcademicFinanceChargeSourceGateway;
use App\Shared\Contracts\Finance\DTO\FinanceIntakeData;
use App\Shared\Contracts\Finance\Enums\FinancialEffect;
use App\Shared\Contracts\Finance\FinanceIntakeContract;
use App\Shared\Contracts\StudentRegistry\StudentReferenceReader;
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
            $registration = app(AcademicFinanceChargeSourceGateway::class)
                ->billingExceptionRegistration($registrationId, lockForUpdate: true);

            if ($registration === null) {
                throw new RuntimeException('Course registration not found');
            }

            $semesterId = (int) ($registration->semester_id ?? $registration->offering_semester_id);

            if ($semesterId <= 0) {
                throw new RuntimeException('Semester is required to fix a missing charge exception');
            }

            if ($requestedSemesterId !== null && $requestedSemesterId > 0 && $requestedSemesterId !== $semesterId) {
                throw new RuntimeException('Requested semester does not match the exception registration');
            }

            $studentCode = app(StudentReferenceReader::class)
                ->find($registration->student_id)
                ?->studentCode;

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
            $registration = app(AcademicFinanceChargeSourceGateway::class)
                ->billingExceptionRegistration($registrationId, lockForUpdate: true);

            if ($registration === null) {
                throw new RuntimeException('Course registration not found');
            }

            if (! $registration->is_retake) {
                throw new RuntimeException('Registration is not marked as retake');
            }

            $sourceRef = 'legacy-course-registration:'.$registration->id;

            $existingObligationId = FinanceObligation::query()
                ->where('source_system', 'finance')
                ->where('source_kind', 'legacy_course_registration')
                ->where('source_ref', $sourceRef)
                ->where('obligation_type', FinanceCharge::TYPE_RETAKE_FEE)
                ->value('id');

            if ($existingObligationId !== null) {
                $existingCharge = FinanceCharge::query()
                    ->where('finance_obligation_id', $existingObligationId)
                    ->where('status', FinanceCharge::STATUS_ACTIVE)
                    ->first();

                if ($existingCharge) {
                    return [
                        'fixed' => true,
                        'message' => 'Retake fee charge already exists',
                        'charge_id' => $existingCharge->id,
                    ];
                }
            }

            $courseName = $registration->unit_name ?? 'Unknown Course';

            // CatalogFixed: amount comes from finance_pricing_catalog_items only.
            $result = app(FinanceIntakeContract::class)->request(new FinanceIntakeData(
                source_system: 'finance',
                source_kind: 'legacy_course_registration',
                source_ref: $sourceRef,
                financial_effect: FinancialEffect::Debit,
                obligation_type: FinanceCharge::TYPE_RETAKE_FEE,
                facts: [
                    'student_id' => $registration->student_id,
                    'semester_id' => $registration->semester_id,
                    'description' => "Retake Fee: {$courseName}",
                ],
            ));

            return [
                'fixed' => true,
                'message' => 'Created retake fee charge',
                'charge_id' => $result->finance_charge_id,
            ];
        });
    }
}
