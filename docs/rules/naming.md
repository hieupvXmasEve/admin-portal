---
title: Naming Rules
status: active
owner: Platform Team
last_verified: 2026-07-25
scope: engineering-rules
applies_to:
  - app
  - resources/js
  - routes
  - database
---

# Naming Rules

## Backend

| Element | Convention | Example |
|---|---|---|
| Module | singular PascalCase | `Academic`, `Finance` |
| Model | singular PascalCase | `CourseOffering` |
| Controller | entity plus `Controller` | `CourseOfferingController` |
| Action | verb + entity + `Action` | `CreateEventAction` |
| Query | verb + entity + context + `Query` | `ListAcademicRecordsQuery` |
| Policy | model + `Policy` | `AcademicRecordPolicy` |
| FormRequest | intent + `Request` | `StoreRoomRequest` |
| API Resource | entity + `Resource` | `StudentResource` |
| Table | plural snake_case | `course_offerings` |
| Column | snake_case | `first_name` |

Web controllers live under `Http/Web/{Actor}` and API controllers under
`Http/Api/{Actor}`. Use the standard resource methods (`index`, `show`,
`create`, `store`, `edit`, `update`, `destroy`) when their semantics fit.

Action entrypoints use a typed public static `run(...)`; Query entrypoints use a
typed public `handle(...)`.

## Frontend

| Element | Convention | Example |
|---|---|---|
| Page directory | PascalCase | `pages/Academic/Records` |
| Page | route-aligned PascalCase | `Index.vue`, `Show.vue` |
| Component | specific PascalCase | `RecordTable.vue` |
| Composable | `use` + capability | `useDataTable.ts` |
| Type or interface | PascalCase | `PaginatedResponse` |
| Store file | camelCase | `userStore.ts` |

Do not create generic shared component names such as `Table.vue` or `Form.vue`.
New page paths map explicitly to route/controller structure. Existing
kebab-case or lowercase page directories are legacy; see
[legacy-migration.md](legacy-migration.md).

## Routes and contracts

- Route names use dot notation: `{module}.{resource}.{action}`.
- URL segments use kebab-case.
- Versioned API paths use `/api/v1/{actor}/{resource}`.
- Prefer named route helpers over literal paths.
- Contract names describe the noun and capability. Avoid vague `*Service` and
  `I*` interface names.

## Stable field names

Do not rename these established contract fields while refactoring:

- Student decision/import fields: `decision_number`, `decision_signed_at`,
  `decision_signer`, `decision_id`, `missing_documents`,
  `shared_upload_record_id`.
- Notification V2 fields: `notification_event_outbox.campus_id`,
  `notification_messages.recipient_user_id`,
  `notification_messages.campus_id`.

Laravel-to-Inertia prop keys remain snake_case, and TypeScript interfaces match
that casing.
