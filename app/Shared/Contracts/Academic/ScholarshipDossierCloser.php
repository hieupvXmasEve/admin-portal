<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Academic;

/**
 * Closes an Academic scholarship-adjustment dossier once Finance has
 * approved the corresponding restoration proposal. Finance owns the
 * adjustment row (and its academic_dossier_id snapshot) — it resolves the
 * dossier id itself and passes it here rather than the adjustment id, so
 * Academic never has to resolve a Finance-owned identifier.
 */
interface ScholarshipDossierCloser
{
    public function closeDossier(int $dossierId): void;
}
