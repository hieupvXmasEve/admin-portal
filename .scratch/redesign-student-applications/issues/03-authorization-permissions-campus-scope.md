# Authorization: campus-scoped lifecycle permissions

Status: ready-for-agent

## Parent

`.scratch/redesign-student-applications/PRD.md`

## What to build

Gate the Application lifecycle actions so only Academic staff, at campuses they are permitted for, can perform them.

End-to-end behavior:

- New permissions `approve_student_application`, `reject_student_application`, `revoke_student_application`.
- A `StudentApplication` policy enforces these on the staff web routes, and the check is **campus-scoped** — a staff member may only act on Applications for campuses they hold the permission at (consistent with the platform's Campus scoping).
- The permissions are granted to the Academic department's role(s) via seeding/role assignment; being able to admit is a matter of holding the permission, not a hardcoded department check.

(Revoke enforcement is wired here so slice 04 can rely on it.)

## Acceptance criteria

- [ ] The three permissions exist and are seeded/granted to the Academic department role(s).
- [ ] A user without the permission gets `403` on approve/reject/revoke.
- [ ] A user with the permission at campus A gets `403` acting on an Application for campus B.
- [ ] A user with the permission at the Application's campus succeeds.
- [ ] Feature tests cover the authorized, unauthorized, and wrong-campus cases.

## Blocked by

- `02-application-lifecycle-core`
