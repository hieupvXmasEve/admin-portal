<?php

declare(strict_types=1);

namespace App\Modules\AI\Support;

use App\Models\CourseOffering;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;

class EntityCatalog
{
    public const VERSION = 'entity-catalog:v1';

    public const TOOL_SCHEMA_VERSION = 'search_entities:v1';

    /**
     * @return list<string>
     */
    public function entityKeys(): array
    {
        return array_keys($this->entities());
    }

    public function version(): string
    {
        return self::VERSION;
    }

    public function toolSchemaVersion(): string
    {
        return self::TOOL_SCHEMA_VERSION;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function entity(string $keyOrAlias): ?array
    {
        $key = $this->normalizeEntityType($keyOrAlias);

        return $key ? $this->entities()[$key] : null;
    }

    /**
     * @param  list<string>  $entityTypes
     * @return list<string>
     */
    public function normalizeEntityTypes(array $entityTypes): array
    {
        $normalized = [];

        foreach ($entityTypes as $entityType) {
            $key = $this->normalizeEntityType($entityType);

            if ($key !== null && ! in_array($key, $normalized, true)) {
                $normalized[] = $key;
            }
        }

        return $normalized;
    }

    public function normalizeEntityType(string $entityType): ?string
    {
        $candidate = str($entityType)->lower()->squish()->toString();

        if (array_key_exists($candidate, $this->entities())) {
            return $candidate;
        }

        foreach ($this->entities() as $key => $definition) {
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
            'invalid_entity_search_schema',
            'unsupported_entity_type',
            'unsupported_entity_filter',
            'invalid_entity_filter_value',
            'query_too_short',
            'result_limit_exceeded',
            'forbidden_by_permission',
            'forbidden_by_campus_scope',
            // A clarification result (ambiguous campus over MCP), NOT a deny.
            'clarification_required',
            'entity_resolver_missing',
            'entity_reference_encoding_failed',
            'source_query_failed',
            'source_result_truncated',
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function entities(): array
    {
        return [
            'student' => [
                'key' => 'student',
                'aliases' => ['student', 'sinh vien', 'sv'],
                'business_meaning' => 'A learner record in the current campus.',
                'model' => Student::class,
                'required_permission' => 'view_student',
                'campus_scope_rule' => 'current_campus_only',
                'search_fields' => ['student_id', 'full_name'],
                'result_fields' => ['entity_ref', 'student_code', 'display_name', 'program_code', 'intake_semester_code', 'status'],
                'allowed_filters' => ['semester', 'program_id'],
                'max_results' => 5,
                'source_report' => 'academic.entity-search.student',
                'source_reference_policy' => 'entity_candidate_list',
                'hidden_sections' => ['student_profile'],
            ],
            'program' => [
                'key' => 'program',
                'aliases' => ['program', 'major', 'nganh'],
                'business_meaning' => 'Academic program reference data.',
                'model' => Program::class,
                'required_permission' => 'view_program',
                'campus_scope_rule' => 'global_reference',
                'search_fields' => ['code', 'name'],
                'result_fields' => ['entity_ref', 'code', 'name'],
                'allowed_filters' => [],
                'max_results' => 5,
                'source_report' => 'academic.entity-search.program',
                'source_reference_policy' => 'entity_candidate_list',
                'hidden_sections' => [],
            ],
            'semester' => [
                'key' => 'semester',
                'aliases' => ['semester', 'term', 'ky'],
                'business_meaning' => 'Academic term reference data.',
                'model' => Semester::class,
                'required_permission' => 'view_semester',
                'campus_scope_rule' => 'global_reference',
                'search_fields' => ['code', 'name'],
                'result_fields' => ['entity_ref', 'code', 'name', 'is_active', 'start_date', 'end_date'],
                'allowed_filters' => [],
                'max_results' => 5,
                'source_report' => 'academic.entity-search.semester',
                'source_reference_policy' => 'entity_candidate_list',
                'hidden_sections' => [],
            ],
            'course_offering' => [
                'key' => 'course_offering',
                'aliases' => ['class', 'section', 'lop hoc'],
                'business_meaning' => 'Course offering section in a semester.',
                'model' => CourseOffering::class,
                'required_permission' => 'view_course_offering',
                'campus_scope_rule' => 'current_campus_only',
                'search_fields' => ['section_code', 'unit.code', 'unit.name', 'semester.code'],
                'result_fields' => ['entity_ref', 'section_code', 'unit_code', 'unit_title', 'semester_code', 'course_status', 'label'],
                'allowed_filters' => ['semester'],
                'max_results' => 5,
                'source_report' => 'academic.entity-search.course-offering',
                'source_reference_policy' => 'entity_candidate_list',
                'hidden_sections' => ['course_roster'],
            ],
        ];
    }
}
