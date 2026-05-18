# Current Story Pack: B5 — `UpdateNotificationTemplateRequest` FormRequest

**Epic:** B. Save-time guards
**Story:** B5
**Mode:** `standard_feature`
**Source:** [approach-p2.md](approach-p2.md) §3 + Risk Map "FormRequest variable-allow-list validation"
**CONTEXT.md decisions honored:** D8 (reject unknown `{{var}}`)

## Outcome

`UpdateNotificationTemplateRequest` validates subject + body_html shape, regex-scans
the saved body for `{{var}}` tokens, rejects ANY token not in
`NotificationTemplateTypeKey::availableVariables()` allow-list (D8), and runs
`Purifier::clean($body_html, 'email_body')` in `passedValidation()` BEFORE
the controller writes.

Bridges B1 (enum), B2 (purifier), B3 (policy) into the controller update flow
that A2 wires.

## Entry State

- B1 done: `NotificationTemplateTypeKey::availableVariables()` returns allow-list per case.
- B2 done: `Purifier` facade available with `email_body` config.
- B3 done: `NotificationTemplatePolicy` registered.
- No `app/Modules/Notification/Http/Requests/UpdateNotificationTemplateRequest.php` exists.

## Exit State

1. New `app/Modules/Notification/Http/Requests/UpdateNotificationTemplateRequest.php`:
   - `authorize(): bool` → `$this->user()->can('update', $this->route('template'))` (delegates to NotificationTemplatePolicy).
   - `rules(): array` →
     ```php
     [
       'subject' => ['required', 'string', 'max:500'],
       'body_html' => ['required', 'string', 'min:1', 'max:65535'],
     ]
     ```
   - `withValidator(Validator $v): void` — adds a closure check that scans subject + body_html for `{{(\w+)}}` regex, gathers all matched names, and adds an error on `body_html` (or `subject` depending where) if any are NOT in `$this->route('template')->type_key->availableVariables()` keys.
   - `passedValidation(): void` — `$this->merge(['body_html' => Purifier::clean($this->body_html, 'email_body')])`.
2. New `tests/Feature/Notification/Http/UpdateNotificationTemplateRequestTest.php` Pest:
   - **(a)** Valid request (only allow-list vars) → passes.
   - **(b)** Body with `{{unknown_var}}` → 422 with error field `body_html` mentioning `unknown_var`.
   - **(c)** Subject with `{{unknown_var}}` → 422 with error field `subject` mentioning `unknown_var`.
   - **(d)** Body with `<script>` → after `passedValidation()`, `$request->body_html` no longer contains `<script` (proves purifier wired).
   - **(e)** Non-super-admin user → 403 (proves Policy gate via authorize()).

## Files Likely Touched

| File | Action |
|---|---|
| `app/Modules/Notification/Http/Requests/UpdateNotificationTemplateRequest.php` | CREATE |
| `tests/Feature/Notification/Http/UpdateNotificationTemplateRequestTest.php` | CREATE |

**Total: 2 CREATE.**

## Feasibility Assumptions

| Assumption | Risk | Proof |
|---|---|---|
| `Validator::after` / `withValidator` works for cross-field allow-list scan | LOW | Standard Laravel pattern. |
| `Purifier::clean` works inside `passedValidation()` before controller writes | LOW | `passedValidation()` runs after validation; controller reads `$request->validated()` which reflects merged data. |
| `$this->route('template')` exposes the model-bound `NotificationEmailTemplate` | LOW | Standard route-model binding when route declares `{template}` and uses `NotificationEmailTemplate` model. A2 must declare this. B5 depends on A2's route shape — note as cross-story coupling. |

## Verification (Done-When)

1. `./scripts/dev.sh test tests/Feature/Notification/Http/UpdateNotificationTemplateRequestTest.php` → 5 cases green.
2. `./scripts/dev.sh test tests/Unit/Notification tests/Feature/Notification` → regression green.
3. `./scripts/dev.sh artisan about > /dev/null` exit 0.
4. Pint clean on touched files.

## Out of Scope

- The actual update endpoint (controller method) — that's A2.
- Route definition with `{template}` model binding — that's A1/A2.
- Subject sanitization (subject is plain text; Purifier on subject is overkill; D8 unknown-var rejection is enough).

## Bead Mapping

Pending validation. Depends on B1+B2+B3 done. ~30 min.
