# Academic Module Migration — Scoping Note

Deliverable for Phase 5 (planning only, no code). Written after Phases 1-4
shipped (15 models across Facilities/Upload/Merchandise + 16 across
Engagement, 7 commits: 90f7ef02, c6c4bedc, 09c48d83, 50ed50a4, 81ed1304,
ebfda3ff). This note feeds a future `/ak:plan` run scoped to Academic; it is
not itself an execution plan.

## What Phases 1-4 actually cost (for sizing the follow-up plan)

Per sub-batch (3-8 models), the move+shim+arch-test pattern consistently
took ~1-2h including one review round. Real friction points, in order of
how often they recurred:

1. **Base-class imports get missed.** Any model `extends AuditableModel`
   (or another base class staying in `app/Models`) needs an explicit
   `use App\Models\AuditableModel;` after the move — the implicit
   same-namespace resolution that worked pre-move silently breaks. Hit in
   Merchandise (6 of 7 models), cost one full review round. **Academic
   impact: check which of the 53 candidate models extend AuditableModel
   before batching — likely most of them (audit logging is standard here).**

2. **Factories moved without `protected $model` break for any
   cross-module caller.** Laravel's default factory-name resolver only
   infers the model correctly for `App\Models\*` — a factory moved to
   `Database\Factories\Modules\<Owner>\Models\XFactory` resolves to the
   wrong class unless `$model` is explicit. Missed in sub-PR 4a, shipped
   broken, caught by review, the fix didn't even make it into the same
   commit (staging mistake) — landed in the next sub-PR instead. **Always
   grep for a `protected $model` line and add it before running the
   suite, don't rely on default resolution for any factory that outlives
   this move.**

3. **A model's own pre-existing arch/boundary test can conflict with the
   move.** Facilities' `belongsTo(Campus::class)` needed an explicit
   `App\Models\Campus` import post-move, which an existing boundary test
   banned outright (module was supposed to reach Campus only through a
   contract). Resolved by narrowing the test to exempt `Models/` for that
   one class, not by changing the relationship. **Academic almost
   certainly has boundary tests of its own — read
   `tests/Feature/Architecture/*.php` for anything scanning
   `app/Modules/Academic/**` before starting, the same way this surfaced
   only once Facilities/Engagement work began.**

4. **Sibling-model imports become redundant once both models share the
   new namespace.** When FormResponse (4b) and QueryTicket (4c) ended up
   in the same `App\Modules\Engagement\Models` namespace, Pint correctly
   stripped the now-self-referential `use` line. Not a bug, just
   something to expect — don't "fix" it back.

5. **Pint must be scoped to the exact touched-file list, never a whole
   directory.** Phase 1's first pass ran Pint on `app/Models` broadly and
   reformatted 40 unrelated legacy models; had to be reverted file-by-file.
   Always pass explicit file paths.

6. **A prod-affecting bug hid in the shim mechanism itself, unrelated to
   any one model.** `docker/Dockerfile.production` ran
   `composer dump-autoload --classmap-authoritative`, which drops
   `class_alias`-only files from the classmap entirely — any code still
   on the old `App\Models\X` path would 500 in production. Fixed once in
   Phase 1 (dropped the flag), verified not to recur since. Already fixed
   repo-wide; nothing further needed for Academic.

None of these were architectural blockers — all were caught by the
mandatory narrow-test → full-suite → code-review loop before merge. Budget
one review round per sub-batch; assume ~1.5h/batch is realistic, not the
0.5-1h a bare model move would suggest.

## Candidate Academic model list (53, unclassified)

Computed as: all files remaining in `app/Models/` minus the 30 shims from
Phases 1-4, minus the 3 base/abstract classes (`AuditableModel`,
`StudentAuditableModel`, `UserAuditableModel`), minus the 24 Finance/
Identity-adjacent models this plan's original scout already flagged as
out-of-scope (`TuitionPlan`, `TuitionPlanTerm`, `BillingCycle`,
`VoucherDefinition`, `VoucherApplication`, `ScholarshipDefinition`,
`GraduationRequirement`, `IeltsCertificate`, `GraduationApplication`,
`ProgramChangeRequest`, `DeferCase`, `DeferCaseItem`, `EgcBlock`,
`EgcRetakeDiscountLink`, `CanvasCourseMapping`, `CanvasIntegration`,
`EmailConfiguration`, `EmailLog`, `User`, `Role`, `Permission`,
`RolePermission`, `CampusUserRole`, `UserEmailPreference`).

This is **5 more than the plan's original "~48" estimate** — the original
count didn't separately track `Campus` or a handful of Admissions/wallet-
adjacent models. The follow-up plan's first job is resolving these, not
assuming they're all Academic:

```
AcademicHold                   AcademicProgressionEvent       AcademicRecord
AcademicStanding               AcademicWarningSetting         Answer
AnswerOption                   ApplicationGuardian             AssessmentComponent
AssessmentComponentDetail      AssessmentComponentDetailScore Attendance
Campus                         ClassSession                    CourseOffering
CourseRegistration             CourseRetakeRegistration        CurriculumModule
CurriculumUnit                 CurriculumVersion               Department
DepartmentMembership           Enrollment                      EquivalentUnit
ExamResitAttempt               ExamResitSession                ExamRoomSlot
ExamRoomSlotInvigilator        GpaCalculation                  Lecture
Module                         Option                          ParentProfile
Program                        Question                        Semester
Specialization                 Student                         StudentActionAttachment
StudentActionLog               StudentApplication               StudentChange
StudentDecision                StudentFormAssignment           StudentFormSurvey
StudentScholarshipAward        StudentSetting                  StudentWallet
StudentWarningLog              SyllabusTemplate                Unit
UnitPrerequisiteCondition      UnitPrerequisiteGroup
```

**Flagged for owner judgment before batching** (likely not core Academic,
verify with a domain owner rather than assuming):
- `Campus` — shared/Institution-scoped, referenced by every module moved
  so far (Facilities, Merchandise, Engagement all `belongsTo(Campus)`).
  Moving it under Academic would be wrong; it may need its own
  Institution/Shared module instead of joining this migration at all.
- `ParentProfile`, `ApplicationGuardian`, `StudentApplication` —
  Admissions-flow models, not teaching/curriculum domain.
- `StudentWallet` — Gold/wallet balance, referenced by `GoldTransaction`
  (Merchandise-owned since Phase 3); may belong with Merchandise instead.

## Coupling counts (informs batch order and the Student-alone rule)

| Model | Files referencing `App\Models\X` |
|---|---|
| `Student` | 467 |
| `Program` | 156 |
| `Enrollment` | 10 |

(File counts, not raw occurrence counts — the original plan's scout pass
quoted 1,340 raw occurrences for `Student`; both numbers point the same
direction.) `Student` is two orders of magnitude more coupled than
`Enrollment` and three times `Program`. The Phase 4 pattern (least-coupled
sub-domain first) applies directly: whatever the follow-up plan picks as
its first Academic sub-batch, it should start from the low end of this
table, not `Student`.

## Recommendation for the follow-up plan

1. Resolve the 4 flagged models above (Campus, ParentProfile,
   ApplicationGuardian, StudentApplication, StudentWallet) with a domain
   owner before drafting sub-batches — don't guess ownership the way this
   note's list-diff did.
2. Batch the remaining ~48 into 3-8-model sub-PRs, same shape as Phase 4's
   4a/4b/4c, ordered by ascending reference count. Curriculum/definition
   models (`CurriculumModule`, `CurriculumUnit`, `CurriculumVersion`,
   `EquivalentUnit`, `Module`, `Unit`, `UnitPrerequisiteCondition`,
   `UnitPrerequisiteGroup`, `SyllabusTemplate`) look like the
   lowest-coupling starting sub-domain based on Phase 1-4's own precedent
   (Facilities before Merchandise before Engagement), but this needs a
   real grep count per model before the follow-up plan commits to an
   order, not an assumption from this note.
3. `Student` (467 files / 1,340 occurrences) ships as its own final PR,
   not bundled with anything else — per the original plan's Risk
   Assessment, unchanged.
4. Before starting, read `tests/Feature/Architecture/*.php` for any
   test scanning `app/Modules/Academic/**` for banned `App\Models\*`
   imports (lesson #3 above) and grep every Academic model for
   `extends AuditableModel` / other base classes (lesson #1) so both are
   known going in rather than discovered mid-batch.
5. Every moved model's factory (if one exists) needs `protected $model`
   verified explicitly — do not trust default resolution (lesson #2).

## Success criteria (from phase-05-academic-module-scoping-deferred.md)

- [x] This note exists, informed by Phases 1-4's actual cost and lessons
- [x] No `app/Models/*` Academic file touched by this phase
- [ ] A follow-up `/ak:plan` run scoped to Academic, sized 3-8 models per
      phase — **not done here**; this note is its input, not a substitute
