# Design

## Domain Model

Milestone 5 is a **read-model presentation layer**. New backend objects extract
and formalize existing list queries; no write Actions or money calculations are
added.

New read models:

- **`ListFinanceChargesQuery`** — campus-scoped charge list with whitelisted
  server sort (extracted from inline `FinanceChargeController::index` logic).
- **`ListStudentInvoicesQuery`** — campus-scoped invoice list with whitelisted
  sort (wraps existing model scopes + appended amounts).
- **`FilterFinanceChargesRequest`**, **`FilterStudentInvoicesRequest`**,
  **`FilterPaymentsRequest`** — centralized, documented filter contracts.

`ListPaymentsQuery` is reused unchanged; M5 only adds request validation and UI
for `date_range` + existing sort whitelist.

Business rules (non-negotiable):

- **Preserve per-surface permissions exactly:** `view_finance_charges`,
  `view_finance_invoices`, `view_finance_payments`, `view_finance_audit_workspace`.
- **Campus scope unchanged:** global = current campus unless
  `view_finance_all_campus` (UI must indicate scope).
- **Row actions respect permissions:** Batch Studio hand-off buttons appear only
  when the operator has `create_finance_payments` (DNG) or the reminders ops
  permission.
- **All amounts from existing sources** — `SettlementService`, query accessors,
  audit graph builders; never recompute money in Vue.

## Application Flow

### Lookup surfaces (Charges, Invoices, Payments)

```
GET index → Filter*Request validates → List*Query::handle() → Inertia page
         → useDataTable (sort/filter/paginate) → Table + DataPagination
         → row click / LookupRowActions → Student 360 (focus=type:id)
         → multi-select → SendToBatchBar → Batch Studio (student_ids query)
```

Mirror `Finance/Operations/Settlement.vue` for `useDataTable` markup patterns.

### Audit Workspace (unchanged backend)

```
GET audit → existing FinanceAuditWorkspaceController
         → deferred graph / timeline / warnings
         → MoneyFlowGraph (nodes by type + edges by kind)
         → signed timeline +/− coloring
         → optional finding_code banner (M3 param, read-only)
```

## Interface Contract

### Web (Inertia) — modified index routes only

| Route name | Permission | Handler change |
| --- | --- | --- |
| `finance.charges.index` | `view_finance_charges` | Thin controller → `ListFinanceChargesQuery` |
| `finance.invoices.index` | `view_finance_invoices` | Thin controller → `ListStudentInvoicesQuery` |
| `finance.payments.index` | `view_finance_payments` | `FilterPaymentsRequest` + existing `ListPaymentsQuery` |
| `finance.audit.index` | `view_finance_audit_workspace` | Frontend only — `MoneyFlowGraph` + polish |

Detail/show routes are **not** modified.

### Filter contracts (representative)

**Charges:** `search`, `student_id`, `semester_id`, `charge_type`, `status`,
`sort`, `direction`, `per_page` — sort whitelist aligned with query.

**Invoices:** `search`, `semester_id`, `status`, `sort`, `direction`, `per_page`.

**Payments:** `search`, `source`, `status`, `date_range`, `sort`, `direction`,
`per_page` — reuses `ListPaymentsQuery` whitelist
(`amount`, `paid_at`, `status`, `created_at`, `student_id`, `student_name`,
`unapplied_amount`).

### Frontend contracts (Inertia v3)

- List pages: `useDataTable` from `@/composables/useDataTable` (not
  `useInertiaFilters` / `useTableFilters`).
- Routes: `financeRoutes.lookup.*`, `financeRoutes.collect.payments()`,
  `financeRoutes.students.overview(id, focus?)`, `financeRoutes.batchStudio.*`.
- Props: snake_case in TS interfaces.
- Shared selection: `useLookupSelection` → `sendToBatch('dng' | 'reminders')`.
- Focus format: `"<type>:<id>"` (e.g. `charge:42`, `invoice:7`, `payment:99`).

## Data Model

No new database tables or migrations. Queries use existing Eloquent models and
scopes.

## UI / Platform Impact

**New files:**

- `resources/js/composables/useLookupSelection.ts`
- `resources/js/components/finance/lookup/SendToBatchBar.vue`
- `resources/js/components/finance/lookup/LookupRowActions.vue`
- `resources/js/components/finance/audit/MoneyFlowGraph.vue`
- `app/Modules/Finance/Queries/Lookup/ListFinanceChargesQuery.php`
- `app/Modules/Finance/Queries/Lookup/ListStudentInvoicesQuery.php`
- `app/Modules/Finance/Http/Requests/Lookup/Filter*Request.php` (×3)

**Modified pages:**

- `resources/js/pages/Finance/Charges/Index.vue`
- `resources/js/pages/Finance/Invoices/Index.vue`
- `resources/js/pages/Finance/Payments/Index.vue`
- `resources/js/pages/Finance/Audit/Workspace.vue`

**Sidebar:** Task 10 verifies existing "Tra cứu & Audit" + "Thu & Đối soát"
group links — no nav change expected.

## Observability

- Pest feature tests under `tests/Feature/Finance/Lookup/` (filter, sort,
  campus scope, authz).
- Targeted eslint on changed Vue/TS files only (no whole-project `vue-tsc` —
  OOM in dev container).
- Browser smoke for four surfaces (charges, invoices, payments, audit).
- `finance:audit-invariants` **sanity check** (read-only milestone — counts must
  be unchanged vs pre-M5 run).

## Alternatives Considered

1. **Keep inline controller queries (rejected):** prevents whitelisted-sort tests
   and duplicates filter logic across three surfaces.
2. **Client-side sort/filter (rejected):** violates §7.3 dense-table standard and
   campus-scoped server pagination contract.
3. **Replace detail pages with 360-only navigation (rejected):** Audit
   `SOURCE_ROUTES` and repair workflows still need show pages.
4. **Graph visualization library (rejected):** columnar panel delivers "full
   graph" without new dependency; SVG/force-directed deferred to follow-up.