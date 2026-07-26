---
title: System Configuration and Branding Operations
status: current
owner: Platform Team
last_verified: 2026-07-26
scope: global-system-configuration
portal_impact: none
---

# System Configuration and Branding Operations

## Authority and scope

Platform owns global runtime configuration in MySQL `system_settings`. The
table contains code-defined keys with JSON values; it is not an administrator
extensible store. Required rows are materialized by the migration, including
typed Facilities and Engagement defaults. A missing required row is a readiness
failure, never a fallback to source or file configuration.

The staff app is the only branding surface in scope. Student and lecturer
portals retain their deploy-time branding and require no portal change.

## Access and public data

Only users with the `super_admin` system role can mutate global settings or
branding. Campus-local `manage_system_config` permission alone is insufficient.

Public configuration is a code-owned projection containing app labels and
resolved branding URLs. It never exposes private setting keys, Upload record
IDs, storage paths, or Upload metadata. Missing or unavailable branding objects
resolve to an explicit empty asset state.

## Branding assets

Branding uses the Upload module's internal `branding` context. It accepts only
PNG, JPEG, and WebP, validates decoded image dimensions and pixel count, and
stores immutable generated names on the configured images disk. Generic Upload
routes, context discovery, configuration, listing, show, delete, and chunked
initialization must not create, reveal, or manage branding records.

The four slots are `logo_full`, `logo_text`, `favicon`, and
`apple_touch_icon`. Settings store their private Upload IDs; Platform resolves
URLs through `FileUploadGateway`. Replacements retain the prior object. If the
database reference swap fails, Platform compensates by deleting the newly
stored object.

## Deployment and recovery

Before cutover, back up MySQL and the configured branding storage as one
recovery set. Apply the migration, verify all required rows and four null
branding IDs,
then deploy the DB-capable application build. Do not import legacy JSON values
or static assets: operators update text and upload replacement branding after
the cutover.

Use a shared cache store for multi-instance deployments; file, array, null,
and process-local Octane caches are rejected. Verify read-after-write behavior
across instances and immutable asset URLs after a rebuild/redeploy.

Recovery restores the database and branding storage from the same recovery
point, then deploys a DB-capable last-known-good image. Do not roll back to
file-based configuration code or reverse the cutover migration in production.
