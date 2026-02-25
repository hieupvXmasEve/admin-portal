# Design Guidelines

Last updated: 2026-02-23

This document captures the current UI/UX implementation conventions used in the Vue + Inertia frontend.

## 1) UI Stack

- Vue 3 + TypeScript
- Inertia page model
- Tailwind CSS v4
- Theme tokens and CSS variables in `resources/css/app.css`
- Reka UI + shadcn-style component primitives
- Lucide icons
- `vue-sonner` for toast notifications

## 2) Layout and Navigation

### Default App Layout

- Global app layout is wired in `resources/js/app.ts`.
- Most pages use `resources/js/layouts/AppLayout.vue`.
- Auth and campus-selection pages are explicit layout exceptions.

### Sidebar and Permission-Aware Menus

- Sidebar component: `resources/js/components/AppSidebar.vue`.
- Menu definitions: `resources/js/constants/menu-sidebar.ts`.
- Menu visibility should be filtered by permissions from shared Inertia props.

## 3) Theming

- Theme behavior is handled by `resources/js/composables/useAppearance.ts`.
- Light/dark/system mode is supported.
- Core tokens are defined as CSS variables (`--background`, `--primary`, `--sidebar-*`, etc.).

Guideline:
- Reuse existing token variables instead of hardcoding palette values.

## 4) Page Composition

- Organize pages by feature under `resources/js/pages/`.
- Keep pages focused on composition and orchestration.
- Move reusable behavior to composables under `resources/js/composables/`.
- Keep shared primitives/components in `resources/js/components/`.

## 5) Form and Interaction Patterns

- Prefer typed props and explicit interfaces for component contracts.
- Use existing confirmation/modal/toast patterns instead of introducing new interaction systems.
- Keep loading/error/empty states explicit in feature pages.

## 6) Realtime UX

- Realtime wiring is centralized in `resources/js/lib/echo.ts`.
- Notification listeners are implemented through composables (for example `useRealtimeNotifications`).

Guideline:
- Initialize realtime only when required env keys exist.
- Keep user-facing notification copy concise and actionable.

## 7) Accessibility and Responsiveness Baseline

- Reuse existing accessible primitives from UI library wrappers.
- Preserve keyboard and focus behavior when customizing components.
- Validate layouts for desktop and mobile breakpoints when updating major pages.

## 8) Design Consistency Rules

- Do not introduce parallel button/input/table systems if an existing component already covers the use-case.
- Use existing spacing and typography scales from global styles.
- Keep icon usage semantically consistent (same icon for same action category).

## 9) Definition of Done for UI Changes

UI work is complete only when:
- it follows current layout/navigation conventions,
- it respects existing theme tokens,
- permission-aware navigation behavior remains intact,
- no regressions are introduced in primary admin workflows.

## Unresolved Questions

- Should a formal design token reference page be generated from `resources/css/app.css`?
- Which pages are considered canonical visual references for future UI updates?
- Should dark mode be treated as mandatory parity for all newly built screens?
