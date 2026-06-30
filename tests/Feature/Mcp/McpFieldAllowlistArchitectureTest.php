<?php

declare(strict_types=1);

use App\Modules\AI\Support\EntityCatalog;
use App\Modules\AI\Support\StudentProfileSectionCatalog;

/**
 * Invariant seam (ADR-0011, "the tool catalogs are the field allowlist"): the data an
 * external model can ever receive over MCP is bounded by the curated tool catalogs.
 * This test locks that surface to an approved allowlist, so adding a field to a catalog
 * fails the build until someone adds it here on purpose, AND it independently rejects any
 * sensitive field name (national id, address, phone, email, date of birth, …) even if
 * someone tries to wave it through the allowlist.
 */

/**
 * The exact set of field names the MCP tools are allowed to expose. Mirrors the curated
 * catalogs today. Extending a catalog WITHOUT adding the field here is the failure that
 * forces a review.
 *
 * @return list<string>
 */
function approvedMcpFieldAllowlist(): array
{
    return [
        // search_entities result fields (EntityCatalog)
        'entity_ref', 'student_code', 'display_name', 'program_code', 'intake_semester_code',
        'status', 'code', 'name', 'is_active', 'start_date', 'end_date', 'section_code',
        'unit_code', 'unit_title', 'semester_code', 'course_status', 'label',
        // get_entity_profile section fields (StudentProfileSectionCatalog) — identity
        'campus_code', 'specialization_code', 'curriculum_version_code', 'academic_status',
        'admission_date', 'expected_graduation_date', 'gc_level_snapshot',
        // academic_summary
        'current_semester_code', 'semester_gpa', 'cumulative_gpa', 'academic_standing',
        'registration_counts', 'credit_points', 'active_holds_count', 'retake_count',
        // enrollments
        'unit_name', 'registration_status', 'attempt_number', 'is_retake',
        // attendance_summary
        'summary', 'courses',
        // finance_summary
        'balance', 'dng', 'installments', 'exception',
        // lifecycle_actions
        'action_type', 'signed_at', 'effective_at', 'semester_codes', 'campus_codes',
        'previous_status', 'new_status', 'decision_number',
    ];
}

/**
 * Substrings that may never appear in any MCP-exposed field name. Contact PII, identity
 * documents, demographics, and health/family data are out of bounds for this surface.
 *
 * @return list<string>
 */
function bannedMcpFieldSubstrings(): array
{
    return [
        'national_id', 'citizen', 'id_card', 'passport', 'address', 'phone', 'mobile',
        'email', 'date_of_birth', 'birth', 'dob', 'gender', 'religion', 'ethnic',
        'guardian', 'parent', 'family', 'health', 'medical', 'salary', 'income',
        'ssn', 'tax_code',
    ];
}

/**
 * @return list<string>
 */
function mcpExposedFields(): array
{
    $entity = collect(app(EntityCatalog::class)->entities())
        ->flatMap(fn (array $definition): array => $definition['result_fields'] ?? []);

    $profile = collect(app(StudentProfileSectionCatalog::class)->sections())
        ->flatMap(fn (array $definition): array => $definition['fields'] ?? []);

    return $entity->merge($profile)->unique()->values()->all();
}

it('keeps every MCP-exposed field within the approved allowlist', function () {
    $unapproved = array_values(array_diff(mcpExposedFields(), approvedMcpFieldAllowlist()));

    expect($unapproved)->toBe([], 'New MCP-exposed field(s) detected: '.implode(', ', $unapproved)
        .' — add them to approvedMcpFieldAllowlist() only after confirming they are not sensitive.');
});

it('exposes no sensitive field name over MCP', function () {
    foreach (mcpExposedFields() as $field) {
        foreach (bannedMcpFieldSubstrings() as $banned) {
            expect(str_contains($field, $banned))->toBeFalse(
                "Sensitive field '{$field}' (matched '{$banned}') must never be exposed over MCP."
            );
        }
    }
});

it('keeps the approved allowlist itself free of sensitive field names', function () {
    // Belt-and-braces: the allowlist cannot be used to smuggle a sensitive field in.
    foreach (approvedMcpFieldAllowlist() as $field) {
        foreach (bannedMcpFieldSubstrings() as $banned) {
            expect(str_contains($field, $banned))->toBeFalse(
                "Allowlisted field '{$field}' matches banned substring '{$banned}'."
            );
        }
    }
});
