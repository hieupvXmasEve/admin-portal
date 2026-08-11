---
phase: 3
title: "Engagement module sweep"
status: pending
priority: P1
effort: "2-3h"
dependencies: [1, 2]
---

# Phase 3: Engagement module sweep

## Overview

Sweep the 23 callers of Engagement's **16** shims — the largest shim group —
backfill the 19 remaining `Club` morph rows, delete all 16 shim files, and
retire the three Engagement placement arch tests' shim-must-exist blocks. Two
files in this phase deliberately keep the old FQCN as *data* and must not be
rewritten.

## Requirements

- Functional: no file references `App\Models\<any of the 16 Engagement shims>`, except the two intentional data-holders listed below.
- Functional: all 16 shim files deleted.
- Functional: `activity_log.subject_type` holds zero `App\Models\Club` rows.
- Functional: `$activity->subject` still resolves for previously-affected Club rows.
- Non-functional: import rewrites, deletions, and one backfill migration only.

## Architecture

Shims in scope, caller counts measured 2026-08-11 (all canonical under
`App\Modules\Engagement\Models\*`):

| Shim | Callers | Shim | Callers |
|---|---|---|---|
| `Form` | 11 | `QueryTicket` | 6 |
| `FormVersion` | 11 | `QueryReply` | 5 |
| `FormTarget` | 10 | `FormSection` | 4 |
| `Event` | 9 | `ClubMember` | 3 |
| `FormResponse` | 9 | `EventParticipant` | 3 |
| `Club` | 2 | `ClubMemberRoleHistory` | 1 |
| `FormResultVisibility` | 1 | `FormSurvey` | 1 |
| `QueryAssignment` | 1 | `QueryTopic` | 1 |

23 unique files (heavy overlap — the Form/Query test files import 5-8 shims each).

### "Zero-caller shims" claim is wrong (correction to plan.md)

`plan.md` lists 8 shims with zero code callers and calls 6 of them "free
deletions". Re-measured with a regex that also catches escaped string literals:
**only `MerchandiseImage` (phase 5) has zero references.** Five of the
supposedly-free Engagement shims — `QueryTopic`, `QueryAssignment`,
`FormSurvey`, `FormResultVisibility`, `ClubMemberRoleHistory` — are each
referenced exactly once, all in the same place:

`tests/Feature/Architecture/EngagementQueryTicketModelPlacementArchTest.php:47-51`
holds a 16-entry list of Engagement shim FQCNs as **negative-assertion string
literals** ("no `App\Models\Club*` reference remains inside
`app/Modules/Engagement`"). That list becomes meaningless once the shims are
deleted and must be removed in this phase, not left to rot.

### Morph rows — ClubMember is already done

Phase 1's backfill migration has already been applied to the dev `asia`
database: 100 `App\Models\ClubMember` rows are now
`App\Modules\Engagement\Models\ClubMember`, 99 resolve their subject, and the
1 null is a pre-existing orphan (`subject_id=3` was hard-deleted from
`club_members`; it resolved to null before the migration too — the migration
only rewrote the type string). **Do not write a second ClubMember backfill.**

Remaining work in this phase is `Club` only:

| `activity_log.subject_type` | Rows |
|---|---|
| `App\Models\Club` | 19 |

The other 14 Engagement shims have zero morph rows.

### Two files intentionally keep the old FQCN

These appear in the caller grep but **must not be rewritten**:

- `database/migrations/2026_08_11_021157_backfill_clubmember_shimmed_morph_subject_type.php`
  — the old FQCN is its `WHERE` value. Rewriting it would make the migration a
  no-op for anyone who has not run it yet.
- `tests/Feature/Engagement/ClubMemberShimMorphBackfillMigrationTest.php`
  — the old FQCN is the legacy row it seeds to prove the rewrite happens.

Both must therefore stay in `SHIMMED_MODEL_IMPORT_BASELINE` after this phase,
which means the baseline does **not** reach empty at the end of phase 5. Update
the guard's docblock accordingly, or drop these two files once the plan
completes and the pilot migration is squashed/retired.

Note the migration's `down()` already self-disables via
`class_exists(OLD_FQCN)`, so deleting the `ClubMember` shim in this phase does
not create a corrupting rollback path.

### Cross-module coupling

- `app/Modules/Upload/Models/UploadRecord.php` imports `App\Models\FormResponse`, `App\Models\QueryReply`, `App\Models\QueryTicket` — so **this phase edits a file inside the Upload module.** Expected; see phase 2's symmetric note.
- Shared with **phase 2** (sequence, do not parallelize): `tests/Feature/Api/V1/Student/QueryTicketApiTest.php`, `tests/Feature/Form/AdminQueryInboxTest.php`, `tests/Feature/Form/QueryTicketWorkflowTest.php`.
- Shared with **phase 5**: `tests/Feature/Gold/ReclaimGoldRewardTest.php` (imports `Event` + `EventParticipant` here, `GoldTransaction` there).

## Related Code Files

- Create: `database/migrations/<timestamp>_backfill_club_shimmed_morph_subject_type.php`
- Delete: `app/Models/{Club,ClubMember,ClubMemberRoleHistory,Event,EventParticipant,Form,FormResponse,FormResultVisibility,FormSection,FormSurvey,FormTarget,FormVersion,QueryAssignment,QueryReply,QueryTicket,QueryTopic}.php` (16 files)
- Modify: `tests/Feature/Architecture/EngagementClubEventModelPlacementArchTest.php` (drop shim-must-exist block)
- Modify: `tests/Feature/Architecture/EngagementFormModelPlacementArchTest.php` (drop shim-must-exist block)
- Modify: `tests/Feature/Architecture/EngagementQueryTicketModelPlacementArchTest.php` (drop shim-must-exist block **and** the 16-FQCN negative-assertion list at lines 47-51)
- Modify: `tests/Feature/Architecture/DeprecatedModelShimArchTest.php` (shrink both lists)
- Modify: `config/migration_debt.php` (lower `shared_model_imports` baseline)
- Modify (callers, 21 of the 23 — excluding the two data-holders above):
  - `app/Modules/Academic/Delivery/Queries/GetCourseOfferingSurveyQuery.php`
  - `app/Modules/Upload/Models/UploadRecord.php`
  - `app/Services/NotificationService.php`
  - `app/Services/QRCodeService.php`
  - `app/Services/V1/Student/TimetableEventQuery.php`
  - `app/Services/V1/Student/TimetableService.php`
  - `database/seeders/InitialSetup/EventSeeder.php`
  - `tests/Feature/Api/V1/Student/EngagementFormsApiTest.php`
  - `tests/Feature/Api/V1/Student/QueryTicketApiTest.php`
  - `tests/Feature/Api/V1/Student/TimetableControllerTest.php`
  - `tests/Feature/Engagement/CourseOfferingSurveyRouteTest.php`
  - `tests/Feature/Engagement/ProvisionCourseSurveyActionTest.php`
  - `tests/Feature/Form/AdminQueryInboxTest.php`
  - `tests/Feature/Form/FormManagementWorkflowTest.php`
  - `tests/Feature/Form/QueryTicketWorkflowTest.php`
  - `tests/Feature/Form/SurveyAggregateConfigTest.php`
  - `tests/Feature/Form/SurveyResultDownloadTest.php`
  - `tests/Feature/Gold/ReclaimGoldRewardTest.php`
  - `tests/Feature/Lecture/LecturerGpaReportTest.php`
  - `tests/Unit/Notification/EventNotificationServiceTest.php`
  - (plus `tests/Feature/Architecture/EngagementQueryTicketModelPlacementArchTest.php`, covered above)

## Implementation Steps

1. **Record the pre-sweep baseline:**

   ```bash
   ./scripts/dev.sh artisan test tests/Feature/Form tests/Feature/Engagement tests/Feature/Api/V1/Student tests/Feature/Architecture
   ```

2. **Re-measure morph rows** for all 16 (rows accrue; `Club` was 19 at plan time):

   ```bash
   ./scripts/dev.sh artisan tinker --execute="foreach (['Club','ClubMember','ClubMemberRoleHistory','Event','EventParticipant','Form','FormResponse','FormResultVisibility','FormSection','FormSurvey','FormTarget','FormVersion','QueryAssignment','QueryReply','QueryTicket','QueryTopic'] as \$m) { \$n = DB::table('activity_log')->where('subject_type','App\\\\Models\\\\'.\$m)->count(); if (\$n) { echo \$m.' = '.\$n.PHP_EOL; } }"
   ```

3. **Sweep the callers** — 21 files, `App\Models\X` →
   `App\Modules\Engagement\Models\X`. Skip the two data-holder files.

4. **Write the `Club` backfill migration**, copying
   `2026_08_11_021157_backfill_clubmember_shimmed_morph_subject_type.php`
   including its `class_exists` guard in `down()`. Add a case for any other
   model step 2 turned up non-zero. Run it **after** step 3 so callers have
   stopped writing the old FQCN.

5. **Delete the 16 shim files.**

6. **Update the three Engagement placement arch tests** — drop the
   shim-must-exist block from each, and delete the now-dead 16-FQCN literal list
   from `EngagementQueryTicketModelPlacementArchTest.php`.

7. **Shrink the phase-1 guard and the debt baseline** (same as phase 2 steps
   6-7). `SHIMMED_MODELS` drops to 11 entries. Leave the two data-holder paths
   in `SHIMMED_MODEL_IMPORT_BASELINE` and amend the docblock's
   "empty list is the completion signal" claim.

8. **Spot-check subject resolution on real data:**

   ```bash
   ./scripts/dev.sh artisan tinker --execute="\$r=0;\$n=0; foreach (Spatie\Activitylog\Models\Activity::whereIn('subject_type',['App\\\\Modules\\\\Engagement\\\\Models\\\\Club','App\\\\Modules\\\\Engagement\\\\Models\\\\ClubMember'])->get() as \$a) { \$a->subject ? \$r++ : \$n++; } echo 'resolved='.\$r.' null='.\$n.PHP_EOL;"
   ```

   Expect `null=1` (the known pre-existing `ClubMember` orphan), not more.

## Success Criteria

- [ ] No `App\Models\<Engagement shim>` reference outside the two documented data-holders
- [ ] All 16 shim files deleted; `SHIMMED_MODELS` down to 11 entries
- [ ] `activity_log.subject_type` holds zero `App\Models\Club` rows
- [ ] Club/ClubMember activity resolves its subject; `null` count is still exactly 1 (pre-existing orphan), not higher
- [ ] Backfill `down()` reverses on a test DB and carries the `class_exists` guard
- [ ] Three Engagement placement arch tests green with shim-must-exist blocks and the dead FQCN list removed
- [ ] `shared_model_imports` baseline lowered
- [ ] Touched suites match the step-1 baseline — no new failures

## Risk Assessment

| Risk | Mitigation |
|---|---|
| A blind find/replace rewrites the two intentional data-holders, silently making the pilot migration a no-op | Both files named explicitly in Related Code Files and excluded from step 3; success criteria check the migration still contains the old FQCN |
| A second ClubMember backfill is written, re-migrating already-migrated rows | Documented above: phase 1's migration is already applied on dev `asia`; step 2 will show `ClubMember = 0` |
| 16 shims in one PR makes the diff too large to review | Diff is import lines + 16 one-line file deletions + one migration; reviewer greps rather than reads. Split Form / Club-Event / QueryTicket into three PRs if the reviewer prefers |
| Deleting the `ClubMember` shim breaks the pilot migration's `down()` | Already handled — `down()` self-disables via `class_exists(OLD_FQCN)` |
| Merge conflict with phase 2 (3 files) or phase 5 (1 file) | Sequence the phases; this one depends on phase 2 |
| The 19 Club rows grow between measurement and migration | Migration runs after the caller sweep in the same PR, and step 2 is re-run immediately before |
