---
title: Backend Rules
status: active
owner: Platform Team
last_verified: 2026-07-25
scope: engineering-rules
applies_to:
  - "**/*.php"
---

# Backend Rules

## PHP baseline

- Use PHP 8.4 and Laravel 13 conventions.
- Every PHP file declares `strict_types=1`.
- Use braces for every control structure.
- Use explicit parameter and return types and constructor property promotion.
- Prefer descriptive names, PHPDoc for useful shapes, and no explanatory
  comments unless the behavior is genuinely non-obvious.
- Run Artisan, Composer, tests, and Pint through `./scripts/dev.sh`; do not use
  host `php artisan`.

## Controllers, Actions, and Queries

- Controllers orchestrate only: authorize, validate through a FormRequest, call
  one or more application use cases, and return the response.
- One Action represents one state-changing business use case. It contains no
  request validation or HTTP response construction and throws domain
  exceptions for domain failures.
- Queries are read-only and own complex retrieval or reporting behavior.
- Business behavior is organized by use case, not duplicated by actor. Web and
  API adapters may invoke the same Action.
- Use `DB::transaction()` for multi-write flows whose business outcome must be
  atomic.
- Keep technical integration support separate from business decisions.

New Actions expose a typed public static `run(...)` entrypoint. Queries expose a
typed public `handle(...)` entrypoint. When migrating a legacy use case with a
different signature, update its callers deliberately rather than introducing a
third convention.

## Validation and responses

- Use a specific FormRequest for accepted input. Keep rules out of controllers
  and Actions.
- Web controllers return Inertia props or redirects. Inertia pages should
  receive their initial business data through props.
- JSON endpoints return Eloquent API Resources where applicable and wrap
  responses with `ApiResponse::success()`, `ApiResponse::paginated()`, or
  `ApiResponse::error()`.
- Do not call `response()->json()` directly for application APIs.
- API controllers do not query another domain's tables directly; use the
  appropriate contract.
- Generate links through named routes and `route()`.

## Inertia v3 backend rules

Use only the Inertia v3 contract below; v2 examples and removed APIs are not
valid in this repository.

- Deferred props use `Inertia::defer()`.
- First-visit-only props use `Inertia::once()`.
- Optional partial-reload props use `Inertia::optional()`.
- Flash messages use `Inertia::flash()`, not redirect session `with(...)`.
- Do not use removed `Inertia::lazy()` behavior.
- Keep prop keys snake_case.

## Authorization and campus scope

- Keep authentication and authorization explicit in middleware and policies.
- Apply campus scope at the boundary and preserve it throughout the use case.
- Policies and gates decide access; Actions do not emit HTTP authorization
  responses. See [security.md](security.md).

## Project-specific write paths

### Finance

- Settlement uses `PaymentApplication` and `DiscountAllocation`; do not add new
  references to legacy `payment_allocations`.
- Charge voiding and allocation flow through `VoidFinanceChargeAction`,
  `AllocatePaymentAction`, and `AutoAllocatePaymentsAction`.
- Respect the `invoice_lines.status` lifecycle (`active` or `void`).

### DNG

- All provider calls go through `DngClient`; verify webhook checksums before
  processing.
- Use `DngPaymentService` entrypoints for create, push, and payment-link flows.
- The provider student identifier is `student_code` (the string MSSV), never
  the integer database key.
- Resolve DNG campus codes from Finance-owned
  `finance_dng_campus_mappings`, not Institution metadata or a global service
  config value.
- For the same student and fee type, cancel earlier unpaid requests only after
  the replacement push succeeds.
- Cancelled requests are terminal; late webhooks or reconciliation must not
  revive them.
- Queue webhook work through `ProcessDngWebhookJob`.
- Audit provider requests and responses in the DNG request and webhook event
  records.

### Notification V2

Use the outbox dispatch path described in
[architecture.md](architecture.md). Do not bypass it with direct delivery
writes.

## Code generation and formatting

- Use `./scripts/dev.sh artisan make:* --no-interaction` for Laravel-generated
  files and inspect existing sibling files before editing.
- Do not add or change dependencies without approval.
- After changing PHP, run scoped tests and format changed files with Pint as
  required by [testing.md](testing.md).
