---
title: Swinx Design Guidelines
status: canonical
owner: Frontend Team
last_verified: 2026-07-25
scope: visual-design
---

# Swinx Design Guidelines

This document owns visual language, layout, accessibility, and interaction
quality. Technical Vue/Inertia rules live in `docs/rules/frontend.md`.

## Design principles

- Operational clarity before decoration.
- Dense information remains scannable through hierarchy and grouping.
- State, scope, and consequences are visible before an action is committed.
- Reuse established primitives and patterns instead of introducing local visual
  dialects.
- Desktop workflows remain usable on smaller screens without hiding critical
  state.

## Foundations

- Use the design tokens and CSS variables in `resources/css/app.css`.
- Do not hardcode palette values when a semantic token exists.
- Use existing typography, spacing, radius, and border scales.
- Use `lucide-vue-next` icons with consistent size and semantic meaning.
- Use existing components from `@/components/ui`.

## Color and contrast

- Pair semantic surfaces with their foreground token:
  `bg-primary text-primary-foreground`,
  `bg-accent text-accent-foreground`, and equivalent pairs.
- Do not use solid primary/accent backgrounds for selected list rows.
- Preferred selected-row treatment:
  `border-primary bg-primary/5 ring-1 ring-primary/30`.
- Preferred row hover treatment: `hover:bg-muted/50`.
- Do not rely on color alone to communicate status or validation.

## Layout

- Keep page titles, scope indicators, primary action, and filters in a stable
  hierarchy.
- Use the existing application layout and navigation shell.
- Preserve permission-aware navigation behavior.
- Keep tables and operational lists aligned across pages.
- Use sticky headers or summaries only when they materially reduce navigation
  cost.
- Avoid nesting multiple scroll regions without a clear operational need.

## Forms and actions

- Labels remain visible for business-critical fields.
- Required/optional state and validation errors are explicit.
- Destructive actions state the affected record and consequence.
- Preview complex or bulk changes before commit.
- Disable repeated submission while a request is pending.
- Preserve user-entered values when server validation fails.
- Date/time controls use project pickers and remain keyboard accessible.

## Loading, empty, and error states

- Deferred content displays a shape-matching skeleton.
- Empty states explain whether the cause is no data, current filters, missing
  permission, or unavailable scope.
- Errors provide a safe recovery action where possible.
- Do not display stale metrics as current; label freshness or loading state.

## Tables and filters

- Columns prioritize the operator's decision, not database shape.
- Long identifiers and secondary metadata use subdued hierarchy.
- Active filters are visible and removable.
- Pagination, sorting, search, and export describe the same dataset scope.
- Selection state remains distinct from hover, warning, and destructive states.

## Accessibility

- Preserve native keyboard and focus behavior of shared components.
- Dialogs and drawers trap focus and restore it on close.
- Interactive icons have accessible names.
- Touch targets remain usable on mobile.
- Text and status badges meet contrast requirements.
- Validate focus order when adding conditional content.

## Responsive behavior

- Critical status and primary actions remain visible on narrow screens.
- Tables may switch to cards only when information hierarchy is preserved.
- Filters may move into a drawer, but active-filter state stays visible.
- Avoid horizontal page scrolling; use deliberate table overflow when required.

## Review checklist

- Existing primitives and tokens are reused.
- Selected, loading, empty, error, and disabled states are covered.
- Keyboard/focus behavior is preserved.
- Permission and campus scope remain understandable.
- Mobile and desktop layouts are checked for major workflow changes.
