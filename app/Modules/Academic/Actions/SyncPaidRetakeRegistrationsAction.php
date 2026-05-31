<?php

declare(strict_types=1);

namespace App\Modules\Academic\Actions;

use App\Models\CourseRegistration;
use App\Models\CourseRetakeRegistration;
use App\Models\FinanceCharge;
use App\Shared\Contracts\Academic\RetakeRegistrationPaymentSyncer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

class SyncPaidRetakeRegistrationsAction implements RetakeRegistrationPaymentSyncer
{
    /**
     * @return array{checked:int,eligible:int,synced:int,waiting_for_class:int,skipped:int,failed:int,details:array<int,array<string,mixed>>}
     */
    public function runAll(bool $dryRun = false): array
    {
        return $this->runQuery(CourseRetakeRegistration::query(), $dryRun);
    }

    /**
     * @return array{checked:int,eligible:int,synced:int,waiting_for_class:int,skipped:int,failed:int,details:array<int,array<string,mixed>>}
     */
    public function runForStudent(int $studentId, bool $dryRun = false): array
    {
        return $this->runQuery(
            CourseRetakeRegistration::query()->where('student_id', $studentId),
            $dryRun,
        );
    }

    /**
     * @return array{checked:int,eligible:int,synced:int,waiting_for_class:int,skipped:int,failed:int,details:array<int,array<string,mixed>>}
     */
    public function runForRegistration(int $registrationId, bool $dryRun = false): array
    {
        return $this->runQuery(
            CourseRetakeRegistration::query()->whereKey($registrationId),
            $dryRun,
        );
    }

    /**
     * @param  array<int>  $chargeIds
     * @return array{checked:int,eligible:int,synced:int,waiting_for_class:int,skipped:int,failed:int,details:array<int,array<string,mixed>>}
     */
    public function runForChargeIds(array $chargeIds, bool $dryRun = false): array
    {
        $chargeIds = array_values(array_unique(array_filter(array_map('intval', $chargeIds))));

        if ($chargeIds === []) {
            return $this->emptyResult();
        }

        return $this->runQuery(
            CourseRetakeRegistration::query()->whereIn('finance_charge_id', $chargeIds),
            $dryRun,
        );
    }

    /**
     * @return array{checked:int,eligible:int,synced:int,waiting_for_class:int,skipped:int,failed:int,details:array<int,array<string,mixed>>}
     */
    private function runQuery(Builder $query, bool $dryRun): array
    {
        $result = $this->emptyResult();

        $registrations = $query
            ->whereIn('status', [
                CourseRetakeRegistration::STATUS_PAYMENT_PENDING,
                CourseRetakeRegistration::STATUS_PAID,
                CourseRetakeRegistration::STATUS_ENROLLED,
            ])
            ->where(function (Builder $query): void {
                $query
                    ->where(function (Builder $query): void {
                        $query
                            ->whereIn('status', [
                                CourseRetakeRegistration::STATUS_PAYMENT_PENDING,
                                CourseRetakeRegistration::STATUS_PAID,
                            ])
                            ->whereNull('course_registration_id');
                    })
                    ->orWhere(function (Builder $query): void {
                        $query
                            ->where('status', CourseRetakeRegistration::STATUS_ENROLLED)
                            ->where(function (Builder $query): void {
                                $query
                                    ->whereDoesntHave('courseRegistration')
                                    ->orWhereHas('courseRegistration', fn (Builder $courseRegistrationQuery) => $courseRegistrationQuery
                                        ->whereNotIn('registration_status', CourseRegistration::CLASS_ROSTER_REGISTRATION_STATUSES));
                            });
                    });
            })
            ->whereNotNull('finance_charge_id')
            ->with(['student:id,student_id,full_name', 'financeCharge'])
            ->orderBy('id')
            ->get();

        foreach ($registrations as $registration) {
            $result['checked']++;

            $charge = $registration->financeCharge;
            if (! $charge || $charge->status !== FinanceCharge::STATUS_ACTIVE || ! $charge->is_fully_paid) {
                $result['skipped']++;

                continue;
            }

            $result['eligible']++;
            $detail = [
                'registration_id' => $registration->id,
                'student_id' => $registration->student?->student_id,
                'finance_charge_id' => $charge->id,
                'paid_amount' => $charge->paid_amount,
                'balance' => $charge->balance,
            ];

            if ($dryRun) {
                $result['details'][] = $detail + ['status' => 'eligible'];

                continue;
            }

            try {
                AutoEnrollRetakeCourseAction::handlePaymentConfirmed($charge);

                $fresh = $registration->fresh(['courseRegistration']);
                $freshStatus = $fresh->status;
                if ($freshStatus === CourseRetakeRegistration::STATUS_ENROLLED && $this->hasActiveClassLink($fresh)) {
                    $result['synced']++;
                    $result['details'][] = $detail + ['status' => $freshStatus];
                } elseif ($freshStatus === CourseRetakeRegistration::STATUS_PAID && $fresh->course_registration_id === null) {
                    $result['waiting_for_class']++;
                    $result['details'][] = $detail + ['status' => 'paid_waiting_for_class'];
                } elseif ($freshStatus === CourseRetakeRegistration::STATUS_ENROLLED) {
                    $result['skipped']++;
                    $result['details'][] = $detail + ['status' => 'needs_review'];
                } else {
                    $result['skipped']++;
                    $result['details'][] = $detail + ['status' => $freshStatus];
                }
            } catch (\Throwable $e) {
                $result['failed']++;
                $result['details'][] = $detail + [
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                ];

                Log::warning('Failed to sync paid retake registration', [
                    'registration_id' => $registration->id,
                    'finance_charge_id' => $charge->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $result;
    }

    /**
     * @return array{checked:int,eligible:int,synced:int,waiting_for_class:int,skipped:int,failed:int,details:array<int,array<string,mixed>>}
     */
    private function emptyResult(): array
    {
        return [
            'checked' => 0,
            'eligible' => 0,
            'synced' => 0,
            'waiting_for_class' => 0,
            'skipped' => 0,
            'failed' => 0,
            'details' => [],
        ];
    }

    private function hasActiveClassLink(CourseRetakeRegistration $registration): bool
    {
        $courseRegistration = $registration->courseRegistration;

        return $courseRegistration !== null
            && in_array(
                $courseRegistration->registration_status,
                CourseRegistration::CLASS_ROSTER_REGISTRATION_STATUSES,
                true,
            );
    }
}
