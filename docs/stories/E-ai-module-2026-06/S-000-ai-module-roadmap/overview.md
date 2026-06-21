# AI-MOD-000 - AI Module Roadmap

## Status

implemented

## Lane

high-risk

## Current Behavior

Swinx has a blueprint for AI data analysis in `docs/features/ai/ai.md`, but no
tracked Harness story set for implementing it safely over time.

Current baseline:

- No runtime AI module, LLM provider integration, vector store, MCP server, or
  AI tool registry is present in the checked application surface.
- Swinx already has useful building blocks for a safe AI assistant: module-level
  Query classes, campus/session scope, Gate/permission checks, audit logging,
  and staff-facing reports.
- The AI blueprint is intentionally broad: provider settings, glossary,
  catalogs, query DSL, tool calling, orchestration, context, security, audit,
  evaluation, structured output, governance, and MCP readiness.
- Without an explicit story set, the module can lose context or drift into a
  large unbounded implementation.

## Target Behavior

The AI module has a master high-risk story packet and a phase tracker that lets
future work split cleanly into child stories.

Target planning behavior:

- `docs/stories/E-ai-module-2026-06/README.md` is the source of truth for phase
  order, story status, dependencies, and shared guardrails.
- `AI-MOD-000-ai-module-roadmap` captures the overall product and architecture
  boundaries without implementing runtime code.
- Future stories start as small, high-risk slices with explicit validation and
  Portal impact.
- The default path remains internal, read-only, campus-scoped, permission-aware,
  allowlisted, audited, and evidence-backed.

## Affected Users

- Platform engineers planning the AI module.
- Future agents implementing AI slices.
- Admin/staff users who may later use internal AI analysis surfaces.
- Compliance or operational reviewers who need auditability for AI data access.

## Affected Product Docs

- `docs/features/ai/ai.md`
- `docs/stories/E-ai-module-2026-06/README.md`
- `docs/project-overview-pdr.md` when runtime behavior lands.
- `docs/system-architecture.md` when runtime module boundaries land.
- `docs/codebase-summary.md` when code-verified AI behavior exists.

## Portal Impact

Portal impact: none.

This master story creates planning artifacts only. Student or lecturer portal
impact must be declared by future child stories if they change
`/api/v1/student/*`, `/api/v1/lecturer/*`, Identity context/auth contracts, or
nested Nuxt portal code.

## Acceptance Criteria

- Create a high-risk AI module story set under
  `docs/stories/E-ai-module-2026-06/`.
- Create one master story packet for `AI-MOD-000-ai-module-roadmap`.
- Decompose the AI module into ordered phases and child story IDs.
- Include status tracking, dependencies, shared guardrails, non-goals, and
  first-slice recommendation.
- Classify the initiative as high-risk because it touches authorization,
  audit/security, provider credentials, data model, contracts, weak proof, and
  multiple domains.
- Register the master story in Harness.
- Do not implement runtime code, migrations, UI, provider calls, MCP exposure,
  or student/lecturer portal changes in this story.

## Non-Goals

- Do not implement the AI module.
- Do not create migrations or provider configuration.
- Do not add UI routes or menu entries.
- Do not build MCP, vector search, model fine-tuning, or write/action mode.
- Do not create child story packets until a phase is selected for execution.
- Do not change student or lecturer portal contracts.
