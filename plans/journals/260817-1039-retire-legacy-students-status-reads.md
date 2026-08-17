# Retired students.status Reads, Found a Real Auth Bypass Along the Way

**Date**: 2026-08-17 10:39
**Severity**: High
**Component**: Student lifecycle status (Academic/Finance/Auth), `app/Models/Student.php`, `program_enrollments` projection
**Status**: Resolved

## What Happened

Executed all 6 phases of `plans/260817-0017-retire-legacy-studentsstatus-reads/` end to end: moved lifecycle-status reads off the write-dead `students.status` column onto the `program_enrollments` primary-row projection, keeping the column as fallback (no enrollment) and as deliberate historical source for 3 EGC rules. 63 files changed, +4103/-241, committed as `2a9651b7a` on `dev` (not pushed, user-approved via AskUserQuestion).

The red-team review before implementation (3 hostile reviewers, 29 findings, 15 after dedupe) flagged C1 as critical: `Student::isActive()` only computed from an eager-loaded relation and silently fell back to the stale column otherwise. `scopeActive()` (SQL) already flipped correctly, so list views correctly excluded a withdrawn-by-enrollment student — but `isActive()` didn't, meaning that same student kept portal login and admin impersonation. Auth checked one thing, lists showed another.

## The Brutal Truth

This is the kind of bug that looks fine in every code review because it's not wrong in the common case — it's wrong exactly when the relation isn't loaded, which auth-path code (single-student lookups, not list queries) hits constantly. A withdrawn student staying logged in past withdrawal is a real security defect, not a cosmetic inconsistency, and it shipped originally because "compute only if relation loaded, never lazy-load" sounded like a reasonable performance guard instead of what it actually was: a silent correctness hole. Nobody caught it until a red-team pass explicitly hunted for auth-path divergence from list-path behavior.

## Technical Details

Fix: `Student::lifecycleStatus()` now resolves explicitly (one extra query when the relation isn't preloaded) instead of falling back silently. Because every auth-path caller (`AuthController`, `EloquentStudentImpersonationTokenIssuer`, `StudentApiAuthorization`, `ParentStudentAccess`) already routes through `$student->isActive()`, zero auth files needed direct edits — the fix landed once, centrally.

Two other things surfaced mid-execution, not part of the plan:
- `AcademicFinanceChargeSourceGateway.php` had an errant `use App\Models\Student;` from unrelated prior WIP, breaking `StudentRegistryFinanceBoundaryArchTest`. Fixed by extracting `StudentLifecycleStatusPresenter` into `app/Shared/Support/Academic/`, decoupling 6 Finance/Academic files from the Student model.
- The phase-5 guard test (comment-stripped, receiver-anchored regex scan for `students.status` reads) caught `EloquentSemesterEnrollmentEligibilityReader.php` — classified for migration back in phase 0/1 but never actually touched until the guard flagged it in phase 5. The plan's own inventory missed its own migration target.

Query-count budgets needed real threshold bumps, not fudging: `CollectionProgressViewTest` 17→19, `BillingExceptionsPaginationTest` <10→<14 — phase 1's correct `StudentReferenceReader::findMany()` batching adds 2 fixed, non-per-row queries. Documented as an accepted cost, not silently absorbed.

Final sweep: 448 tests, 5 pre-existing failures, each confirmed via `git stash` against clean `dev` HEAD before this session touched anything — so none of them are misattributed to this change. Drift probe against real `asia` dev data (read-only) showed zero unexplained drift beyond the 15 known/approved students; all SQL-vs-PHP symmetric-difference checks empty.

## What We Tried

Building the guard arch test took several failed iterations before it worked: "any file under `app/Models/` is a candidate" produced false positives on unrelated models' own `status` columns; a loose proximity window around a `whereHas(...)` pattern crossed scope boundaries into an unrelated method later in the same class. Each iteration was verified empirically against the actual codebase rather than trusted on read — a regex that looks right on paper is not the same as a regex that doesn't false-positive on 60+ real files.

## Root Cause Analysis

The original `isActive()` design conflated two different concerns — "avoid an extra query when we already have the data" and "what happens when we don't" — and picked silent staleness as the answer to the second without anyone deciding that on purpose. The plan's own inventory (phase 0) was thorough but not exhaustive; the guard test in phase 5 existed specifically because "we listed every call site" is not the same claim as "we can prove nothing was missed."

## Lessons Learned

A function that behaves correctly with a precondition (relation loaded) and silently degrades without it is a landmine, not an optimization — if the fallback path is wrong, fail loud or resolve it, don't drift. Auth-path code and list-path code reading the "same" logical field from two different mechanisms (eager relation vs. raw column) will diverge eventually; route both through one resolver. A regression guard test is only as good as its false-positive rate — an arch test that never triggers on real code hasn't been tested, it's been assumed.

## Next Steps

- Commit `2a9651b7a` sits on `dev`, unpushed — push when ready per normal workflow, no action pending from this session.
- No CI in this repo (per existing memory `no-ci-test-workflow-in-repo`) — the guard arch test only protects against regression on manual runs; anyone touching lifecycle-status reads later must run `tests/Feature/Architecture/` by hand.
- ADR-0033 was amended this session to record the source-of-truth decision; durable rationale lives there, not here.
