# Exec Plan

## Goal

Track Task 2 from the Milestone 1 plan as its own story: add and seed the
Finance shell permissions.

## Scope

In scope:

- Add permission declarations to `config/permission.php`.
- Map `view_finance_student_overview` to finance-capable roles.
- Keep `view_finance_all_campus` super-admin scoped.
- Run permission seed/sanity checks.

Out of scope:

- Route/controller implementation.
- UI rendering.
- Portal authorization.
- Money state changes.

## Risk Classification

Risk flags:

- Authorization: new permission gates.
- PII: controls access to student finance identity.
- Existing behavior: role maps affect staff visible surfaces.

Hard gates:

- Do not grant all-campus scope to ordinary finance staff.
- Do not use route names before permissions are declared and synced.

## Work Phases

1. Declare permissions.
2. Map roles.
3. Seed and verify rows.
4. Let later stories consume gates.

## Stop Conditions

Pause if:

- Existing finance role boundaries are ambiguous.
- Seeder output would delete or orphan unrelated permissions.
