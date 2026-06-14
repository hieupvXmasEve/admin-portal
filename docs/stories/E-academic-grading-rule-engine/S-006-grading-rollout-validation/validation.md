# Validation

## Proof Strategy

Prove the rollout command is read-only, fails when required coverage is missing,
and passes when a pilot database fixture has schemes, sample outcomes, Canvas
guard behavior, GPA configuration, and portal evidence.

## Test Plan

| Layer | Cases |
| --- | --- |
| Unit | Report builder marks coverage, Canvas, GPA, and portal checks correctly. |
| Integration | Command exits `1` on missing scheme coverage and `0` on complete fixture. |
| E2E | Pilot validation run creates a JSON report with stable keys. |
| Platform | Portal validation commands from S-004 are rerun for evidence. |
| Performance | Command uses bounded filters; no cross-database scan. |
| Logs/Audit | Read-only command writes report and operational log only. |

## Fixtures

- Pilot campus with active syllabus templates.
- At least one `metropolia_v1` syllabus template.
- One active template without a scheme to prove failure.
- One Canvas-synced course with default grading.
- One custom-scheme sample course with finalized records.

## Commands

```bash
./scripts/portal-status.sh
./scripts/dev.sh test tests/Feature/Academic/Grading/GradingRolloutValidationCommandTest.php
./scripts/dev.sh artisan academic:validate-grading-rollout --scheme=metropolia_v1 --output=json
(cd FE/student-nuxt && pnpm lint && pnpm typecheck && pnpm build)
(cd FE/lecturer-nuxt && pnpm lint && pnpm typecheck && pnpm build)
./scripts/dev.sh artisan pint app/Modules/Academic app/Console/Commands/Academic tests/Feature/Academic/Grading
git diff --check -- app docs/stories/E-academic-grading-rule-engine docs/superpowers/plans docs/runbooks
```

## Acceptance Evidence

The story is complete when the rollout report exists, all required checks have
explicit pass/fail fields, and the Harness trace links the report path plus
portal validation results.
