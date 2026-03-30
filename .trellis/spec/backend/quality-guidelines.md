# Quality Guidelines

> Code quality standards for backend development.

---

## Overview

- PHP files should use strict typing.
- Controllers should stay thin and delegate business logic.
- Pest is the real backend test framework.
- Local quality gates matter even when repository CI enforcement is not the only reliable signal.

---

## Forbidden Patterns

- Heavy business logic inside controllers.
- Guessing schema fields, route names, or model relationships.
- New finance writes against deprecated `payment_allocations`.
- Silent catch blocks that neither log nor rethrow.
- New docs or code claiming patterns that are not verified in repo code.

---

## Required Patterns

- `declare(strict_types=1);` in PHP files.
- Explicit request validation, usually via FormRequest.
- `DB::transaction(...)` for multi-write mutations.
- Campus-aware filtering for campus-scoped data.
- Prefer module `Actions` / `Queries` for new domain work when the feature area already uses them.

Examples:

- Centralized bootstrapping and exception/middleware wiring: `bootstrap/app.php`
- Transactional mutation + domain logging: `app/Modules/Academic/Actions/RecordStudentActionAction.php`
- Query class shaping paginated read data: `app/Modules/Finance/Queries/ListPaymentsQuery.php`

---

## Testing Requirements

- Write or update a focused Pest test for every backend behavior change.
- Prefer `tests/Feature` for route/controller/query behavior and `tests/Unit` for isolated mapping/utility logic.
- Use factories, `RefreshDatabase`, and `assertInertia(...)` where relevant.
- Run the smallest meaningful test slice first.

Examples:

- Pest bootstrap: `tests/Pest.php`
- Feature test with auth/permission assertions: `tests/Feature/Notification/NotificationOpsControllerTest.php`
- Edge-case-heavy finance testing: `tests/Feature/Finance/Dng/ProcessDngWebhookJobTest.php`

---

## Code Review Checklist

- Does the change follow the existing module vs legacy placement in that area?
- Are field names, relationships, and route names verified against code?
- Are auth, validation, and campus scope handled explicitly?
- Does the change preserve existing API/JSON envelopes where applicable?
- Was a focused Pest test added or updated, and was it run locally?
- If checks could not run, was that gap stated clearly?
