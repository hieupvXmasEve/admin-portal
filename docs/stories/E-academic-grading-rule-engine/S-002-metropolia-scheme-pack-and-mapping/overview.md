# Overview

## Current Behavior

The Metropolia grading reference exists as prose in
`docs/features/academic/Grading_Schemes_Metropolia.md`. After
`S-001-syllabus-campus-grading-rule-engine`, the backend can execute a
`metropolia_v1` scheme, but the repo still lacks a canonical scheme pack and a
repeatable per-database mapping path.

## Target Behavior

The repo contains a deterministic Metropolia scheme catalog, validation rules,
operator mapping documentation, and a dry-run-first command that applies scheme
definitions to selected `syllabus_templates` in one school database.

## Affected Users

- Academic operations staff who map local syllabus templates to Metropolia
  rules.
- Academic admins who need repeatable scheme setup in each school database.
- Engineers who need fixtures for the rule engine.

## Affected Product Docs

- `docs/features/academic/Grading_Schemes_Metropolia.md`
- `docs/features/academic/metropolia-grading-schemes.json`
- `docs/features/academic/metropolia-component-mapping.md`

## Portal Impact

None. This story does not change `/api/v1/student/*` or `/api/v1/lecturer/*`
response shapes.

## Non-Goals

- No visual editor.
- No portal display changes.
- No historical grade recalculation.
- No shared-database tenant isolation. Each school database applies its own
  mappings.
