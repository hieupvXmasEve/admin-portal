# Exec Plan

## Goal

Add the first live-provider Staff Copilot agent so staff can ask
natural-language analytical questions while Swinx keeps all business-data access
inside allowlisted, permission-aware, audited tools.

## Scope

In scope:

- Discover current Laravel AI SDK package version and supported agent/tool/
  structured-output APIs.
- Add a live runtime mode for Staff Copilot behind provider-setting and
  permission gates.
- Build a safe prompt context from MetricCatalog, EntityCatalog, current campus,
  actor context, and tool definitions.
- Ask the live model for structured planner output.
- Validate all model-proposed tool calls server-side.
- Execute accepted calls through ToolDispatcher.
- Ask the live model to synthesize a final answer from redacted tool results.
- Persist provider usage, runtime mode, prompt/tool versions, tool call evidence,
  safe errors, and final answer metadata.
- Preserve deterministic fallback.
- Add fake-backed tests for live planning, final synthesis, fallback, denied
  tool calls, invalid model output, and provider failure.
- Update docs and Harness evidence after runtime implementation lands.

Out of scope:

- Direct DB/SQL access by AI.
- New business-data tools beyond `query_metrics` and `search_entities`.
- Student profile sections (`AI-MOD-007`).
- Conversation context/memory (`AI-MOD-008`).
- Compare metrics (`AI-MOD-009`).
- Risk/recommendation rules (`AI-MOD-010`).
- Write/action mode (`AI-MOD-016`).
- MCP, vector stores, embeddings, streaming, files, audio, images, provider-native
  tools, sub-agents, or portal assistants.

## Risk Classification

Risk flags:

- Authorization: tool calls depend on staff permissions and campus scope.
- Audit/security: prompts, tool calls, provider usage, hidden sections, and
  final answers must be redacted and traceable.
- External provider: live model invocation, timeout, cost, and model behavior.
- Public contracts: Staff Copilot page props and answer payloads change.
- Existing behavior: deterministic copilot fallback must keep working.
- Weak proof: live model behavior is non-deterministic without fakes/evaluation.
- Multi-domain: AI reads Academic/Finance evidence through tools.

Hard gates:

- Authorization.
- Audit/security.
- External provider behavior.

Portal impact: none.

## Work Phases

1. Discovery
    - Confirm installed `laravel/ai` version and supported APIs.
    - Read official Laravel AI SDK docs for agents, tools, structured output,
      fakes/assertions, events, usage metadata, timeouts, and provider options.
    - Inspect AI-MOD-001 provider settings and AI-MOD-002 audit recorder.
    - Inspect current StaffCopilotAgentRunner, ToolRegistry, ToolDispatcher,
      QueryMetricsResult, and EntitySearchResult.

2. Runtime mode design
    - Define runtime mode selection:
        - deterministic;
        - live_provider;
        - fallback.
    - Decide how provider setting selects provider/model.
    - Define timeout and safe failure behavior.

3. Prompt and structured output contracts
    - Build compact catalog summaries.
    - Define planner output DTO/validator.
    - Define final answer output DTO/validator.
    - Add prompt version and model/tool schema version stamps.

4. Live provider execution
    - Implement live agent service.
    - Call SDK with fakes in tests.
    - Validate planner output.
    - Dispatch accepted tool calls through ToolDispatcher.
    - Feed redacted tool results into final synthesis.
    - Persist provider usage and trace metadata.

5. UI and fallback
    - Add live/deterministic/fallback capability state to page props.
    - Render clarification messages and provider failure safe states.
    - Keep existing answer evidence rendering.

6. Validation
    - Add live-agent feature/unit tests with SDK or adapter fakes.
    - Re-run AI regression suite.
    - Run targeted Pint and frontend checks.
    - Verify no live provider credentials are required for automated tests.

7. Docs and Harness
    - Update current-state docs after runtime lands.
    - Update story validation evidence.
    - Record Harness trace with detailed high-risk metadata.

## Stop Conditions

Pause for human confirmation if:

- Laravel AI SDK does not support required agent/structured-output/fake behavior.
- A custom provider client appears necessary.
- Runtime implementation would need schema changes not already accepted.
- Live provider behavior cannot be tested without real credentials.
- Tool validation would need to be weakened.
- The model would need access to raw SQL, broad schema metadata, raw rows, hidden
  fields, or direct DB queries.
- Provider cost/quota behavior is unclear.
- Product scope expands into profile sections, write actions, portal assistants,
  MCP, vector search, streaming, or provider-native tools.
