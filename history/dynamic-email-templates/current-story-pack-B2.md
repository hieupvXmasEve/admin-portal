# Current Story Pack: B2 — `mews/purifier` install + `email_body` config + round-trip test

**Epic:** B. Save-time guards
**Story:** B2
**Mode:** `standard_feature`
**Source:** [approach-p2.md](approach-p2.md) §3 + Risk Map row "Sanitizer config preserves email-client styles"
**Critical Pattern honored:** #1 (parity tests need unsafe-character fixtures)

## Outcome

`mews/purifier ^3.4` installed; `config/purifier.php` ships with an `email_body`
allow-list that preserves inline `style=` attributes (table emails) but strips
script/javascript/event-handler injections. Round-trip snapshot test on the 4
seeded bodies + an unsafe-fixture test cover both directions.

Foundation for B5 (FormRequest sanitization in `passedValidation()`).

## Entry State

- `composer.json` has no `mews/purifier` entry.
- `config/purifier.php` does not exist.
- `composer.lock` already has `ezyang/htmlpurifier` (transitive dep — fine; it's the underlying engine).
- `config/notifications.php` exists but does not reference purifier.

## Exit State

1. `composer require mews/purifier:^3.4` ran cleanly; `composer.json:require` lists it.
2. `composer.lock` regenerated.
3. `config/purifier.php` exists with the **default `default` profile** untouched + a new `email_body` profile containing:
   - `HTML.Allowed` = `p,br,strong,b,em,i,u,s,a[href|target|rel|style],img[src|alt|width|height|style],ul,ol,li,table,thead,tbody,tr,th[style|colspan|rowspan],td[style|colspan|rowspan],blockquote,hr,h1[style],h2[style],h3[style],span[style],div[style]`
   - `CSS.AllowedProperties` = `color,background-color,font-family,font-size,font-weight,font-style,text-align,text-decoration,line-height,margin,margin-top,margin-bottom,margin-left,margin-right,padding,padding-top,padding-bottom,padding-left,padding-right,border,border-collapse,border-color,border-style,border-width,border-left,border-right,border-top,border-bottom,width,height,max-width`
   - `AutoFormat.RemoveEmpty` = `false`
   - `Attr.AllowedFrameTargets` = `['_blank']`
4. New `tests/Feature/Notification/EmailContent/SanitizerRoundtripTest.php` Pest test:
   - **(a)** Round-trip parity per type_key: for each of the 4 seeded `notification_email_templates` rows, `Purifier::clean($row->body_html, 'email_body')` byte-equals the original (whitespace-normalised). Proves no inline style/structure is stripped.
   - **(b)** XSS strip: a fixture body containing `<script>alert(1)</script>` + `<a href="javascript:alert(1)">x</a>` + `<img src=x onerror=alert(1)>` is sanitized to a version with `script`/`javascript:`/`onerror` removed. Asserts the SANITIZED output **does not** contain `<script` (case-insensitive), `javascript:`, or `onerror=`.
5. `./scripts/dev.sh test tests/Feature/Notification/EmailContent/SanitizerRoundtripTest.php` green.
6. `./scripts/dev.sh artisan about > /dev/null` exit 0.
7. `docker exec swinx-app-dev vendor/bin/pint --test config/purifier.php tests/Feature/Notification/EmailContent/SanitizerRoundtripTest.php` clean.

## Files Likely Touched

| File | Action |
|---|---|
| `composer.json` + `composer.lock` | EDIT via `composer require mews/purifier:^3.4` (do NOT hand-edit) |
| `config/purifier.php` | CREATE (publish vendor config, then edit to add `email_body` profile) |
| `tests/Feature/Notification/EmailContent/SanitizerRoundtripTest.php` | CREATE — Pest test with cases (a) + (b) |

**Total: 1 composer command + 1 config CREATE + 1 test CREATE = 3 file ops (composer.lock is generated).**

## Feasibility Assumptions

| Assumption | Risk | Proof |
|---|---|---|
| `mews/purifier 3.4.x` supports Laravel 13 + PHP 8.3 | LOW | Verified in P1 (validation-report.md Q3). |
| Vendor publish target name is `config` tag | LOW | Standard Mews convention: `vendor:publish --provider="Mews\Purifier\PurifierServiceProvider"`. |
| Round-trip parity holds on all 4 seeded bodies | MEDIUM | The four legacy heredocs use `<table>` with inline `style=` — must survive. Snapshot test is the proof. |
| Sanitizer removes `javascript:` URI in `href` | LOW | HTMLPurifier strips dangerous URI schemes by default (URI.AllowedSchemes constraint). |

## Verification (Done-When)

1. `composer show mews/purifier` returns `^3.4.x`.
2. `config/purifier.php` exists; `php artisan tinker --execute='echo isset(config("purifier.settings.email_body")) ? "EXISTS" : "MISSING";'` → `EXISTS`.
3. `./scripts/dev.sh test tests/Feature/Notification/EmailContent/SanitizerRoundtripTest.php` → 2 cases green.
4. `./scripts/dev.sh test tests/Feature/Notification tests/Unit/Notification` → regression green (no B1/B4 break).
5. `docker exec swinx-app-dev vendor/bin/pint --test config/purifier.php tests/Feature/Notification/EmailContent/SanitizerRoundtripTest.php` clean.
6. `./scripts/dev.sh artisan about > /dev/null` exit 0.

## Out of Scope

- B5 wiring (Purifier::clean inside `passedValidation()`) — that's B5.
- Touching the admin UI editor — that's A-batch.
- Renaming the existing `default` Purifier profile.

## Bead Mapping

Pending validation. After validating clears B2, one execution bead. ~30 min.
