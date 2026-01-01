---
name: review-architecture
description: Reviews code for compliance with the project's Modular Monolith architecture, strict boundary rules, and coding standards. Use before committing or when reviewing a PR.
allowed-tools: Read, Grep, Glob
---

# Review Architecture & Standards

This skill analyzes the codebase to ensure adherence to the project's strict Modular Monolith rules.

## Instructions

1.  **Analyze Scope**:
    -   If a specific file or directory is mentioned, focus on that.
    -   Otherwise, review the `app/Modules` directory generally or the user's current working files.

2.  **Check for Violations**:

    ### 1. Cross-Module Boundaries (Strict)
    -   **Rule**: Module A cannot import Models from Module B.
    -   **Check**: Grep for `use App\Modules\{OtherModule}\Models` inside `app/Modules/{CurrentModule}`.
    -   **Fix**: Suggest using a Shared Contract (`app/Shared/Contracts`).

    ### 2. Logic in Controllers
    -   **Rule**: Controllers must be thin adapters.
    -   **Check**: Look for complex logic (loops, calculations, direct DB transactions) inside `*Controller.php`.
    -   **Fix**: Move logic to an Action (`Create...Action`) or Query.

    ### 3. Inertia Frontend Rules
    -   **Rule**: Pages should not call API endpoints directly.
    -   **Check**: Grep for `axios.`, `fetch(`, or `useApi` inside `resources/js/Pages`.
    -   **Fix**: Pass data as Props from the Controller.

    ### 4. Naming Conventions
    -   **Actions**: Must end in `Action` (e.g., `RegisterStudentAction`).
    -   **Contracts**: `{Noun}{Verb}{Purpose}` (e.g., `StudentAcademicReader`).
    -   **Modules**: PascalCase, Singular.

3.  **Report**:
    -   List any violations found with file paths.
    -   Reference the specific rule from `docs/rules/CODING_RULES.md` or `docs/rules/CONTRACT_RULES.md`.
    -   Provide a concrete recommendation for fixing it.

## Example Checks

**1. Check for Cross-Module Model Usage:**
```bash
grep -r "use App\\\\Modules\\\\.*\\\\Models" app/Modules
```
*Analyze the output to ensure a module isn't importing another module's models.*

**2. Check for Logic in Controllers:**
```bash
grep -r "DB::transaction" app/Http/Controllers
grep -r "foreach" app/Http/Controllers
```
*Controllers should delegate to Actions.*

**3. Check for API calls in Vue Pages:**
```bash
grep -r "axios\." resources/js/Pages
```
*Inertia pages should receive data via props, not fetch it.*
