# 01 - Admission, course, adjustment, and manual DNG audit

Status: done
Depends on: -
Program PRD: ../../finance-obligation-v2-migration/PRD.md
ADRs: docs/adr/0029-billing-account-is-payer-key-for-new-finance-aggregates.md
Portal impact: none

## What to build

Run the audit-first Wave 6 slice before any tail implementation. The audit must determine whether `admission_fee` and DNG `PRE` are used in production, whether `course_fee` has real data or generators, what existing `adjustment` rows mean, and whether manual-only DNG codes `THHB` and `F1` should become obligation types or stay manual DNG requests.

## Acceptance criteria

- [x] Production row counts and generator evidence are sampled for admission fee, course fee, adjustment, PRE, THHB, and F1.
- [x] Existing adjustment rows are classified into positive debit obligation, negative credit entitlement, or settlement correction.
- [x] Admission-fee collection timing is confirmed as post-Approve only, or a separate ADR need is explicitly raised for pre-Approve collection.
- [x] Course fee is classified as migrate, formally retire, or needs-info with evidence.
- [x] The issue records follow-up implementation tickets or a no-action decision for each audited tail item.

## Blocked by

- None - can start immediately

---

## Audit findings (2026-07-10)

### Sample surface

| Surface | Value |
|---|---|
| App env | `local` |
| DB connection | `mariadb` / database **`asia`** (same baseline as program PRD inventory 2026-07-09) |
| Live prod host | **not queried** this session (credentials exist only as commented `.env` lines; shared prod not opened from agent) |
| Note | PRD treats `asia` as the inventory sample. Counts below re-sample that baseline. True remote prod re-sample remains a human ops step if `asia` is known to diverge. |

### Row counts — `finance_charges`

| charge_type | active | void | sum amount | first/last created |
|---|---:|---:|---:|---|
| `admission_fee` | **0** | **0** | — | — |
| `course_fee` | **0** | **0** | — | — |
| `adjustment` | **0** | **0** | — | — |

Full `finance_charges` inventory on `asia` (for context): `tuition_term` 280a/5v, `egc_level_fee` 370a/58v, `retake_fee` 16a/5v, `exam_resit_fee` 20a/1v, `bhyt` 4a/3v, `scholarship_credit` 124v, `voucher_credit` 111v. **No** admission/course/adjustment rows.

Description search: 0 rows matching admission / xét tuyển / nhập học / course fee / phí môn.

`source_type` like `%DeferCase%`: **0** charges (defer forfeit adjustment generator has never produced rows on this sample).

### Row counts — `dng_payment_requests.fee_type`

| fee_type | count | notes |
|---|---:|---|
| HP | 200 | tuition/EGC path |
| PTL | 33 | exam resit |
| HL | 19 | retake |
| BHYT | 7 | wave 2 |
| **PRE** | **0** | admission exam fee code — unused |
| **THHB** | **0** | scholarship clawback — unused |
| **F1** | **0** | scholarship seat-hold fee — unused |
| GC | 0 | legacy frozen |
| KHAC | 0 | — |

Total DNG requests on sample: **259**. Manual-only codes PRE/THHB/F1/GC have **zero** history here.

### Generator / code evidence

#### `admission_fee`

| Path | Evidence |
|---|---|
| Automated generator | **None** — no Action/Job/batch path creates `TYPE_ADMISSION_FEE` |
| Manual create UI | Allowed on charge create form (`StoreFinanceChargeRequest` + `FinanceChargeController::MANUAL_CREATE_CHARGE_TYPES`) via direct `CreateFinanceChargeAction` (**not** intake; only `manual_fee` is intake-cutover) |
| Requires Student | `student_id` required + `exists:students,id` — cannot create for Applicant alone |
| Fee Monitor | `ListFeeMonitorQuery::rowsFromAdmissionExpectation` expects admission for intake-semester students in financial statuses |
| Registry | `ObligationTypeRegistry`: debit, sources `admission_enrollment`/`manual_fee`, **`dngCollectionCode: null`** → `fromChargeType` → **KHAC** |
| UI DNG label | `FinanceChargeController` display map says admission → **PRE** (label only; auto-push does **not** map PRE) |
| Applicant billing | ADR-0029: account for Applicant only when fee arises; **collection ledger student-keyed**; pre-Approve collect needs separate ADR if required |

#### `course_fee`

| Path | Evidence |
|---|---|
| Automated generator | **None** anywhere under `app/` |
| Manual create UI | **Not** in `MANUAL_CREATE_CHARGE_TYPES` — staff cannot pick it on create form |
| Registry | Debit, `allowedSourceKinds: ['legacy_course_fee']`, DNG **HP**, `CancellationPolicy::AuditPending`, `permission: null` |
| DNG HP gate | `CreateBatchDngFromChargesAction` still **excludes** `course_fee` from obligation-link guard (“stays free until wave 6”) — defensive only; zero rows |
| Tests only | Fixtures in DNG worklist / tuition DNG closure / enum parity tests |

#### `adjustment`

| Path | Evidence |
|---|---|
| Automated generator | **`ApplyDeferFinancePolicyAction::applyForfeit`** — creates **positive** `TYPE_ADJUSTMENT` with `source_type=DeferCase`, amount = consumed released paid cash, then allocates cash onto it |
| PRESERVE defer | **No** adjustment (void only) |
| Manual create UI | Allowed; amount **not** forced positive (unlike `manual_fee`) |
| Money-sign CHECK | `adjustment` is **unconstrained** sign (`2026_06_14_000003_add_finance_money_sign_checks`) — only type that may be + or − |
| Registry | Debit effect declared; sources `manual_adjustment` / `settlement_correction`; DNG **KHAC**; `AuditPending` cancel policy |
| Sample rows | **0** — classification is of **code shapes**, not legacy data |

#### Manual-only DNG codes `PRE`, `THHB`, `F1`

| Code | Label (dropdown) | Reverse charge map | Sample usage |
|---|---|---|---|
| PRE | Lệ phí xét tuyển | **none** (`chargeTypesForDngCollectionCode` empty → worklist throws) | 0 |
| THHB | Thu hồi học bổng | **none** | 0 |
| F1 | Phí giữ chỗ học bổng | **none** | 0 |

- Listed in `DngFeeTypeOptions::all()` for **manual** DNG create dropdown.
- `LinkExistingDngToChargesCommand` cannot link unmapped fee types to charges.
- No internal `charge_type` and no obligation registry entry for THHB/F1.

### Adjustment classification (existing rows)

**No legacy `adjustment` rows on `asia`.** Nothing to backfill-classify.

**Code-path classification (for future writes / generators):**

| Shape | Sign / source | PRD 3-way bucket | Decision |
|---|---|---|---|
| Defer FORFEIT auto charge | amount **> 0**, `source_type=DeferCase` | **Positive debit obligation** | Keep as debit obligation type; cut over via intake in follow-up (not a pure reallocation — it mints a charge then pays it with released cash) |
| Manual positive adjustment | amount **> 0**, staff form | **Positive debit obligation** | Same |
| Manual negative adjustment | amount **< 0**, staff form (CHECK allows) | **Negative credit entitlement** | Must not remain a signed charge on the new path — convert policy is FinanceCreditEntitlement (manual credit memo) |
| Settlement correction | reallocation without new economic debt | **Settlement correction** | Must be ledger reallocation op, **never** a charge row. No current generator creates this as `adjustment`; do not invent charge rows for pure reallocation |

### Admission-fee collection timing

**Confirmed: post-Approve / post-Student only. No pre-Approve collection ADR required.**

Evidence:

1. Manual create hard-requires `students.id`.
2. ADR-0029 already forbids pre-student materialize/invoice/DNG/payment without a new ADR.
3. Sample has **0** `admission_fee` charges and **0** PRE DNG requests — no live pre-Approve collect pattern to preserve.
4. Fee Monitor admission expectation is **student**-scoped (intake semester + financial statuses), i.e. after Student exists (Approve per ADR-0001).

**No separate pre-Approve collection ADR.**

### Course fee classification

**Decision: formally retire.**

Evidence: zero rows; zero generators; not on manual create form; only enum + registry + HP DNG free-pass + tests. Migrating would invent an unused intake path. Retire enum usage from active product surface; remove HP free-pass; keep historical enum value in DB CHECK/enum until wave-7 cleanup if desired.

### Per-item disposition (follow-ups)

| Item | Decision | Follow-up |
|---|---|---|
| `admission_fee` | **No-action for wave-6 implement** — type may stay in registry/manual form for rare staff use; no applicant provisioning; no PRE auto-map until business asks | Documented no-action below |
| DNG `PRE` | **Stay manual-only DNG code** | No-action |
| `course_fee` | **Formally retire** | [02-course-fee-formal-retire.md](./02-course-fee-formal-retire.md) |
| `adjustment` | **3-way split on new path**; zero legacy backfill | [03-adjustment-three-way-split.md](./03-adjustment-three-way-split.md) |
| DNG `THHB` | **Stay manual DNG**; no obligation type | No-action |
| DNG `F1` | **Stay manual DNG**; no obligation type | No-action |
| Pre-Approve admission collect ADR | **Not needed** | No-action |

#### No-action detail — admission / PRE / THHB / F1

- Do **not** build Applicant billing-account provisioning in wave 6 until product shows real PRE or admission volume.
- Do **not** invent obligation types for THHB/F1; staff keep using manual DNG payment requests if ever needed.
- Do **not** map `admission_fee` → PRE in `fromChargeType` unless/until a real admission intake path ships (today auto-map → KHAC; UI label PRE is cosmetic).
- Wave 7 materializer-only arch tests will eventually force any remaining direct `CreateFinanceChargeAction` uses (including admission/adjustment) through intake — covered by [wave-7 hardening](../../finance-obligation-v2-wave-7-hardening/issues/01-materializer-only-hardening.md) rather than inventing unused wave-6 generators.

### Residual risks / open human checks

1. **Remote prod re-sample:** if ops confirms `asia` ≠ live `portal-asia`, re-run the same SQL there before implementing retire/split. Zero-on-asia is strong but not a substitute for live prod if datasets diverge.
2. Fee Monitor will keep showing **missing admission** for intake students — intentional soft signal; not a generator.
3. Stale comment in `LinkExistingDngToChargesCommand` still lists BHYT as unmapped (BHYT now maps) — out of scope cleanup.

## Comments

- 2026-07-10: Wave 6 audit completed against `asia`. Follow-ups 02 (course_fee retire) and 03 (adjustment 3-way split) filed. Admission/PRE/THHB/F1 no-action.
