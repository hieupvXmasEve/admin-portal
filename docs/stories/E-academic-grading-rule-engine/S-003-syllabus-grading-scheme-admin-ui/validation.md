# Validation

## Proof Strategy

Prove the UI can round-trip `grading_scheme`, validation rejects malformed
schemes, preview uses the backend calculator, and default templates still save
with `grading_scheme = null`.

## Test Plan

| Layer | Cases |
| --- | --- |
| Unit | JSON helper formats, parses, and normalizes supported scheme choices. |
| Integration | Preview endpoint returns calculated output for sample scores and validation errors for malformed schemes. |
| E2E | Admin create/edit pages submit default and custom scheme payloads. |
| Platform | Inertia v3 props remain snake_case and routes use route helpers where available. |
| Performance | Preview processes one sample payload at a time. |
| Logs/Audit | Save path records normal syllabus audit activity; preview path does not mutate. |

## Fixtures

- Admin user with `view_syllabus`, `create_syllabus`, and `edit_syllabus`.
- Syllabus template with `grading_scheme = null`.
- Syllabus template with `metropolia_v1`.
- Preview payload with assignment and exam component scores.

## Commands

```bash
./scripts/dev.sh test tests/Feature/Academic/Grading/SyllabusGradingSchemeAdminUiTest.php
./scripts/dev.sh npm run type-check
./scripts/dev.sh npm run lint
./scripts/dev.sh npm run format:check
./scripts/dev.sh artisan pint app/Http/Controllers/Web/SyllabusTemplateController.php app/Http/Requests/SyllabusTemplate app/Modules/Academic tests/Feature/Academic/Grading
git diff --check -- resources/js/pages/syllabus app/Http app/Modules/Academic docs/stories/E-academic-grading-rule-engine docs/superpowers/plans
```

## Acceptance Evidence

The story is complete when targeted backend tests pass, frontend checks either
pass or record only pre-existing unrelated diagnostics, and a Harness trace
captures the create/edit/show UI proof.
