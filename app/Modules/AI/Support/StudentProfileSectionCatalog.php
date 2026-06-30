<?php

declare(strict_types=1);

namespace App\Modules\AI\Support;

class StudentProfileSectionCatalog
{
    public const VERSION = 'student-profile-sections:v1';

    public const TOOL_SCHEMA_VERSION = 'get_entity_profile:v1';

    public function version(): string
    {
        return self::VERSION;
    }

    public function toolSchemaVersion(): string
    {
        return self::TOOL_SCHEMA_VERSION;
    }

    /**
     * @return list<string>
     */
    public function sectionKeys(): array
    {
        return array_keys($this->sections());
    }

    /**
     * @return array<string, mixed>|null
     */
    public function section(string $key): ?array
    {
        $normalized = $this->normalizeSection($key);

        return $normalized ? $this->sections()[$normalized] : null;
    }

    /**
     * @param  list<string>  $sections
     * @return list<string>
     */
    public function normalizeSections(array $sections): array
    {
        $normalized = [];

        foreach ($sections as $section) {
            $key = $this->normalizeSection($section);

            if ($key !== null && ! in_array($key, $normalized, true)) {
                $normalized[] = $key;
            }
        }

        return $normalized;
    }

    public function normalizeSection(string $section): ?string
    {
        $candidate = str($section)->lower()->squish()->replace('-', '_')->toString();

        if (array_key_exists($candidate, $this->sections())) {
            return $candidate;
        }

        foreach ($this->sections() as $key => $definition) {
            if (in_array($candidate, $definition['aliases'], true)) {
                return $key;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public function safeErrorCodes(): array
    {
        return [
            'invalid_profile_request_schema',
            'unsupported_entity_type',
            'unsupported_profile_section',
            'invalid_entity_reference',
            'expired_entity_reference',
            'forbidden_by_permission',
            'forbidden_by_campus_scope',
            // A clarification result (ambiguous campus over MCP), NOT a deny.
            'clarification_required',
            'entity_not_found',
            'profile_resolver_missing',
            'source_query_failed',
            'source_result_truncated',
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function sections(): array
    {
        return [
            'identity' => [
                'key' => 'identity',
                'aliases' => ['identity', 'basic', 'student_identity'],
                'required_permission' => 'view_student',
                'source' => 'academic',
                'source_report' => 'academic.student-profile.identity',
                'source_reference_policy' => 'profile_section_summary',
                'hidden_section' => 'student.identity',
                'fields' => [
                    'student_code',
                    'display_name',
                    'campus_code',
                    'program_code',
                    'specialization_code',
                    'curriculum_version_code',
                    'status',
                    'academic_status',
                    'intake_semester_code',
                    'admission_date',
                    'expected_graduation_date',
                    'gc_level_snapshot',
                ],
            ],
            'academic_summary' => [
                'key' => 'academic_summary',
                'aliases' => ['academic', 'summary', 'academic_status'],
                'required_permission' => 'view_student_summary',
                'source' => 'academic',
                'source_report' => 'academic.student-profile.summary',
                'source_reference_policy' => 'profile_section_summary',
                'hidden_section' => 'student.academic_summary',
                'fields' => [
                    'current_semester_code',
                    'semester_gpa',
                    'cumulative_gpa',
                    'academic_standing',
                    'registration_counts',
                    'credit_points',
                    'active_holds_count',
                    'retake_count',
                ],
            ],
            'enrollments' => [
                'key' => 'enrollments',
                'aliases' => ['enrollment', 'registrations', 'courses'],
                'required_permission' => 'view_student_summary',
                'source' => 'academic',
                'source_report' => 'academic.student-profile.enrollments',
                'source_reference_policy' => 'profile_section_summary',
                'hidden_section' => 'student.enrollments',
                'fields' => [
                    'semester_code',
                    'unit_code',
                    'unit_name',
                    'section_code',
                    'registration_status',
                    'credit_points',
                    'attempt_number',
                    'is_retake',
                ],
            ],
            'attendance_summary' => [
                'key' => 'attendance_summary',
                'aliases' => ['attendance', 'attendance_overview'],
                'required_permission' => 'view_student_summary',
                'source' => 'academic',
                'source_report' => 'academic.student-profile.attendance-summary',
                'source_reference_policy' => 'profile_section_summary',
                'hidden_section' => 'student.attendance_summary',
                'fields' => [
                    'summary',
                    'courses',
                ],
            ],
            'finance_summary' => [
                'key' => 'finance_summary',
                'aliases' => ['finance', 'balance', 'student_360'],
                'required_permission' => 'view_finance_student_overview',
                'source' => 'finance',
                'source_report' => 'finance.student-profile.summary',
                'source_reference_policy' => 'profile_section_summary',
                'hidden_section' => 'student.finance_summary',
                'fields' => [
                    'balance',
                    'dng',
                    'installments',
                    'exception',
                ],
            ],
            'lifecycle_actions' => [
                'key' => 'lifecycle_actions',
                'aliases' => ['actions', 'lifecycle', 'action_history'],
                'required_permission' => 'view_student_action',
                'source' => 'academic',
                'source_report' => 'academic.student-profile.lifecycle-actions',
                'source_reference_policy' => 'profile_section_summary',
                'hidden_section' => 'student.lifecycle_actions',
                'fields' => [
                    'action_type',
                    'signed_at',
                    'effective_at',
                    'semester_codes',
                    'campus_codes',
                    'previous_status',
                    'new_status',
                    'decision_number',
                ],
            ],
        ];
    }
}
