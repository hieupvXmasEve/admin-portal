# Code Styles & Coding Rules

This document outlines the coding standards and architectural rules for this project (Laravel 12 + Vue 3 + InertiaJS Modular Monolith).

## 1. Overall Rules (Mandatory)

### R1. No Business Logic in Controllers
- Controllers are only Adapters.
- Business logic belongs in **Actions** (Write operations) or **Queries** (Read operations).

### R2. Backend-Driven Business Logic
- The Frontend should not decide business logic.
- Return status/state from the Backend; the Frontend only displays UI based on that state.

### R3. Module Ownership
- Each database table has **one owner module**.
- Other modules must access it as read-only or through the owner module's interface/API.

### R4. Inertia Pages & API Calls
- Inertia Pages should NOT call APIs directly via axios/fetch.
- Prefer passing data through Props from the Controller.

### R5. No Cross-Module Eloquent Joins
- Never use Eloquent `join` between Models belonging to different modules.
- Use Query Builders or separate queries if cross-module data is needed.

### R6. Business-Centric Actions
- Divide Actions by Business Use-case, NOT by Actor (e.g., use `UpdateStudentProfileAction` instead of separate actions for Student and Admin).
- Authorization is handled in **Policies/Gates**.

## 2. Naming Conventions - Backend

### 2.1 Modules
- PascalCase, Singular (e.g., `Academic`, `Finance`, `Identity`).

### 2.2 Models
- Singular, PascalCase (e.g., `Student`, `CourseOffering`).
- Avoid prefixes like `Tbl` or suffixes like `Entity`.

### 2.3 Controllers
- Web (Stateful): `Http/Web/Admin/{Entity}Controller.php`
- Api (Stateless): `Http/Api/Student/{Entity}Controller.php`

### 2.4 Actions (Business Logic)
- **Format:** `Verb + [Entity] + Action` (e.g., `CreateEventAction`, `UpdateStudentProfileAction`).
- **Entry point:** Must use a static `run(array $data)` method.

### 2.5 Queries (Read-only)
- **Format:** `[Verb/Get] + Entity + [Context] + Query` (e.g., `ListAcademicRecordsQuery`).
- **Entry point:** Must use a `handle(...$args)` method.

## 3. Route Naming

### 3.1 Web Routes
- **Format:** `{module}.{resource}.{action}` (e.g., `identity.login.show`).

### 3.2 API Routes
- **Example:** `/api/v1/student/auth/login`.

## 4. Frontend Conventions (Vue)

### 4.1 Page Naming
- 1-1 mapping with Routes (e.g., `Academic/Records/Index.vue`).

### 4.2 Component Naming
- PascalCase (e.g., `RecordTable.vue`). Avoid generic names like `Table.vue`.

### 4.3 Composables
- Use `use` prefix (e.g., `useInertiaFilters.ts`).
- Do not put complex business logic in Composables.

## 5. Validation & Error Handling

- **Validation:** Must reside in **Form Requests**, not in Actions or Controllers.
- **Error Handling:** Actions throw **Domain Exceptions**. Controllers catch them and return appropriate responses.
