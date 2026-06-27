# CRM ingestion auth scaffolding (service token + /api/v1)

Status: done

## Parent

`.scratch/redesign-student-applications/PRD.md`

## What to build

A thin tracer bullet that proves the server-to-server auth path end-to-end before the heavy ingestion payload is built (slice 08). Reusable infrastructure per ADR-0004.

End-to-end behavior:

- A dedicated service-account User (`UserType::SERVICE`, new enum case) that cannot log into the UI.
- A way to mint a Sanctum personal access token for that account, carrying the ability `admissions:ingest`.
- A versioned `/api/v1` admissions route group guarded by `auth:sanctum` + the `admissions:ingest` ability, returning the standard `ApiResponse` envelope. A trivial ping/health endpoint verifies the path.
- Hardening wired here for the group: a dedicated rate-limit and a CRM IP allowlist, plus an audit-log record of each ingestion-group call.

## Acceptance criteria

- [x] `UserType::SERVICE` exists and a service-account User can be created that cannot authenticate to the web UI (staff-only web login gate rejects it).
- [x] A Sanctum token with ability `admissions:ingest` can be minted for the service account (`AdmissionsServiceAccountManager` + `admissions:mint-ingest-token` command).
- [x] The `/api/v1` admissions ping endpoint returns success (in the `ApiResponse` envelope) only with a valid token carrying the ability; missing/invalid token (401) or missing ability (403) is rejected.
- [x] Rate-limit (named `admissions-ingest` limiter, config-tunable) and IP allowlist apply to the group; each call is audit-logged via activity-log channel `admissions-ingestion`.
- [x] Feature tests cover authorized, missing-ability, and unauthenticated cases against the ping endpoint (plus minting, web-login lockout, IP allowlist, rate-limit, and audit).

## Blocked by

- `02-application-lifecycle-core`
