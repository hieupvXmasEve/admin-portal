<?php

declare(strict_types=1);

namespace App\Modules\Admissions\Queries;

use App\Models\StudentApplication;
use App\Models\User;
use App\Modules\Admissions\Models\CrmValueMapping;
use App\Shared\Contracts\Admissions\ApplicationProgramMappingReader;

/**
 * One readiness rule, two consumers: the approve guard and the list/detail
 * badge (ponytail — duplicating this in Vue is how they drift apart).
 *
 * Short-circuit order matters: `curriculum_match_count` is derived from the
 * *resolved* program/intake, so a null program yields 0 matches —
 * indistinguishable from a genuinely missing curriculum version. Campus,
 * program, and intake must all resolve before the curriculum count means
 * anything.
 */
final class GetApplicationConversionReadinessQuery
{
    /**
     * Memoizes `resolve()` per distinct (campus_code, intended_program,
     * intake, intended_specialization) tuple for this instance's lifetime.
     * `resolve()` runs ~5 queries including an uncached campus lookup; a list
     * page reuses one query instance across every row via `->through()`, and
     * a freshly synced batch typically shares identical (often all-null)
     * inputs, so this collapses what would otherwise be O(rows) resolver
     * calls down to O(distinct combinations) — usually one.
     *
     * @var array<string, array{campus_id: ?int, program_id: ?int, intake_semester_id: ?int, curriculum_version_id: ?int, curriculum_match_count: int, specialization_id: ?int}>
     */
    private array $resolveCache = [];

    public function __construct(private readonly ApplicationProgramMappingReader $programMappingReader) {}

    /**
     * @return array{
     *     ready: bool,
     *     missing: list<array{field: string, crm_value: string|null, kind: string|null, reason: string}>,
     *     warnings: list<array{field: string, crm_value: string, kind: string}>,
     * }
     */
    public function handle(StudentApplication $application): array
    {
        $intent = [
            'campus_code' => $application->campus_code,
            'intended_program' => $application->intended_program,
            'intake' => $application->intake,
            'intended_specialization' => $application->intended_specialization,
        ];
        $cacheKey = json_encode($intent);
        $resolved = $this->resolveCache[$cacheKey] ??= $this->programMappingReader->resolve($intent);

        $missing = [];

        if (($resolved['campus_id'] ?? null) === null) {
            $missing[] = ['field' => 'campus_code', 'crm_value' => $application->crm_campus, 'kind' => CrmValueMapping::KIND_CAMPUS, 'reason' => 'unmapped'];
        }
        if (($resolved['program_id'] ?? null) === null) {
            $missing[] = ['field' => 'intended_program', 'crm_value' => $application->crm_major, 'kind' => CrmValueMapping::KIND_MAJOR, 'reason' => 'unmapped'];
        }
        if (($resolved['intake_semester_id'] ?? null) === null) {
            $missing[] = ['field' => 'intake', 'crm_value' => null, 'kind' => CrmValueMapping::KIND_INTAKE, 'reason' => 'unset'];
        }

        // Approval provisions a User with a UNIQUE email (EloquentStudentAccessWriter::provision).
        // The DB email index was intentionally dropped for the CRM path (D1/finding 4),
        // so a blank or already-used email is a real approval-time failure, not a
        // schema guard — it must surface here or approval throws an opaque
        // QueryException with no readiness signal beforehand.
        $email = $application->email;
        if ($email === null || trim($email) === '') {
            $missing[] = ['field' => 'email', 'crm_value' => null, 'kind' => null, 'reason' => 'blank_email'];
        } elseif (User::query()->where('email', $email)->exists()) {
            $missing[] = ['field' => 'email', 'crm_value' => $email, 'kind' => null, 'reason' => 'duplicate_email'];
        }

        // Only meaningful once campus/program/intake all resolved — otherwise
        // a null program alone yields 0 matches and reports a phantom
        // "missing curriculum" alongside the real "unmapped major" blocker.
        if ($missing === []) {
            $matchCount = (int) ($resolved['curriculum_match_count'] ?? 0);

            if ($matchCount === 0) {
                $missing[] = ['field' => 'curriculum_version', 'crm_value' => null, 'kind' => null, 'reason' => 'no_curriculum'];
            } elseif ($matchCount > 1) {
                $missing[] = ['field' => 'curriculum_version', 'crm_value' => null, 'kind' => null, 'reason' => 'ambiguous_curriculum'];
            }
        }

        return [
            'ready' => $missing === [],
            'missing' => $missing,
            'warnings' => $this->warnings($application),
        ];
    }

    /** @return list<array{field: string, crm_value: string, kind: string}> */
    private function warnings(StudentApplication $application): array
    {
        $warnings = [];

        $optional = [
            'scholarship' => CrmValueMapping::KIND_SCHOLARSHIP,
            'pathway_gateway' => CrmValueMapping::KIND_PATHWAY_GATEWAY,
            'uu_dai_gc' => CrmValueMapping::KIND_UU_DAI_GC,
        ];

        foreach ($optional as $field => $kind) {
            $value = $application->{$field};
            if ($value === null || $value === '') {
                continue;
            }

            $mapped = CrmValueMapping::query()->where('kind', $kind)->where('crm_value', $value)->whereNotNull('local_code')->exists();
            if (! $mapped) {
                $warnings[] = ['field' => $field, 'crm_value' => $value, 'kind' => $kind];
            }
        }

        return $warnings;
    }
}
