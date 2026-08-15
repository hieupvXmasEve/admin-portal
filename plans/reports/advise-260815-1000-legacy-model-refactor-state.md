# advise-state
phase: advise (COMPLETE — report written)
input: "Refactor code legacy sang kiến trúc domain hiện tại, gặp vấn đề khi trước đó có nhiều Model thiết kế gắn kết 2 chiều với nhau, kiến trúc cũ thiết kế kiểu Model first" (refactor legacy → module/domain architecture; blocked by bidirectionally-coupled Model-first legacy models)
flags: --agent (no output flags)

## input-analysis
- Stated: how to migrate legacy app/Models with 2-way Eloquent relations into app/Modules/<Owner>/ boundaries.
- Implied: decoupling order/strategy when a model pair spans two owner modules.
- Assumption to test: not every bidirectional pair must be broken — same-owner pairs move together untouched.

## scout-findings (VERIFIED)
- app/Models: 84 models remain; ~70 ALIVE-LEGACY awaiting module migration (Academic 28, StudentRegistry 16, Finance 8, Admissions 4, other ~17). Audit: plans/reports/audit-260810-0004-legacy-code-inventory.md (updated Aug 14).
- Established recipe already exists & proven on 30-model sweep (24 shims deleted, 6 remain: ApplicationDocument, FormResponse, GoldTransaction, QueryReply, QueryTicket, UploadRecord): move model to module → class_alias shim in app/Models → sweep callers → cross-module reads via contract (app/Shared/Contracts/<Consumer|Owner>/... interface + Eloquent impl in owner Support/ + ServiceProvider bind) → shrink DeprecatedModelShimArchTest allow-list → delete shim.
- Guard: tests/Feature/Architecture/DeprecatedModelShimArchTest.php caps shim count + importer baseline; empty list = sweep done.
- Worst bidirectional hubs: Student (hasOne BillingAccount→Finance, hasMany ClubMember→Engagement; Finance models import Student back); ClassSession/ExamRoomSlot ↔ Facilities\Room; DeferCase ↔ FinanceCharge (FinanceCharge imports Student, Semester, BillingCycle, User); CourseOffering ↔ Engagement\FormTarget.
- Danger: EgcRetakeDiscountLink split-brain — live class in BOTH app/Models and Modules/Finance, no alias; Finance authoritative per audit.
- ADRs: 0026 (Academic/Finance boundary, source-triple decoupling), 0031 (actor roles ≠ bounded contexts), 0033 (program enrollment separate from student identity).
- ApplicationDocument shim deferred: fraud-sensitive CRM write path pending design review.

## qa-log
- Q1: driver/outcome? -> A1: "tất cả" — wants (a) strategy for remaining ~70 legacy models, (b) unblocking bidirectional clusters, (c) general patterns for breaking 2-way coupling across modules.
- Q2: boundary strictness? -> A2: no pick; user asked to explain concretely what "break relation → contract/event" means (mechanics, code shape, cost) before choosing.
- Q2b: strictness w/ mechanics sketch -> A2b: still no pick; asked "nếu tách hoàn toàn thì đắt như thế nào — chỉ nói kiến trúc, bỏ qua thời gian code": is full contract+event separation architecturally appropriate for this single-DB monolith?
- Q2c: strictness w/ architecture-fit verdict -> A2c: user inferred "hướng refactor hiện tại đang bị thừa / over" — misread: my critique targeted hypothetical FULL purity (Mức 2), NOT the repo's existing shim-sweep + ADR-0026 work. Repo's current practice already ≈ Mức 3 (import-level + targeted events at hot boundary). Still no level picked.
- Q3: confirm direction -> A3: picked "Tiếp tục Mức 3" — continue proven recipe (model→owner module, contract-for-read, events only Academic↔Finance) for remaining ~70 models per domain.
- Q4 (pending): CONFIRM REFRAMING (includes proposed sequencing: fix split-brain first → finish 6 shims → then domain-by-domain smallest-first, Student hub LAST).

## reframing-draft (presented for confirmation as Q4)
problem: ~70 ALIVE-LEGACY models in app/Models (Model-first, bidirectional Eloquent relations) must move into owner modules without breaking the monolith's transactional/join strengths.
requirements:
 1. Continue proven Mức-3 recipe: model→owner module + class_alias shim → sweep callers → delete shim; DeprecatedModelShimArchTest shrinks each phase.
 2. Cross-module READ = contract in app/Shared/Contracts + Eloquent impl in owner module; keep student_id-style FK columns + intra-module joins.
 3. Cross-module WRITE via events ONLY at Academic↔Finance (ADR-0026); elsewhere direct call to owner module's public Action/Service.
 4. Fix EgcRetakeDiscountLink split-brain FIRST (Finance authoritative).
 5. Finish 6 remaining shims before starting new domains (ApplicationDocument gated on fraud-design review).
 6. Sequencing: per-domain batches, smallest/least-coupled first; Student hub LAST (most imported model — break inbound edges from other domains first so Student's own move is cheap).
goals: app/Models empty (or only true-core), DeprecatedModelShimArchTest allow-list + importer baseline empty, all module placement arch tests green, no new global files.
non-goals: full DDD purity (no-cross-relations-anywhere), microservice/deploy split prep, DB schema changes/FK removal, rewriting working intra-module Eloquent relations.
constraints: one owner module per feature; arch tests per boundary; docker via ./scripts/dev.sh; single MySQL; live production (pilot-scale); Vietnamese/English team of ~1.

## next
DONE. Q4 confirmed ("Đúng, viết advice"). Report written: plans/reports/advise-260815-1000-legacy-model-refactor.md
