---
id: ADR-0050
title: "ApplicationDocument write path stays in Admissions; three CRM-ingest safety fixes"
status: accepted
owner: Admissions Team
last_verified: 2026-08-15
scope: architecture-decision
---

# ApplicationDocument write path stays in Admissions; three CRM-ingest safety fixes

**Context.** `ApplicationDocument` (owner: Upload) is the last `app/Models` shim blocking plan `260815-1320`'s shim sweep. Unlike the five shims already closed, it is pinned by a live cross-module **write**, not a read: `App\Modules\Admissions\Actions\UpsertCrmApplicationAction` writes it from two CRM ingest surfaces — the external push webhook (`IngestionController`, allowlist + ability + throttle guarded) and the NE nightly pull sync (`CrmApplicationSyncService`, which swallows `Throwable` per record and only logs — a refactor mistake here fails silently in a log nobody reads). The plan gated this phase on an explicit design pass rather than sweeping it alongside the read-only shims.

This ADR records the write-ownership decision plus three CRM-ingest safety fixes: two new findings the investigation surfaced (decisions 2 and 3 below), and one the plan's own validation log had already decided on before this ADR (the push-shape match-key fix, below), which turned out to need a real judgment call of its own once implementation started.

## Decision 1 — write ownership

Admissions keeps full orchestration of the CRM ingest write (`UpsertCrmApplicationAction` — shape detection, field mapping, guardian reconciliation, document reconciliation, academic score sync all stay where they are). Upload exposes a narrow `App\Shared\Contracts\Upload\ApplicationDocumentWriter` contract for the raw persistence operations only (upsert-by-key, delete-not-in-set), implemented against the real `App\Modules\Upload\Models\ApplicationDocument` model.

This matches the shape every other shim in this plan closed with: the owning module exposes a contract, the calling module keeps orchestration. CRM shape-detection and reconciliation rules are Admissions' own business logic — Upload's actual concern is document *storage*, not CRM ingest semantics — so moving the orchestration into Upload would maximize behavior drift for no boundary benefit. The already-decided push-shape match-key fix (below) is scoped inside this same contract.

## Decision 2 — NE-empty-documents is a no-op, not delete-all

**Finding.** `reconcileNeDocuments()` had no guard for an empty `documents` payload: an empty array deleted every existing `ne:`-scoped document for the application (`whereNotIn('crm_file_id', [])` matches everything). The push shape already guards this case (`syncDocuments()` returns early on `$documents === []`); the NE shape did not.

**Decision.** Treat an empty `documents` array as a no-op for NE too, matching the push shape. Nothing in the codebase or available CRM documentation confirms the NE feed ever intentionally signals "this application now has zero documents" via omission versus a partial/glitched response — and the current code could not tell those two cases apart. Deleting real, previously-synced documents on an ambiguous signal is the wrong default. If the NE CRM later needs to genuinely communicate "all documents removed," it should say so explicitly (e.g. a `documents_cleared` flag in the payload), not through an empty list that is indistinguishable from missing data.

**Known gap.** This decision has no remediation path: if an applicant's real, last remaining document is genuinely removed at the CRM, the stale local row now survives every subsequent sync indefinitely — there is no operator tool to delete it. Accepted as the safer failure mode (never lose data on an ambiguous signal) until the `documents_cleared` flag above exists or an operator deletion path is built.

## Decision 3 — restrict `documents.*.link` to http/https on the push path too

**Finding.** `documents.*.link` on the **push** ingest surface (`IngestApplicationRequest`, the external webhook) validated as `required|string|max:2048` with no scheme restriction, and renders in staff-facing admin pages (`StudentApplications/Documents.vue`, `StudentApplications/Show.vue`) as a raw `<a :href="doc.link">`. A malicious or compromised push payload could set `link` to a `javascript:` or `data:` URI, executing against a staff session on click.

Investigating this surfaced that the **NE** path already had stricter protection: `App\Modules\Admissions\Support\Crm\CrmDocumentUrlValidator` (pre-existing — introduced in `50ad0e3ca`, before this plan started — unrelated to this plan) already restricts every `file_*` URL to `https` (not `http`) before it ever reaches `UpsertCrmApplicationAction`, silently dropping anything else. NE was never actually exposed — the gap was push-only.

**Decision.** Add the same `http`/`https` scheme requirement to `IngestApplicationRequest` (push), and additionally enforce it once more inside `EloquentApplicationDocumentWriter::upsert()` itself — the single persistence choke point both shapes now share. The writer-level check is deliberately redundant with `CrmDocumentUrlValidator` for the NE path today; it protects any future caller of the contract that doesn't pre-validate, matching this plan's established pattern of pushing safety invariants down to the owning contract rather than trusting every caller to remember them.

## Already-decided going into this ADR, with a real complication found

The push shape's `updateOrCreate` keyed `ApplicationDocument` on `crm_file_id` alone — globally unique in the schema, but matched without `student_application_id` — so a payload for application B carrying application A's `crm_file_id` could silently re-point A's document row to B. The plan's validation log had already decided to fix this by scoping the match to `(crm_file_id, student_application_id)`, matching how the NE shape already matches.

Implementing it surfaced a real conflict: an existing, currently-passing test (`tests/Feature/Admissions/IngestionApplicationsTest.php`, "re-points a document to the latest admission without colliding on its global crm_file_id") explicitly exercised and asserted the *old* re-pointing behavior as a feature, using two genuinely different applicants (distinct `student_code`/`email`/`national_id`).

**Stated assumption (unverifiable from the repo — recorded here so a future reader doesn't mistake the removed test for an unexplained regression).** Confirmed in chat with the user on 2026-08-15: the push document-array ingest shape is no longer sent by the real CRM at all, superseded by the flat NE format. On that basis the fix was applied and the conflicting test was rewritten to assert the new rejection behavior (`ApiResponse::businessLogicError`, 422, via a dedicated `App\Shared\Contracts\Upload\ApplicationDocumentConflictException`) instead of silent re-pointing. Because the whole write runs inside one `DB::transaction`, the rejection rolls back the newly-created application row too, not just the document — the test pins that as well.

Independent of that product claim, the "legitimate re-point" scenario the old test defended is nearly unreachable by construction: `student_code` is unique on `student_applications`, so the same person re-applying under a new `crm_admission_id` would fail on that constraint before the document write is ever reached. The removed test's use of deliberately distinct identity fields means it was only ever exercising the cross-applicant collision case this decision closes — the fix is defensible on that basis alone, even if the push-shape-retirement claim above turns out to be wrong later.

## Consequences

- `ApplicationDocumentWriter` is the fourth Upload-owned contract this plan introduces (alongside `ApplicationDocumentCatalogReader`, `UploadRecordReader`, and GoldService's phase-3 methods) — same shape, same rationale each time.
- `reconcileNeDocuments()` gains an empty-array guard identical in spirit to `syncDocuments()`'s existing one; a characterization test pins the new no-op behavior (was previously untested and delete-all). Known gap: no remediation path for a genuine all-documents-removed case — see Decision 2.
- `IngestApplicationRequest`'s `documents.*.link` rule gains an `http`/`https` scheme check, and `EloquentApplicationDocumentWriter` enforces the same invariant independently. Both the scheme rejection and the cross-application collision guard raise a dedicated `App\Shared\Contracts\Upload\ApplicationDocumentConflictException` (extends `InvalidArgumentException`, so `CrmApplicationSyncService`'s existing bare `catch (InvalidArgumentException)` per-record handling keeps working unchanged) rather than a bare `InvalidArgumentException` — a bare catch in `IngestionController` would otherwise risk swallowing an unrelated exception (framework or Carbon) that also extends it and returning its raw internal message to an external caller.
- Push-shape document matching becomes `(crm_file_id, student_application_id)`-scoped, with `EloquentApplicationDocumentWriter::upsert()` stripping both keys from the caller-supplied `$fields` before writing so a caller cannot pass either key through `$fields` and bypass the collision check. The existing test that asserted the old silent-re-point behavior was rewritten into the plan's mandated negative test.
- `ApplicationBackfillService.php` (the one-time CSV backfill command) keeps its original `crm_file_id`-alone match and is not routed through the new writer — deliberately, per the plan's explicit "modify in place only" scoping. It is reachable only via `php artisan applications:backfill` with operator-supplied CSV paths, dry-run by default; no route, job, or queue exposes it to untrusted input, so it does not carry the same fraud exposure as the two live ingest surfaces.
