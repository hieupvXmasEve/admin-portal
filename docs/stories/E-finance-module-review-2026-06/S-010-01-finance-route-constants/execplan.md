# Exec Plan

## Goal

Track Task 1 from the Milestone 1 plan as its own story: add Finance route-name
constants and a shared `financeRoutes` helper.

## Scope

In scope:

- Create `resources/js/constants/finance-routes.ts`.
- Add `financeRoutes` to `resources/js/utils/routes.ts`.
- Keep route names aligned with `app/Modules/Finance/routes/web.php`.

Out of scope:

- New backend routes.
- Permission changes.
- Sidebar or topbar UI changes.
- Product tests beyond lint/build proof.

## Risk Classification

Risk flags:

- Public contract: frontend route helper surface.
- Existing behavior: later navigation depends on these names.
- Weak proof: frontend has no JS unit runner.

Hard gates:

- Helper names must match real or planned Ziggy route names.
- Do not call planned route helpers from rendered UI before their routes exist.

## Work Phases

1. Add route constants.
2. Add grouped helper exports.
3. Lint the touched files.
4. Let later stories consume the helper.

## Stop Conditions

Pause if:

- A required Finance route name does not exist and no later story owns it.
- Adding helpers would require changing backend route names.
