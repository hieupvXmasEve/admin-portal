# Design

## Domain Model

The validation report is read-only. It summarizes:

- syllabus templates with custom schemes
- mapped Metropolia scheme keys
- unmapped active templates for selected campus/program
- sample course offerings and calculated records
- Canvas-synced courses using custom schemes
- GPA source configuration
- portal contract documentation status

## Application Flow

1. Operator runs `academic:validate-grading-rollout` with campus, program, or
   semester filters.
2. Command queries the selected database without mutating records.
3. Command writes a JSON report and prints a pass/fail summary.
4. Operator attaches the report to the Harness trace and rollout checklist.

## Interface Contract

Console command:

```text
academic:validate-grading-rollout
  --campus-id=1
  --program-id=2
  --semester-id=3
  --scheme=metropolia_v1
  --sample-course-offering-id=4
  --output=json
```

The command exits `0` when required checks pass and `1` when coverage, Canvas,
GPA, or portal-readiness checks fail.

## Data Model

No migration. Reports are written to `storage/app/academic-grading-rollout/`.

## UI / Platform Impact

CLI and runbook. Portal validation commands are part of evidence gathering.

## Observability

The command logs target filters, pass/fail status, failed check names, and
report path.

## Alternatives Considered

1. Use a manual checklist only.
2. Treat rollout validation as part of S-005.
3. Run validation against all school databases from one place.

The selected design keeps validation repeatable while respecting separate
school databases.
