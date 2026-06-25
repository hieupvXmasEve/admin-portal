# Validation

## Proof Strategy

Prove the form builder produces schemes that pass the existing validator and
calculators for every documented Metropolia rule shape; that `code` persists
and is enforced as the grading join key; and that the default weighted path
still saves `grading_scheme = null` unchanged.

## Test Plan

| Layer | Cases |
| --- | --- |
| Unit (PHP) | Store/Update requests: code required when custom scheme present; duplicate codes in one submission rejected; scheme component code not in assessment codes rejected; default path (no scheme) passes without code requirement. |
| Unit (TS) | Builder serializer emits valid scheme JSON for v1 (linear/threshold/direct/pass_fail/gate) and v2 (formula + pass_requirements); deserializer round-trips an existing scheme into form state. |
| Integration | Create/update persists `assessment_components.code`; each worked example payload validates and previews via the existing preview endpoint; mismatch returns 422. |
| E2E | Admin create/edit submits a v1 and a v2 scheme built through the form (no JSON); guide-modal "Áp dụng ví dụ" loads a scheme into the builder. |
| Platform | Inertia v3 props stay snake_case; routes use helpers; no whole-project type-check (per known dev-container OOM) — use per-file lint/host/CI. |
| Regression | Default weighted course save still emits `grading_scheme = null` and identical finalization output. |

## Fixtures

- Admin user with `view_syllabus`, `create_syllabus`, `edit_syllabus`.
- Syllabus template with `grading_scheme = null` (default path).
- Assessment components with explicit codes (`LAB`, `QUIZ`, `EXAM`,
  `ASSIGN`).
- Worked-example scheme payloads: Software 1 Programming (v1 linear+gate),
  Database (v1 pass_fail), Maths & Physics (v1 threshold sum), Cloud Computing
  (v2 formula), Health Technology (v2 formula).

## Commands

```bash
./scripts/dev.sh test tests/Feature/Academic/Grading
./scripts/dev.sh npm run lint
./scripts/dev.sh npm run format:check
./scripts/dev.sh composer exec pint -- --dirty --format agent
git diff --check -- resources/js/pages/syllabus app/Http app/Actions/SyllabusTemplate app/Modules/Academic docs/stories/E-academic-grading-rule-engine
```

Note: whole-project `npm run type-check` OOMs in `swinx-app-dev`; rely on
per-file eslint plus host/CI for type checking.

## Acceptance Evidence

Complete when: backend request/action tests pass; each worked example built
through the form validates and previews via the existing endpoint; default
weighted path regression passes; frontend checks pass or record only
pre-existing unrelated diagnostics; and a Harness trace captures the
create/edit/show UI plus the guide modal.
