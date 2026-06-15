# Validation

## Proof Strategy

Milestone 3 is read-only on the Cockpit aggregation path. Action Panel writes reuse
existing routes and must not introduce new money paths. Validation must prove:

- Cockpit page renders with KPI, queues, phase; denies without `view_finance_cockpit`.
- Semester-agnostic vs semester-bound queue flags match §3.1.
- Deferred `data_health` exposes 15 invariants + balance match + scope badge.
- Invariant drilldown resolver maps all 15 codes; Audit accepts new search params.
- Queue rows JSON returns top-N for panel-capable queues.
- `finance:audit-invariants` counts match cockpit data-health tiles for same scope.
- Sidebar "Hôm nay" lands on cockpit for permitted users.

## Test Plan

| Layer | Cases |
| --- | --- |
| Unit | `FinanceInvariantSampleResolver` code→target_type/list URL mapping |
| Integration | `CockpitOverviewTest` (render, authz, semester flags); `CockpitDataHealthTest` (deferred shape, scope badge); `CockpitQueueRowsTest`; `InvariantDrilldownTest`; `FinanceAuditWorkspacePageTest` regression |
| E2E | Browser smoke: permission matrix, poll + manual refresh, semester change behavior, data-health drilldown, CRITICAL banner, Action Panel webhook retry, phase shortcuts |
| Platform | Targeted eslint on `resources/js/components/finance/cockpit/*`, `Finance/Cockpit/Index.vue`, `menu-sidebar.ts` |
| Logs/Audit | `finance:audit-invariants` cross-check vs cockpit tiles; harness trace + perf backlog |

## Commands

```text
./scripts/dev.sh test tests/Feature/Finance/Cockpit
./scripts/dev.sh test tests/Feature/Finance/Audit/InvariantDrilldownTest.php
./scripts/dev.sh test tests/Feature/Finance/Audit/FinanceAuditWorkspacePageTest.php
./scripts/dev.sh artisan finance:audit-invariants
./scripts/dev.sh npm run build
./scripts/dev.sh npm run lint -- resources/js/pages/Finance/Cockpit/Index.vue resources/js/components/finance/cockpit resources/js/constants/menu-sidebar.ts
```

## Acceptance Evidence

### Milestone 3 — Cockpit "Hôm nay"

Scope: triage landing screen with KPI ribbon, six queues, deferred data-health,
invariant drilldown, Action Panel, phase shortcuts, sidebar repoint. Read-only
aggregation; Action Panel reuses existing writes.

**Portal impact: none** (admin/staff Inertia UI only).

_Status: implemented — automated acceptance recorded 2026-06-16._

#### Automated acceptance

- [x] `./scripts/dev.sh test tests/Feature/Finance/Cockpit` — overview, data-health, queue-rows (6 passed)
- [x] `./scripts/dev.sh test tests/Feature/Finance/Audit/InvariantDrilldownTest.php` (2 passed)
- [x] `./scripts/dev.sh test tests/Feature/Finance/Audit/FinanceAuditWorkspacePageTest.php` — no regression (3 passed)
- [x] Targeted eslint on changed cockpit frontend files — 0 errors
- [ ] `./scripts/dev.sh artisan finance:audit-invariants` — counts match cockpit tiles for same scope (manual cross-check pending)

#### Browser smoke (manual — not run in CI)

- [ ] `view_finance_cockpit` only → page loads; queues without source permission hidden
- [ ] Queues poll ~90s without spamming; "Làm mới" reloads incl. `data_health`
- [ ] Semester change updates `dng_due`/`lifecycle`/`charge_errors` but not `webhook_errors`/`unallocated`
- [ ] `data_health` skeleton → tiles; invariant tile → Audit with resolved target
- [ ] CRITICAL banner when `critical_count > 0`
- [ ] Webhook Action Panel: retry removes row and refreshes counts
- [ ] Phase shortcuts reflect inferred phase; manual switch persists

#### Permission matrix (server-side contracts)

| Surface | Permission | Notes |
| --- | --- | --- |
| Cockpit page | `view_finance_cockpit` | Thin gate |
| Each queue card | queue `permission` field | Hide card if lacking source permission |
| Data-health panel | `view_finance_audit_workspace` | Required for invariant drilldown links |
| All-campus scope | `view_finance_all_campus` | `scope_badge: all_campus` |

#### Queue → permission → scope (fill during smoke)

| Queue key | Source permission | `obeys_semester` | Scope badge |
| --- | --- | --- | --- |
| `webhook_errors` | `view_finance_dng_webhook_events` | false | campus |
| `unallocated` | `allocate_finance_payment` | false | campus |
| `dng_due` | `view_finance_operations_due_calendar` | true | semester |
| `lifecycle` | `view_finance_operations_due_calendar` | true | semester |
| `charge_errors` | `view_finance_operations_exceptions` | true | semester |
| `installment_failures` | `create_finance_payments` | false | campus |

#### Invariant drilldown mapping (§4.4)

Record resolver `TARGET_TYPE` table and list-view fallbacks (INV-11) during Task 4 acceptance.