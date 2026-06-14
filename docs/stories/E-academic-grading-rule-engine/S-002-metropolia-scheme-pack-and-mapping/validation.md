# Validation

## Proof Strategy

Prove that the catalog is valid, all reference courses have explicit scheme
keys, dry-run mode does not mutate templates, and commit mode only updates
templates listed in the mapping file.

## Test Plan

| Layer | Cases |
| --- | --- |
| Unit | Catalog loads every Metropolia scheme key and rejects malformed component rules. |
| Integration | Mapping action applies a scheme to the selected template and leaves unrelated templates unchanged. |
| E2E | Console dry-run prints counts and keeps database rows unchanged; console commit writes `grading_scheme`. |
| Platform | Docker wrapper command works through `./scripts/dev.sh artisan`. |
| Performance | Bounded by mapping file size; no broad recalculation. |
| Logs/Audit | Write mode emits a command summary and normal `SyllabusTemplate` audit activity. |

## Fixtures

- A unit with one active syllabus template for `software_1.programming`.
- A unit with no mapped syllabus template.
- A unit with two matching syllabus templates to prove ambiguity fails.
- A mapping JSON file with three entries: valid, missing, and ambiguous.

## Commands

```bash
./scripts/dev.sh test tests/Feature/Academic/Grading/MetropoliaSchemePackTest.php
./scripts/dev.sh test tests/Feature/Academic/Grading/ApplyMetropoliaSchemePackCommandTest.php
./scripts/dev.sh artisan pint app/Modules/Academic/Grading app/Modules/Academic/Actions app/Console/Commands/Academic tests/Feature/Academic/Grading
git diff --check -- docs/features/academic docs/stories/E-academic-grading-rule-engine docs/superpowers/plans
```

## Acceptance Evidence

The story is complete when the commands above pass and the harness trace records
the catalog keys, dry-run result, and commit-mode mutation proof.
