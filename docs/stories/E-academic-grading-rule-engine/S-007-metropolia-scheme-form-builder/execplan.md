# Exec Plan

## Goal

Replace the raw-JSON grading scheme editor with a form-only Metropolia builder
plus a guide modal, and wire `assessment_components.code` end-to-end so
Metropolia grading receives real component scores at finalization.

## Scope

In scope:

- Form builder for both engines (`metropolia_v1`, `metropolia_v2`) and both
  scales (`0-5`, `pass_fail`), serializing to the existing scheme contract.
- Guide modal with worked examples loadable into the builder.
- `code` input in Assessment Components; persistence in create/update actions.
- Request validation: code required with custom scheme, unique per submission,
  and scheme/pass_requirement codes subset of assessment codes.
- Show page renders resolved scheme in human-readable form.

Out of scope:

- Grading calculator / scheme JSON contract changes.
- Portal display, historical recalculation (S-005), Canvas boundary.
- New engine types or new preview routes.

## Risk Classification

Risk flags:

- Authorization (admin write path)
- Existing behavior (replaces S-003 editor; default path must still save null)
- Public contracts (request payload shape for syllabus template save)
- Data integrity (code is the join key to grading)
- Weak proof (UI serialization correctness)

Hard gates:

- Authorization-sensitive admin write path.
- Default weighted path (`grading_scheme = null`) must remain byte-compatible.

## Work Phases

1. Backend first (TDD): request validation for `code` + code-subset rule;
   create/update actions persist `code`. Feature tests prove persistence and
   422 on mismatch.
2. Frontend types: extend `types/grading-scheme.ts` with typed conversions and
   builder helpers.
3. Build `MetropoliaSchemeBuilder.vue` (form + serialization) and
   `GradingSchemeGuideModal.vue` (examples that load into the builder).
4. Integrate Create/Edit/Show pages; add `code` field to Assessment Components;
   remove `GradingSchemeEditor.vue` JSON path.
5. Round-trip proof: builder output validates and previews via existing
   endpoints for each worked example.
6. Targeted verification (tests, type-check, lint, format, pint) + Harness
   trace of create/edit/show UI.

## Stop Conditions

Pause for human confirmation if:

- The agreed contract cannot represent a required Metropolia rule without a
  calculator change (would expand scope into S-001 territory).
- Assessment Components form patterns need a broad rewrite to host `code`.
- Admin permissions are missing or named differently than expected.
- Editing codes is found to mutate already-finalized `AcademicRecord` rows
  (defer to S-005).
