---
title: Cross-Module Contract Rules
status: active
owner: Platform Team
last_verified: 2026-07-25
scope: engineering-rules
applies_to:
  - app/Modules
  - app/Shared/Contracts
---

# Cross-Module Contract Rules

Whenever one module needs data or behavior owned by another module, it uses an
explicit contract. Contracts are the legal boundary of the modular monolith.

## When a contract is required

- Reading another module's data.
- Checking state owned by another module.
- Invoking stable business behavior such as GPA, graduation, tuition, or
  balance decisions.
- Returning an aggregate whose inputs span modules.
- Serving core domain data to an API or external integration.
- Establishing a seam that may later become a service boundary.

Use domain events for already-committed facts and explicit projections for
purpose-built cross-context read models. Do not replace a direct dependency
with an equally direct import of another module's concrete class.

## Interface rules

- Interfaces live in `app/Shared/Contracts/{Domain}`.
- The owner module provides the implementation and binds it in its service
  provider.
- Consumers depend only on the interface.
- Contracts accept and return primitives, enums, immutable DTOs, or explicit
  result objects.
- Contracts do not accept or return Eloquent models or collections of models.
- A multi-field result uses a named DTO rather than a generic array.
- Contract operations express domain intent, not storage queries.

Contract names use a descriptive noun and capability, such as
`StudentAcademicReader`, `WalletBalanceProvider`, or
`SurveyCompletionChecker`. Avoid vague `*Service` names and interface prefixes.

## Ownership safeguards

- Each table remains owned by one module.
- Consumers do not join or query the owner's tables.
- Consumers never instantiate or import the concrete implementation.
- Shared Kernel identity references do not grant access to the referenced
  context's internals.
- Academic and Finance exchange source-neutral contracts, events, or
  projections; they never share money or lifecycle models. See ADR-0026.

## Review checklist

- Does the change read, import, or join another module's internal data?
- Does it ask another module to make a business decision?
- Does the public shape leak a model, schema, or mutable collection?
- Is the implementation registered by the owner and resolved through the
  interface?
- Is the contract named around domain intent and stable enough for consumers?
