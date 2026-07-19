# PRD: Swinx Domain Boundary Decomposition

Status: ready-for-agent

Portal impact: both

> Source: the accepted domain-modeling session and ADR-0031 through ADR-0043. ADR-0026 remains the governing Academic/Finance boundary. This is an umbrella architecture PRD: deliver it through dependency-ordered, behavior-preserving slices rather than one large refactor.

## Problem Statement

Swinx maintainers need to evolve university operations without every Academic change rippling through Identity, Finance, Facilities, Admissions, Notification, and both portals. The current Academic Module is a catch-all: Student and Semester state are consumed across domains, lecturer employment is mixed with teaching assignments and authentication, Campus and Building administration sit under Academic, Admissions directly creates User and Student records, and cross-module flows still import concrete Actions or shared Eloquent business models.

This makes ownership ambiguous. A Student currently represents identity, academic lifecycle, guardian access, and Finance correlation; Semester represents period identity, registration windows, active-period policy, and selected UI context; Lecture combines employment, pay, authentication eligibility, preferences, and delivery workload. Large services then coordinate these mixed responsibilities directly, which makes safe change, isolated testing, and staged replacement difficult.

The problem is not merely folder placement. Moving existing God Models into new directories would preserve the same coupling under new names. Swinx needs explicit Bounded Context ownership, stable cross-context contracts, architecture enforcement, and a migration order that preserves production behavior while dependencies are cut one seam at a time.

## Solution

Keep Swinx as a Laravel modular monolith while decomposing business ownership into explicit Bounded Contexts:

- Identity & Access
- Institution & Organization
- Student Registry
- Admissions
- Faculty Workforce
- Academic Catalog & Calendar
- Course Delivery & Assessment
- Academic Progression & Lifecycle
- Finance
- Facilities
- Notification
- AI & Reporting as a read-only supporting context

Student, guardian/parent, and lecturer remain Actors or business profiles rather than portal-shaped domains. Semester becomes the current implementation of the institution-wide Academic Period. The current Academic Module first gains internal context namespaces, neutral references, shared contracts, and architecture tests; separate top-level Modules are created only when a context has clean dependencies and enough depth to justify physical extraction.

Delivery follows a strangler sequence. The first work establishes boundary safety nets and reference contracts. Academic Catalog & Calendar is the first complete Academic extraction. Student Registry, Admissions, Faculty Workforce, Course Delivery & Assessment, and Academic Progression & Lifecycle follow in dependency order. Existing routes, tables, and portal contracts remain stable during initial slices unless a later issue explicitly coordinates a contract migration.

## User Stories

**Architecture and maintainability**

1. As a platform maintainer, I want every business concept to have one owning Bounded Context, so that changes do not require guessing which module is authoritative.
2. As a platform maintainer, I want physical Modules distinguished from logical Bounded Contexts, so that ownership can improve before disruptive file and schema moves.
3. As a platform maintainer, I want cross-context dependencies expressed through narrow contracts, so that a context can change its persistence without changing consumers.
4. As a platform maintainer, I want architecture tests to reject forbidden cross-context imports, so that cleaned boundaries do not regress.
5. As a platform maintainer, I want transitional adapters to have explicit owners and retirement criteria, so that migration code does not become permanent architecture.
6. As a platform maintainer, I want each migration slice to preserve external behavior, so that architectural improvement does not become a product rewrite.
7. As a platform maintainer, I want business transactions to remain atomic where users require an immediate complete outcome, so that context separation does not create partial records.
8. As a platform maintainer, I want post-commit reactions delivered through durable events and outbox processing, so that notifications and integrations are retryable.
9. As a platform maintainer, I want reporting reads separated from command-side rules, so that stale projections cannot authorize irreversible actions.
10. As a platform maintainer, I want the boundary migration delivered in dependency order, so that later contexts build on stable identity, period, and result contracts.

**Identity, Student Registry, and guardian access**

11. As a Student, I want one stable Student Identity across my academic and financial history, so that records remain correlated when my Program Enrollment changes.
12. As a Student, I want my login state independent from my Program Enrollment Status and Study Stage, so that authentication policy does not overwrite academic history.
13. As a Student Registry staff member, I want Student Identity to contain only stable identifiers, contact details, and campus affiliation, so that lifecycle and money rules remain with their owners.
14. As a context owner, I want to consume a minimal Student Reference rather than the Student God Model, so that I cannot accidentally depend on unrelated state.
15. As Academic Affairs staff, I want Program Enrollment to preserve program, curriculum, intake, and lifecycle history, so that transfers and completed study remain auditable.
16. As Academic Affairs staff, I want Program Enrollment Status distinct from Study Stage, so that deferred enrollment and EGC/course/major placement are not represented by one ambiguous status.
17. As a Guardian, I want my relationship to a Student preserved even if I have no email or portal account, so that family and responsibility records are complete.
18. As an Identity administrator, I want Guardian Access Grant managed separately from the Student–Guardian Relationship, so that access can be changed without deleting the relationship.
19. As a Guardian, I want revocation of portal access to leave the underlying relationship intact, so that operational authorization does not rewrite Registry truth.
20. As an Identity maintainer, I want student and guardian login flows to read Identity-owned access state, so that authentication does not infer authorization from Registry or Academic tables.

**Admissions**

21. As Admissions staff, I want Admissions to remain the owner of Applicant, Application, Applicant Guardian, and admission documents, so that pre-student data has one source of truth.
22. As Admissions staff, I want Approve to remain one atomic action, so that an Applicant never becomes a partially provisioned Student.
23. As Admissions staff, I want Approve to create Student Identity through Student Registry, so that Admissions does not write Registry models directly.
24. As Admissions staff, I want Approve to create account access through Identity & Access, so that credentials and grants follow one authorization policy.
25. As Admissions staff, I want Approve to create Program Enrollment through Academic Progression & Lifecycle, so that intended program and intake become history-bearing academic facts.
26. As Admissions staff, I want every Applicant Guardian preserved during Approve even without an account, so that no responsibility relationship is silently discarded.
27. As Admissions staff, I want Revoke to use the same context contracts and existing downstream-activity guard, so that rollback is safe and ownership remains consistent.

**Institution, Campus, and Academic Calendar**

28. As an institution administrator, I want Institution Campus to have a canonical Swinx identity, so that all contexts reference the same campus.
29. As an institution administrator, I want Department identity and hierarchy owned outside Academic Catalog, so that organizational changes do not rewrite curricula.
30. As Academic Affairs staff, I want one institution-wide Academic Period identity, so that teaching, progression, reporting, Admissions, and Finance correlate to the same period.
31. As campus operations staff, I want a Campus Period Schedule for genuine local date or registration-window differences, so that campuses can vary without duplicating Academic Periods.
32. As a staff user, I want Selected Academic Period treated as navigation and filter state, so that my UI choice does not change the institution's Current Academic Period.
33. As Academic Catalog staff, I want Programs, Curriculum Versions, Units, syllabi, and Academic Periods owned together, so that catalog rules and calendar references evolve coherently.
34. As a context consumer, I want Academic Period and Campus references through stable contracts, so that I do not depend on the current Semester or Campus model internals.

**Faculty Workforce and lecturer access**

35. As workforce staff, I want Faculty Member employment, contract, rank, qualifications, expertise, preferences, and pay terms owned by Faculty Workforce, so that HR-like facts are not mixed with course delivery.
36. As an Academic scheduler, I want Teaching Eligibility supplied by Faculty Workforce, so that professional and contractual eligibility has one source of truth.
37. As an Academic scheduler, I want Instructor Assignment owned by Course Delivery & Assessment, so that workload, timetable, and current conflicts are evaluated with live delivery state.
38. As an Academic scheduler, I want assignment decisions to combine Teaching Eligibility with Delivery availability, so that neither context claims to answer the whole question alone.
39. As a Lecturer, I want my portal access independent from individual course assignments, so that removing one assignment does not disable my account.
40. As a Lecturer, I want leave or sabbatical to preserve access by default, so that I can still use permitted lecturer capabilities unless policy explicitly says otherwise.
41. As an Identity administrator, I want termination, suspension, or contract expiry to revoke Lecturer Access Grant synchronously, so that ineligible users cannot continue authenticating.
42. As an Identity maintainer, I want lecturer login and token refresh to read Account Status and Lecturer Access Grant only, so that authentication does not query employment tables.
43. As a workforce auditor, I want Faculty Access Eligibility to include a reason, so that access changes are explainable.
44. As a platform operator, I want durable reconciliation between Faculty Access Eligibility and Lecturer Access Grant, so that failed handoffs cannot leave access drift indefinitely.

**Course Delivery, assessment, and progression**

45. As a Lecturer, I want Course Offering, roster, sessions, attendance, gradebook, and assessments managed as one delivery context, so that teaching operations remain cohesive.
46. As Academic Affairs staff, I want Course Registration to represent roster participation in a Course Offering, so that it is not confused with Program Enrollment.
47. As a Lecturer, I want Course Result finalized from Delivery-owned assessment and attendance evidence, so that Transcript logic does not inspect gradebook internals.
48. As Academic Affairs staff, I want Transcript Entry created from a finalized Course Result, so that GPA, standing, best attempt, and graduation use a stable progression record.
49. As Academic Affairs staff, I want Course Completion to commit Course Results and Transcript Entries atomically, so that a completed offering never lacks transcript evidence.
50. As Academic Affairs staff, I want Recalculate to use the same Course Result-to-Transcript boundary, so that corrected results follow the same invariants as initial completion.
51. As a Student, I want Program Enrollment, Transcript Entries, EGC progression, lifecycle actions, and graduation history preserved independently from identity, so that my academic record remains auditable.
52. As an academic auditor, I want Academic Progression & Lifecycle to own GPA and Academic Standing, so that reporting cannot derive them inconsistently from component scores.

**Finance, Facilities, and Notification**

53. As Finance staff, I want Finance to consume Student Reference and academic lifecycle facts through contracts or events, so that Finance does not infer billing from Student status fields.
54. As Finance staff, I want DNG Campus Mapping owned by Finance, so that provider-specific codes do not pollute Institution Campus identity.
55. As Finance staff, I want hard financial gates to query authoritative Finance state synchronously, so that stale projections cannot grant clearance.
56. As Facilities staff, I want Buildings, Rooms, capacity, Space Availability, and reservations owned by Facilities, so that physical resources are not Academic models.
57. As an Academic scheduler, I want to request Space Availability through a Facilities contract, so that Delivery owns timetable intent while Facilities owns physical conflicts.
58. As Facilities staff, I want Facilities to remain independent from Academic scheduling services, so that room rules can change without importing Academic implementation.
59. As a domain owner, I want to publish business facts through a shared Domain Event publisher, so that I do not import Notification's concrete Actions or models.
60. As a Notification operator, I want Notification to own templates and delivery lifecycle but not source business decisions, so that retries cannot recreate or reinterpret business events.

**AI, reporting, release, and operations**

61. As an authorized staff user, I want AI and dashboards to read owner-provided data, so that answers respect permissions, campus scope, and domain semantics.
62. As a domain owner, I want Cross-context Read Projections to be explicitly stale-tolerant, so that consumers know they cannot use them as hard gates.
63. As an AI maintainer, I want AI to remain a read-only consumer rather than a source of truth, so that model output cannot mutate authoritative business state.
64. As a reporting maintainer, I want multi-context reports composed outside command-side aggregates, so that analytical joins do not become hidden domain coupling.
65. As a release operator, I want initial boundary slices to preserve existing routes and API response shapes, so that deployments do not require simultaneous portal releases.
66. As a release operator, I want every student-facing contract change marked with student portal impact, so that backend and portal types remain synchronized.
67. As a release operator, I want every lecturer-facing contract change marked with lecturer portal impact, so that lecturer authentication and delivery views remain synchronized.
68. As a release operator, I want expand, backfill, cutover, and cleanup performed as separate reversible steps, so that production migration risk stays bounded.
69. As an auditor, I want no historical academic, financial, access, or guardian evidence deleted during decomposition, so that architecture cleanup preserves accountability.
70. As a product owner, I want Student Support & Engagement split only when independent rules justify it, so that the project does not create empty speculative modules.
71. As a product owner, I want each completed wave to deliver a measurable reduction in forbidden dependencies, so that architectural progress is visible before the full program finishes.
72. As a maintainer, I want legacy services and adapters removed only after their consumers have migrated and tests prove replacement behavior, so that cleanup does not precede cutover.

## Implementation Decisions

### Target ownership

- Identity & Access owns accounts, Account Status, Actors, roles, permissions, Guardian Access Grant, and Lecturer Access Grant.
- Institution & Organization owns Institution Campus, Department, and canonical organizational references.
- Student Registry owns Student Identity, Student Reference publication, contact and campus affiliation, and Student–Guardian Relationships.
- Admissions owns Applicant, Application, Applicant Guardian, admission documents, Approve, Reject, and Revoke.
- Faculty Workforce owns Faculty Member employment and contract state, rank, qualifications, expertise, preferences, pay terms, Teaching Eligibility, and Faculty Access Eligibility.
- Academic Catalog & Calendar owns Programs, Curriculum Versions, Units, syllabi, Academic Periods, and Campus Period Schedules.
- Course Delivery & Assessment owns Course Offerings, Course Registrations and rosters, sessions, attendance, assessments, exams, Instructor Assignments, and Course Results.
- Academic Progression & Lifecycle owns Program Enrollments, Program Enrollment Status, Study Stage, Transcript Entries, GPA, Academic Standing, EGC progression, lifecycle actions, Decisions, best attempt, and graduation.
- Finance retains all money ownership and additionally owns DNG Campus Mapping and other payment-provider identifiers.
- Facilities owns Buildings, Rooms, capacity, Space Availability, reservations, and physical conflicts.
- Notification owns domain-event intake, templates, messages, delivery attempts, and channels; it does not own the facts that trigger delivery.
- AI & Reporting consumes owner-provided readers or Cross-context Read Projections and owns no Academic, Registry, Workforce, Admissions, or Finance source data.

### Logical boundaries before physical extraction

- Swinx remains a Laravel modular monolith. This PRD does not authorize microservices.
- Academic Catalog & Calendar, Course Delivery & Assessment, and Academic Progression & Lifecycle first become internal ownership boundaries within the current Academic Module.
- A context receives explicit namespace ownership, contracts, and architecture tests before any top-level Module, route, model, or table relocation.
- Student, guardian/parent, lecturer, and portal surfaces do not become Modules. They are Actors or business profiles whose data belongs to the owning contexts.
- Transitional shared Eloquent models are compatibility mechanisms, not approved cross-context interfaces. New consumers must use contracts or neutral references.

### Student and academic lifecycle

- Student Identity carries stable identifiers, display/contact data, campus affiliation, and account linkage only.
- Program, Curriculum Version, intake, Program Enrollment Status, Study Stage, progression, and graduation are removed conceptually from Student Identity and owned by Program Enrollment or related Progression aggregates.
- Swinx initially allows at most one primary active Program Enrollment per Student while preserving historical enrollments.
- Account Status, Program Enrollment Status, and Study Stage remain separate state machines. No generic Student status is introduced as a replacement.
- Cross-context consumers receive a minimal Student Reference and narrow owner answers, not the Student aggregate.

### Guardian and faculty boundaries

- Approval preserves every Applicant Guardian as a Student–Guardian Relationship, including Guardians without email or account.
- Identity separately creates or changes Guardian Access Grant; revoking access never removes the Registry relationship.
- Faculty Workforce answers professional/contractual Teaching Eligibility. Course Delivery combines that answer with workload, assignments, and timetable state to create Instructor Assignment.
- Faculty Workforce supplies Faculty Access Eligibility. Identity owns the final Lecturer Access Grant and Account Status used by login and refresh.
- Termination, suspension, and contract expiry synchronously revoke lecturer access through an Identity-owned command, backed by durable event/reconciliation. Leave and sabbatical do not revoke access by default.

### Academic Period and Institution references

- The current Semester identity becomes the institution-wide Academic Period; separate per-campus Semester identities are prohibited.
- Genuine campus differences use Campus Period Schedule overlays without changing Academic Period identity.
- Selected Academic Period is application/session state and is never treated as Current Academic Period or academic lifecycle truth.
- Institution Campus stores canonical Swinx identity only. DNG and future provider codes move to Finance-owned mappings.
- Department identity belongs to Institution & Organization. Catalog and Workforce reference Departments without owning their hierarchy.
- Building and Room ownership moves to Facilities; Academic contexts retain only space references and availability/reservation contracts.

### Delivery, Course Result, and Transcript Entry

- Detailed assessment evidence and grading rules remain inside Course Delivery & Assessment.
- A finalized or recalculated Course Result is the only supported handoff into Academic Progression & Lifecycle.
- Progression creates Transcript Entry and derives GPA, Academic Standing, best attempt, and graduation without querying gradebook internals.
- Course Completion remains synchronous and atomic in the modular monolith: failure to commit Transcript Entries rolls back the Course Offering completion transition.
- Notification and integration events are emitted only after the Course Result and Transcript Entry transaction commits.
- Eventual consistency for Transcript Entry is not allowed unless a future architecture explicitly introduces a visible pending-transcript lifecycle.

### Admissions orchestration

- Admissions owns the Approve application use case and staff authorization.
- Approve synchronously commands Student Registry, Identity & Access, and Academic Progression & Lifecycle through owner contracts inside one database transaction.
- Admissions does not construct or persist another context's model directly.
- Revoke follows the same ownership boundaries and retains the existing rule that downstream activity blocks destructive rollback.

### Cross-context interaction policy

- A fresh question uses a narrow synchronous Cross-context Query Contract owned by the answering context.
- An atomic state change uses a narrow synchronous Cross-context Command Contract owned by the receiving context.
- A post-commit reaction uses a durable Cross-context Domain Event and outbox with idempotent consumers.
- Reporting, dashboards, and AI use owner readers or rebuildable Cross-context Read Projections; projections never decide irreversible or hard-gate behavior.
- Contexts do not import another context's Eloquent models, concrete Actions, internal services, or schema-specific enums.
- Notification publication is exposed through a shared publisher boundary; producers do not depend on Notification implementation types.
- Finance consumes academic lifecycle facts through source contracts/events and Finance-owned snapshots. It does not inspect Student, Program Enrollment, Course Registration, Transcript, or EGC persistence to infer obligations.
- Facilities answers Space Availability and owns reservations. It does not import Academic conflict-checking services.

### Migration sequence

1. Boundary safety net: add neutral references, Shared Contracts, adapters, dependency inventory, and architecture tests. Prioritize Student Reference, Academic Period reference, faculty access, Notification publishing, and removal of direct Academic/Finance calls.
2. Institution and Catalog foundation: establish Institution reference ownership; extract Academic Catalog & Calendar as the first complete Academic context; move DNG mapping toward Finance and Building/Room ownership toward Facilities.
3. Student Registry and Identity cleanup: move stable Student Identity and guardian relationships behind Registry contracts; make login/access decisions Identity-owned.
4. Admissions orchestration: refactor Approve and Revoke to use Registry, Identity, and Progression commands while preserving one transaction.
5. Faculty Workforce: separate employment, eligibility, and access signals while leaving assignments in Delivery.
6. Course Delivery & Assessment: establish ownership of offering, roster, attendance, assessment, exam, Course Result, and Facilities interaction.
7. Academic Progression & Lifecycle: establish Program Enrollment, Transcript Entry, GPA, EGC, Decisions, and lifecycle ownership after upstream identity and result seams are stable.
8. Supporting cleanup: add engagement or cross-context reporting projections only when justified, then retire legacy services, shared-model reads, and adapters slice by slice.

### Compatibility and delivery constraints

- Initial slices preserve public routes, response envelopes, table names, model identifiers exposed to clients, and portal behavior.
- A later slice may change an API contract only with explicit portal impact metadata, matching portal type/composable updates, and coordinated verification.
- Schema evolution follows expand/backfill/cutover/cleanup. Historical academic, finance, access, guardian, notification, and audit evidence is preserved.
- Transitional architecture-test allowlists must be explicit, measurable, and shrink with every wave. New violations are never added to an allowlist merely to unblock delivery.
- Existing business flows are migrated vertically one use case at a time; mass namespace moves without ownership cutover are rejected.

## Testing Decisions

A good test exercises observable behavior at the highest stable seam and remains valid when internal classes, namespaces, or persistence move. Tests should assert authorization, response/redirect behavior, durable records, state transitions, emitted outbox facts, and transaction rollback. They should not assert that a particular service method ran or that an implementation class lives in a particular folder, except for dedicated architecture invariants.

Two complementary primary seams are required:

1. **Behavior seam — public HTTP or application use case.** Existing web/API feature tests prove that Admissions approval, authentication, course completion, lifecycle, Finance, Facilities, Notification, and portal-facing responses behave identically before and after each slice. When no public endpoint exists, test the highest application command rather than an internal repository or model.
2. **Boundary seam — Pest architecture tests.** Static architecture rules prove that contexts import only allowed Shared Contracts, DTOs, neutral references, and events. These tests reject direct Eloquent, concrete Action, internal service, and schema-enum dependencies across context boundaries.

Contract-level integration tests are added only where the behavior seam cannot efficiently prove atomic rollback, idempotency, freshness, or adapter compatibility. Avoid duplicating every scenario at HTTP, contract, and model layers.

Required test coverage by area:

- Student Registry: stable Student Reference, Student Identity updates, guardian preservation without account, access revocation without relationship deletion, and historical Program Enrollment independence.
- Identity & Access: Account Status separation, Guardian Access Grant, Lecturer Access Grant, login and refresh reading only Identity state, synchronous revoke on ineligible workforce transitions, and reconciliation after delivery failure.
- Admissions: Approve succeeds atomically across Registry/Identity/Progression; any failed command rolls back all writes; Revoke respects downstream-activity guards and owner contracts.
- Institution and Catalog: one Academic Period identity across campuses, Campus Period Schedule overlays, Selected Academic Period isolation, and neutral Campus/Department references.
- Faculty Workforce and Delivery: Teaching Eligibility does not imply assignment; assignment includes delivery workload/timetable checks; leave/sabbatical and termination/suspension access policies remain distinct.
- Course Delivery & Assessment: roster, attendance, assessment, Course Result finalization, Facilities availability, and no Progression access to component-score persistence.
- Academic Progression & Lifecycle: Course Result creates Transcript Entry; GPA, standing, best attempt, EGC, and graduation operate from Progression-owned evidence.
- Course Completion: transcript failure rolls back offering completion; Recalculate uses the same boundary; post-commit notifications are absent on rollback and present once on success.
- Finance: lifecycle facts enter through approved contracts/events; hard gates query fresh Finance state; no Finance query derives eligibility from Student or Academic tables; DNG Campus Mapping resolves provider codes independently from Institution Campus.
- Facilities: Space Availability and booking conflicts remain correct without importing Academic scheduling implementation.
- Notification: producer publication uses the shared boundary, outbox consumption is idempotent, and retries never repeat source business transitions.
- AI & Reporting: readers enforce permission and campus scope, projections declare freshness, AI remains query-only, and no reporting projection is used by a hard gate.
- Migration/backfill: expand and backfill preserve counts, identifiers, relationships, audit facts, and financial totals; cutover can be verified before cleanup; retries are idempotent.
- Portal compatibility: run student portal verification for Student Registry, Program Enrollment, guardian, or student API changes; run lecturer portal verification for faculty access, assignment, gradebook, or lecturer API changes; run both when shared authentication/context contracts change.

Prior art to follow:

- Existing Academic/Finance architecture and red-proof tests for enforcing forbidden imports.
- Existing Finance materializer architecture tests for protecting a permanent ownership boundary.
- Existing Admissions ingestion and Student Application lifecycle feature tests for HTTP-level orchestration and atomic behavior.
- Existing Facilities room availability and booking-conflict feature tests for resource-boundary behavior.
- Existing Course Completion credit-point tests and Notification outbox tests for result handoff and post-commit effects.
- Existing actor login and token tests for Student, Guardian, and Lecturer authentication behavior.

Every wave is complete only when targeted behavior tests pass, the relevant architecture allowlist shrinks or stays empty, full boundary architecture tests pass, documentation matches the landed ownership, and any affected portal passes its required checks.

## Out of Scope

- Splitting Swinx into microservices or separate databases.
- A big-bang move of Student, Semester, Lecture, Course Offering, or AcademicRecord models.
- Creating empty top-level Modules solely to match the target context map.
- Immediate renaming of existing database tables, routes, URL paths, or public API fields.
- Redesigning the student or lecturer portal UI as part of boundary extraction.
- Changing Finance ledger, pricing, settlement, or DNG business behavior beyond removing cross-context coupling and relocating provider mapping ownership.
- Replacing Notification V2 or its outbox architecture.
- Introducing multiple simultaneous primary Program Enrollments.
- Creating separate Academic Period identities per campus.
- Making Course Result-to-Transcript handoff eventually consistent in the current modular monolith.
- Building Student Support & Engagement as a new context before independent rules and use cases justify it.
- Allowing AI to mutate authoritative business state.
- Deleting historical academic, financial, admissions, guardian, access, notification, or audit evidence.
- Delivering all eight migration waves in one pull request or deployment.

## Further Notes

- Current dependency pressure supports the sequence: Student is imported broadly by both Academic and Finance, Semester is shared across Academic and Finance, and Academic still calls Finance and Notification implementations directly while Finance and Facilities retain reverse dependencies into Academic.
- Academic Catalog & Calendar is the first complete extraction because it establishes stable Program, Unit, Curriculum Version, Academic Period, Campus, and Department references needed by downstream contexts without beginning with the highest-risk Student or Progression aggregates.
- Student Registry must be introduced through a facade/reference boundary before the current Student model is decomposed. Moving the model first would change placement without reducing dependency.
- Academic Progression & Lifecycle is intentionally late because it currently absorbs the largest state-machine, transcript, EGC, defer/resume, decision, and mega-service complexity. Its input contracts should be stable before internal extraction.
- Each implementation wave should be decomposed into independently reviewable issues with one primary ownership seam and explicit predecessor relationships.
- The final cleanup wave removes transitional adapters only after runtime consumers, scheduled jobs, reports, and both portals have cut over and architecture tests prove there is no remaining bypass.
