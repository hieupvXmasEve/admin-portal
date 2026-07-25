---
title: Academic Grading Operations
status: current
type: runbook
scope: Academic grading configuration and operations
last_verified: "2026-07-25"
owner: Academic Module
audience:
  - academic administrators
  - developers
---

# Academic Grading Operations

## Purpose

Use this runbook to select, preview, apply, and troubleshoot syllabus grading
schemes. The executable contract lives in the Academic grading classes and
tests; this document records the operator workflow and the constraints that
must not be inferred from the UI alone.

## Current contract

- `syllabus_templates.grading_scheme = null` selects the default weighted
  percentage calculator.
- A non-null scheme must pass
  `app/Modules/Academic/Support/Grading/GradingSchemeValidator.php`.
- `metropolia_v1` converts components independently and combines the converted
  results. `metropolia_v2` evaluates an allowlisted arithmetic formula over raw
  component percentages. The formula evaluator never executes PHP.
- Component gates and course-level pass requirements are authoritative. A gate
  failure fails the course even when the numeric total would otherwise pass.
- Custom-engine results are persisted with their audit evidence in
  `academic_records.grade_breakdown`. Finalization reads the custom result
  rather than applying the default letter-grade lookup again.
- Canvas detail scores may feed a custom scheme, but a Canvas course total must
  not replace the custom engine's calculated result.

Executable owners:

- `app/Modules/Academic/Support/Grading/GradingCalculatorResolver.php`
- `app/Modules/Academic/Support/Grading/MetropoliaV1Calculator.php`
- `app/Modules/Academic/Support/Grading/MetropoliaV2Calculator.php`
- `app/Modules/Academic/Support/Grading/SafeArithmeticEvaluator.php`
- `app/Modules/Academic/Support/Grading/Presenters/GradeDisplayPresenter.php`
- `tests/Feature/Academic/Grading/`

## Metropolia scheme pack

The runtime pack remains at
`docs/features/academic/metropolia-grading-schemes.json`. Do not copy its keys,
formulas, or component definitions into another document; the JSON file is the
machine-readable authority.

The pack records the source course and any modeling caveat on each scheme.
`hardware_1.networking` still depends on an academic decision for grade 2-5
boundaries because the supplied rule defines only the grade-1 threshold. Confirm
that scheme with academic staff before a production pilot.

## Apply a pack to one database

Schools share the repository but not a database. Each database therefore needs
an explicit local mapping file. The mapping file is a JSON array. Every entry
must contain `scheme_key` and enough of these selectors to resolve exactly one
syllabus template:

- `syllabus_template_id`
- `unit_code`
- `syllabus_title`
- `campus_code`

Always validate before writing:

```bash
./scripts/dev.sh artisan academic:apply-grading-scheme-pack \
  --mapping=/absolute/path/metropolia-map.json \
  --dry-run
```

Commit only after the dry run reports no failed or ambiguous mappings:

```bash
./scripts/dev.sh artisan academic:apply-grading-scheme-pack \
  --mapping=/absolute/path/metropolia-map.json \
  --commit
```

Exactly one of `--dry-run` and `--commit` is required. A mapping error makes the
command fail without applying a partial plan. The command and transaction
behavior are owned by:

- `app/Console/Commands/Academic/ApplyGradingSchemePackCommand.php`
- `app/Modules/Academic/Actions/ApplyGradingSchemePackAction.php`
- `app/Modules/Academic/Support/Grading/MetropoliaSchemeCatalog.php`

## Edit and preview in the admin app

Use the syllabus template create/edit pages for a single template. The editor
loads the supported engine options, validates JSON, and calls the same runtime
calculator for previews. Saving the default mode sends `grading_scheme: null`;
saving a custom mode stores the validated object.

The page and endpoint owners are:

- `resources/js/pages/syllabus/components/GradingSchemePreview.vue`
- `resources/js/pages/syllabus/components/grading-scheme-builder.ts`
- `app/Modules/Academic/Catalog/Actions/PreviewSyllabusGradingSchemeAction.php`
- `app/Modules/Academic/Catalog/routes/web.php`

Preview is read-only. For existing course results, use the course offering
Scores tab's preview-first recalculation flow rather than editing
`academic_records` directly:

- `app/Modules/Academic/Http/Web/RecalculatePreviewController.php`
- `app/Modules/Academic/Http/Web/RecalculateApplyController.php`
- `resources/js/pages/course-offerings/components/tabs/ScoresTab.vue`

## Verification

Run the focused grading checks after changing a scheme or grading code:

```bash
./scripts/dev.sh artisan test --compact tests/Feature/Academic/Grading
```

For a pack-only change, at minimum run:

```bash
./scripts/dev.sh artisan test --compact \
  tests/Feature/Academic/Grading/MetropoliaSchemePackTest.php
```

Investigate mismatches from the stored `grade_breakdown`, the syllabus scheme,
and the component scores together. Do not repair a mismatch by overwriting the
final letter grade alone.
