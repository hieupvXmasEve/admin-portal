---
phase: 4
title: "Engagement module migration"
status: pending
priority: P1
effort: "3h (3 sub-PRs)"
dependencies: [3]
---

# Phase 4: Engagement module migration

## Overview

16 models qualify for Engagement — too many for one PR under the 3-8 batch rule.
Split into 3 sub-PRs by sub-domain: Clubs+Events, Forms, Query/Ticketing. Each
sub-PR follows the identical move+shim+arch-test steps from Phases 1-3.

## Requirements

- Functional: all 16 FQCNs keep resolving via shim after their sub-PR lands.
- Non-functional: one placement arch test per sub-PR (or one combined test file
  added to incrementally, reviewer's call at PR time).

## Architecture

`app/Modules/Engagement` already exists with a full feature layer (Queries,
Actions, Support, Http, Jobs, Console, `EngagementServiceProvider`, existing
`QueryTicketWorkflow.php`) — only `Models/` is missing. Create
`app/Modules/Engagement/Models/` in sub-PR 4a, reuse for 4b/4c. Do not touch
existing layers (same in-module-caller repoint question, see `plan.md` Open Questions).

### Sub-PR 4a — Clubs + Events (5 models)
`Club`, `ClubMember`, `ClubMemberRoleHistory`, `Event`, `EventParticipant`

### Sub-PR 4b — Forms (7 models)
`Form`, `FormResponse`, `FormResultVisibility`, `FormSection`, `FormSurvey`,
`FormTarget`, `FormVersion`

### Sub-PR 4c — Query/Ticketing (4 models)
`QueryAssignment`, `QueryReply`, `QueryTicket`, `QueryTopic`

## Related Code Files

- Create: `app/Modules/Engagement/Models/{Club,ClubMember,ClubMemberRoleHistory,Event,EventParticipant}.php` (4a)
- Create: `app/Modules/Engagement/Models/{Form,FormResponse,FormResultVisibility,FormSection,FormSurvey,FormTarget,FormVersion}.php` (4b)
- Create: `app/Modules/Engagement/Models/{QueryAssignment,QueryReply,QueryTicket,QueryTopic}.php` (4c)
- Modify (→ shim): matching 16 files under `app/Models/`
- Modify (namespace repoint, no logic change): all `app/Modules/Engagement/**/*.php`
  files found referencing any of the 16 FQCNs in each sub-batch's step 5 grep
  (known existing reference: `Support/QueryTicketWorkflow.php` → `QueryTicket`, sub-PR 4c)
- Create: `tests/Feature/Architecture/EngagementClubEventModelPlacementArchTest.php` (4a)
- Create: `tests/Feature/Architecture/EngagementFormModelPlacementArchTest.php` (4b)
- Create: `tests/Feature/Architecture/EngagementQueryTicketModelPlacementArchTest.php` (4c)

## Implementation Steps

Per sub-PR (4a, then 4b, then 4c):
1. Read every model file in the sub-batch fully before moving.
2. `git mv` each into `app/Modules/Engagement/Models/`.
3. Update namespace `App\Models` → `App\Modules\Engagement\Models`.
4. Confirm/add explicit `$table` per model (check migration, don't guess).
5. Grep repo-wide for each FQCN in the sub-batch. Split into in-module
   (`app/Modules/Engagement/*`) vs cross-module; note cross-module counts for PR description.
5b. Repoint in-module references to `App\Modules\Engagement\Models\*` (mechanical
   find/replace). Cross-module callers stay on the shim.
6. Write `class_alias()` shim at each old path.
7. Write the sub-batch's placement arch test.
8. `./scripts/dev.sh artisan test --filter=Engagement` (narrow), then full suite (broad).

## Success Criteria

- [ ] 4a: 5 Club/Event models migrated, in-module callers repointed, arch test passes, suite green
- [ ] 4b: 7 Form models migrated, in-module callers repointed, arch test passes, suite green
- [ ] 4c: 4 Query/Ticket models migrated, in-module callers repointed (incl.
      `Support/QueryTicketWorkflow.php`), arch test passes, suite green
- [ ] `app/Models/` has no real Engagement model classes left, only shims
- [ ] Zero legacy `App\Models\{Club,ClubMember,...,QueryTopic}` references remain
      inside `app/Modules/Engagement/*` after 4c

## Risk Assessment

- `QueryTicket`/`QueryReply` may be used by a support/helpdesk feature with its
  own tests outside the obvious `Engagement` filter — run full suite (not just
  `--filter=Engagement`) before merging 4c.
- Same class_alias/reflection caveat as Phase 1, applies to all 3 sub-PRs.
