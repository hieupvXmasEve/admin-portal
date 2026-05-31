# Exec Plan

## Goal

Let staff filter deferred students by source semester and, for EGC students,
the Block 1/Block 2 point at which defer started.

## Scope

In scope:

- Academic audit metadata column and historical Block 1 backfill.
- Student-action create, correction, import, list, detail, and export surfaces.
- Targeted tests and post-migration verification.
- Product docs, decision record, test matrix, and Harness evidence.

Out of scope:

- Finance or course-registration behavior changes.
- Historical semester entry from the web create dialog.
- Automatic inference from EGC runtime blocks.
- Cleanup or redesign of EGC block numbering.

## Risk Classification

Risk flags:

- Data model.
- Audit/security.
- Existing behavior.
- Weak proof.
- Multi-domain awareness due to adjacent EGC finance data.

Hard gates:

- Data migration.
- Audit record behavior.

## Work Phases

1. Confirm reporting-only semantics, Block terminology, Block 1 backfill, and
   current-semester-only form behavior with the human.
2. Add the audit migration, model fillable field, request validation, and
   persistence.
3. Add create/detail/edit/import UI and boundary support.
4. Replace the report UI/export filter with `from_semester_id`; add the block
   filter and visible output.
5. Add targeted persistence, correction, query-filter, export, import, and
   migration-verification coverage.
6. Run migration verification, targeted tests, formatting, lint, type check,
   and diff checks.
7. Update product docs, test matrix evidence, and Harness trace.

## Stop Conditions

Pause for human confirmation if:

- The block value must change tuition, finance charges, registrations, or EGC
  progression behavior.
- Existing records require a different backfill rule than Block 1.
- Historical semester selection must be opened on the web form.
- The import path must reject blank historical block values instead of applying
  the Block 1 default.
- Validation requirements need to be weakened.
- Architecture direction changes.
