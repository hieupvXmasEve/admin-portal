---
paths: '**/*.php'
---

**Objective:**
Strictly follow the `Action-Request-Resource Pattern` when implementing or modifying any module. Ensure the codebase is well-structured, maintainable, and scalable.

## Required Steps for Each Module (e.g., `Room`)

### 1. Prepare the Structure

Ensure the following directories exist:

- `app/Modules/<Domain>/Actions`
- `app/Modules/<Domain>/Http/Web`
- `app/Modules/<Domain>/Http/Api`
- `app/Modules/<Domain>/Http/Requests/<Domain>`
- `app/Http/Resources/<Domain>`

### 2. Implement the Business Logic (Actions)

**Create an Action for each use-case.**

- Location: `app/Modules/<Domain>/Actions/<Verb><Entity>Action.php`
- Example: `CreateRoomAction.php`, `BookRoomAction.php`.
- **Responsibility:**
    - Execute the single business logic unit.
    - Interact with Models.
    - Use services for technical support (e.g., `FileService` for uploads).
    - Throw exceptions for errors.

### 3. Implement Validation (Form Request)

- Location: `app/Modules/<Domain>/Http/Requests/<Domain>/*.php`
- Create specific requests for actions (e.g., `StoreRoomRequest`, `UpdateRoomRequest`).
- Ensure validation rules are strict.

### 4. Implement API Resources

- Location: `app/Http/Resources/<Domain>/*.php`
- Create resources to transform models into JSON responses.

### 5. Implement Controllers

**Web Controller** (`app/Modules/<Domain>/Http/Web/Admin/<Entity>Controller.php`)

- **Use**: FormRequests.
- **Call**: The appropriate `Action`.
- **Return**: Inertia response (`Inertia::render(...)`).

**API Controller** (`app/Modules/<Domain>/Http/Api/Admin/<Entity>Controller.php`)

- **Use**: FormRequests.
- **Call**: The appropriate `Action`.
- **Return**: JSON response via `Resource`.

### 6. Configure Routes

- **Web Routes:** Use Web controller.
- **API Routes:** Use API controller, wrap in `Route::apiResource` or specific groups.

## Key Rules

### Core Rule: Actions are the single source of truth.

- **Actions** represent application use-cases.
- **Controllers** are adapters (Web / API) that call Actions.
- **Services** provide technical support, NOT business decisions.

### Rules for Actions (MANDATORY)

- One file = one use-case
- Naming: `Verb + Entity + Action`
- Does NOT return HTTP responses.
- Does NOT know about controllers.

### Rules for Controllers

- Controllers MUST be thin.
- Controllers MUST NOT contain business logic.
- Controllers MUST NOT call other controllers.
- Web and API controllers MAY call the same Action.

Whenever you are asked to create or modify a module, you must follow this exact sequence. If any step is missing, automatically complete it or ask for clarification if needed.
