# Student Academic Roadmap API

## Endpoint

`GET /api/v1/student/curriculum/roadmap`

This endpoint returns the curriculum structure for the authenticated student, enriched with their academic progress (specifically whether units have been completed). It is designed to power the visual Roadmap UI.

## Response Structure

The response follows the standard `ApiResponse` format. The `data` property contains the roadmap object.

### TypeScript Interface

```typescript
interface ApiResponse<T> {
    success: boolean;
    message: string;
    data: T;
}

interface RoadmapResponse {
    id: number;
    version_code: string;
    program: {
        id: number;
        name: string;
        code: string;
    };
    specialization: {
        id: number;
        name: string;
        code: string;
    };
    effective_from_semester: {
        id: number;
        name: string;
        code: string;
    };
    curriculum_units: RoadmapCurriculumUnit[];
}

interface RoadmapCurriculumUnit {
    id: number;
    unit_id: number;
    semester_number: number;
    year_level: number;
    status: 'completed' | 'pending'; // Derived from AcademicRecord.completion_status === 'completed'
    unit: Unit | null;
}

interface Unit {
    id: number;
    code: string;
    name: string;
    credit_points: number;
    prerequisite_groups: PrerequisiteGroup[];
}

interface PrerequisiteGroup {
    logic_operator: 'AND' | 'OR';
    conditions: PrerequisiteCondition[];
}

interface PrerequisiteCondition {
    type: string;
    required_unit_id: number | null;
    required_unit_code?: string;
}
```

## Logic

1. **Curriculum Fetching**: Relies on `$student->curriculumVersion` to get the base structure.
2. **Progress Integration**: Checks `AcademicRecord`s for the student where `completion_status` is `'completed'`.
3. **Status Field**:
    - `completed`: The student has successfully passed this unit.
    - `pending`: The student has not yet completed this unit (could be in progress, failed, or not started).

## Key Differences from Web Controller

- **Status Field**: The key addition is the `status` field on each `curriculum_unit`.
- **User Context**: Data is strictly filtered for the authenticated student.
