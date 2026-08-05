# Development Rules

Use this file when editing code, tests, scripts, or configuration.

## Baseline

- Follow project docs in `docs/` and existing local patterns.
- Prefer YAGNI, KISS, and DRY in that order.
- Implement real behavior. Do not add fake data, mocks, or temporary shortcuts just to satisfy a check.
- Keep changes scoped to the request and the affected contracts.
- Use descriptive kebab-case file names for new files when the repo has no stronger convention.
- Split code only when it reduces real complexity or matches existing module boundaries.

## File Placement & Module Ownership

This repo separates code by feature/domain module (`app/Modules/<Owner>/...`).
A whole feature — model, services, queries, policy, HTTP controllers,
FormRequests, and its route registration — must live under ONE owner module.

- **Resolve the owner FIRST, then copy that owner's convention for the exact
  file TYPE.** Before creating any file, find where the owning module already
  puts that type — do NOT anchor on a same-named file in another location.
  - Right: creating an Academic controller → `find app/Modules/Academic -iname '*Controller.php'` → place under `app/Modules/Academic/Http/Web/`.
  - Wrong: `find app -iname 'Scholarship*Controller'` → copying a global `app/Http/Controllers/*` location just because the name matches.
- **Register routes in the owner module's own route file**
  (`app/Modules/<Owner>/routes/web.php`), not the global `routes/web/*` shells.
- **Pre-existing global classes stay put** unless the task explicitly moves
  them; the rule governs NEW files, and "there's an old global one nearby" is
  not a reason to add a new global one.
- **Every new module boundary gets a placement arch test** (repo pattern): a
  test asserting the feature's HTTP + routes live in the module and no stray
  copy exists under global `app/Http`. Placement bugs are otherwise silent —
  cross-import arch tests do not cover HTTP-layer location.
- **Plans must pin an exact path for EVERY layer** (migration, model, service,
  query, policy, controller, FormRequest, route file), route file included as
  `app/Modules/<Owner>/routes/web.php`. Leave no layer "unspecified" — the
  blank is exactly where placement is guessed wrong.

## Quality Gates

- Run the narrowest useful test first, then broaden when shared behavior or public contracts changed.
- Do not hide failing tests, lint, type, build, or syntax errors.
- Preserve public contracts unless the change intentionally updates them and the user accepted that scope.
- Keep commits focused and use conventional commit format without AI references.
- Never commit secrets, dotenv files, tokens, private keys, database credentials, or personal data.

## Tooling

- Use `gh` for GitHub operations when needed.
- Use current docs only when the API/tooling may have changed.
- Use relevant skills by reading their descriptions first, then opening only the needed `SKILL.md`.
- Use `/ak:preview` only when a visual explanation will materially help the user understand the change.
- **Never call bare `php`, `composer`, `npm`, or raw `docker exec`** — app runs
  inside Docker (`swinx-app-dev`), not on host. Route every command through
  `./scripts/dev.sh`:
  - `./scripts/dev.sh artisan <cmd>` (migrate, tinker, test, boost:mcp, ...)
  - `./scripts/dev.sh composer <cmd>`
  - `./scripts/dev.sh npm <cmd>` / `pnpm <cmd>`
  - `./scripts/dev.sh mysql` for a DB shell, or `./scripts/dev.sh mysql -e "SQL"` for one-off queries
  - `./scripts/dev.sh shell` to get an app-container shell
  - See `scripts/dev.sh` usage line for the full subcommand list.
