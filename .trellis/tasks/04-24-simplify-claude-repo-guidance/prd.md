# Simplify Claude Repo Guidance

## Goal

Make `CLAUDE.md` concise and useful for the current Swinx repository. It should orient Claude Code quickly, point to source-of-truth docs, and avoid duplicating long framework guidance already documented elsewhere.

## What I Already Know

- User wants to remove redundant content from `CLAUDE.md`.
- Project is Laravel 12 + Vue 3 + Inertia monolith.
- `README.md` states canonical docs live in `docs/`.
- Local commands should run through `./scripts/dev.sh`.
- Existing `CLAUDE.md` is 542 lines and embeds a long Laravel Boost guideline block.

## Requirements

- Keep `CLAUDE.md` repo-specific.
- Keep only instructions Claude needs before editing code.
- Link to source-of-truth docs instead of repeating them.
- Preserve important project constraints: Docker-first commands, modular monolith direction, schema/route verification, docs sync.
- Do not modify unrelated docs.

## Acceptance Criteria

- [ ] `CLAUDE.md` is much shorter and easier to scan.
- [ ] Redundant Laravel Boost block is removed.
- [ ] Current project stack and command wrappers remain documented.
- [ ] Source-of-truth docs are linked.
- [ ] Final file still gives clear implementation, validation, and documentation expectations.

## Definition of Done

- `CLAUDE.md` updated directly.
- Markdown sanity checked.
- Change summary provided.

## Out of Scope

- Changing application code.
- Updating Laravel/Vue docs.
- Refactoring `docs/` contents.
- Running app tests for documentation-only change.

## Technical Notes

- Read `README.md`, `docs/code-standards.md`, `docs/codebase-summary.md`, and existing `CLAUDE.md`.
- This is a documentation cleanup, so no compile/test command is required beyond file inspection.
