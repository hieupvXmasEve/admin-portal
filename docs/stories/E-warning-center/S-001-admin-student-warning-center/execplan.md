# Exec Plan

## Goal

Provide a dedicated admin Warning Center and consistent student-facing warning presentation for academic-standing and attendance-risk cases.

## Scope

In scope:

- Admin academic-standing warning panel.
- Admin active-semester attendance warning panel.
- One-row send action for academic-standing warning.
- One-row send action for attendance warning.
- Student in-app notification visibility.
- Student email notification delivery.
- Student dashboard/GPA standing and metric consistency.
- Staff-configurable warning settings and templates.
- Duplicate suppression per warning milestone.

Out of scope:

- Bulk send.
- Scheduled automated sends.
- Changing GPA or attendance calculations.

## Risk Classification

Risk flags:

- Authorization.
- Audit/security.
- Public contracts.
- External systems.
- Existing behavior.
- Multi-domain.
- Weak proof.

Hard gates:

- Authorization.
- Audit/security.
- External provider behavior.

## Work Phases

1. Confirm product copy, placement, settings scope, and duplicate-send rules.
2. Add warning settings and warning log data model.
3. Add warning query/actions in the Academic module.
4. Add admin Inertia Warning Center and settings page.
5. Route send actions through Notification V2 with `realtime` and `email` channels.
6. Add warning-specific notification type keys and email templates.
7. Update student dashboard/GPA/notification API contracts.
8. Update `FE/student-nuxt` dashboard, notification list/detail, and optional attendance deep-link behavior.
9. Add targeted backend and frontend tests.
10. Update product docs and Harness evidence.

## Stop Conditions

Pause for human confirmation if:

- The warning threshold differs from cumulative GPA below 50 on the 100-point scale.
- The attendance early-warning formula should be a fixed 10% instead of half of the syllabus absence allowance.
- Staff need row-level editable custom messages in this first slice.
- Duplicate warning suppression rules need institution-specific policy.
