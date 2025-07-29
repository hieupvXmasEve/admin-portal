Review only the current working set for:

- Missing authorization checks (e.g., `authorize()`, `Gate::allows()`, `can()` directives)
- Incorrect or missing use of Spatie roles/permissions
- Campus context violations (global access to scoped data)
- Unprotected API routes
- Frontend actions rendered without proper auth guards

Rules:
- Campus permissions must be enforced in every access point
- Follow `CLAUDE.md` middleware and authorization strategy
- Suggest where to apply middleware or permission check

$ARGUMENTS
!git status

