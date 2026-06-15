# Exec Plan

## Goal

Add the Milestone 2 read-model foundation for Student 360: four status cards,
grouped ledger lens data, and action permission flags.

## Scope

In scope:

- `app/Modules/Finance/Queries/Student360/GetStudent360StatusCardsQuery.php`
- `app/Modules/Finance/Queries/Student360/GetStudent360LedgerQuery.php`
- `app/Modules/Finance/Http/Web/Admin/FinanceStudentOverviewController.php`
- `tests/Feature/Finance/Student360/Student360OverviewTest.php`
- `status_cards.balance.unapplied_payment_id` for the later allocation drawer

Out of scope:

- Manual allocation preview route.
- Manual payment recording.
- DNG reviewed cancel.
- Frontend card/lens rendering.
- Any Finance money-write behavior.

## Risk Classification

Risk flags:

- Authorization: action flags and existing Student 360 permission boundary.
- Public contract: new Inertia props.
- Existing behavior: the M1 Student 360 page is augmented.
- PII/finance data: student finance details are exposed to staff.
- Weak proof: frontend has no JS unit runner; this story relies on backend
  feature tests and later browser smoke.

Hard gates:

- Campus scope and `view_finance_student_overview` must remain enforced.
- Money values must come from existing Finance read models/services.
- `ledger_groups` must use Inertia v3 deferred props.
- `unapplied_payment_id` must identify a real unapplied payment or be `null`;
  do not derive it from the DNG card.

## Work Phases

1. Write/extend the Student 360 feature test for `status_cards`, `actions`, and
   `unapplied_payment_id`.
2. Add the status-card query.
3. Add the grouped-ledger query.
4. Augment the controller with additive props only.
5. Run the targeted feature test.
6. Record validation evidence in this story.

## Stop Conditions

Pause if:

- A card requires new settlement math rather than existing service output.
- The current M1 shell files are missing or not merged.
- Campus visibility cannot be preserved with the current request/controller
  shape.
- The implementation needs schema changes or writes to support this read model.
