# 02 — Remove retired Finance and DNG surfaces

Status: ready-for-human

## Parent

[Finance Legacy Retirement](../PRD.md)

## What to build

Remove the retired standalone DNG worklist and deprecated bulk EGC generation surface end to end. Batch Studio DNG and the current EGC operations flow remain the only supported entry points.

Also remove runtime-unreachable requests, resources, actions, route constants, page components, exports, controller methods, and throw-only compatibility methods that exist solely for these retired paths. Hard removal is approved: old bookmarks and external callers are not entitled to a redirect or deprecation response.

## Acceptance criteria

- [x] The old DNG worklist GET and POST routes are not registered and return `404`.
- [x] The deprecated preview, export-preview, and run-generate APIs are not registered and return `404`.
- [x] The retired DNG page, compatibility controller, route helpers/constants, and deprecated generation-only code have no runtime references and are removed.
- [x] Proven-dead Finance request/resource/action classes and throw-only compatibility methods identified by the audit are removed when they have no remaining caller.
- [x] Batch Studio DNG and current EGC operations still render and complete their supported preview/submit flows.
- [x] Route, feature, type, and lint checks cover both removal and surviving canonical flows.

## Blocked by

- None — can start immediately.

## Verification

- `./scripts/dev.sh artisan route:list --path=finance/operations/dng-worklist --except-vendor` reported no matching routes; the Finance operations route list retains six supported APIs and omits all three retired bulk EGC APIs.
- `./scripts/dev.sh artisan test --compact tests/Feature/Finance/Dng/RetiredDngSurfacesTest.php tests/Feature/Finance/Batch/BatchDngCommitTest.php tests/Feature/Finance/Batch/BatchChargePreviewTest.php tests/Feature/Finance/Batch/BatchStudioAuthzTest.php` — 17 passed, 69 assertions.
- `./scripts/dev.sh composer exec pint -- --dirty --format agent` and scoped ESLint for `resources/js/constants/finance-routes.ts` and `resources/js/utils/routes.ts` passed. Ziggy was regenerated from the live route table.
- `./scripts/dev.sh npm run type-check` completed without diagnostics. Full `npm run lint` remains a repository baseline failure: it lints gitignored portal generated output and reports 4,783 unrelated errors. Broader legacy Finance query tests also fail on their existing settlement-position fixture/data assumptions; this slice does not modify the canonical DNG query or its logic.
