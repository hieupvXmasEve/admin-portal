# Validation

## Proof Strategy

This story is documentation and Harness tracking only. Validation proves that
the master story exists, is registered, and gives future agents enough structure
to split work without losing context.

Runtime proof is intentionally deferred to child stories.

## Test Plan

| Layer | Cases |
| --- | --- |
| Unit | Not applicable; no product code changes. |
| Integration | Not applicable; no runtime integration changes. |
| E2E | Not applicable; no UI or API behavior changes. |
| Platform | Verify Harness can query the master story row after registration. |
| Performance | Not applicable. |
| Logs/Audit | Not applicable for runtime logs; Harness intake/story/trace records provide planning evidence. |

## Fixtures

No runtime fixtures.

Future child stories must define deterministic users, campus context, role
permissions, provider mock responses, report data, expected tool calls, and
expected answer fixtures before implementation.

## Commands

Planning validation:

```text
./scripts/harness query matrix --numeric
git status --short
```

## Acceptance Evidence

- Harness intake recorded as `#124`.
- Harness story row registered for `AI-MOD-000-ai-module-roadmap`.
- `./scripts/harness query matrix --numeric | rg -F "AI-MOD-000-ai-module-roadmap"` returned the master story row.
- Self-review removed the remaining unfinished acceptance-evidence note.
- No runtime code, migrations, routes, provider calls, or portal files were changed.
