# Grade Breakdown API And Portal Display Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Expose safe grade display data for custom grading schemes and update student and lecturer portals to render those results without local percentage-derived grade assumptions.

**Architecture:** Add a backend presenter that converts `AcademicRecord` plus `grade_breakdown` into a backwards-compatible `grade_display` object. Student and lecturer APIs add optional fields, then both Nuxt portals update shared types and small display components while preserving existing default weighted-percentage behavior.

**Tech Stack:** Laravel 13, PHP 8.4, Sanctum API routes, Nuxt student and lecturer portals, TypeScript, Pest feature tests, portal `pnpm` checks.

---

## Scope Guardrails

Run `./scripts/portal-status.sh` before touching portal files. Keep root Swinx git state and nested portal git states separate. This story does not change grade entry or rule calculation; it only presents the result already stored by S-001.

## File Structure

Create:

- `app/Modules/Academic/Grading/Presenters/GradeDisplayPresenter.php` - one display-safe formatter.
- `tests/Feature/Api/V1/Student/GradeBreakdownApiTest.php`
- `tests/Feature/Api/V1/Lecturer/GradeBreakdownApiTest.php`

Modify:

- `app/Services/V1/Student/GradeService.php` - add `grade_display` to course grade payloads.
- `app/Services/AssessmentReportService.php` - add display data to grade matrix/report output.
- `docs/api/student/GRADES_API.md`
- `docs/api/student/course.md`
- `docs/api/lecturer/grade-management-api.md`
- `docs/api/lecturer/assessments.md`
- `FE/student-nuxt/shared/types/grade.ts`
- `FE/student-nuxt/shared/types/course.ts`
- `FE/student-nuxt/app/components/grade/CurriculumUnitCard.vue`
- `FE/student-nuxt/app/components/course-enrolled/CourseGrades.vue`
- `FE/lecturer-nuxt/shared/types/grade-matrix.ts`
- `FE/lecturer-nuxt/app/components/course/GradeBookTab.vue`
- `FE/lecturer-nuxt/app/components/grade/GradeTable.vue`

---

### Task 1: Add Backend Grade Display Presenter

**Files:**
- Create: `app/Modules/Academic/Grading/Presenters/GradeDisplayPresenter.php`
- Create: `tests/Feature/Api/V1/Student/GradeBreakdownApiTest.php`

- [ ] **Step 1: Write presenter-focused API test**

```php
<?php

declare(strict_types=1);

use App\Models\AcademicRecord;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('includes custom grade display data in student grades', function () {
    $student = Student::factory()->create();
    AcademicRecord::factory()->create([
        'student_id' => $student->id,
        'final_percentage' => 5,
        'final_letter_grade' => '5',
        'grade_points' => 5,
        'is_passed' => true,
        'grade_breakdown' => [
            'engine' => 'metropolia_v1',
            'scale' => 'numeric_0_5',
            'final_label' => '5',
            'final_numeric' => 5,
            'components' => [
                ['code' => 'EXAM', 'label' => 'Exam', 'raw_percentage' => 88, 'converted_grade' => 5, 'requirement_status' => 'passed'],
            ],
        ],
    ]);

    $this->actingAs($student->user, 'sanctum')
        ->getJson('/api/v1/student/grades')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.grades_by_semester.0.curriculum_units.0.grade_display.scheme_engine', 'metropolia_v1')
        ->assertJsonPath('data.grades_by_semester.0.curriculum_units.0.grade_display.final_label', '5');
});
```

- [ ] **Step 2: Run test and verify it fails**

Run:

```bash
./scripts/dev.sh test tests/Feature/Api/V1/Student/GradeBreakdownApiTest.php
```

Expected: FAIL because `grade_display` is not present.

- [ ] **Step 3: Implement presenter**

```php
<?php

declare(strict_types=1);

namespace App\Modules\Academic\Grading\Presenters;

use App\Models\AcademicRecord;

final class GradeDisplayPresenter
{
    /** @return array<string, mixed> */
    public function present(AcademicRecord $record): array
    {
        $breakdown = is_array($record->grade_breakdown) ? $record->grade_breakdown : [];
        $engine = (string) ($breakdown['engine'] ?? 'default_weighted_percentage');
        $scale = (string) ($breakdown['scale'] ?? 'percentage');

        return [
            'scheme_engine' => $engine,
            'scale' => $scale,
            'final_label' => (string) ($breakdown['final_label'] ?? $record->final_letter_grade ?? ''),
            'final_numeric' => $breakdown['final_numeric'] ?? ($record->final_percentage !== null ? (float) $record->final_percentage : null),
            'pass_status' => $record->is_passed ? 'passed' : 'failed',
            'components' => $this->components($breakdown['components'] ?? []),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function components(mixed $components): array
    {
        if (! is_array($components)) {
            return [];
        }

        return collect($components)
            ->filter(fn ($component) => is_array($component))
            ->map(fn (array $component) => [
                'code' => (string) ($component['code'] ?? $component['component'] ?? ''),
                'label' => (string) ($component['label'] ?? $component['code'] ?? ''),
                'raw_percentage' => $component['raw_percentage'] ?? null,
                'converted_grade' => $component['converted_grade'] ?? null,
                'requirement_status' => $component['requirement_status'] ?? null,
            ])
            ->values()
            ->all();
    }
}
```

- [ ] **Step 4: Wire presenter into student grade payloads**

In `app/Services/V1/Student/GradeService.php`, inject through `app(GradeDisplayPresenter::class)` where records are mapped:

```php
'grade_display' => app(\App\Modules\Academic\Grading\Presenters\GradeDisplayPresenter::class)->present($record),
```

Add this key beside existing `final_percentage`, `final_grade`, `numeric_grade`, and `grade_points` fields in curriculum unit, sub-unit, EGC, and course detail grade payloads.

- [ ] **Step 5: Run student API test**

Run:

```bash
./scripts/dev.sh test tests/Feature/Api/V1/Student/GradeBreakdownApiTest.php
```

Expected: PASS.

### Task 2: Add Lecturer API Display Data

**Files:**
- Create: `tests/Feature/Api/V1/Lecturer/GradeBreakdownApiTest.php`
- Modify: `app/Services/AssessmentReportService.php`

- [ ] **Step 1: Write lecturer API test**

```php
<?php

declare(strict_types=1);

use App\Models\AcademicRecord;
use App\Models\CourseOffering;
use App\Models\Lecture;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('includes final display labels in lecturer grade matrix', function () {
    $lecturer = Lecture::factory()->create();
    $course = CourseOffering::factory()->create(['lecture_id' => $lecturer->id]);
    AcademicRecord::factory()->create([
        'course_offering_id' => $course->id,
        'final_letter_grade' => 'PASS',
        'is_passed' => true,
        'grade_breakdown' => [
            'engine' => 'metropolia_v1',
            'scale' => 'pass_fail',
            'final_label' => 'PASS',
            'final_numeric' => null,
        ],
    ]);

    $this->actingAs($lecturer, 'sanctum')
        ->getJson("/api/v1/lecturer/courses/{$course->id}/assessments/report/grade-matrix")
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.grade_matrix.student_grades.0.grade_display.final_label', 'PASS');
});
```

- [ ] **Step 2: Run test and verify it fails**

Run:

```bash
./scripts/dev.sh test tests/Feature/Api/V1/Lecturer/GradeBreakdownApiTest.php
```

Expected: FAIL because lecturer grade matrix does not include `grade_display`.

- [ ] **Step 3: Update report service**

In `AssessmentReportService::generateGradeMatrix`, load academic records for the course and add:

```php
$displayPresenter = app(\App\Modules\Academic\Grading\Presenters\GradeDisplayPresenter::class);

'grade_display' => $academicRecord
    ? $displayPresenter->present($academicRecord)
    : null,
```

Place this key beside the current total/final grade fields for each student row.

- [ ] **Step 4: Run lecturer API test**

Run:

```bash
./scripts/dev.sh test tests/Feature/Api/V1/Lecturer/GradeBreakdownApiTest.php
```

Expected: PASS.

### Task 3: Update Student Portal Types And Display

**Files:**
- Modify: `FE/student-nuxt/shared/types/grade.ts`
- Modify: `FE/student-nuxt/shared/types/course.ts`
- Modify: `FE/student-nuxt/app/components/grade/CurriculumUnitCard.vue`
- Modify: `FE/student-nuxt/app/components/course-enrolled/CourseGrades.vue`

- [ ] **Step 1: Run portal status**

Run:

```bash
./scripts/portal-status.sh
```

Expected: command prints nested repo state for `FE/student-nuxt` and `FE/lecturer-nuxt`.

- [ ] **Step 2: Add shared student type**

Add to `FE/student-nuxt/shared/types/grade.ts` and export from `FE/student-nuxt/shared/types/course.ts` where course detail types live:

```ts
export interface GradeDisplayComponent {
  code: string
  label: string
  raw_percentage: number | string | null
  converted_grade: number | string | null
  requirement_status: string | null
}

export interface GradeDisplay {
  scheme_engine: string
  scale: 'percentage' | 'numeric_0_5' | 'pass_fail' | string
  final_label: string
  final_numeric: number | string | null
  pass_status: 'passed' | 'failed' | string
  components: GradeDisplayComponent[]
}
```

Add optional `grade_display?: GradeDisplay | null` to `CurriculumUnit`, `SubUnit`, `EGCUnit`, and `TotalGrade`.

- [ ] **Step 3: Prefer display label in student components**

In `CurriculumUnitCard.vue` and `CourseGrades.vue`, add helpers:

```ts
function displayGradeLabel(record: { grade_display?: GradeDisplay | null, final_grade?: string | null, final_letter_grade?: string | null, final_percentage?: string | number | null }) {
  if (record.grade_display?.final_label)
    return record.grade_display.final_label

  return record.final_grade ?? record.final_letter_grade ?? '-'
}
```

Use this label where the component currently derives a grade from percentage.

- [ ] **Step 4: Run student portal checks**

Run:

```bash
(cd FE/student-nuxt && pnpm lint && pnpm typecheck && pnpm build)
```

Expected: PASS or record unrelated baseline diagnostics.

### Task 4: Update Lecturer Portal Types And Display

**Files:**
- Modify: `FE/lecturer-nuxt/shared/types/grade-matrix.ts`
- Modify: `FE/lecturer-nuxt/app/components/course/GradeBookTab.vue`
- Modify: `FE/lecturer-nuxt/app/components/grade/GradeTable.vue`

- [ ] **Step 1: Add lecturer grade display type**

In `FE/lecturer-nuxt/shared/types/grade-matrix.ts`, add the same `GradeDisplay` and `GradeDisplayComponent` interfaces from Task 3. Add `grade_display?: GradeDisplay | null` to the student grade row type used by `GradeBookTab.vue`.

- [ ] **Step 2: Render backend final label**

In `GradeBookTab.vue`, prefer:

```ts
studentGrade.grade_display?.final_label ?? studentGrade.final_grade ?? '-'
```

In `GradeTable.vue`, keep editable score fields unchanged and only use
`grade_display` in read-only final display areas.

- [ ] **Step 3: Run lecturer portal checks**

Run:

```bash
(cd FE/lecturer-nuxt && pnpm lint && pnpm typecheck && pnpm build)
```

Expected: PASS or record unrelated baseline diagnostics.

### Task 5: Docs, Verification, And Harness

**Files:**
- Modify: `docs/api/student/GRADES_API.md`
- Modify: `docs/api/student/course.md`
- Modify: `docs/api/lecturer/grade-management-api.md`
- Modify: `docs/api/lecturer/assessments.md`
- Modify: `docs/stories/E-academic-grading-rule-engine/S-004-grade-breakdown-api-and-portal-display/validation.md`

- [ ] **Step 1: Document `grade_display`**

Add this response fragment to the four API docs:

```json
"grade_display": {
  "scheme_engine": "metropolia_v1",
  "scale": "numeric_0_5",
  "final_label": "5",
  "final_numeric": 5,
  "pass_status": "passed",
  "components": [
    {
      "code": "EXAM",
      "label": "Exam",
      "raw_percentage": 88,
      "converted_grade": 5,
      "requirement_status": "passed"
    }
  ]
}
```

- [ ] **Step 2: Run full verification**

Run:

```bash
./scripts/portal-status.sh
./scripts/dev.sh test tests/Feature/Api/V1/Student/GradeBreakdownApiTest.php
./scripts/dev.sh test tests/Feature/Api/V1/Lecturer/GradeBreakdownApiTest.php
./scripts/dev.sh artisan pint app/Modules/Academic app/Services/V1/Student app/Services/AssessmentReportService.php app/Http/Resources tests/Feature/Api/V1
(cd FE/student-nuxt && pnpm lint && pnpm typecheck && pnpm build)
(cd FE/lecturer-nuxt && pnpm lint && pnpm typecheck && pnpm build)
git diff --check -- app docs FE/student-nuxt FE/lecturer-nuxt
```

Expected: backend tests and targeted formatting pass; portal commands pass or record unrelated baseline diagnostics.

- [ ] **Step 3: Update Harness**

Run:

```bash
./scripts/harness story update --id S-004-grade-breakdown-api-and-portal-display --status implemented
./scripts/harness trace --story S-004-grade-breakdown-api-and-portal-display --summary "Grade breakdown API and portal display implemented" --actions "Added presenter, API payloads, portal types, portal display, and docs" --changed "app/Modules/Academic app/Services docs/api FE/student-nuxt FE/lecturer-nuxt tests/Feature/Api/V1" --outcome completed
```

## Self-Review

Spec coverage: S-004 covers safe backend presenter, student API, lecturer API, both portals, and API docs.

Self-audit scan: completed with zero matches for the forbidden terms pattern.

Type consistency: `grade_display`, `GradeDisplay`, and `GradeDisplayPresenter` are named consistently across backend and both portals.
