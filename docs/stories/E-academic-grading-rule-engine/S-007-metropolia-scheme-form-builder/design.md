# Design

## Domain Model

No new engine, no new calculator, no change to the stored `grading_scheme`
contract. The builder is a serialization layer: form state in, the exact same
scheme JSON out that `GradingSchemeValidator` and the calculators already
accept. One persistence change only: `assessment_components.code` (column
already exists, nullable, unique per template) becomes user-set instead of
always null.

## Application Flow

1. Create/edit pages render the Assessment Components section (now with a
   `code` input per component) and a dedicated Metropolia builder section.
2. Admin picks outcome (`scale`: `0-5` | `pass_fail`) and method
   (`engine`: `metropolia_v1` | `metropolia_v2`), or leaves the default
   weighted path (saves `grading_scheme = null`).
3. Builder renders only the controls relevant to the chosen engine + per-row
   conversion type. It serializes to scheme JSON on every change.
4. Existing preview endpoint validates + calculates sample scores without
   saving (unchanged).
5. On submit, request validation enforces scheme integrity and the new
   code-subset rule; actions persist component codes and the scheme.

## Single Source of Truth

The builder does NOT own a component list. It reads the template's Assessment
Components (passed in as `{ code, label }[]`) and only stores the *grading rule*
per code. Staff define each gradable item once — in the Assessment Components
section (name, code, weight, details) — and the builder reuses it. The
Assessment Component `code` field is a catalog combobox (datalist of the
canonical Metropolia codes `ASSIGNMENT/EXAM/LAB/QUIZ/PROJECT/HTML_CSS/JS`, plus
free typing). Components without a code are excluded from Metropolia grading and
flagged inline.

The guide modal's "Áp dụng ví dụ" seeds BOTH the Assessment Components and the
scheme (emits `apply-components` to the page + sets the scheme), so an example is
a true starting template, not just a formula.

## Formula Semantics (v2)

Formula variables are the component **raw average percentages (0–100)** that
`aggregateManualGrades` feeds — NOT pre-weighted points and NOT the component
`weight %` field (which v2 ignores). So a source rule written for weighted
points must bake the weights into the formula. Example: Cloud Computing's
documented `(total_lab_score + total_quiz_score + final_exam_score/2 − 40)/10`
(weighted points) is authored in the builder as
`(0.5*LAB + 0.3*QUIZ + 0.1*EXAM − 40)/10` over raw percentages. The guide and
the formula helper text state this explicitly to prevent silent miscalculation.

## Builder Controls

`metropolia_v1` (sum of converted grades) — one editor per coded component:

- Per component, `conversion type`:
  - `linear` -> `min_pct`, `max_pct`, `min_grade`, `max_grade`
  - `threshold` -> repeatable `{ min_pct, grade }` steps
  - `direct` -> `max_grade`
  - `pass_fail` -> `min_pct` (only meaningful with `scale = pass_fail`)
  - gate-only -> no conversion
- Optional per-row `gate.min_pct` (course-fail requirement).

`metropolia_v2` (single formula):

- Component rows declare variables only (`code`, `label`).
- `formula` text input with clickable variable chips (the declared codes) and a
  live "undeclared variable" warning mirroring the server validator.
- Repeatable `pass_requirements` rows: `{ code, min_pct }`.

Advanced (collapsed, sensible defaults): `clamp_min = 0`, `clamp_max = 5`,
`rounding_stage = after_total`.

## Guide Modal

`GradingSchemeGuideModal.vue` (shadcn `Dialog`). Explains scale vs engine,
each conversion type, gate vs pass_requirement. Worked examples from
`Grading_Schemes_Metropolia.md`, each with "Áp dụng ví dụ" to load into the
builder:

| Example | Engine | Shape |
| --- | --- | --- |
| Software 1 – Programming | v1, 0-5 | Exam linear 40%->1, 88%->5 + Assignment gate 40% |
| Database | v1, pass_fail | Assignment pass/fail >= 80% |
| Maths & Physics | v1, 0-5 | threshold steps; FG = Assignment grade + Exam grade |
| Cloud Computing | v2, 0-5 | `(LAB + QUIZ + EXAM/2 - 40)/10` |
| Health Technology | v2, 0-5 | `(ASSIGN - 40)/10` |

## Interface Contract

No new routes. Reuses S-003 endpoints:

```text
GET  /api/syllabus-templates/grading-scheme/options
POST /api/syllabus-templates/grading-scheme/preview
POST /api/syllabus-templates/{syllabusTemplate}/grading-scheme/preview
```

Store/update requests (`StoreSyllabusTemplateRequest`,
`UpdateSyllabusTemplateRequest`) add:

- `assessment_components.*.code`: required when a custom scheme is present,
  string, uppercase-sluggable, unique within the submitted set.
- Cross-field rule: every `grading_scheme.components[].code` and every
  `pass_requirements[].code` must be in the submitted assessment component
  codes. Reuse `GradingSchemeValidator` for engine-contract errors.

## Data Model

No migration. `assessment_components.code` already exists
(`2025_05_29_152159_create_assessment_components_table.php`, nullable, unique
`[syllabus_template_id, code]`). Create/update actions start writing it.

## UI / Platform Impact

Admin Inertia pages:

- `resources/js/pages/syllabus/TemplatesCreate.vue`
- `resources/js/pages/syllabus/TemplatesEdit.vue`
- `resources/js/pages/syllabus/TemplatesShow.vue` (show resolved scheme in
  human-readable form, not raw JSON)

New focused Vue components in `resources/js/pages/syllabus/components/`:

- `MetropoliaSchemeBuilder.vue` (replaces `GradingSchemeEditor.vue`)
- `GradingSchemeGuideModal.vue`

Retained: `GradingSchemePreview.vue` (already form-driven). Removed:
`GradingSchemeEditor.vue` JSON textarea path.

## Observability

Invalid submits return field validation errors without writing. Save uses
existing syllabus template audit logging. Preview path stays read-only.

## Alternatives Considered

1. Builder owns its own component list (codes typed/selected inside the
   builder, independent of Assessment Components). Initially chosen, then
   reversed: it duplicated component entry and let the scheme codes drift from
   `assessment_components.code`. Final design is single-source — the builder
   reads the Assessment Components and only stores grading rules — so staff
   create each item once.
2. Keep a collapsed "Advanced (JSON)" escape hatch. Rejected: staff asked for
   form-only; JSON reintroduces the readability problem the story exists to fix.
3. Auto-generate codes silently and hide them. Rejected: codes must be visible
   and editable because they are the join key to grading and to formulas.

## Risks

- Builder serialization drifting from the validator vocabulary. Mitigation:
  serialize to the documented contract and round-trip through the existing
  preview/validate endpoints in tests.
- Editing codes after `AcademicRecord` rows exist could orphan stored
  breakdowns. Mitigation: out of scope here; recalculation remains S-005.
