# Red Team — sidebar menu IA restructure

Date: 2026-08-16
Plan: `plans/260816-2125-sidebar-menu-ia-restructure/`
Reviewers: 3 (Security Adversary, Assumption Destroyer, Failure Mode Analyst), Standard verification tier
Result: **15 findings, 6 Critical. All accepted. Plan rewritten, not patched.**

## Why the plan failed review

Two independent causes, either sufficient on its own:

1. **Wrong evidence granularity.** The original IA was derived from a domain-prefix rollup of `roles x role_permissions x permissions` (e.g. bucket everything matching `scholarship`). Real ownership is per-permission and does not follow name prefixes. 3 of 7 proposed moves were wrong once holder sets were queried properly.
2. **Undiscovered surface.** `docs-site/` mirrors the sidebar group labels in `astro.config.mjs` with ko/zh translations and directory slugs, 64 doc pages declare `menu-sidebar.ts` as their `source:`, and `scripts/check-docs-freshness.sh` fails CI when a source changes without its pages. This surface is larger than the target file and was absent from the plan.

## Findings

| # | Sev | Finding | Evidence |
|---|-----|---------|----------|
| 1 | Critical | Plan claimed "no test asserts on this file". Three Pest tests read it with `file_get_contents` and assert literal labels. Phase 1 turned them red. | `tests/Feature/Finance/Cutover/FinanceOfficeCutoverTest.php:98,110,118,123-124`; `tests/Feature/Finance/Reporting/FinanceReportingShellTest.php:82,90`; `tests/Feature/Finance/DetailRouteContractTest.php:111` |
| 2 | Critical | `docs-site` coupling omitted entirely: 64 pages + hard-coded labels/slugs/translations + live CI gate. | `docs-site/astro.config.mjs:57-89`; `scripts/check-docs-freshness.sh:15,29-33`; `.github/workflows/admin-portal-user-guide.yml:26` |
| 3 | Critical | Plan's safety premise ("menu filtering makes restructuring safe") is not an access control. Filtering is client-side; several routes it hides have no server-side gate. | `resources/js/composables/usePermissions.ts:34-61`; `app/Modules/Engagement/routes/web.php:84-97`; `app/Modules/Academic/routes/web.php:787-799` |
| 4 | Critical | Persona table unreproducible. Seeder creates 5 roles; dev DB has 9. `HQ`, `Cán Bộ Đào tạo`, `Hành chính` exist in no seeder. Role IDs have also drifted. | `database/seeders/InitialSetup/RoleAndPermissionSeeder.php:71-75` vs live DB |
| 5 | Critical | "Discounts & Funding is HQ-owned" was backwards for its key item. `view_scholarship_adjustment` is held by `Cán Bộ Đào tạo`, which holds **zero** `finance_*`. Folding it into Finance would show Academic Staff a Finance group containing one item — and contradicts ADR-0026 (Academic owns Progression Actions). | live holder query; `resources/js/constants/menu-sidebar.ts:481`; `docs/adr/0026-*` |
| 6 | Critical | Route-parity gate — the plan's only safety net — broken three ways: baseline `HEAD` is post-Phase-2 so it cannot see Phase 2 drops; a sorted-*set* compare is invariant under duplication yet the plan claimed it detects duplicates; no extraction command given, and `href` has two syntaxes (string literal and call expression) so a naive regex passes vacuously. | plan phase-03 step 6 vs its own success criterion; `menu-sidebar.ts:435` vs `:478` |
| 7 | High | Goal 1 ("one persona per group") falsified for the groups the plan created. `view_event` has 4 holders but `view_clubs`/`view_merchandise` have 2, so "Campus Life" reduces to Events-only for two academic personas. `view_room` has 5 holders. `view_finance_revenue_report` is Super-Admin-only, not HQ. | live holder query |
| 8 | High | Menu `requiredPermissions` diverge from what routes enforce. | menu `view_notification_ops` vs `NotificationOpsController.php:40` `view_any_notification`; menu `review_form` vs `QueryController.php:31` `can:view_queries`; menu `view_survey_results_aggregate` vs `Engagement/routes/web.php:78` `can:view_survey` |
| 9 | High | Canvas consolidation was justified by comparing a real permission against `canvas_courses`, which does not exist. Both entries already share `view_canvas_integration`. | `menu-sidebar.ts:151,765`; `config/permission.php:247` |
| 10 | High | Campus scoping ignored. Permissions are resolved per selected campus and cached 1 day; 13 users hold roles on more than one campus, 6 hold more than one role. Role-level rollup is an upper bound, not any user's actual sidebar. | `EloquentCampusPermissionReader.php:18,24-26,82`; `HandleInertiaRequests.php:50,67` |
| 11 | High | Plan asserted `title`/`label` are "display-only strings — nothing keys off them". They are the Vue `v-for` keys at every level. Duplicate siblings after renaming/promotion produce duplicate keys. | `NavMain.vue:85,89`; `NavMenuItem.vue:112,133` |
| 12 | Medium | `filterMenuItems` returns early for any item with `children`, so a wrapper's own `requiredPermissions` is never evaluated — yet the plan cited this function as its safety proof while adding new wrappers. | `usePermissions.ts:35-46` |
| 13 | Medium | Only automated gate was `npx eslint`, which the repo forbids (must route through `./scripts/dev.sh`), and `no-unused-vars` is a warning so eslint exits 0 with an orphaned import present — the exact hazard the phase named. | `.claude/rules/development-rules.md`; `eslint.config.js:6-19` |
| 14 | Medium | Rename table had wrong line numbers (`Sinh phí` is 382, not 383), claimed 805 lines (file is 804), included one row that was already English, and its implied non-ASCII verification misses `Doanh thu` and `Sinh phí`. The orphan-import step named `Play`/`CheckSquare`, neither of which is imported. | `menu-sidebar.ts:2-45,376,382,399` |
| 15 | Medium | Faculty/Discounts folds convert always-visible root items into collapsed subgroups for the largest persona; `isOpen` only ever sets `true` (`NavMenuItem.vue:56,67-69`) so collapsibles latch open for the session. Sequencing contradiction: plan says resolve `Course Statistics` before Phase 2, then schedules it as Phase 3 step 5. | `NavMenuItem.vue:56,59-70`; plan Open Question 2 vs phase-03 step 5 |

## Corrected evidence base

Per-permission holder sets for all 71 permissions referenced in `menu-sidebar.ts` were queried and now anchor the rewritten plan. Legend: SA=Super Admin, DIR=Giám Đốc Đào Tạo, ACA=Cán Bộ Đào tạo, ADM=Hành chính, TP=Trưởng Phòng, CB=Cán Bộ.

Clusters that actually exist:

- **Pure HQ** (`HQ|SA`): every `finance_*` except revenue/settings/dng-campus-mappings, all `egc_*`, `clubs`, `merchandise`, `merchandise_report`, `redemption_order`, `scholarship`, `tuition_plan`, `voucher`, `crm_value_mapping`
- **Pure Hành chính** (`ADM|SA`): `view_room_booking`, `create_room_booking`, `approve_room_booking`
- **Pure Academic Staff** (`ACA|SA`): `exam_resit`, `manage_exam_schedule`, `student_action`, `academic_report`
- **Academic both** (`ACA|DIR|SA`): `canvas_integration`, `lecturer`
- **Super Admin only**: `notification_ops`, `send_manual_notification`, `role`, `system_config`, `system_log`, `ai_metrics`, `ai_provider_settings`, `manage_departments`, `finance_revenue_report`, `finance_settings`, `finance_dng_campus_mappings`
- **Broadly shared** (`ACA|DIR|HQ|SA`): `attendance`, `campus`, `course_offering`, `curriculum_version`, `form`, `grade`, `semester`, `syllabus`, `event`, `review_form`
- **Widest**: `view_student` (6 roles), `view_program`/`view_unit` (5), `view_user` (5, notably excluding ACA), `view_room` (5)

Consequences for the IA:

- Fold Discounts into Finance is right for `Tuition Plans`, `Scholarships`, `Student Scholarships`, `Vouchers` (all `HQ|SA`) and wrong for `Scholarship Adjustments` (`ACA|HQ|SA`), which belongs to Academic.
- The Campus Operations split axis was wrong. `view_room` and `view_event` have nearly identical holder sets; the genuine boundary is the **booking workflow** (`ADM|SA`), not facilities-vs-commerce. Rooms and Events belong together; Clubs and Merchandise are the pure-HQ cluster.
- Goal 1 must be restated as "one owner plus declared shared items". Single-persona purity is achievable only for Finance, Store & Clubs, and the Super-Admin-only entries.

## Actions taken

- Ungated routes filed separately as swinburne-edu/asia-admin-portal#135 — pre-existing, not caused by this plan.
- Baseline commit recorded: `31bea61e8cd16715f99b1c896cdd31189d64838f` (target file clean at that SHA).
- Plan rewritten from 3 phases to 5, anchored on the corrected holder sets.

## Unresolved questions

1. Which role/permission assignment is authoritative — the seeder (5 roles), the dev DB (9 roles), or production? Every placement decision depends on this and it is unresolved until production is confirmed.
2. Who owns adding `HQ`, `Cán Bộ Đào tạo`, `Hành chính`, and `Check student application` to `RoleAndPermissionSeeder`? Without them no permission-shaped plan in this repo is reproducibly verifiable.
3. `CRM Value Mappings`: seeder grants `manage_crm_value_mapping` to `Giám Đốc Đào Tạo`, dev DB grants it to `HQ`. Its placement is deferred until question 1 is answered.
4. Does `Course Statistics` report attendance or grade performance? Determines whether it stays under Attendance.
