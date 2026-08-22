# GPA Correction Campaign — Phase 5 Runbook

Operational runbook for the correction campaign in
`plans/260814-1359-gpa-reopen-on-post-finalize-grade-change/phase-05-correction-campaign-for-73-diverged-rows.md`.
No code — data correction through the shipped tooling. Fill the "Result" blocks
as you go; this file doubles as the campaign artifact.

## Preconditions (all must hold before starting)

- [ ] Phases 1, 2, 3, 4, 6 deployed to prod (correctable re-finalize, divergence
      badge, campus authz, sync alert, pass/fail-from-is_passed + the earned-credit
      fix command). Re-finalize (Phase 1) and the fix command (Phase 6) are the
      load-bearing ones; without them this campaign cannot run.
- [ ] Fresh DB backup taken and its restore path recorded (rollback = restore).
- [ ] A registrar/admin user id chosen for attribution (`ADMIN_ID` below) — it is
      written to `gpa_calculations.finalized_by_id` and the activity causer.
- [ ] Prod artisan entrypoint known. Prod is not the local docker; use prod's
      own invocation (e.g. `php artisan` from the app root under
      `/www/wwwroot/<app>`), not `./scripts/dev.sh`. `ssh root@157.10.186.103`.

> Measurements in the plan are **dev-only**. Re-measure on prod with the baseline
> step below; do not assume the 73/9/5 counts transfer.

## Step 0 — Baseline (before any change)

Capture the pre-campaign audit as the artifact:

```
php artisan academic:audit-progression-reconciliation --all --format=json > /root/gpa-campaign-baseline.json
```

Record counts (from the JSON `counts` block):

- GPA-001 (`persisted_value_differs_from_transcript_derived_value`): ____
- `missing_persisted_calculation` for CLOSED semesters (exclude the in-progress
  semester, e.g. SUMMER2026): ____
- HUB-001 (`student_hub_consumer_evidence_differs_from_transcript`): ____

Confirm the failed-unit earned-credit rows the Phase 6 command will touch:

```
php artisan academic:fix-failed-record-earned-credits --dry-run
```

- academic_records ids reported: ____
- transcript_entries ids reported: ____

**Result (baseline):**

```
(paste counts + dry-run output)
```

## Step 1 — Phase 6 data fix (must precede re-finalize)

Re-finalize recomputes earned credits from the transcript; the failed-unit rows
must be zeroed first or the wrong values get written into `gpa_calculations`.

```
php artisan academic:fix-failed-record-earned-credits --dry-run   # confirm the ids again
php artisan academic:fix-failed-record-earned-credits --commit
```

Verify zero remain:

```sql
SELECT COUNT(*) FROM academic_records   WHERE is_passed = 0 AND (credit_points_earned > 0 OR credit_hours_earned > 0);  -- expect 0
SELECT COUNT(*) FROM transcript_entries WHERE is_passed = 0 AND credit_points_earned > 0;                                -- expect 0
```

**Result (Step 1):** ____

## Step 2 — Enumerate the (campus, semester) set to re-finalize

Derive it from the audit rather than hardcoding. Diverged + never-finalized keys
both resolve to a semester; re-finalizing the whole semester covers every
affected student in it (matching students are skipped, idempotent — Phase 1).

Diverged semesters (from GPA-001 keys) and never-finalized semesters (from
`missing_persisted_calculation`, closed semesters only):

```sql
-- Diverged (student, semester) pairs → the semesters needing re-finalize
SELECT gc.semester_id, s.code, COUNT(*) AS diverged_students
FROM gpa_calculations gc
JOIN semesters s ON s.id = gc.semester_id
WHERE gc.is_finalized = 1
GROUP BY gc.semester_id, s.code;   -- cross-check against the audit's GPA-001 semesters
```

Prefer the JSON baseline's `exceptions[].stable_key` (`student:X:semester:Y`) as
the authoritative source of affected semesters. List them here:

**Semesters to re-finalize (closed only; exclude the in-progress semester):**

| semester_id | code | reason (diverged / never-finalized) |
|---|---|---|
| | | |

## Step 3 — Re-finalize each affected semester

For each semester in the table, re-run Finalize. Two equivalent routes:

- **Finalize page (preferred, matches the plan's "ordinary Finalize page"):** open
  the GPA finalize page for that campus + semester, confirm the Phase 2 divergence
  badge count matches the audit for that scope, then click Finalize. Repeat per
  campus. Campus authz (Phase 3) requires the operator be a member of each campus.
- **Console (whole semester, all campuses at once)** — bypasses the HTTP campus
  gate; use only if a single operator is correcting every campus:

```
php artisan tinker
>>> app(\App\Actions\Academic\FinalizeSemesterGpaAction::class)->execute(SEMESTER_ID, ADMIN_ID, null);
```

`campusId = null` finalizes every student in the semester. Unchanged rows are
skipped; diverged rows update in place and emit a `gpa_refinalized` activity
entry carrying the prior GPA; the 5 never-finalized pairs get created.

**Result (Step 3):** per-semester processed / skipped counts:

```
(paste each run's result summary)
```

## Step 4 — Verify

```
php artisan academic:audit-progression-reconciliation --all --format=json > /root/gpa-campaign-after.json
```

- [ ] GPA-001 for closed semesters (FALL2025, SPRING2026, …) = 0
- [ ] HUB-001 = 0 (Phase 6 transcript accessor already removes this structurally;
      confirm on real data)
- [ ] `missing_persisted_calculation` for closed semesters = 0 (in-progress
      semester rows remain absent by design)
- [ ] Spot-check AUS19927 (student 506) SPRING2026:

```sql
SELECT semester_gpa, cumulative_gpa FROM gpa_calculations
WHERE student_id = 506 AND semester_id = (SELECT id FROM semesters WHERE code LIKE 'SPRING2026%')
  AND is_finalized = 1;   -- expect 80.737 / 81.293
```

- [ ] Every corrected row has a `gpa_refinalized` activity entry:

```sql
SELECT COUNT(*) FROM activity_log WHERE log_name = 'gpa_refinalized';
```

**Result (Step 4):** ____

## Step 5 — Per-student delta list (dispute record)

62 students' published GPA drops (dev: median 0.85, max 4.85); `academic_standing`
unchanged for all. No student notification (user decision). Capture the deltas so
any dispute is answerable — diff baseline vs after JSON, or from the
`gpa_refinalized` activity properties (`old` vs `new`):

```sql
SELECT JSON_EXTRACT(properties,'$.student_id')  AS student_id,
       JSON_EXTRACT(properties,'$.semester_id') AS semester_id,
       JSON_EXTRACT(properties,'$.old')         AS old_gpa,
       JSON_EXTRACT(properties,'$.new')         AS new_gpa
FROM activity_log WHERE log_name = 'gpa_refinalized' ORDER BY id;
```

**Result (Step 5) — delta list:**

```
(paste)
```

## Rollback

If verification fails or a run goes wrong: restore the Step-0 DB backup. All
changes here are data (fix command + re-finalize writes), so a restore fully
reverts. No code rollback needed (code ships independently).

## Sign-off

- Run by: ____   Date: ____
- Baseline artifact: `/root/gpa-campaign-baseline.json`
- After artifact: `/root/gpa-campaign-after.json`

## Unresolved questions

- Confirm the prod artisan entrypoint and the app root path before Step 0.
- Confirm the registrar `ADMIN_ID` to attribute the corrections to.
- Decide route for Step 3 (Finalize page per campus vs console all-campus) based
  on who is running the campaign and their campus membership.
