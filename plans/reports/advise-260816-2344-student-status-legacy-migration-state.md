# advise-state
phase: advise (complete)
input: "rà soát và fix các code cũ dùng cột status cũ ở bảng student, chuyển sang dùng status của `program_enrollments`" (audit + fix legacy readers of students.status → migrate to program_enrollments status)
flags: --agent
report: /Users/hunt2412/hieupvdev/project/swinx/plans/reports/advise-260816-2344-student-status-legacy-migration.md

## established-context (from orchestrator, verified this session)
- Canonical source = program_enrollments primary row (is_primary=1): enrollment_status + study_stage. Progression does NOT write back to students.status.
- Two shared readers exist:
  - ProgramEnrollmentReader (EloquentProgramEnrollmentReader) — rich, keeps dropout_transfer/admission_deferred distinct, ~5 extra queries/call.
  - StudentLifecycleStatusReader — 1-2 queries, collapses withdrawn→dropout (loses dropout_transfer).
  - Both fall back to students.status when no primary enrollment (lazy materialization).
- Live drift on dev DB confirmed (11 deferred-vs-active rows etc.). 186 active / 43 deferred / 6 withdrawn primary rows; 1 active row NULL study_stage.
- ALREADY DONE this session (uncommitted, do NOT re-recommend): Student::statusLabelFor/statusColorFor statics; ListDueItemsQuery + AcademicFinanceChargeSourceGateway batch via StudentLifecycleStatusReader; LifecycleDueExceptionRowMapper/ReviewEventMapper, ListCollectionProgressQuery, ListFeeMonitorQuery, ListDngLifecycleQuery label/color from live status; 2 new Finance tests.
- Known hotspot: EloquentStudentRegistryStore reference()/joinedReference() → StudentReference.status/.statusLabel from legacy column; ~20 consumers incl. Academic Delivery LOGIC (eligibility/enroll/retake/roster/gradebook) + Engagement.
- Still legacy: Student::FINANCIAL_STATUSES/BLOCKED_STATUSES/CLASS_ROSTER_INACTIVE_STATUSES scopes; getStatusLabelAttribute direct reads.
- No CI running tests; Finance suites have 1-2 pre-existing unrelated failures; large multi-dir runs flaky.

## scout-findings

### tests/factories/seeders (scout 4, done)
- StudentFactory default status='active'; states inactive(), graduated(); does NOT create program_enrollments. NO ProgramEnrollment factory exists.
- ~291 test files use Student::factory; only 3 create ProgramEnrollment rows directly (~13 touch it at all). So ~288 test files exercise the students.status FALLBACK path, not the enrollment path.
- ~373 factory calls pass 'status' => directly; values used: active, inactive, graduated, intake_course, pending_course_opening, deferred.
- Seeders: StudentSeeder sets 'active'; MetropoliaGradingScenarioSeeder sets 'intake_course'.
- tests/Unit/Models/StudentStatusLabelTest.php = label mapping test. No arch/guard test forbidding students.status usage exists.
- Heavy test dependency: Finance ~59 files, Academic ~42 files.

### StudentReference consumers (scout 3, done)
- DTO: app/Shared/Contracts/StudentRegistry/DTO/StudentReference.php (status L24, statusLabel L27); populated in EloquentStudentRegistryStore L286/289 (reference) and L313/316 (joinedReference) straight from students.status / status_label.
- LOGIC consumers of StudentReference->status (would change behavior on flip):
  - CheckCourseRegistrationEligibilityQuery L32: blocks if in ['inactive','dropout','dropout_transfer','graduated','pending']
  - RegisterStudentForActiveSemesterUnitsAction L41: same list
  - CreateRetakeCourseRegistrationAction L63: requires status === 'intake_course'
  - BulkRegisterCourseOfferingStudentsAction L35 + SearchCourseOfferingStudentsAction L62-63: EGC units require 'intake_pre_uni_gc', others 'intake_course'
  - GetStudentStatusBySemesterQuery L216: passes through 'pending'/'admission_deferred'
  - EloquentProgramEnrollmentReader L178 + MaterializeProgramEnrollmentAction L99: use students.status as intake study_stage SEED (intentional bootstrap reads — keep)
- Already-migrated pattern in some consumers: ListStudentsQuery L67 + StudentController L241/321 + Fee/Dng/Collection queries overwrite with legacyCompatibleStatus() ?? fallback.
- DISPLAY consumers: AiAcademicEntitySearchReader, AiAcademicStudentProfileReader, LecturerAttendanceService, PreviewStudentDecisionBulkLinkQuery, AcademicPlacementController, ImpersonationTokenIssuer, PortalProfileReader, EventParticipantResource, Api/StudentController, Api/AuthController, Exports/StudentExport.
- Value-set gaps on flip to enrollment-derived: 'pending'/'admission_deferred' = pre-enrollment, fallback covers (SAFE); 'dropout_transfer' has NO enrollment equivalent via cheap reader (withdrawn→dropout collapse) — registration gates would still block (dropout is in blocklist) but the distinct value is lost; 'pending_course_opening' + intake stages depend on study_stage being populated (1 active row NULL study_stage in dev DB); 'suspended'/'deferred' not enrollment concepts — pass through/fallback.
- Key nuance: registration-eligibility blocklists treat both 'dropout' and 'dropout_transfer' as blocked, so the withdrawn→dropout collapse does NOT change gate outcomes there; fidelity loss matters only where the two must display/report differently.

### readers inventory (scout 1, done) — remaining non-migrated readers, 11 PHP files
- SQL-FILTER (7 hits/4 files): LifecycleDueItemPredicate L78/L100 (whereIn students.status FINANCIAL_STATUSES — fallback branch for unmaterialized); EloquentStudentCollectionEligibilityReader L40 (fallback whereIn); GetLifecycleDueExceptionSummaryQuery L37-44; ListLifecycleDueExceptionsQuery L152/156.
- LOGIC (3): LifecycleDueItemPredicate L15/24 ($student->status vs FINANCIAL_STATUSES); CourseRegistration L154-155 (CLASS_ROSTER_INACTIVE_STATUSES → roster status); EloquentStudentPortalContextReader L52 (already enrollment-derived lifecycleStatus vs BLOCKED_STATUSES — value-set reuse, not column read).
- EXPORT/REPORT (4): DashboardChartsService L58-59/267-268 (COUNT CASE on students.status); DashboardStatsService L69-71 (groupBy status); ListLifecycleDueExceptionsQuery L36 eager-load; StudentLifecycleStatusReader L32 (intentional fallback pluck — keep).
- DISPLAY: Student accessors getStatusLabelAttribute/getStatusColorAttribute (read $this->status); EloquentStudentRegistryStore status_label; Vue pages Students/Index, FeeMonitor, CollectionProgress, Clubs/Show display it.

### writers inventory (scout 2, done)
- Only 6 writer sites, all create-time: Api/AuthController L193-199 ('inactive', self-registration); RegisterAdmittedStudentAction ('active'); StudentService::createStudentForStaff + createAdmittedStudent ('active'); StudentSeeder ('active'); StudentService::updateStudentStatus L554 (param-driven, CURRENTLY UNUSED).
- Only 2 values ever written today: 'active', 'inactive'. All other 11 constants (intake_*, deferred, dropout*, etc.) are historical data only — column is dead-on-write after creation.
- CONFIRMED: no Progression write-back to students.status (lifecycle writer touches program_enrollments only; StudentStatusService writes academic_status, different column).

### synthesis
- Column is frozen at creation ('active'/'inactive'); every later lifecycle change lives only in program_enrollments → drift is structural and permanent, not incidental.
- Biggest behavior surface: StudentReference.status → 5 Delivery gates (registration eligibility, retake, bulk/search offering, semester-status query).
- Blast radius of "drop the column": 288 test files + factory rely on fallback; no ProgramEnrollment factory; pre-enrollment states (pending/admission_deferred) genuinely need a non-enrollment home; intake seed reads (MaterializeProgramEnrollmentAction/EloquentProgramEnrollmentReader) intentionally read it.

## qa-log
- Q1 (endgame): route-all-reads vs retire-column vs gates-only -> A1: "Route all reads + keep fallback" — all remaining readers (incl. StudentReference/gates, dashboard/export) switch to enrollment-derived status; keep students.status fallback for students without enrollment; no schema change; don't touch the 288 tests.
- Q2 (flip point): flip StudentReference at source vs per-consumer vs dual-field -> A2: "Flip at source, hành vi trên prod như vậy là đúng, không dùng legacy nữa, chốt" — EloquentStudentRegistryStore reference()/joinedReference() resolve from enrollment (fallback for unmaterialized). User EXPLICITLY ACCEPTS prod behavior change: 11 drifted students (legacy 'deferred', enrollment 'active') regain registration eligibility. No second DTO field, no parallel legacy path.
- Q3 (reader fidelity): cheap vs rich vs cheap+fix-collapse -> A3: "Cheap reader everywhere" — StudentLifecycleStatusReader for StudentReference + dashboard + SQL-filter. Accepts losing dropout_transfer vs dropout distinction on display/reports. Surfaces already on rich reader (ListStudentsQuery, FeeMonitor, DNG Lifecycle) stay unchanged.
- Q4 (verification): -> A4: "Targeted suites + drift probe + guard test" — per-file Delivery/StudentRegistry/Finance suites (avoid flaky big runs), tinker probe comparing old/new status on dev DB for enrolled students, arch/guard test forbidding NEW students.status reads outside whitelist (fallback + intake seed).

## reframing-draft
problem: legacy students.status column drifts from canonical program_enrollments status; remaining readers (esp. StudentReference → Academic Delivery logic) act on stale data.
requirements: TBD via interview
goals: TBD
non-goals: TBD
constraints: no CI; dual-read fallback exists for students w/o primary enrollment; advisory rules — no bare php, use ./scripts/dev.sh

## interview-candidates (ordered)
1. Q1 (blast radius/scope): StudentReference.status flip semantics — display-only vs also logic gating? (biggest behavior decision)
2. Column endgame: backfill+retire students.status vs keep dual-read fallback forever?
3. Fidelity: acceptable to lose dropout_transfer/admission_deferred distinction where cheap reader used?
4. Rollout: big-bang vs per-module incremental?
5. Verification bar given no CI (targeted suites? drift re-measure? guard/arch test?)

- Q5 (confirm reframing): -> A5: user confirmed content (complained about verbosity only, no corrections) — reframing LOCKED. Orchestrator directive: short report, inventory-table-first, 3 groups (source flip / 11 readers / intentional keeps), checklist + measurable metrics.

## next
DONE. Report written to advise-260816-2344-student-status-legacy-migration.md (inventory-first format per user preference). ADVICE_READY emitted.
