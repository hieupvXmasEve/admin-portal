<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support\Integrity;

/**
 * A single finance data-integrity invariant.
 *
 * The SQL templates carry a `{scope}` token that the auditor replaces with
 * `1=1` for a global run, or with `studentScopePredicate` (its `{ids}` token
 * filled by a sanitized int CSV) for a student-scoped run.
 */
final class FinanceInvariant
{
    public function __construct(
        public readonly string $code,
        public readonly string $severity,
        public readonly string $label,
        public readonly string $countSqlTemplate,
        public readonly string $sampleSqlTemplate,
        public readonly string $studentScopePredicate,
    ) {}
}
