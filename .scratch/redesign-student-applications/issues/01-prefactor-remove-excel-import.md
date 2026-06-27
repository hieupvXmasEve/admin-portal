# Prefactor: remove the Excel import path

Status: ready-for-agent

## Parent

`.scratch/redesign-student-applications/PRD.md`

## What to build

Prefactoring slice — done first to clean the controller before the lifecycle reshape ("make the change easy, then make the easy change"). Remove the Excel import capability for Applications entirely: the import service, the controller import actions, the import/preview/process/template routes, the template export, and the `import_student_application` permission. No replacement — manual entry and CRM ingestion replace it in later slices.

## Acceptance criteria

- [ ] Import-related routes (`import`, `import.preview`, `import.process`, `import.template`) are removed and 404.
- [ ] The import service and template export are removed; no dead references remain.
- [ ] The `import_student_application` permission is removed and any seeders/role grants no longer reference it.
- [ ] No remaining code references the removed import paths (controller, requests, exports, frontend menu entries).
- [ ] Existing Application list/show/create still work (no regression in the surviving routes).
- [ ] Test suite green.

## Blocked by

None - can start immediately
