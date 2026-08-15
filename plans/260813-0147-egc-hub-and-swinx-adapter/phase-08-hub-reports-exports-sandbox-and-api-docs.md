---
phase: 8
title: "Hub Reports, Exports, Sandbox, API Docs"
status: pending
priority: P2
effort: "4d"
dependencies: [7]
---

# Phase 8: Hub Reports, Exports, Sandbox, API Docs

## Overview
EGC-org analytics, Excel exports everywhere, third-party onboarding surface (sandbox env + published API docs).

## Requirements
- Functional:
  - Reports: pass rate + attendance rate by school/level/lecturer/block; drill-down lists
  - Excel export on every admin list (classes, rosters, attendance sheets, teaching hours, reports) — operators must never need to rebuild Excel by hand
  - API docs site published from `docs/openapi-v1.yaml` (redoc/scalar static build) + integration guide (auth, idempotency rules, webhook signature verification sample code, replay protocol)
  - Sandbox: separate hub deployment with seeded fake schools/students + test API keys, self-serve reset
- Non-functional: reports queries indexed; exports queued for large datasets

## Related Code Files (egc-hub/)
- Create: `app/Queries/{PassRateReportQuery,AttendanceReportQuery}.php`
- Create: `app/Exports/{ClassRosterExport,AttendanceSheetExport,PassRateReportExport}.php`
- Create: `app/Http/Controllers/Admin/ReportController.php` + `resources/js/pages/Admin/Reports/*.vue`
- Create: `docs/integration-guide.md`; docs build script `scripts/build-api-docs.sh`
- Create: `database/seeders/SandboxSeeder.php`; `app/Console/Commands/ResetSandbox.php`
- Modify: deploy config for sandbox instance

## Implementation Steps
1. Report queries + UI with filters (school/level/block/lecturer).
2. Export classes wired to every list page (reuse one export pattern).
3. Static API docs build from OpenAPI + integration guide with copy-paste verification snippets (PHP + Node examples for HMAC).
4. Sandbox deployment + seeder + reset command (scheduled weekly or on-demand).

## Success Criteria
- [ ] Third-party dev completes enrollment→schedule→attendance→webhook round-trip on sandbox using docs only (test with someone who didn't build it)
- [ ] Every admin list has working xlsx export
- [ ] Pass-rate report matches manually computed sample

## Risk Assessment
- Docs drift from code → OpenAPI contract tests (phase 3) run in CI; docs built from same file.
