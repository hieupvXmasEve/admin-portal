---
title: DNG Payment Operations
status: current
type: runbook
scope: DNG payment collection and reconciliation operations
last_verified: "2026-07-25"
owner: Finance Module
audience:
  - finance operators
  - platform operators
  - developers
---

# DNG Payment Operations

## Scope

This runbook covers the active DNG collection, callback, review, and
reconciliation paths. Finance owns the money state; student and academic
records are references or sources of obligations, not alternate settlement
ledgers.

## Configuration

Provider credentials are read from the `dng` section of
`config/services.php`:

```env
DNG_BASE_URL=<provider-base-url>
DNG_ACCESS_CODE=<access-code>
DNG_HASH_KEY=<hash-key>
DNG_API_CODE=<api-code>
DNG_CLIENT_CODE=<client-code>
DNG_LOGIN=<login>
DNG_API_TIMEOUT=30
```

Campus codes are not a single environment value. Configure the selected
campus's provider code in Finance → DNG Campus Mapping. The authority is
`finance_dng_campus_mappings`, resolved by
`app/Modules/Finance/Dng/Services/DngCampusCodeResolver.php`. Request creation
and reconciliation must fail when the required campus mapping is missing.

## Collection workflow

New staff collection work uses Finance Batch Studio:

1. open the DNG job;
2. select the eligible Finance obligations;
3. preview the exact rows;
4. confirm the preview token and selected row keys;
5. review the per-row result.

The page, preview, and commit routes are owned by:

- `app/Modules/Finance/routes/web.php`
- `app/Modules/Finance/routes/api.php`
- `app/Modules/Finance/Queries/Batch/AssembleBatchDngPreviewQuery.php`
- `app/Modules/Finance/Actions/CreateBatchDngFromChargesAction.php`

Do not restore the retired `/finance/payments/create` flow. Single-fee and
installment paths use the same reservation and DNG request state model.

Provider submission is an external side effect. An uncertain provider response
must move to `unknown_outcome` or review; it must not be treated as a safe
failure and blindly submitted again.

## Request state

The executable state machine is
`app/Modules/Finance/Dng/Models/DngPaymentRequest.php`. Do not duplicate its
status list or transitions in another client or document.

Operationally:

- holding states reserve the collection slot;
- paid states must not be revived into an unpaid state;
- cancellation states are terminal;
- `unknown_outcome` and `needs_review` block a new collection until evidence is
  resolved;
- a verified late provider receipt may still advance a review state to paid.

Use the DNG request, lifecycle report, receipt-exception, and webhook-event
pages to inspect state. Permissions for viewing, resolving, mapping, and
creating payments are distinct in `config/permission.php`.

## Webhook contract

DNG posts to:

```text
POST /api/webhooks/dng/payment
```

The public controller persists the raw event and returns an accepted response.
Checksum and business-field validation happen asynchronously on the `webhooks`
queue. Keeping the inbox response separate from processing lets Swinx preserve
evidence and stop provider retry storms.

A successful provider payment can arrive in two callbacks:

1. payment received without invoice metadata;
2. invoice metadata attached later.

`ItemId`, student code, and campus code correlate the request. The first valid
paid transition creates/bridges the canonical Payment once. The later callback
attaches invoice evidence and must not settle again. Exact callback retries are
deduplicated by payload hash; the two different callbacks are not collapsed.

Incoming and outgoing checksum strings differ. Their only executable authority
is:

- `app/Modules/Finance/Dng/Services/DngChecksumService.php`
- `app/Modules/Finance/Dng/Services/DngClient.php`
- `app/Modules/Finance/Dng/Services/DngWebhookService.php`

Do not reconstruct a checksum from this runbook. Use the service and provider
contract fixtures.

## Reconciliation

`ReconcileDngPaymentsJob` runs every fifteen minutes from
`routes/console.php` on the `reconciliation` queue. It processes every
configured DNG campus code and reports backfilled, already-current, orphan, and
error outcomes. It also warns about stale pending requests and long-standing
paid-but-uninvoiced requests.

Production therefore needs:

- the Laravel scheduler;
- a worker consuming `webhooks`;
- a worker consuming `reconciliation`;
- monitoring for failed jobs and DNG receipt exceptions.

## Operator recovery

### Failed or mismatched webhook

1. Open the webhook event and inspect `processing_status`, `error_category`,
   checksum validity, and the linked request.
2. Correct local configuration or resolve the provider evidence.
3. Retry only through the authorized webhook retry action.
4. Confirm a Payment was bridged at most once.

Never edit the event to `processed` manually.

### Unknown provider outcome

Use the request outcome-resolution action with provider evidence. Do not create
a replacement DNG request while the active slot is held.

### Cancellation

Use the reviewed cancellation flow and read its impact preview. A request that
reached DNG may require a provider-side cancel. Preserve the cancellation
evidence and do not delete the request or linked charges.

### Paid but not invoiced

Allow the later invoice callback or reconciliation to attach evidence. Escalate
when the lifecycle report shows it beyond the operational threshold; do not
create a second Payment.

## Provider evidence gate

Fixtures under `tests/Fixtures/Finance/DngProviderContract/` capture the adapter
contract exercised by automated tests. Before changing collection semantics,
obtain provider confirmation for retry identity, cancellation finality,
pay-one/pay-all behavior, late receipts, and reconciliation correlation. Local
adapter behavior alone is not a provider SLA.

## Focused validation

```bash
./scripts/dev.sh artisan test --compact tests/Feature/Finance/Dng
./scripts/dev.sh artisan test --compact \
  tests/Feature/Finance/Batch/BatchDngCommitTest.php
./scripts/dev.sh artisan test --compact \
  tests/Feature/Finance/DngInstallmentContextResolverTest.php
```

Use `tests/Feature/Finance/Dng/DngProviderContractFixturesTest.php` whenever the
provider payload or checksum contract changes.
