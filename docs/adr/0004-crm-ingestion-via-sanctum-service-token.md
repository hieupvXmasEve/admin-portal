---
id: ADR-0004
title: "CRM ingestion authenticates via a Sanctum service token"
status: accepted
owner: Platform Team
last_verified: 2026-07-25
scope: architecture-decision
---

# CRM ingestion authenticates via a Sanctum service token

The admissions CRM (a server-to-server caller) authenticates to the ingestion API with a **Sanctum personal access token** issued to a dedicated service-account User (`UserType::SERVICE`, new enum case), scoped by the ability `admissions:ingest`. We chose this over a bespoke API-key table because the app already standardizes on Sanctum, which provides hashing, revocation, rotation, expiry, and ability-scoping for free.

The contract: ingestion is `/api/v1` + `ApiResponse` envelope; upsert is idempotent by `crm_admission_id` (Application) and `crm_file_id` (document); approve/reject/revoke are **not** exposed to the API (staff-only). Hardening: dedicated rate-limit, IP allowlist for the CRM, and an audit-logged record of every ingestion call.

Document types mirror the CRM-owned catalog (`application_document_types`, synced from CRM) so required/optional documents can be validated and "missing document" gaps surfaced; document files are stored as **external link references** (not managed uploads), 1-n per type.
