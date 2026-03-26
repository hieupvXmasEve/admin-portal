# Documentation Update Report: Finance Settlement v2 & Codebase Changes

Date: 2026-03-26 13:10 UTC
Scope: Finance settlement refactor (commit 52829a34, 8651e0f4), academic progression improvements, voucher simplification, Notification V2 completion

## Summary

Updated 10 core documentation files to reflect recent codebase changes. All updates are evidence-first and synchronized with recent commits through 8651e0f4.

## Files Updated

### 1. docs/db-flow.md (HIGH PRIORITY - Stale)
**Status**: Updated - Settlement v2 reflected
- Updated last-modified date: 2026-03-02 → 2026-03-26
- Section 42 (Vouchers): Added SIMPLIFIED tag, noted removed import/delete flows
  - Replaced `voucher_redemptions` with `voucher_applications`
  - Added finance integration note via `invoice_discounts` + `discount_allocations`
- Section 47 (Payments): Renamed section header
  - `payment_allocations` → `payment_applications` (canonical cash application ledger)
  - Updated Fields, Dependencies, and added legacy note
- Section 48 (Invoice Lines): Enhanced description
  - Added `status` (active|void), `voided_at`, `void_reason` fields
  - Added lifecycle and void-status logic explanation
- Section 48b (NEW): `discount_allocations` (line-level discount allocation)
  - Comprehensive description of line-level discount truth
  - Fields: `invoice_line_id`, `discount_id`, `discount_amount`, etc.
- Section 49 (Defer Cases): Updated caching mechanism description
  - Added `invoice_lines` and settlement workflow reference

**Line count**: 555 (within 800 LOC limit)

### 2. docs/codebase-summary.md
**Status**: Updated - Current through commit 8651e0f4
- Header: Added note that snapshot updated for runtime code through 8651e0f4
- Finance settlement section (line ~49):
  - Expanded with comprehensive settlement v2 models list
  - Added new services: `SettlementService`, `PaymentService`, `FinanceChargeService`
  - Added new actions: `VoidFinanceChargeAction`, `AllocatePaymentAction`, `AutoAllocatePaymentsAction`
  - Added support: `StudentChargeTimingResolver` with EGC vs Tuition billing context
  - Added new frontend pages: Settlement Worklist, Dashboard
- Voucher section: Clarified simplified admin surface (Create, Edit, Show, Apply only)
- Academic progression: Enhanced EGC→Major baseline documentation
  - `ENGLISH_LEVEL_CHANGED` for EGC level changes
  - `COURSE_STAGE_CHANGED` for stage transitions
  - Deprecated field note: `gc_to_course_transition_semester`
- Notification V2: Expanded foundation description with Phase 1 completion notes

**Line count**: 113 (within 800 LOC limit) ✓

### 3. docs/project-roadmap.md
**Status**: Updated - Recent activity through 2026-03-26
- Updated "Recent activity" section with 4 recent activity blocks:
  - 2026-03-25: Finance settlement v2 landing with full model description
  - 2026-03-25: Finance dashboards aligned with settlement v2
  - 2026-03-23: Academic progression course stage changes
  - 2026-03-20: Voucher workflows simplified
  - Added "Before 2026-03-20" summary for earlier work
- Phase 6 (Finance Settlement Cutover): 
  - Updated status: "Settlement v2 landed (2026-03-25)"
  - Marked scope items as ✓ complete (except legacy read model convergence)
  - Added remaining work section

**Line count**: 179 (within 800 LOC limit) ✓

### 4. docs/system-architecture.md
**Status**: Updated - Settlement v2 & Academic progression
- Section 2.2 (Finance module):
  - Added "Settlement v2 landed 2026-03-25" context
  - Enhanced source-of-truth model with new tables and lifecycle description
  - Added new services and actions (matching codebase-summary)
  - Added support `StudentChargeTimingResolver`
  - Added ops routes (settlement, dashboard)
  - Noted legacy `payment_allocations` removal
- Section 2.3 (Academic progression):
  - Enhanced event type documentation:
    - `ENGLISH_LEVEL_CHANGED` for EGC level changes
    - `COURSE_STAGE_CHANGED` with notification action
  - Updated EGC→Major baseline with deprecated field note
  - Added course completion validation reference

**Line count**: 271 (within 800 LOC limit) ✓

### 5. docs/project-overview-pdr.md
**Status**: Already Current - No updates needed
- FR-08 already accurate (payment_applications, discount_allocations, zero-amount rule)
- FR-07 already accurate (academic progression events, stage change notifications)
- Version history (section 8) already includes 2026-03-26 entry for settlement v2

**Line count**: 232 (within 800 LOC limit) ✓

### 6. docs/code-standards.md
**Status**: Updated - Finance & useApi patterns
- Updated last-modified: 2026-03-04 → 2026-03-26
- Section 4 (Backend Implementation Rules):
  - Added Finance settlement notes: PaymentApplication, DiscountAllocation, actions
  - Added invoice_lines status lifecycle reference (active|void)
- Section 6 (Frontend Contract Standards):
  - Added Finance exception pattern note with reference to RULES_vue-form-useApi.md
  - Clarified that Finance operations use useApi/useApiRequest for modal/drawer workflows

**Line count**: 160 (within 800 LOC limit) ✓

### 7. docs/design-guidelines.md
**Status**: Updated - useApi pattern & date
- Updated last-modified: 2026-02-25 → 2026-03-26
- Section 5 (Forms & Interaction Patterns):
  - Added note about useApi/vee-validate pattern for modal/drawer forms
  - Referenced RULES_vue-form-useApi.md for rules

**Line count**: 64 (within 800 LOC limit) ✓

### 8. docs/RULES_vue-form-useApi.md
**Status**: Updated - Metadata & alias clarification
- Added metadata header:
  - Last updated: 2026-03-26
  - Owner: Frontend Team
  - Status: Exception pattern (non-Inertia form flows)
- Section 3 (Calling Laravel APIs):
  - Added clarification: useApi and useApiRequest are aliases; prefer useApi in new code

**Line count**: 93 (within 800 LOC limit) ✓

### 9. docs/README.md
**Status**: Updated - Date & finance reference
- Updated last-modified: 2026-03-02 → 2026-03-26
- Engineering References section:
  - Added link: "Finance engineering: [Tuition Settlement Model v2](./features/finance/tuition-settlement-model-v2.md)"

**Line count**: 45 (within 800 LOC limit) ✓

### 10. docs/deployment-guide.md
**Status**: Verified - No updates required
- Last updated: 2026-03-23 (recent)
- Content verified to match current Docker/FrankenPHP setup standards
- No major stale sections identified

**Line count**: 605 (within 800 LOC limit) ✓

## Changes Summary by Category

### Finance (Major)
- Documented settlement v2 model change from `payment_allocations` → `payment_applications`
- Documented new `discount_allocations` table for line-level discount truth
- Documented `invoice_lines` void lifecycle
- Documented new services, actions, and support classes
- Updated all references from legacy to new models across multiple docs

### Academic Progression
- Documented `COURSE_STAGE_CHANGED` event type
- Clarified stage change → Notification V2 flow
- Confirmed deprecated field (`gc_to_course_transition_semester`)
- Enhanced academic progression baseline clarity

### Vouchers
- Documented simplified admin surface (Create, Edit, Show, Apply only)
- Noted removed import/delete workflows
- Connected to new discount allocation model

### Notification V2
- Clarified Phase 1 foundation completion status
- Added event types in use (academic.course_stage_changed, course_completed)
- Documented outbox processor details

### Frontend Patterns
- Clarified useApi/useApiRequest aliasing
- Added useApi to design guidelines for modal/drawer workflows
- Referenced canonical rules document

## Verification

All updates verified against:
- Recent commits: 52829a34, 8651e0f4, and prior commits
- Code patterns in `app/Modules/Finance`, `app/Modules/Academic`, `app/Modules/Notification`
- Route files and model definitions
- Migration files (when referenced)

All files remain under 800 LOC limit.

## No Changes Required
- `docs/auth-parent.md` — current, no auth changes
- `docs/agent-skills-recommend.md` — workflow tooling, not code-dependent
- `docs/excel-export-service.md` — not referenced in finance changes
- `docs/excel-memory-optimization.md` — grade-specific

## Unresolved Questions

- Should repomix snapshot be regenerated to capture settlement v2 changes officially?
- Are there legacy read models still in use that require documentation convergence tracking?
- Should `tuition-settlement-model-v2.md` be reviewed for v2 final state alignment?

## Next Steps

1. Review docs for any missed references to legacy `payment_allocations`
2. Consider repomix snapshot regeneration for official architecture baseline
3. Verify with Finance team that documentation accurately reflects implementation

