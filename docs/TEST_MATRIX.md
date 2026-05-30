# Test Matrix

This file maps product behavior to proof.

No product behavior has been defined or implemented yet. Do not mark a row
implemented until tests or validation evidence exist.

## Status Values

| Status | Meaning |
| --- | --- |
| planned | Accepted as intended behavior, not implemented |
| in_progress | Actively being built |
| implemented | Implemented and proof exists |
| changed | Contract changed after earlier implementation |
| retired | No longer part of the product contract |

## Matrix

| Story | Contract | Unit | Integration | E2E | Platform | Status | Evidence |
| --- | --- | --- | --- | --- | --- | --- | --- |
| S-001-course-survey-class-result-download | Admin/staff can download per-class course survey aggregate results without raw student identifiers | yes | yes | no | no | implemented | `tests/Feature/Form/SurveyResultDownloadTest.php`; targeted Pint, ESLint, Prettier passed; repo-wide `vue-tsc` still fails on pre-existing typing drift |
| S-001-filter-lecturers-by-semester-program | Staff can filter the lecturer directory by assigned semester and teaching program/unit type, including EGC, within the current campus | yes | yes | no | no | implemented | `tests/Feature/Lecture/ListLecturesQueryTest.php`; targeted Pint, ESLint, Prettier passed; repo-wide `vue-tsc` still fails on pre-existing typing drift |

## Evidence Rules

- Unit proof covers pure domain and application rules.
- Integration proof covers backend enforcement, data integrity, provider
  behavior, jobs, or service contracts.
- E2E proof covers user-visible browser flows.
- Platform proof covers only shell, deployment, mobile, desktop, or runtime
  behavior that cannot be proven in lower layers.
- A story can be implemented without every proof column if the story packet
  explains why.
