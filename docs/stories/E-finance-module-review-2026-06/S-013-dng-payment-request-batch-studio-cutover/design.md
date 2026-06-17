# Design

## Domain Model

Use clear names for three different concepts:

- **DNG payment-request candidate**: a student plus eligible charge obligations
  that can become a DNG payment request for a selected DNG fee type.
- **DNG payment request**: the created provider/audit record in
  `dng_payment_requests`, with linked charges through
  `dng_payment_request_charges` where applicable.
- **DNG webhook event**: provider callback evidence in `dng_webhook_events`.

Batch Studio owns the command surface for creating DNG payment requests in bulk.
DNG payment request and webhook pages remain read/audit surfaces.

The current `ListDngWorklistQuery` behavior can remain as the candidate-source
query for the first implementation slice, but any touched user-facing label must
say `Lập yêu cầu thanh toán DNG` or `Yêu cầu thanh toán DNG`. If implementation
renames PHP/TS identifiers, prefer names such as
`ListDngPaymentRequestCandidatesQuery`; do that only with targeted tests because
the query is already reused by Batch Studio preview.

## Application Flow

1. Operator enters Finance Office from sidebar, Cockpit phase shortcuts, Lookup
   bulk action bar, or an old bookmarked DNG worklist URL.
2. All creation paths land in Batch Studio DNG:
   `finance.batch-studio.dng`.
3. Batch Studio step 1 captures fee type, due date, description, estimate time,
   semester context, and optional prefilled student ids.
4. Step 2 calls the JSON preview endpoint and receives canonical lines plus a
   preview token.
5. Step 3 confirms selected lines, warning banners, rerun-cancels-old-DNG, and
   override danger.
6. Step 4 commits through the existing Batch Studio DNG commit path, which
   re-resolves selected lines and consumes the preview token before calling the
   existing write Action.
7. Result and retry affordances stay in Batch Studio.

## Interface Contract

### Web routes

| Route | Target contract |
| --- | --- |
| `GET finance.batch-studio.dng` | Primary DNG payment-request creation wizard. |
| `POST finance.batch-studio.dng.commit` | Only DNG bulk creation write path; requires Batch Studio preview token and selected keys. |
| `GET finance.operations.dng-worklist` | Compatibility redirect/forward to `finance.batch-studio.dng`; preserve safe filters where supported. |
| `POST finance.operations.dng-worklist.store` | Retired/blocked compatibility path; must not create DNG requests without Batch Studio preview-token verification. |
| `GET finance.dng.payment-requests.*` | DNG request audit/list/detail, not bulk creation. |
| `GET finance.dng.webhook-events.*` | Provider callback audit/list/detail. |

### Query/prefill contract

Supported prefill keys should be explicit and ignored safely when unsupported:

- `dng_fee_type`
- `semester_id`
- `campus_id` only for users with all-campus permission
- `student_ids[]` or a compact equivalent from lookup/cockpit handoff

The selected semester must use the existing Finance semester context where
possible. DNG/payment lifecycle audit pages must not become hidden by the
current semester switcher.

### Authorization

No permission may be widened by the cutover.

- Opening Batch Studio DNG uses the existing Batch Studio view gate.
- Previewing DNG candidates requires `create_finance_payments`.
- Committing DNG requests requires `create_finance_payments` and
  `void_finance_charges`, because rerun can cancel old DNG requests with linked
  charges.
- Menu visibility and shortcut visibility must match the route gates.

## Data Model

No migrations or data repair are planned for this story.

The implementation must preserve:

- `dng_payment_requests` audit records.
- `dng_payment_request_charges` charge linkage.
- `dng_webhook_events` callback audit records.
- Existing replacement behavior for one active unpaid request per
  `student + fee_type`.

## UI / Platform Impact

Likely touched surfaces:

- `resources/js/pages/Finance/BatchStudio/DngPush.vue`
- `resources/js/pages/Finance/BatchStudio/Hub.vue`
- `resources/js/pages/Finance/Operations/DngWorklist.vue` if removed or changed
  into a redirect-only compatibility surface.
- `resources/js/constants/menu-sidebar.ts`
- `resources/js/constants/finance-routes.ts`
- `resources/js/utils/routes.ts`
- `resources/js/components/finance/cockpit/PhaseShortcuts.vue`
- `resources/js/components/finance/lookup/SendToBatchBar.vue`
- `app/Modules/Finance/routes/web.php`
- `app/Modules/Finance/Http/Web/Admin/DngWorklistController.php`
- Existing DNG worklist and Batch Studio tests.

User-facing copy should follow the naming contract:

- Avoid `DNG Worklist` in menus, page titles, and headings.
- Avoid `Push DNG` as a page-level label.
- Keep `DNG` because it is the provider/business channel name used by staff.

## Observability

The cutover must prove that DNG creation still produces the same audit trail and
charge linkage as before, through the Batch Studio path only. If tests exercise
real write paths, run the Finance invariant audit and record known pre-existing
findings separately from new regressions.

## Alternatives Considered

1. Keep both pages and only rename the sidebar. Rejected because two write paths
   would keep operator behavior split and can bypass the Batch Studio safety
   contract.
2. Delete the legacy route immediately. Rejected because old links, tests, and
   operator bookmarks may still point to the old URL.
3. Rename the job to `Đẩy DNG`. Rejected as the primary business label because
   the staff outcome is creating payment requests; provider push is the final
   technical action.
