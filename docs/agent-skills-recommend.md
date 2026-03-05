# Agent Skills Recommendation (Current)

Last updated: 2026-03-04  
Owner: Platform Team  
Status: Current workflow snapshot

This file reflects skills currently available in this repository's OpenCode/ClaudeKit setup. It replaces older references to non-existent scaffold skills.

## Primary Delivery Skills

| Skill            | Use case                                        |
| ---------------- | ----------------------------------------------- |
| `ck:scout`       | Fast codebase/doc scouting before edits         |
| `ck:plan`        | Build implementation plans in `plans/`          |
| `ck:cook`        | Required pre-implementation workflow guard      |
| `ck:fix`         | Required pre-fix workflow guard                 |
| `ck:test`        | Run targeted test suites and summarize failures |
| `ck:code-review` | Post-implementation review pass                 |
| `ck:docs`        | Update and synchronize docs in `docs/`          |

## Supporting Skills Often Used Here

| Skill                     | Use case                                                                    |
| ------------------------- | --------------------------------------------------------------------------- |
| `ck:repomix`              | Generate `repomix-output.xml` for repo snapshots                            |
| `ck:git`                  | Structured staging/commit/PR flow                                           |
| `ck:debug`                | Root-cause analysis for regressions                                         |
| `ck:web-testing`          | Browser-level checks when needed                                            |
| `ck:frontend-development` | Vue/TS implementation support                                               |
| `ck:backend-development`  | Laravel/PHP implementation support                                          |
| `inertia-filter-table`    | Build server-side filtered/sorted/paginated tables (Laravel + Vue + Inertia) |

## Orchestration Reality

- Planning/research/testing/review are often delegated as role-specific subagent steps.
- Team mode is optional; default sessions usually run single-agent with skill activation.
- Docs updates are expected when code contracts/routes/runtime behavior change.

## Notes

- Older doc references like `scaffold-module`, `scaffold-crud`, `create-action`, `create-query` are not the active catalog in this repo.
- Always check the live skill registry in the current agent session when in doubt.
