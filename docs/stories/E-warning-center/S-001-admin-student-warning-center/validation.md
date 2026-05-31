# Validation

## Proof Strategy

Prove warning eligibility, row metrics, authorization, duplicate suppression, email plus in-app notification publication, and student-facing status consistency independently.

## Test Plan

| Layer | Cases |
| --- | --- |
| Unit | Academic standing below 50 maps to warning; 50 and above maps to normal; attendance warning threshold derives from `min_attendance_threshold`; `min_attendance_threshold=80,total_sessions=12` yields early warning after 1 absence and allowed absences 2. |
| Integration | Admin warning queries are campus-scoped and active-semester scoped; send actions publish Notification V2 events with `realtime` and `email`; duplicate send for the same milestone is blocked; unauthorized users are rejected. |
| E2E | Admin opens warning page, expands attendance subject/section, sees threshold math, sends academic and attendance warning, sees updated sent state, and cannot resend the same milestone. |
| Platform | Student API notification list returns the warning message; `FE/student-nuxt` notification views render warning category/type; student dashboard/GPA payload returns matching warning/normal labels and credit metrics. |
| Performance | Large active semester loads should avoid N+1 queries when expanding subjects and sections. |
| Logs/Audit | Warning send records actor, target, source metrics, duplicate key, warning type, notification event id, and email/in-app delivery records. |

## Fixtures

- One active campus.
- One active semester.
- One student with current cumulative GPA 49.999.
- One student with current cumulative GPA 50.000.
- One active course offering with sections `.1` and `.2`.
- Sections with 10, 12, 15, and 20 total sessions to verify percent-to-session conversion.
- Students below, at, and above early-warning threshold.
- Students below, at, and above exceeded-limit threshold.
- A successful warning log for one milestone, plus a later absence that creates a new milestone.

## Commands

Use repo wrappers when implementation begins:

```text
./scripts/dev.sh test
./scripts/dev.sh npm run type-check
./scripts/dev.sh npm run lint
./scripts/dev.sh artisan pint
```

## Acceptance Evidence

Not run. This story currently records analysis and UI/UX planning only.
