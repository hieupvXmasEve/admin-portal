---
paths: "**/*.{php,vue,js,ts}"
---

# Security & Authorization Rules

## 1. Core Concept
- **Gate**: Permission-level check. Protects the "Door".
- **Policy**: Data-level check. Protects the "Record".
- **Rule**: Do not mix them.

## 2. When to Use What?

| Scenario | Use | Reasoning |
| :--- | :--- | :--- |
| Route has **NO** `{id}` | **Gate** | Checking generic permission (e.g. "Can view list?") |
| Route **HAS** `{id}` | **Policy** | Checking specific object (e.g. "Can edit *this* event?") |
| Menu / Sidebar | **Gate** | UI visibility |
| Controller | **Policy** | Controller acts on specific data |

## 3. Implementation Rules

### 3.1 Gates
- **Purpose**: Check if user has a permission (e.g., `event.create`).
- **Usage**: Route middleware, Menu checks.
- **Prohibited**:
    - Querying the DB.
    - Accepting a Model.
    - Complex business logic.

### 3.2 Policies
- **Purpose**: Check if user can perform action on a *specific* model instance.
- **Usage**: Route middleware (`can:` with model), Controller `$this->authorize()`.
- **Allowed**: Calling Gates, checking business rules, checking ownership.

### 3.3 Controllers
- **Rule**: Controllers MUST use `$this->authorize('action', $model)` for actions on specific resources.
- **Prohibited**: Controllers should NOT call `Gate::allows()` directly for resource actions.

## 4. Workflow
1.  **User Login** -> Resolve Permissions.
2.  **Route (Middleware)** -> **Gate** checks generic access.
3.  **Controller** -> **Policy** checks specific object access.

## 5. Review Checklist
- [ ] Does the Route with `{id}` have a Policy check?
- [ ] Does the Policy check data ownership/state (not just permission)?
- [ ] Are Gates simple permission checks without DB queries?
