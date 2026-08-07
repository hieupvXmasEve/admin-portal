<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Academic;

/**
 * Tells the student their tuition generation was deferred pending a
 * scholarship-adjustment dossier decision. Finance (batch-studio charge
 * generation) consumes this through the Shared contract only — it never
 * touches Academic's event vocabulary or dossier model directly (ADR-0026
 * boundary).
 */
interface ScholarshipReviewDeferralNotifier
{
    /**
     * @param  int[]  $studentIds  Students whose tuition_term generation was
     *                             just skipped for $targetSemesterId.
     */
    public function notifyTuitionDeferred(array $studentIds, int $targetSemesterId): void;
}
