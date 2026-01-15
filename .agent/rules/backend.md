---
trigger: always_on
---

# Backend Rules (Laravel/PHP)

## 1. Controller Rules

- **No Business Logic**: Controllers are adapters only.
- **Responsibility**: Validate request (via FormRequest) -> Call Action/Query -> Return Response.
- **Inertia Responses**: Pass data via Props. Do not make Page components call APIs directly.

## 2. Action Rules

- **Format**: `Verb + [Entity] + Action` (e.g., `CreateEventAction`).
- **Structure**: Must have a public static `run(array $data): mixed` method.
- **Scope**: 1 Action = 1 Business Use-case.
- **No Validation**: Validation belongs in FormRequests.
- **Exceptions**: Throw Domain Exceptions; do not handle HTTP responses here.

## 3. Query Rules

- **Format**: `[Verb/Get] + Entity + [Context] + Query` (e.g., `ListAcademicRecordsQuery`).
- **Structure**: Must have a public `handle(...$args): mixed` method.
- **Purpose**: Read-only logic, complex reporting, or specific data retrieval.

## 4. Validation

- **FormRequests**: MANDATORY.
- **Location**: `app/Modules/{Domain}/Http/Requests/{Domain}/`.
- **Grouping**: Group by Domain Context, NOT by Actor.

## 5. Security & Authorization

- **Policies/Gates**: Handle "Who can do this" logic here, not in Actions.
- **Middleware**: Use for campus selection and broad access control.

## 6. Contracts

- **Mandatory Usage**:
    - Reading data from another module.
    - Critical/Stable business logic (GPA, Finance).
    - Checking state/status across modules.
    - External integrations (API/Mobile) reading core data.
- **Prohibited**:
    - Returning/Accepting Eloquent Models in Contracts.
    - Direct DB queries in API Controllers.
- **Location**: `app/Shared/Contracts/{Domain}/`.
