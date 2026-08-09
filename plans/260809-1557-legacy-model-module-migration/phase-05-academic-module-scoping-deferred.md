---
phase: 5
title: "Academic module scoping (deferred)"
status: pending
priority: P2
effort: "0.5h (planning only, no code)"
dependencies: [4]
---

# Phase 5: Academic module scoping (deferred)

## Overview

Academic is explicitly last — ~48 models, heaviest coupling (`Student` 1,340 refs,
`Program` 459 refs, `Enrollment` 32 refs). This phase does **no migration**. It
produces a scoped follow-up plan once Phases 1-4 have proven the move+shim+arch-test
pattern in production.

## Requirements

- Deliverable is a document, not code: a new `ak:plan` run scoped to Academic,
  informed by what Phases 1-4 actually cost (time per model, any shim/reflection
  surprises, reviewer feedback on batch size).
- Do not start moving Academic models under this phase.

## Architecture

N/A — planning phase.

## Related Code Files

- Create (in a later plan run, not here): new plan dir under `plans/` for Academic migration.

## Implementation Steps

1. After Phase 4 ships, re-run `/ak:plan` scoped to Academic model migration only,
   feeding it: actual time-per-batch from Phases 1-4, the 48-model list, and
   `Student`/`Program`/`Enrollment` coupling counts already gathered in this plan's
   scout pass.
2. That follow-up plan should batch Academic's 48 models into 3-8-model PRs same as
   Phase 4, starting with the least-coupled Academic sub-domain (likely form/survey
   or curriculum-definition models, not `Student`/`Enrollment`/`Program`).
3. `Student` migration (1,340 refs) should be its own final PR in that follow-up
   plan — do not bundle it with anything else given the reference count.

## Success Criteria

- [ ] Follow-up Academic plan exists with phases sized 3-8 models each
- [ ] No `app/Models/*` Academic file touched by this phase

## Risk Assessment

- Attempting Academic in this plan (instead of scoping a follow-up) risks a stalled
  PR — 48 models × shim + arch-test review overhead is not a 1-PR-per-group change.
  Keeping it deferred is the mitigation.
