# Design

## Domain Model

The UI edits the existing `syllabus_templates.grading_scheme` JSON. It does not
create new tables. Validation and preview use the same backend validator and
calculator contracts as runtime grading.

## Application Flow

1. Create/edit pages receive available grading engines and the current scheme.
2. Admin selects `default` or `metropolia_v1`.
3. JSON is validated client-side for parseability and server-side for engine
   contract.
4. Preview endpoint calculates sample component scores without saving.
5. Save sends `grading_scheme: null` for default behavior or the parsed scheme
   for custom behavior.

## Interface Contract

Routes under `routes/web/syllabus-templates.php`:

```text
GET  /api/syllabus-templates/grading-scheme/options
POST /api/syllabus-templates/grading-scheme/preview
POST /api/syllabus-templates/{syllabusTemplate}/grading-scheme/preview
```

All JSON endpoints use `ApiResponse::success()` or `ApiResponse::error()`.

## Data Model

No migration. This story relies on `syllabus_templates.grading_scheme` from
S-001.

## UI / Platform Impact

Admin Inertia pages:

- `resources/js/pages/syllabus/TemplatesCreate.vue`
- `resources/js/pages/syllabus/TemplatesEdit.vue`
- `resources/js/pages/syllabus/TemplatesShow.vue`

New focused Vue components live in `resources/js/pages/syllabus/components/`.

## Observability

Invalid previews return validation errors without writing. Save operations use
existing syllabus template audit logging.

## Alternatives Considered

1. Build a full visual rule builder.
2. Hide scheme JSON from admins and use CLI only.
3. Store preview sample scores in the database.

The selected design gives admins immediate validation while keeping the first UI
small and reversible.
