# 0006 Harness Experimental Only

Date: 2026-05-31

## Status

Accepted

## Context

The repository previously carried several agent workflow systems at once:
Harness, Khuym state and hooks, and local claudekit-style Claude directories.
Those overlapping entrypoints made session startup ambiguous and could route new
work through stale task state.

The user explicitly selected
`hoangnb24/harness-experimental` as the main workflow and asked to stop using
Khuym and claudekit for this project.

## Decision

Use Harness Experimental as the only operational workflow system.

Agents must enter work through `AGENTS.md`, `docs/HARNESS.md`,
`docs/FEATURE_INTAKE.md`, and the `./scripts/harness` CLI. Khuym and claudekit
artifacts are retired and must not be used as live workflow inputs.

## Alternatives Considered

1. Keep Khuym paused but installed. This still leaves hook files, compact
   prompts, and status commands available for accidental reactivation.
2. Keep claudekit local directories as an alternate workflow. This preserves
   conflicting agent instructions and weakens Harness as the single source of
   operational truth.

## Consequences

Positive:

- New work has one intake, story, decision, trace, and backlog loop.
- Agent startup no longer depends on Khuym state or claudekit command packs.
- Historical investigation notes remain available through `history/` without
  acting as a task runner.

Tradeoffs:

- Existing Khuym state is no longer queryable from the working tree.
- Local claudekit conveniences must be recreated as Harness improvements if
  they are still valuable.

## Follow-Up

- Add Harness CLI examples to project docs whenever the CLI schema changes.
