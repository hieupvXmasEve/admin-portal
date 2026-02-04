# Architecture Rules: Modular Monolith

## 1. Core Principles

- **Modular Monolith**: The system is divided into independent Modules (e.g., Identity, Academic, Finance) sharing a common data layer.
- **Independence**: One module must NOT depend directly on the internal logic of another module.
- **Shared Models**: `app/Models` contains shared Eloquent models (unless a module owns distinct data).
- **Actions over Services**: Business logic is concentrated in **Actions** (Single Use-Case).
- **Thin Controllers**: Controllers act only as Adapters: receive input, call Action/Query, return response.

## 2. Cross-Module Communication

- **Contract Mandatory**: Whenever Module A needs data or behavior from Module B, it **MUST** use a Contract.
- **No Direct Eloquent Joins**: Never use `join` between models of different modules.
- **Module Ownership**: Each DB table has **one owner module**. Others must read via Contract/API.

## 3. Directory Structure (Backend)

```text
app/Modules/{Domain}/
 ├─ Actions/             # Business Logic (Write/State change)
 ├─ Queries/             # Read Logic (Complex joins/Reports)
 ├─ Http/                # Communication Layer
 │   ├─ Web/             # Stateful (Inertia/Blade)
 │   │   ├─ Admin/       # Controllers for Admin Portal
 │   │   └─ Student/     # Controllers for Student Portal
 │   ├─ Api/             # Stateless (JSON)
 │   │   ├─ Admin/       # APIs for Admin FE
 │   │   ├─ Student/     # APIs for Student Portal
 │   │   └─ Lecturer/    # APIs for Lecturer Portal
 │   └─ Requests/        # Validation by Domain
 │       └─ {Domain}/    # e.g., Identity, Enrollment
 ├─ Policies/            # Authorization rules (Gates/Policies)
 ├─ Providers/           # Module ServiceProvider
 └─ routes/              # routes/web.php, api.php
```

## 4. Development Workflow

1. **Analyze**: Identify Module and Business Case (Action).
2. **Database**: Update `app/Models` and Migrations.
3. **Backend**:
    - Create `Action` in `app/Modules/{Module}/Actions`.
    - Create `FormRequest` for validation.
    - Create `Controller` as Adapter.
    - Register `Route`.
4. **Frontend**:
    - Create Page component.
    - Map Route-to-Page 1-1.
