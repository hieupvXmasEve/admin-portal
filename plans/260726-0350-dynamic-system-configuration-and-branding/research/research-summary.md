---
title: "Dynamic System Configuration Research"
status: complete
owner: Platform Team
created: 2026-07-26
scope: plan-research
---

# Dynamic System Configuration Research

## Summary

Current values are already runtime files, not committed Laravel config:
`SystemConfigurationStore` writes `system_config.json` to the default disk.
Production mounts `/app/storage`, so values normally survive same-host deploys.
The design still fails the requested operational goal across host/volume loss,
multi-node deployment, favicon changes, and consistent first-render branding.

Recommended target: DB-only MySQL key/value settings + Upload-owned immutable
branding objects + one cached Platform branding projection for Blade, Inertia,
and API. Migration creates explicit defaults, does not import/backfill the
current JSON or branding assets, and users upload replacements after cutover.

## Findings

| Finding | Evidence | Impact |
|---|---|---|
| Settings use whole-file JSON and forever cache | `app/Modules/Platform/Support/SystemConfigurationStore.php` | Lost-update and cache/cutover risk |
| Production persists `/app/storage` in named volumes | `docker/docker-compose.production.yml`, `docker/docker-compose.host.yml` | Survives normal deploy, not host/volume loss |
| Favicon/title remain source/deploy-time | `resources/views/app.blade.php` | Cannot change from admin page |
| Logo upload overwrites fixed public names | `SystemConfigurationStore::upload()` | Weak CDN/browser invalidation and rollback |
| Raw logo path remains client-editable | update request + store upload path logic | Manager can target other `/storage` files |
| Upload bypasses Upload module | Platform upload action/store | Misses central validation and storage contract |
| SVG is same-origin and insufficiently sanitized | upload request + `FileValidator` | Active-content/XSS and MIME mismatch risk |
| Client refresh is a no-op after first load | `resources/js/composables/useSystemConfig.ts` | Changes appear inconsistently |
| Campus-scoped permission currently mutates global state | Platform routes/request tests/audit scope | Cross-campus/global privilege mismatch |
| Generic Upload API accepts every configured context | Upload request/policy/list/delete paths | Internal branding context needs API isolation |
| Facilities/Engagement consume non-brand keys | `RoomBookingService`, `RoomBookingSlotValidator`, `ProvisionCourseSurveyAction` | Branding-only migration would break behavior |
| Portals use environment/source branding | both `FE/*-nuxt` repositories | Staff-only change has Portal impact none |

## Alternatives

| Option | Result |
|---|---|
| Keep JSON + persistent volume | Smallest change, but still single-volume authority and weak multi-node/backup behavior |
| One singleton JSON DB row | Simple, but retains whole-document lost updates and weak typed partial mutation |
| Key/value DB rows + Upload references | Recommended: typed partial writes, explicit exposure, scalable asset storage |
| Store image blobs in MySQL | Rejected: backup size, serving inefficiency, and duplicated Upload responsibility |

## Scope Decision

Keep staff app only. Keep existing Platform contracts. Do not add
campus-specific settings, arbitrary key creation, portal branding, or automatic
historical asset cleanup in this plan.

## Unresolved Questions

None blocking under the selected assumptions.
