# Exec Plan

## Goal

Add a read-only manual allocation preview endpoint for Student 360.

## Scope

In scope:

- `app/Modules/Finance/Queries/Student360/PreviewManualAllocationQuery.php`
- `app/Modules/Finance/Http/Web/Admin/FinanceStudentPaymentController.php`
  with `allocatePreview`
- `app/Modules/Finance/routes/web.php`
- `resources/js/constants/finance-routes.ts`
- `resources/js/utils/routes.ts`
- `tests/Feature/Finance/Student360/ManualAllocationPreviewTest.php`

Out of scope:

- Payment allocation writes.
- Record-payment adapter.
- Allocation drawer UI.
- Any new settlement math.

## Risk Classification

Risk flags:

- Authorization: `allocate_finance_payment`.
- Public contract: new authenticated JSON endpoint and route helper.
- Existing behavior: endpoint supports the existing allocation write path.
- Finance data exposure: payment and candidate line details.
- Weak proof: UI proof lands later.

Hard gates:

- Cross-campus payments must be hidden unless all-campus access is explicitly
  allowed by existing Finance rules.
- Response must use `ApiResponse::success()`.
- Preview must remain read-only.
- Actual apply must continue to use `finance.payments.allocate`.

## Work Phases

1. Write failing feature tests for success, forbidden, and cross-campus 404.
2. Add the query class using `SettlementService`.
3. Add the controller endpoint.
4. Register the route and helper constants.
5. Run the targeted Pest test and per-file frontend lint for changed route
   helper files.
6. Record validation evidence.

## Stop Conditions

Pause if:

- `SettlementService` cannot provide the required outstanding/unapplied data.
- The preview would need to mutate payment or invoice state.
- Route naming conflicts with existing Finance payment routes.
- Campus scope cannot be enforced consistently for the payment's owner student.
