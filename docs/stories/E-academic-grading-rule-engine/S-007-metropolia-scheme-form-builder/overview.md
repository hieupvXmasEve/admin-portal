# Overview

## Current Behavior

S-003 shipped a raw-JSON grading scheme editor on syllabus template
create/edit/show pages. Admins must hand-author scheme JSON (`engine`, `scale`,
`components`, `formula`, `conversion`, `pass_requirements`) to configure
Metropolia grading. Two gaps:

- Non-technical academic staff cannot read or write the JSON, so the feature is
  effectively author-by-engineer only.
- The scheme's component `code`s (e.g. `LAB`, `EXAM`) must match
  `assessment_components.code` for grading to receive real scores at
  finalization, but the Assessment Components UI never sets `code`. Codes are
  null on save, so a Metropolia formula silently reads `0` for every component
  at grading time. The preview panel hides this because it injects sample
  scores keyed by the scheme's own declared codes.

## Target Behavior

A dedicated, form-only Metropolia scheme builder replaces the JSON textarea.
Staff pick an outcome type (numeric `0-5` or pass/fail), pick a calculation
method (sum-of-conversions = `metropolia_v1`, or single formula =
`metropolia_v2`), and fill structured controls per component. No JSON is shown.
A "Hướng dẫn" button opens a guide modal explaining each field and offering
worked examples from `Grading_Schemes_Metropolia.md` that load into the builder
with one click.

`code` is wired end-to-end: the Assessment Components section gains a `code`
input (auto-suggested as an uppercase slug of the name, unique per template),
both create and update actions persist it, and request validation enforces that
every scheme component code references a declared assessment component code.

## Affected Users

- Academic admins who manage syllabus templates (primary).
- Academic operations staff validating Metropolia mappings.

## Affected Product Docs

- `docs/features/academic/grading-rule-engine.md`
- `docs/features/academic/Grading_Schemes_Metropolia.md`
- `docs/features/academic/metropolia-component-mapping.md`

## Portal Impact

None. Admin web pages and web-authenticated preview endpoints only. Grading
math and stored contracts are unchanged (`GradingSchemeValidator`,
`MetropoliaV1Calculator`, `MetropoliaV2Calculator`, `SafeArithmeticEvaluator`).

## Why Both Engines Stay

`metropolia_v1` and `metropolia_v2` are not redundant; they model different
rule shapes and neither can express the other:

- `SafeArithmeticEvaluator` accepts only `+ - * / ( )` and variables — no
  conditionals, no `min`/`max`. So `v2` cannot express threshold bands
  (`55% -> 1, 70% -> 2`) or clamped-linear conversions (`< 40% -> 0`,
  `40% -> 1 .. 88% -> 5`). Those require `v1`.
- `v1` sums independently converted component grades, so it can only
  approximate cross-component formulas with a global offset/divisor
  (`(LAB + QUIZ + EXAM/2 - 40)/10`). Those require `v2`.

The builder exposes both as plain-language choices; the guide modal documents
"when to use which".

## Non-Goals

- No change to the grading calculators or the scheme JSON contract.
- No drag-and-drop canvas. Structured form controls only.
- No portal display changes, no grade recalculation.
- No automatic scheme recommendation from syllabus title.
- No new `engine` types beyond `metropolia_v1` / `metropolia_v2` / default.

## Supersedes

This story reverses the S-003 non-goal "No visual rule builder" and the S-003
alternative "Build a full visual rule builder", which were deferred to keep the
first UI small. The raw-JSON path from S-003 is removed.
