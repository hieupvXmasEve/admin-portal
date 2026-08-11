---
phase: 2
title: "Backfill remaining morph rows"
status: pending
priority: P1
effort: "1-2h + one deploy cycle"
dependencies: [1]
---

# Phase 2: Backfill remaining morph rows

<!-- Rewritten after red-team session 1: migration now precedes the sweep and ships as its own deploy -->

## Overview

Rewrite the remaining 142 `activity_log.subject_type` rows that still name a
shimmed FQCN — `Room` (87), `RoomBooking` (31), `Club` (19), `Building` (5) — to
their canonical namespace. **One migration, deployed on its own, with every shim
left in place.** No caller is swept and no shim is deleted in this phase.

This inverts the original plan's ordering, which had each module migrate its own
rows *after* sweeping its callers in the same PR. Red-team proved that ordering
both unnecessary and actively dangerous — see Architecture.

## Requirements

- Functional: zero `activity_log.subject_type` rows name any of the 30 shimmed FQCNs.
- Functional: `$activity->subject` resolves for all 142 rows afterwards, with every remaining null traced to a hard-deleted subject row.
- Functional: verified against **production**, not just dev `asia`.
- Non-functional: no code change of any kind. One migration file, nothing else.
- Non-functional: deployed and verified before phase 4 deletes any shim.

## Architecture

### Why the migration now goes first (red-team correction)

The original plan asserted rows "keep accruing until the callers stop writing the
old FQCN, since the logger records `get_class($model)`", and derived from that a
same-PR ordering: sweep callers, then migrate, then delete the shim.

**That causal claim is false.** Traced:
`ActivityLogger.php:50` `subject()->associate($model)` →
`MorphTo.php:248` `$model->getMorphClass()` →
`HasRelationships.php:1006-1020` → returns `static::class`.

`class_alias` does not create a second class. `App\Models\Room` and
`App\Modules\Facilities\Models\Room` are one class whose `static::class` is
always the canonical name, regardless of which alias a caller imported. The
second write path (`app/Support/BusinessActionLogger.php:233`,
`get_class($this->subject)`) gives the same answer.

Confirmed by data — last old-FQCN write per model, all predating the
2026-08-09 model move (`50ed50a4`):

| FQCN | Last written |
|---|---|
| `App\Models\Room` | 2026-05-22 |
| `App\Models\RoomBooking` | 2026-07-22 |
| `App\Models\Club` | 2026-04-17 |
| `App\Models\Building` | 2026-01-22 |
| `App\Models\ClubMember` | never (migrated by phase 1) |

**The 142 rows are frozen historical residue, not an accruing set.** Nothing can
write a shimmed FQCN today. Two consequences:

1. The same-PR constraint is unnecessary — the migration can run at any time.
2. Running it *first*, as its own deploy, removes the deploy-window fatal that
   the same-PR ordering created (below).

### The deploy window the old ordering created

`scripts/deploy-ubuntu.sh` sequence: `optimize:clear` (:92) → `migrate:status`
(:116) → `migrate --force` (:120) → `queue:restart` (:141). The workflow does
`git reset --hard <sha>` on the live worktree before that
(`.github/workflows/deploy.yml:70-77`). There is **no `artisan down`** and no
maintenance window.

So under the old ordering, deleted shim files went live minutes before the
backfill ran. During that window any request touching `$activity->subject` for
one of the 142 rows would hit the stale optimized classmap
(`vendor/composer/autoload_classmap.php:613` still maps
`'App\Models\Room' => .../app/Models/Room.php`) for a file that no longer
exists — `require(): Failed opening required`, an uncatchable E_COMPILE_ERROR,
not a catchable `ClassNotFoundException`.

Migrating first, with the shim present, makes that window harmless: both names
resolve throughout.

### Migration shape

One migration, four `UPDATE`s. **Do not copy phase 1's `down()`.** That
implementation is `WHERE subject_type = NEW_FQCN`, which rewrites *every*
canonically-named row — including rows written natively as canonical since
2026-08-09 — not just the ones `up()` touched.

Make `down()` an explicit, documented no-op instead:

```php
public function down(): void
{
    // ponytail: irreversible by design. up() cannot distinguish rows it
    // rewrote from rows the application wrote canonically on its own, so a
    // reversal would corrupt the latter. Recovery is to revert code, not
    // data — old code resolves canonical FQCNs correctly via the shim.
    throw new RuntimeException(
        'Irreversible morph backfill. Revert application code instead; '
        .'canonical FQCNs resolve under both namespaces while the shims exist.'
    );
}
```

This is also why phase 1's `class_exists` guard is not carried forward: it
protected the data but silently turned `migrate:rollback` into a success-reporting
no-op, leaving an operator believing state was reverted when it was not.

`activity_log` is 184,145 rows on dev with a composite `subject(subject_type,
subject_id)` index, so each `UPDATE` is index-scoped over ≤87 rows. Production
volume is unmeasured — step 1 fixes that before anything runs.

## Related Code Files

- Create: `database/migrations/<timestamp>_backfill_remaining_shimmed_morph_subject_types.php`
- Modify: nothing else. No caller, no shim, no test, no config.

## Implementation Steps

1. **Measure production first.** Every number in this plan came from dev `asia`;
   production is a different database (`.env:27-29` shows the commented prod
   target). Before scheduling the deploy, get the real counts and confirm no
   other column drifted:

   ```bash
   ssh root@157.10.186.103
   # then, in the app directory:
   php artisan tinker --execute="foreach (['Room','RoomBooking','Club','Building','ClubMember'] as \$m) { echo \$m.' = '.DB::table('activity_log')->where('subject_type','App\\\\Models\\\\'.\$m)->count().PHP_EOL; } echo 'activity_log total = '.DB::table('activity_log')->count().PHP_EOL;"
   ```

   Record the numbers in this file. If the total is large enough that four
   index-scoped `UPDATE`s are a concern in the deploy window, chunk them.

2. **Value-based sweep for stragglers the column-name enumeration missed.** The
   original enumeration filtered on `%_type`/`%_class` column *names*, so any
   class string in a generically-named column was never examined. Two are known
   to hold shimmed FQCNs:

   - `telescope_entries.content` — 236 rows on dev naming `App\Models\Room` / `App\Models\ClubMember`
   - `activity_log.properties` (JSON) — never checked

   Neither is load-bearing (Telescope is a debug tool; `properties` is a
   snapshot payload, not a resolution key), so the decision is to **leave both
   alone and record them as known residue**. Confirm they are still
   non-load-bearing rather than assuming it.

3. **Write the migration** for the four FQCNs, with the throwing `down()` above.

4. **Deploy this migration alone.** Nothing else in the release. Verify it applied
   in every environment before phase 4 is even scheduled.

5. **Verify resolution on real data** — use `whereIn` with exact strings, never
   `LIKE`:

   ```bash
   ./scripts/dev.sh artisan tinker --execute="\$r=0;\$n=0;\$ids=[]; foreach (Spatie\Activitylog\Models\Activity::whereIn('subject_type',['App\\\\Modules\\\\Facilities\\\\Models\\\\Room','App\\\\Modules\\\\Facilities\\\\Models\\\\RoomBooking','App\\\\Modules\\\\Facilities\\\\Models\\\\Building','App\\\\Modules\\\\Engagement\\\\Models\\\\Club'])->get() as \$a) { if (\$a->subject) { \$r++; } else { \$n++; \$ids[] = \$a->subject_type.'#'.\$a->subject_id; } } echo 'resolved='.\$r.' null='.\$n.PHP_EOL; echo implode(PHP_EOL, \$ids).PHP_EOL;"
   ```

   **`resolved + null` must equal the step-1 count.** A bare "no nulls" result is
   not a pass: a `LIKE 'App\Modules\...\%'` pattern matches **zero** rows in
   MySQL — backslash is the `LIKE` escape character, so `\M` collapses to `M`
   and `\%` becomes a literal percent — and prints `resolved=0 null=0`, which
   reads as success while proving nothing. Verified empirically: exact `whereIn`
   returns 100 for the migrated ClubMember rows, the `LIKE` form returns 0.

6. **Trace every null** to a hard-deleted subject row, the way phase 1 traced
   `ClubMember subject_id=3`. The migration rewrites only the type string and
   never touches `subject_id`, so any null resolved to null before the migration
   too.

7. **Note the user-visible consequence** in the deploy notes (see Risks): saved
   or bookmarked `?subject_type=App\Models\Room` links in the Activity Logs UI
   will return zero rows after this migration.

## Success Criteria

- [ ] Production row counts measured and recorded in this file before deploy
- [ ] `activity_log.subject_type` holds zero rows for all 30 shimmed FQCNs, verified with exact `IN (...)` matching, not `LIKE`
- [ ] `resolved + null` equals the pre-migration count (positive control, not just "no nulls")
- [ ] Every null traced to a hard-deleted subject row
- [ ] `down()` throws with a message pointing at code revert as the recovery path
- [ ] Migration deployed and verified applied in every environment
- [ ] Zero files changed outside the one migration
- [ ] `telescope_entries.content` / `activity_log.properties` residue confirmed non-load-bearing and recorded

## Risk Assessment

| Risk | Mitigation |
|---|---|
| Production `activity_log` far larger than dev, so four unbatched `UPDATE`s stall the deploy window | Step 1 measures production first and chunks if needed; each `UPDATE` is index-scoped via the composite `subject` index |
| `LIKE` verification silently matches zero rows and reads as success | Step 5 mandates exact `whereIn` plus a `resolved + null == count` positive control. Empirically proven: the `LIKE` form returns 0 where exact returns 100 |
| Rollback corrupts natively-canonical rows | `down()` throws instead of reversing. Recovery is code revert — old code resolves canonical FQCNs fine through the shims, which this phase leaves untouched |
| **User-visible:** saved/bookmarked Activity Logs filters break | `ActivityLogController.php:79-81` passes `subject_type` straight into an exact `where` with no allow-list, and `:105-111` builds the picker from `DISTINCT subject_type`; `resources/js/pages/Systems/ActivityLogs.vue:243-249` renders the raw FQCN. A bookmarked `?subject_type=App\Models\Room` returns zero rows — indistinguishable from "no room changes" in an audit UI. **This violates plan Goal 4 and must be declared in the PR, not glossed** |
| A null after migration mistaken for a regression | Phase 1 established the pattern; step 6 traces each id |
| Straggler FQCN in a column the name-filtered enumeration missed | Step 2 checks the two known cases by value and records the decision |
