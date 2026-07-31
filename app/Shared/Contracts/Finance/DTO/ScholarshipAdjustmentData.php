<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Finance\DTO;

/**
 * Approved scholarship adjustment handed from the Academic dossier workflow to
 * Finance. Finance re-authorizes everything server-side (caller trust = zero);
 * this DTO only transports the decision facts.
 */
final readonly class ScholarshipAdjustmentData
{
    public function __construct(
        public int $student_id,
        public int $source_semester_id,
        public int $target_semester_id,
        /**
         * Same UNIT as the award's terms: a percent (0–100) for `percentage`
         * awards, a VND money amount for `fixed_amount` awards. Bounds are
         * validated against the original in that unit — a unit mix-up (15 VND
         * meant as 15%) passes bounds, so callers must convert carefully.
         */
        public float $adjusted_amount,
        public string $reason,
        public int $academic_dossier_id,
        public int $maker_user_id,
        public int $checker_user_id,
        /** hash of the award terms the decision was approved against — see fingerprint(). */
        public string $award_fingerprint,
    ) {}

    /**
     * Canonical fingerprint of an award's terms (code|type|amount). Academic
     * computes it at decision approval; Finance recomputes at apply time — a
     * mismatch means the award mutated in between and the apply is refused.
     */
    public static function fingerprint(string $scholarshipCode, string $type, string $amount): string
    {
        return hash('sha256', $scholarshipCode.'|'.$type.'|'.$amount);
    }
}
