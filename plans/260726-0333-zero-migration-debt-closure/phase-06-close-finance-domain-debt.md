---
title: "Phase 6: Close Finance domain debt"
status: todo
priority: P0
effort: XL
dependencies: [3, 5]
---

# Phase 6: Close Finance domain debt

## Overview

Make Finance the sole owner of obligations, settlement, collection, DNG,
billing, scholarships, vouchers, wallets, and Finance administration. Remove
all 93 shared-model imports and dependent compatibility shells without changing money.

## Requirements

- [ ] Replace external Student/Academic model access with owner contracts/events.
- [ ] Prove canonical gross, discount, cash, credit, collectible, and allocation totals.
- [ ] Preserve DNG provider acknowledgment, webhook durability, reconciliation, and idempotency.
- [ ] Do not run pointer-retirement migrations until phases 10–11 pass.
- [ ] Portal impact: student; update Finance API contract and student portal in the same package.

## File Inventory

| Path | Action |
|---|---|
| `/Users/hunt2412/hieupvdev/project/swinx/app/Modules/Finance` | Consolidate owner actions, queries, models, HTTP, commands |
| `/Users/hunt2412/hieupvdev/project/swinx/app/Services` | Retire legacy billing/scholarship/voucher/wallet services |
| `/Users/hunt2412/hieupvdev/project/swinx/app/Http/Controllers` | Retire Finance admin/import shells |
| `/Users/hunt2412/hieupvdev/project/swinx/resources/js/pages/Finance` | Canonical filters, routes, and operations UI |
| `/Users/hunt2412/hieupvdev/project/swinx/FE/student-nuxt` | Update Finance composables/types/pages |
| `/Users/hunt2412/hieupvdev/project/swinx/docs/api` | Update student Finance contract |
| `/Users/hunt2412/hieupvdev/project/swinx/database/migrations` | Preserve guarded forward-only migrations for phase 11 |
| `/Users/hunt2412/hieupvdev/project/swinx/tests/Feature/Finance` | Extend invariant and provider coverage |

## Interface Checklist

- Academic/Registry supply stable identifiers and events, never Finance-owned decisions.
- Finance queries accept typed criteria/arrays rather than `Request`.
- All money writes are transactional, idempotent, auditable, and use canonical precision.
- Provider response is an explicit external contract, not `ApiResponse::compatible()` concealment.

## Dependency Map

`Registry/Progression facts → Finance obligations → settlement/DNG → reports/portal → reconciliation`

## Implementation Steps

1. Classify all Finance imports and legacy surfaces by obligation/settlement workflow.
2. Replace the Student observer with a post-commit Registry event or owner command.
3. Migrate tuition, scholarship, voucher, wallet, billing, EGC, and DNG vertical slices.
4. Move request validation into FormRequests and query criteria into typed DTOs/arrays.
5. Convert admin and API responses while preserving the DNG provider boundary.
6. Migrate Finance pages to canonical filters/routes and delete proven dead pages.
7. Retire shells and lower ratchets only after owner tests and total reconciliation pass.
8. Run student portal lint, typecheck, build, and contract tests for every API package.

## Test Scenario Matrix

| Scenario | Expected |
|---|---|
| Student lifecycle event replay | One canonical obligation effect |
| Discount/cash/credit allocation | Totals reconcile exactly |
| DNG duplicate/out-of-order webhook | Idempotent durable acknowledgment |
| Cancellation/refund/reversal | Ledger and provider state reconcile |
| Admin filter validation | FormRequest error envelope and safe query |
| Shared import scan | Finance count reaches zero |

## Success Criteria

- [ ] Finance has zero non-owner shared-model imports and zero frozen shell debt.
- [ ] Finance invariant suites and production-style reconciliation report zero violations.
- [ ] Forward-only cleanup remains unapplied and has explicit recovery evidence.

## Risks and Security

- Never recompute or discard verified money/provider evidence. Protect webhook signatures,
  campus scoping, replay defense, precision, audit trails, and transactional boundaries.
