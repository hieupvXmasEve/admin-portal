# Validation

## Proof Strategy

Prove the shared eligibility rule through the course-offering HTTP surface so
the tests cover both backend props and direct-submit enforcement.

## Test Plan

| Layer | Cases |
| --- | --- |
| Unit | Assignable scope includes a normal active template and excludes title-based and relation-based Canvas templates. |
| Integration | Create page omits protected templates; store rejects protected ids; edit page omits another offering's protected template; update rejects another offering's protected id; mapping reserves a template before assignment sync; a Canvas-linked offering can preserve its own current template; duplicate cannot bypass the reservation. |
| E2E | Manual browser smoke on course offering create and edit selects after targeted tests pass. |
| Platform | Not required. |
| Performance | Not required; the dropdown query is bounded to active syllabus templates. |
| Logs/Audit | Not required; validation errors are the expected proof surface. |

## Fixtures

- One active reusable syllabus template.
- One active template whose title contains `Canvas`.
- One active renamed template assigned to an offering mapped to Canvas before
  assignment sync.
- One Canvas-synced offering editing its own assigned Canvas template.

## Commands

```text
./scripts/dev.sh test tests/Feature/CourseOffering/CanvasSyllabusTemplateGuardTest.php
./scripts/dev.sh artisan pint --test app/Models/SyllabusTemplate.php app/Rules/CourseOffering/AssignableSyllabusTemplate.php app/Http/Requests/StoreCourseOfferingRequest.php app/Http/Requests/UpdateCourseOfferingRequest.php app/Http/Controllers/Web/CourseOfferingController.php tests/Feature/CourseOffering/CanvasSyllabusTemplateGuardTest.php
```

## Acceptance Evidence

- `./scripts/dev.sh test tests/Feature/CourseOffering/CanvasSyllabusTemplateGuardTest.php`
  passed: 6 tests, 36 assertions.
- Targeted Pint check passed through
  `./scripts/dev.sh composer exec pint -- --test ...`.
- `git diff --check` passed for story files.
- Browser E2E smoke was not run.
