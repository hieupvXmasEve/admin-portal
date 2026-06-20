# Design Guidelines

Last updated: 2026-03-26  
Owner: Frontend Team  
Status: Active baseline

## 1) UI Stack

- Vue 3 + TypeScript
- Inertia.js page model
- Tailwind CSS v4
- Theme tokens in `resources/css/app.css`
- Existing shared component primitives and icon/toast tooling

## 2) Layout and Navigation Standards

- Main app boot and page resolution: `resources/js/app.ts`
- Default layout convention: `resources/js/layouts/AppLayout.vue`
- Keep auth/campus-selection layout exceptions explicit
- Preserve permission-aware menu rendering behavior

## 3) Routing and Integration Standards

- Prefer `route(...)` helpers and shared route constants.
- Use shared route helper modules before adding literal paths.
- Current codebase still has literal URL pockets; treat them as drift-risk and reduce when touching those screens.
- Keep frontend route names aligned with backend route names/constants.

## 4) Theming and Visual Consistency

- Reuse existing design tokens/CSS variables in `resources/css/app.css`.
- Avoid hardcoding palette values when a token exists.
- Keep spacing, typography, and icon semantics consistent with existing pages.
- **Foreground pairing:** any `bg-accent`, `bg-primary`, `bg-secondary`, or `bg-destructive` MUST include the matching `text-*-foreground` class on the same element (or an explicit child wrapper). In Swinx, `accent` and `primary` are the same brand green — solid green backgrounds with default black text are forbidden.
- **Selectable lists:** use subtle selection (`border-primary bg-primary/5 ring-1 ring-primary/30`) instead of solid `bg-accent`. Hover with `hover:bg-muted/50`. See `docs/rules/frontend-gotchas.md` §3.

## 5) Forms and Interaction Patterns

- Use typed props/interfaces for component contracts.
- Keep loading/error/empty states explicit.
- Reuse existing modal/confirm/toast behavior rather than introducing parallel patterns.
- For modal/drawer form workflows: use `vee-validate` + Zod + `useApi`/`useApiRequest` pattern. See `docs/RULES_vue-form-useApi.md` for rules and checklist.

## 6) API Interaction Standards

- Prefer shared API wrappers/composables for consistency.
- If raw `fetch` is used, keep request/response handling aligned with current API envelope behavior.
- When updating high-risk pages, prioritize replacing new literal endpoints with helper/centralized contract usage.

## 7) Accessibility and Responsiveness

- Preserve keyboard and focus behavior of shared components.
- Validate desktop and mobile layout when touching major pages.

## 8) Definition of Done for UI Changes

UI changes are done when:
- they follow existing layout/navigation conventions,
- route and API contracts remain aligned with backend,
- theme/token consistency is preserved,
- permission-sensitive behavior does not regress.

## Unresolved Questions

- Should helper-based route usage become mandatory lint policy?
- Should API wrapper usage be enforced for all authenticated JSON calls?
- Which pages are canonical UI references for future redesign/refactor work?
