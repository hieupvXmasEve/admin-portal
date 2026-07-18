<?php

declare(strict_types=1);

namespace App\Modules\Academic\FacultyWorkforce\Support;

use App\Models\Lecture;
use App\Shared\Contracts\Identity\DTO\FacultyAccessEligibility;

final class FacultyAccessEligibilityResolver
{
    /**
     * The Workforce-owned vocabulary consumed by Identity. Leave and sabbatical
     * remain eligible unless a later policy explicitly changes this resolver.
     */
    public function resolve(Lecture $lecturer, string $sourceVersion): FacultyAccessEligibility
    {
        $status = strtolower(trim((string) $lecturer->employment_status));
        $isContractExpired = $lecturer->employment_type === 'contract'
            && $lecturer->contract_end_date !== null
            && $lecturer->contract_end_date->lessThanOrEqualTo(today());
        $reason = ! $lecturer->is_active
            ? 'ineligible_faculty_inactive'
            : ($isContractExpired
                ? 'ineligible_contract_expired'
                : match ($status) {
                    'active', 'employed', 'contract_active' => 'eligible_active_employment',
                    'on_leave' => 'eligible_leave',
                    'sabbatical' => 'eligible_sabbatical',
                    default => 'ineligible_employment_status',
                });
        $isEligible = str_starts_with($reason, 'eligible_');

        return new FacultyAccessEligibility(
            lecturerId: (int) $lecturer->id,
            userId: (int) $lecturer->user_id,
            tokenSubjectType: $lecturer->getMorphClass(),
            isEligible: $isEligible,
            reason: $reason,
            deduplicationKey: $this->deduplicationKey($lecturer, $sourceVersion, $reason),
            evaluatedAt: now()->toISOString(),
        );
    }

    public function revokeForUnlinkedAccount(Lecture $lecturer, int $userId, string $sourceVersion): FacultyAccessEligibility
    {
        return new FacultyAccessEligibility(
            lecturerId: (int) $lecturer->id,
            userId: $userId,
            tokenSubjectType: $lecturer->getMorphClass(),
            isEligible: false,
            reason: 'ineligible_faculty_identity_unlinked',
            deduplicationKey: $this->deduplicationKey($lecturer, $sourceVersion, 'ineligible_faculty_identity_unlinked'),
            evaluatedAt: now()->toISOString(),
        );
    }

    private function deduplicationKey(Lecture $lecturer, string $sourceVersion, string $reason): string
    {
        return 'faculty-access:'.hash('sha256', implode(':', [
            (string) $lecturer->id,
            (string) $lecturer->user_id,
            $sourceVersion,
            $reason,
        ]));
    }
}
