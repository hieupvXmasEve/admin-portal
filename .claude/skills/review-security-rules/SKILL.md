---
name: review-security-rules
description: Reviews code for security vulnerabilities and adherence to the project's Gate vs Policy authorization rules. Use before committing changes involving permissions or routes.
allowed-tools: Read, Grep, Glob
---

# Review Security & Authorization

This skill audits code for common security issues and strict adherence to the project's Authorization Rules (`docs/rules/authorization.md`).

## Instructions

1.  **Analyze Scope**:
    -   Focus on `routes/`, `Controllers`, `Policies`, and `Gates`.

2.  **Check for Authorization Violations**:

    ### 1. Controller Authorization
    -   **Rule**: Controllers must **NEVER** call `Gate::allows()`. They must use `$this->authorize()` (Policy).
    -   **Check**: Grep `Gate::` inside `*Controller.php`.
    -   **Fix**: Replace with `$this->authorize('action', $model)`.

    ### 2. Route Authorization
    -   **Rule**: Routes with IDs (e.g., `/events/{event}`) must NOT use `can:` middleware.
    -   **Reason**: Middleware cannot check specific object ownership; Policy must do it.
    -   **Check**: Look for `Route::...->middleware('can:...')` on routes with parameters.
    -   **Fix**: Remove middleware, add `$this->authorize()` in the Controller method.

    ### 3. Gate Definitions
    -   **Rule**: Gates must NOT accept Models or query the DB.
    -   **Check**: Review `AuthServiceProvider` or where Gates are defined.
    -   **Fix**: Gates should only check permissions (e.g., `$user->hasPermission(...)`).

    ### 4. Policy Implementation
    -   **Rule**: Policies must check **Data Rules** (ownership, status), not just permissions.
    -   **Check**: `*Policy.php` methods.
    -   **Fix**: Ensure `$user->id === $model->user_id` or similar logic exists.

3.  **Report**:
    -   List violations with file paths and line numbers.
    -   Cite the specific rule from `docs/rules/authorization.md`.

## Example Checks

**1. Controller using Gate (Forbidden):**
```bash
grep -r "Gate::" app/Http/Controllers
```

**2. Route with ID using Middleware (Forbidden):**
```bash
# Manual check of route files
# Look for: Route::get('/{id}', ...)->middleware('can:...')
```

**3. Policy missing Data Check (Suspicious):**
```php
// Suspicious: Only checks permission
public function update(User $user, Post $post) {
    return $user->can('update_posts');
}
// Correct: Checks permission AND ownership
public function update(User $user, Post $post) {
    return $user->can('update_posts') && $post->user_id === $user->id;
}
```
