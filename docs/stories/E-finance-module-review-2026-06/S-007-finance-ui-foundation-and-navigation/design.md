# Design

## Domain Model

UI should reveal the existing finance lifecycle:

`charge -> invoice -> DNG request -> payment -> settlement -> exception`.

The frontend must not create alternate business logic for money or status.

## Application Flow

- Create or standardize finance UI helpers for currency, dates, statuses, and
  impact confirmations.
- Add backend props needed for lifecycle links instead of deriving by raw text.
- Refactor page groups to `useDataTable` only when touching them.
- Replace raw `fetch` and `window.confirm` with existing composables/stores.
- Translate Finance UI to the approved Vietnamese terminology from the review.

## Interface Contract

Inertia props must use snake_case and route helpers. Heavy chart/list props
should use Inertia v3 deferred props where appropriate.

## Data Model

No data model changes expected. Backend query props may include ids/route
context needed for links.

## UI / Platform Impact

Priority surfaces:

- Charges show/index.
- Invoices show/index.
- Payments show/index and manual allocation replacement.
- Settlement worklist.
- DNG request/webhook pages.
- Exceptions queue and Due Calendar action confirmations.

## Observability

Browser screenshots/smoke tests should verify no broken lifecycle links and no
runtime console errors on touched pages.

## Alternatives Considered

1. Full greenfield Finance UI.
   - Rejected because current IA mostly matches the domain and contains useful
     contracts.
2. Keep page-local formatters.
   - Rejected because money display drift is part of the reviewed risk.
