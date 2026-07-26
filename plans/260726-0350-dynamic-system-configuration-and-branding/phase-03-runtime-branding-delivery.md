---
phase: 3
title: "Runtime Branding Delivery"
status: in-progress
priority: P1
effort: "1.5-2 days"
dependencies: [1, 2]
---

# Phase 3: Runtime Branding Delivery

## Overview

Make the initial Blade head, shared Inertia props, admin page, and all staff
branding consumers use one resolved configuration projection.

## Requirements

- Functional: dynamic title, configured full/compact logos, favicon, Apple
  touch icon, copyright, and country on first response; safe empty asset state
  before upload; accessible update flow with clear validation/progress/errors.
- Non-functional: no duplicate async branding fetch, no raw `fetch`, no literal
  application URLs, and immutable asset caching.

## Architecture

Add `GetSystemBrandingQuery` over the single cached configuration snapshot and
convert DB values/upload IDs into a public DTO. A view composer injects it into
the Inertia root and standalone OAuth authorization views; Inertia shares
the same DTO as `system_config`. Vue reads that prop rather than maintaining a
separate forever-loaded singleton. Existing named GET/PUT/upload API routes
remain compatibility adapters to the same Actions and preserve their envelopes.

Use named web mutation routes and Inertia `useForm` on the full settings page.
Text and each branding upload may be separate forms, but each follows normal
Inertia redirects/flash and reloads only `config`/shared branding as needed.

## Related Code Files

- Create: `/Users/hunt2412/hieupvdev/project/swinx/app/Modules/Platform/Queries/GetSystemBrandingQuery.php`
- Modify: `/Users/hunt2412/hieupvdev/project/swinx/app/Modules/Platform/Providers/PlatformServiceProvider.php`
- Modify: `/Users/hunt2412/hieupvdev/project/swinx/app/Http/Middleware/HandleInertiaRequests.php`
- Modify: `/Users/hunt2412/hieupvdev/project/swinx/resources/views/app.blade.php`
- Modify: `/Users/hunt2412/hieupvdev/project/swinx/resources/views/mcp/authorize.blade.php`
- Modify: `/Users/hunt2412/hieupvdev/project/swinx/app/Modules/Platform/Http/Web/Admin/SystemConfigurationController.php`
- Modify: `/Users/hunt2412/hieupvdev/project/swinx/app/Modules/Platform/routes/web.php`
- Modify: `/Users/hunt2412/hieupvdev/project/swinx/app/Modules/Platform/Http/Api/SystemConfigurationController.php`
- Modify: `/Users/hunt2412/hieupvdev/project/swinx/resources/js/pages/SystemConfig/Index.vue`
- Modify: `/Users/hunt2412/hieupvdev/project/swinx/resources/js/composables/useSystemConfig.ts`
- Modify: `/Users/hunt2412/hieupvdev/project/swinx/resources/js/types/systemConfig.ts`
- Modify: `/Users/hunt2412/hieupvdev/project/swinx/resources/js/types/index.d.ts`
- Modify: `/Users/hunt2412/hieupvdev/project/swinx/resources/js/app.ts`
- Modify: `/Users/hunt2412/hieupvdev/project/swinx/resources/js/pages/auth/Login.vue`
- Modify: `/Users/hunt2412/hieupvdev/project/swinx/resources/js/components/AppLogo.vue`
- Modify: `/Users/hunt2412/hieupvdev/project/swinx/resources/js/components/AppSidebar.vue`
- Modify: `/Users/hunt2412/hieupvdev/project/swinx/resources/js/pages/SelectCampus.vue`
- Modify: `/Users/hunt2412/hieupvdev/project/swinx/tests/Feature/Platform/SystemConfigurationMigrationTest.php`
- Create: `/Users/hunt2412/hieupvdev/project/swinx/tests/Feature/Platform/SystemBrandingDeliveryTest.php`

## Implementation Steps

1. Define one snake_case public branding DTO with resolved URLs and
   `branding_version`/`updated_at`.
2. Inject the projection into root/OAuth Blade views. Replace app name, static
   favicon/touch icon, and hard-coded OAuth brand. Expose the escaped app name
   as root HTML data for the Inertia title callback.
3. Share the same DTO through Inertia; replace the current composable's
   hard-coded fallbacks, no-op reload, and stale cache-buster.
4. Update `SharedData` and make `app.ts` build navigation titles from runtime
   branding rather than `VITE_APP_NAME`.
5. Add named web update/upload routes and controller orchestration returning
   Inertia flash/redirect responses.
6. Rewrite the page with `useForm`, named `route(...)`, accessible file inputs,
   real upload progress, server validation, preview, reset, and failure states.
7. Update login/sidebar/logo/campus/OAuth consumers and remove institution-specific
   alt text/hard-coded branding fallbacks.
8. Keep existing API route names, Action adapters, envelopes, and redaction;
   add favicon fields without exposing upload IDs or internal settings.
9. Resolve null/missing upload records to an explicit empty asset state without
   broken URLs or first-render exceptions.

## Todo

- [x] Derive one public branding projection from the cached snapshot.
- [x] Make Blade head and Inertia props use it.
- [x] Update runtime titles, shared TS, and OAuth branding.
- [x] Preserve named GET/PUT/upload API compatibility adapters.
- [x] Replace stale client cache/reload behavior.
- [x] Align the settings page with frontend/Inertia rules.
- [ ] Cover initial render and public API behavior.

## Success Criteria

- [ ] First HTML response contains the current DB app name and configured head assets.
- [ ] Before upload, login/sidebar/campus pages render without broken image URLs.
- [ ] After upload, login, sidebar, compact logo, and campus selection show the same current brand.
- [ ] A successful update is visible after redirect without DOM mutation or full rebuild.
- [ ] Null/missing assets render an explicit empty state without leaking internal paths.
- [ ] Public API preserves `ApiResponse` shape and never exposes private keys/upload IDs.

## Risk Assessment

- Querying branding separately in Blade and Inertia can duplicate work or drift.
  Use one cached snapshot owner and one DTO; do not add a second forever cache.
- Favicon caches are aggressive. Immutable URLs/versioned references are the
  authority; query-string mutation is not the primary strategy.
- Anonymous login needs branding. The projection is public and contains no
  settings outside the explicit allowlist.

## Security Considerations

- Escape Blade values normally; never render admin-provided HTML.
- Keep public URLs and app labels separate from internal upload IDs/metadata.
- Preserve backend authorization on every mutation route.
