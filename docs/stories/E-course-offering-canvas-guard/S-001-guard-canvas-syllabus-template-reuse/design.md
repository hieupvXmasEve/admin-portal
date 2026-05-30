# Design

## Domain Model

Add one reusable query scope on `SyllabusTemplate` for manual course-offering
selection.

A template is protected when either condition is true:

- Its title contains `Canvas`, case-insensitively.
- It is assigned to a course offering with `is_canvas_synced = true`.
- It is assigned to a course offering with a Canvas mapping whose
  `sync_status = mapped`.

Normal create selection excludes protected templates. Edit selection also
excludes protected templates, except that an already Canvas-synced offering may
keep its own current template. This preserves edits to unrelated offering
fields without allowing a Canvas template to be assigned to a second class.

## Application Flow

1. `CourseOfferingController::create()` loads active templates through the
   reusable assignable-template scope.
2. `CourseOfferingController::edit()` loads templates for the offering unit
   through the same scope, with the current offering context.
3. Store and update FormRequests apply a dedicated validation rule backed by
   the same scope.
4. Directly submitted protected ids fail validation before persistence.
5. Duplicate offering checks the same scope without a current-template
   exemption and rejects a protected source template.

## Interface Contract

Routes remain unchanged:

- `GET /course-offerings/create`
- `POST /course-offerings`
- `GET /course-offerings/{courseOffering}/edit`
- `PUT /course-offerings/{courseOffering}`
- `POST /course-offerings/{courseOffering}/duplicate`

Validation failure remains an Inertia form error on `syllabus_template_id`.

## Data Model

No schema changes are required. The rule uses:

- `syllabus_templates.title`
- `course_offerings.syllabus_template_id`
- `course_offerings.is_canvas_synced`
- `canvas_course_mappings.course_offering_id`
- `canvas_course_mappings.sync_status`

## UI / Platform Impact

The Vue pages do not need new filtering logic because the backend props will
contain only eligible options. Existing select rendering remains unchanged.

## Observability

No new runtime logs are required. Laravel validation failures provide the
operator-facing feedback.

## Alternatives Considered

1. Filter only in Vue. Rejected because crafted requests could still reuse a
   Canvas template.
2. Filter only by title. Rejected because a renamed Canvas template would
   bypass the guard.
3. Add a new `is_canvas_template` database column. Deferred because existing
   relations and `is_canvas_synced` state are enough for this bounded guard.
