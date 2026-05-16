# Current Story Pack: B1 — Variable allow-list relocation to enum

**Epic:** B. Save-time guards
**Story id:** B1
**Mode:** `standard_feature`
**Phase:** P2
**Source:** [epic-map-p2.md](epic-map-p2.md), [approach-p2.md](approach-p2.md), [CONTEXT.md](CONTEXT.md) D8

## Outcome

`NotificationTemplateTypeKey` enum becomes the single source of truth for the variable
allow-list of each of the 4 notification email types. The dedicated
`EmailVariableSchema` interface and its 4 implementations on legacy `*EmailContent.php`
classes are retired. Every downstream P2 consumer (B5 FormRequest validator, A2 admin
variables endpoint, A3 Vue editor variable picker, C1 preview sample data) reads from
the enum.

This story unblocks the largest downstream fan-out in P2 and resolves deferred bead
**REV-P2-05** (variable-schema lives on classes scheduled for deletion in the 30-day
cleanup task).

## Entry State

Observable truth before B1 starts (post-P1, commit `07b6a3cf` on `dev`):

- `app/Modules/Notification/EmailContent/Contracts/EmailVariableSchema.php` exists, declares
  `availableVariables(): array<string, array{label:string, sample:mixed}>`.
- 4 legacy classes implement BOTH `EmailContentProvider` AND `EmailVariableSchema`:
  - `app/Modules/Notification/EmailContent/Types/PaymentReminderEmailContent.php`
  - `app/Modules/Notification/EmailContent/Types/ParentPaymentReminderEmailContent.php`
  - `app/Modules/Notification/EmailContent/Types/DngPaymentPushedEmailContent.php`
  - `app/Modules/Notification/EmailContent/Types/DngPaymentReceivedEmailContent.php`
- Each class has an `availableVariables()` method returning the per-type allow-list with
  sample data (e.g. PaymentReminder has student_name/student_code/semester_code/
  invoice_code/balance_formatted/due_date).
- `app/Modules/Notification/Enums/NotificationTemplateTypeKey.php` is a 4-case backed
  enum with no methods.
- `tests/Unit/Notification/EmailContent/EmailVariableSchemaConsistencyTest.php` (81 lines)
  asserts via the legacy classes that schema keys match `$data` keys actually consumed
  in each class's `htmlBody()`.

## Exit State

Testable truth after B1 completes:

1. `App\Modules\Notification\Enums\NotificationTemplateTypeKey` exposes public method
   `availableVariables(): array<string, array{label: string, sample: mixed}>`. Returning
   the same allow-list per case as the legacy classes did pre-relocation (byte-for-byte
   on keys; sample values preserved verbatim).
2. The 4 `*EmailContent.php` classes:
   - NO LONGER declare `implements EmailContentProvider, EmailVariableSchema` —
     they go back to `implements EmailContentProvider` only.
   - NO LONGER have the `availableVariables()` method body.
   - All other methods (`subject()`, `htmlBody()`, `textBody()`) unchanged byte-for-byte.
3. `app/Modules/Notification/EmailContent/Contracts/EmailVariableSchema.php` is DELETED.
4. Test file renamed from `EmailVariableSchemaConsistencyTest.php` to
   `NotificationTemplateTypeKeyVariablesTest.php`. The new test asserts:
   - For each of the 4 enum cases, `case->availableVariables()` returns the expected
     allow-list keys (compare against an inline `expect` array).
   - Each entry has both a non-empty `label` (string) and a non-null `sample`.
   - The sample values are reasonable (e.g. `student_name` sample is the expected
     "Nguyễn Văn A" used in S1.3).
5. `./scripts/dev.sh test tests/Unit/Notification` is green.
6. `./scripts/dev.sh test tests/Feature/Notification/EmailContent` is green (parity
   test still passes — it does not touch the variable-schema surface).
7. `docker exec swinx-app-dev vendor/bin/pint --test <touched files>` is clean.
8. `grep -r 'EmailVariableSchema' app tests` returns ZERO matches (interface fully
   retired; no orphaned references).

## Files Likely Touched

Disjoint write surface; one worker can complete cold.

| File | Action |
|---|---|
| `app/Modules/Notification/Enums/NotificationTemplateTypeKey.php` | EDIT — (a) add `availableVariables(): array` method that switches on `$this` and returns the allow-list (4 branches); (b) update the class-level docblock at line 16 to remove the retired reference "Adding an EmailVariableSchema implementation for the new key" — replace with "Adding a case to `availableVariables()` for the new key". |
| `app/Modules/Notification/EmailContent/Types/PaymentReminderEmailContent.php` | EDIT — drop `EmailVariableSchema` from `implements`; remove `availableVariables()` method + its import; keep everything else |
| `app/Modules/Notification/EmailContent/Types/ParentPaymentReminderEmailContent.php` | EDIT — same as above |
| `app/Modules/Notification/EmailContent/Types/DngPaymentPushedEmailContent.php` | EDIT — same as above |
| `app/Modules/Notification/EmailContent/Types/DngPaymentReceivedEmailContent.php` | EDIT — same as above |
| `app/Modules/Notification/EmailContent/Contracts/EmailVariableSchema.php` | DELETE |
| `tests/Unit/Notification/EmailContent/EmailVariableSchemaConsistencyTest.php` | DELETE (replaced) |
| `tests/Unit/Notification/Enums/NotificationTemplateTypeKeyVariablesTest.php` | CREATE — Pest test asserting enum's `availableVariables()` per case |

**Total: 6 EDIT + 1 DELETE-file + 1 DELETE-file + 1 CREATE = 9 file ops.**

## Feasibility Assumptions

| Assumption | Risk | Proof needed |
|---|---|---|
| **No outside consumer of `EmailVariableSchema`.** P1 only just introduced the interface (commit `07b6a3cf`); only the 4 legacy classes implement it; only the consistency test reads it. | LOW | `grep -r 'EmailVariableSchema' app tests config routes` — should return only the interface file + 4 class files + 1 test file. Validating Q4. |
| **PHP 8.3 backed enum methods are supported.** | LOW | `composer.json` declares `"php": "^8.3"`. PHP 8.1+ supports methods on backed enums; 8.3 supports them with the `match($this)` pattern. No proof needed beyond reading the version constraint. |
| **Method-body relocation does not change any sample value or label.** | LOW | The new test asserts each case's allow-list keys + sample values. Diff the new test fixture against the legacy class bodies before deleting. |
| **Pest test rename does not affect Pest auto-discovery.** | LOW | Pest discovers anything under `tests/` matching its config. Rename = new file path = Pest re-indexes on next run. |

No MEDIUM or HIGH risks. No spike required.

## Verification (Done-When Sequence)

Worker runs these in order:

1. **Static guard:** `grep -r 'EmailVariableSchema' app tests config routes` returns zero
   matches AFTER the edits.
2. **Pint:** `docker exec swinx-app-dev vendor/bin/pint --test app/Modules/Notification/Enums/NotificationTemplateTypeKey.php app/Modules/Notification/EmailContent/Types/PaymentReminderEmailContent.php app/Modules/Notification/EmailContent/Types/ParentPaymentReminderEmailContent.php app/Modules/Notification/EmailContent/Types/DngPaymentPushedEmailContent.php app/Modules/Notification/EmailContent/Types/DngPaymentReceivedEmailContent.php tests/Unit/Notification/Enums/NotificationTemplateTypeKeyVariablesTest.php` shows zero style issues.
3. **New enum test:** `./scripts/dev.sh test tests/Unit/Notification/Enums/NotificationTemplateTypeKeyVariablesTest.php` green for all 4 cases.
4. **Notification unit suite:** `./scripts/dev.sh test tests/Unit/Notification` green.
5. **Email-content feature suite:** `./scripts/dev.sh test tests/Feature/Notification/EmailContent` green (parity + provider tests must still pass — they don't read schema).
6. **No PHP fatal:** `./scripts/dev.sh artisan about > /dev/null` (or `tinker --execute='echo "ok";'`) exits 0 — sanity check that no missing-class error blows up boot.

If any step fails, do NOT progress. Re-read the contract.

## Out of Scope

- **Every other P2 story** (B2 sanitizer, B3 policy, B4 Octane, B5 FormRequest, A1-A3 UI,
  C1-C2 operator tools, D1-D3 cleanup) — they consume B1's enum method but their work
  is separate.
- **Renaming the enum or its cases.** Cases stay `PaymentReminder` etc.; only adds a
  method.
- **Deleting the legacy `*EmailContent.php` classes.** Their `subject()/htmlBody()/textBody()`
  methods remain — they are still the feature-flag fallback target until sunset.
- **Touching `DbEmailContentProvider` or `EmailContentRegistry`.** Neither reads the
  schema interface today; B1 does not need to.

## Bead Mapping

**Status: pending validation.** No bead created yet. After `khuym:validating` clears
the 5 feasibility questions in [approach-p2.md](approach-p2.md), planning's bead-creation
pass produces a single execution bead for B1 (one worker, ~30 min, mechanical refactor):

```text
BR-<epic-id>-B1   relocate availableVariables() to NotificationTemplateTypeKey enum
```

No dependency edges (B1 has no story prerequisites). Provisional file ownership matches
the table above. Acceptance criteria = the 8 Exit-State assertions in this pack.
