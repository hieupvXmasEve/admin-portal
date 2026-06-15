# Validation

## Proof Strategy

Proof must show permissions exist in config/DB and role assignment does not grant
cross-campus scope too broadly.

## Test Plan

| Layer | Cases |
| --- | --- |
| Integration | Permission rows exist after seeding. |
| Integration | Later Student 360 and search tests deny users without `view_finance_student_overview`. |
| Platform | Staff UI gates can read the new permissions. |
| Logs/Audit | Not applicable; no money state or user assignment audit in this story. |

## Fixtures

- Existing finance-capable roles.
- Super admin role.

## Commands

```text
./scripts/dev.sh artisan db:seed --class=UpdatePermissionsSeeder
./scripts/dev.sh artisan tinker --execute="echo \\Spatie\\Permission\\Models\\Permission::whereIn('name',['view_finance_student_overview','view_finance_all_campus'])->count();"
./scripts/dev.sh test tests/Feature/Finance/Student360/StudentOverviewShellTest.php
./scripts/dev.sh test tests/Feature/Finance/Search/FinanceGlobalSearchTest.php
```

## Acceptance Evidence

Recorded from S-010 Milestone 1 evidence:

- `view_finance_student_overview` and `view_finance_all_campus` were declared.
- Permission sync created the two new permissions with no orphan deletions.
- Student 360/search tests cover permission denial.

No product tests were re-run during the story split.
