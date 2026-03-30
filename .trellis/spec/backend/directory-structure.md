# Directory Structure

> How backend code is organized in this project.

---

## Overview

This repo uses a hybrid Laravel 12 backend:

- Newer domain work prefers `app/Modules/{Domain}`.
- Shared or legacy business logic still exists in `app/Services`, `app/Models`, and `app/Http`.
- Global routing, middleware aliases, and exception rendering live in `bootstrap/app.php`.

Document reality, not ideals: new code should usually follow module structure, but you must check nearby code before moving a feature into a module.

---

## Directory Layout

```text
app/
├── Http/
│   ├── Controllers/
│   ├── Middleware/
│   ├── Requests/
│   └── Responses/
├── Models/
├── Modules/
│   ├── Academic/
│   ├── Finance/
│   ├── Identity/
│   └── Notification/
└── Services/

bootstrap/
├── app.php
└── providers.php

routes/
├── api.php
├── console.php
├── web.php
└── web/
```

---

## Module Organization

- Common module subfolders: `Actions`, `Queries`, `Http`, `Providers`, `routes`, `Services`, `Support`.
- Controllers are adapters only: validate/auth -> call Action or Query -> return Inertia/JSON response.
- Query classes usually expose `handle(...)`; action classes often expose `run(...)`.
- Module service providers load module route files and bind services.
- Keep shared framework plumbing in normal Laravel folders unless the feature already lives inside a module.

---

## Naming Conventions

- PHP classes: PascalCase.
- Domains/modules: singular PascalCase (`Finance`, `Academic`, `Identity`).
- Route names: dot notation.
- URL segments: kebab-case.
- Query classes: verb-oriented read names such as `ListPaymentsQuery`.
- Action classes: verb-oriented mutation names such as `RecordStudentActionAction`.

---

## Examples

- Bootstrap and global middleware/exception wiring: `bootstrap/app.php`
- Module route/provider pattern: `app/Modules/Finance/Providers/FinanceServiceProvider.php`
- Module web route cluster: `app/Modules/Academic/routes/web.php`

## Anti-Patterns

- Do not invent new top-level backend folders.
- Do not move legacy code into modules just for consistency unless the task requires it.
- Do not assume route registration lives in one place; check `bootstrap/app.php`, `routes/*`, and module providers first.
