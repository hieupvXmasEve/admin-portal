---
title: "Dynamic System Configuration and Branding Cutover"
date: 2026-07-26
type: technical-journal
status: completed
authority: work-history-only
---

# Dynamic System Configuration and Branding Cutover

## Context

This implementation moved mutable staff-application configuration from the
legacy JSON file to database-owned `system_settings`. It intentionally did not
import legacy values or branding assets: required text and typed settings are
initialized to deterministic defaults, while the four branding references
start empty.

## What Happened

- Platform now owns typed setting definitions, persistence, cache invalidation,
  authorization, and a public configuration allowlist. Facilities and
  Engagement continue to receive explicit typed defaults through their existing
  reader contract.
- Branding assets are Upload-owned immutable objects referenced by upload ID,
  never raw paths or fixed filenames. Generic Upload endpoints reject and
  redact the internal branding context so they cannot create, list, delete, or
  disclose branding records.
- Compatibility fixes preserved existing configuration API envelopes while
  removing private upload IDs from public responses. Missing stored branding
  objects resolve to empty assets instead of broken URLs.
- Blade and Inertia now consume the same branding projection for titles,
  logos, favicons, login, sidebar, and related staff surfaces; the settings UI
  performs the new branding upload flow.
- The legacy fixed-path branding uploader and JSON runtime authority were
  removed, with architecture coverage guarding against their reintroduction.

## Verification

- Focused Platform, Upload, and architecture tests passed: 20 tests and 116
  assertions.
- File-scoped frontend linting, formatting, and type checking passed; Pint and
  `git diff --check` also passed.
- Broader repository-wide suites were intentionally not run because this
  change was verified through the scoped checks defined by the plan.

## Reflection and Decisions

| Decision | Rationale | Impact |
| --- | --- | --- |
| MySQL is the sole runtime settings authority | Avoid dual-write/fallback drift | Deployments require a DB-capable build and a shared cache in multi-instance production |
| Upload owns branding storage | Prevent path overwrite and maintain immutable URLs | Platform stores only managed upload references |
| Preserve public envelope, redact internal identity | Avoid a breaking client contract or asset disclosure | Public consumers receive resolved branding data only |
| Treat absent stored objects as empty assets | Storage recovery may temporarily lag the DB | First render remains safe and does not publish invalid URLs |

## Next

- Perform the still-pending production/staging cutover: back up MySQL and
  object storage together, apply the migration, and verify the exact default
  rows and four empty branding slots.
- Rebuild and redeploy a DB-capable image; smoke-test anonymous login,
  authenticated configuration, API redaction, upload persistence, and
  read-after-write behavior across the production cache topology.
- Have an authorized super administrator set text values and upload replacement
  branding, then rehearse paired DB/object-storage recovery. Do not roll back
  to file-based code after cutover.

AgentWiki publishing was skipped because it is unavailable in this session.
