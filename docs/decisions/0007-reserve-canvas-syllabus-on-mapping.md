# 0007 Reserve Canvas Syllabus on Mapping

Date: 2026-05-31

## Status

Accepted

## Context

Canvas assignment sync clones the syllabus assigned to a course offering and
then replaces its assessment components with Canvas-specific groups. Before
assignment sync runs, a Canvas course can already be mapped to a local offering.

If staff reuse that offering's syllabus template for another class, the later
Canvas workflow can create ambiguity about which template is safe to select and
can lead to incorrect grade-sync setup.

## Decision

Reserve a syllabus template for manual course-offering assignment as soon as
any offering using it has a Canvas mapping with `sync_status = mapped`.

Also reserve templates whose title contains `Canvas` or which are assigned to
an offering with `is_canvas_synced = true`.

Course offering create, update, and duplicate flows must not assign a reserved
template to another offering. Editing an offering may keep its current template
so staff can update unrelated fields without breaking the Canvas link.

## Alternatives Considered

1. Reserve only after assignment sync. Rejected because staff could select a
   mapped template during the gap before first sync.
2. Filter only the Vue dropdown. Rejected because direct HTTP requests and
   duplicate offering could bypass the UI.
3. Add a dedicated database flag. Deferred because mapping and sync relations
   already provide the required source of truth.

## Consequences

Positive:

- Canvas-linked syllabus templates cannot be reused accidentally.
- Reservation begins at the earliest provider-link boundary.
- Existing Canvas offerings remain editable without changing their template.

Tradeoffs:

- A template used by a mapped offering disappears from manual selection even
  before assignment sync runs.
- Existing shared assignments are not rewritten automatically.

## Follow-Up

- Add a cleanup workflow only if existing production data contains templates
  already shared across Canvas-linked offerings.
