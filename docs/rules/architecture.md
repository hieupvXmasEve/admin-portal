# Architecture Rules: Modular Monolith

## 1. Core Principles

- **Modular Monolith**: The system is divided into independent Modules (e.g., Identity, Academic, Finance, Notification) sharing a common data layer.
- **Independence**: One module must NOT depend directly on the internal logic of another module.
- **Shared Models**: `app/Models` contains shared Eloquent models (unless a module owns distinct data). **Exception — bounded contexts (ADR-0026):** Academic and Finance do **not** share money/lifecycle Eloquent models. Money models (`FinanceCharge`, `Payment`, `StudentInvoice`, `InvoiceLine`, …) are Finance-owned and move to `App\Modules\Finance\Models`; the only cross-context shared model is `Student` as a Shared Kernel **identity reference**. Cross-context access goes through `app/Shared/Contracts/*`, never a shared money model or an Eloquent join.
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
2. **Database**: Update the **owning module's** model + migration. Use `app/Models` only for a Shared Kernel identity (`Student`) or legacy not-yet-relocated models — never to add a new cross-context shared money/lifecycle model (ADR-0026).
3. **Backend**:
    - Create `Action` in `app/Modules/{Module}/Actions`.
    - Create `FormRequest` for validation.
    - Create `Controller` as Adapter.
    - Register `Route`.
4. **Frontend**:
    - Create Page component.
    - Map Route-to-Page 1-1.

## 5. Notification Module Rules (Phase 1 Foundation)

- Notification domain logic must stay in `app/Modules/Notification/*`.
- Domain events must be persisted to outbox first (`notification_event_outbox`) and processed through the outbox pipeline.
- Canonical notification recipient is `recipient_user_id`; target types like Student/Lecture must resolve to user id before persistence.
- Campus boundaries are strict: resolve and persist within the same `campus_id` scope only.
- Phase 1 is clean-slate V2 storage (`notification_event_outbox`, `notification_messages`, `notification_deliveries`) with no legacy `notifications` backfill.
- Use `notifications:process-outbox` for dispatch operations.
