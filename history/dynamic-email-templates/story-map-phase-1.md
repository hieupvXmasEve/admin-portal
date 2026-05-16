# Story Map: Phase 1 - DB-Backed Render Parity

**Phase contract:** [phase-1-contract.md](phase-1-contract.md)
**Validation:** [validation-report.md](validation-report.md) (READY w/ constraints Q1/Q3/Q5)

## Dependency Diagram

```text
                    Entry State
                         |
                         v
                    +---------+
                    |  S1.1   |  CREATE TABLE migration
                    +---------+
                         |
                         v
                    +---------+
                    |  S1.2   |  Model + trait + enum
                    +---------+
                       /     \
                      v       v
                +---------+ +---------+
                |  S1.3   | |  S1.4   |  (parallel - no file overlap)
                +---------+ +---------+
                       \     /
                        v   v
                    +---------+
                    |  S1.5   |  DbEmailContentProvider + registry rebind + flag
                    +---------+
                         |
                         v
                    +---------+
                    |  S1.6   |  campus_id injection in 5 call sites
                    +---------+
                         |
                         v
                    +---------+
                    |  S1.7   |  parity test - exit proof
                    +---------+
                         |
                         v
                    Exit State (13 assertions)
```

Critical path length: 6 stories (S1.1 -> S1.2 -> S1.4 -> S1.5 -> S1.6 -> S1.7).
Parallel-eligible pair: S1.3 with S1.4 (after S1.2). Realistic swarm width = 2.

## Story Table

| Story | Outcome | Contributes To | Creates | Done When |
|---|---|---|---|---|
| **S1.1** | Storage exists | Exit #1 | `database/migrations/2026_05_*_create_notification_email_templates_table.php` | `\Schema::hasTable('notification_email_templates')` true; UNIQUE `(campus_id, type_key)` + INDEX `(type_key)` present; FK `campus_id` -> `campuses.id` and FK `updated_by_user_id` -> `users.id` (nullable, ON DELETE SET NULL). |
| **S1.2** | Model + render trait + enum | Exit #3, #4, #5 | `app/Modules/Notification/Models/NotificationEmailTemplate.php`; `app/Modules/Notification/Concerns/HasTemplateRendering.php`; `app/Modules/Notification/Enums/NotificationTemplateTypeKey.php`; **edits** `app/Models/EmailTemplate.php` to consume the trait (replacing private `renderContent()`). | `NotificationEmailTemplateRenderTest` (new) passes; `EmailTemplateTest` (existing, if present) still passes; `vendor/bin/pint` clean on the 4 files. |
| **S1.3** | Variable allow-list contract | Exit #6 | `app/Modules/Notification/EmailContent/Contracts/EmailVariableSchema.php`; **edits** the 4 files in `app/Modules/Notification/EmailContent/Types/*EmailContent.php` to implement `availableVariables()`. | Unit test: `availableVariables()` keys for each type match the actual `$data[...]` keys referenced in that type's `htmlBody()`; matches allow-list table in [discovery.md](discovery.md). |
| **S1.4** | Seeded rows per campus per type | Exit #2 | `database/migrations/2026_05_*_seed_notification_email_templates.php` (data migration); helper `database/seeders/NotificationEmailTemplateSeeder.php` if extracted. | After `--fresh --seed`: `count() == campuses.count() * 4`; subject + body contain mustache placeholders (e.g. `{{student_name}}`); re-running migration adds zero rows; `[Asia Việt Nam]` prefix present for the Asia campus rows. |
| **S1.5** | Provider swap + flag + memo | Exit #7, #8, #9 | `app/Modules/Notification/EmailContent/Types/DbEmailContentProvider.php`; `config/notifications.php`; **edits** `app/Modules/Notification/EmailContent/EmailContentRegistry.php` (binding + flag check); **edits** `app/Modules/Notification/Providers/NotificationServiceProvider.php` if needed for the binding switch. | Feature test `DbEmailContentProviderTest` proves: (a) flag `true` -> `EmailContentRegistry::resolve(key)` returns `DbEmailContentProvider`; (b) flag `false` -> returns legacy class; (c) two consecutive `htmlBody(['campus_id'=>X,...])` calls hit DB once (memo); (d) different `campus_id` hits DB twice. |
| **S1.6** | `campus_id` reaches the provider | Exit #10, #11 | **edits** `app/Modules/Notification/Actions/HandleOutboxEventAction.php` (line 145-167 area); **edits** `app/Modules/Finance/Actions/Operations/SendPaymentRemindersAction.php`, `SendParentPaymentRemindersAction.php`, `SendDueItemRemindersAction.php`, `SendDueItemParentRemindersAction.php`. | Existing feature tests for each Action still pass; new assertion in each test: the resolved provider was called with a `$data` array containing `'campus_id' => <expected>`. |
| **S1.7** | Parity proof | Exit #12 | `tests/Feature/Notification/EmailContent/DbEmailContentParityTest.php`; fixtures under `tests/Fixtures/Notification/EmailContent/` (one frozen `$data` array per type_key + golden HTML snapshot if needed). | All 4 type_keys pass: `assertEquals($legacy->htmlBody($data), $db->render($data)['html'])` after whitespace normalisation. `./scripts/dev.sh test` runs this test in CI. |

## File Ownership Map

Each story has a disjoint write surface. No two stories overlap on the same file.

| File | Owned by |
|---|---|
| `database/migrations/2026_05_*_create_notification_email_templates_table.php` | S1.1 |
| `database/migrations/2026_05_*_seed_notification_email_templates.php` | S1.4 |
| `database/seeders/NotificationEmailTemplateSeeder.php` (optional) | S1.4 |
| `app/Modules/Notification/Models/NotificationEmailTemplate.php` | S1.2 |
| `app/Modules/Notification/Concerns/HasTemplateRendering.php` | S1.2 |
| `app/Modules/Notification/Enums/NotificationTemplateTypeKey.php` | S1.2 |
| `app/Models/EmailTemplate.php` | S1.2 (trait consumption only) |
| `app/Modules/Notification/EmailContent/Contracts/EmailVariableSchema.php` | S1.3 |
| `app/Modules/Notification/EmailContent/Types/PaymentReminderEmailContent.php` | S1.3 |
| `app/Modules/Notification/EmailContent/Types/ParentPaymentReminderEmailContent.php` | S1.3 |
| `app/Modules/Notification/EmailContent/Types/DngPaymentPushedEmailContent.php` | S1.3 |
| `app/Modules/Notification/EmailContent/Types/DngPaymentReceivedEmailContent.php` | S1.3 |
| `app/Modules/Notification/EmailContent/Types/DbEmailContentProvider.php` | S1.5 |
| `app/Modules/Notification/EmailContent/EmailContentRegistry.php` | S1.5 |
| `app/Modules/Notification/Providers/NotificationServiceProvider.php` | S1.5 |
| `config/notifications.php` | S1.5 |
| `app/Modules/Notification/Actions/HandleOutboxEventAction.php` | S1.6 |
| `app/Modules/Finance/Actions/Operations/SendPaymentRemindersAction.php` | S1.6 |
| `app/Modules/Finance/Actions/Operations/SendParentPaymentRemindersAction.php` | S1.6 |
| `app/Modules/Finance/Actions/Operations/SendDueItemRemindersAction.php` | S1.6 |
| `app/Modules/Finance/Actions/Operations/SendDueItemParentRemindersAction.php` | S1.6 |
| `tests/Unit/Notification/Models/NotificationEmailTemplateRenderTest.php` | S1.2 |
| `tests/Unit/Notification/EmailContent/EmailVariableSchemaConsistencyTest.php` | S1.3 |
| `tests/Feature/Notification/EmailContent/DbEmailContentProviderTest.php` | S1.5 |
| `tests/Feature/Notification/EmailContent/DbEmailContentParityTest.php` | S1.7 |
| `tests/Fixtures/Notification/EmailContent/*.php` | S1.7 |

## Story-to-Bead Mapping

**Status: pending validation.** No beads exist yet. The next `khuym:validating`
pass reviews this story map; if it passes bead-readiness, it returns to planning
with explicit instruction to create one execution bead per story (7 beads total)
labelled by `EPIC_ID` + story ID.

Provisional bead skeleton (NOT created yet):

```text
BR-<EPIC_ID>-S1.1   storage table migration
BR-<EPIC_ID>-S1.2   model + trait + enum
BR-<EPIC_ID>-S1.3   variable schema interface + 4 implementations
BR-<EPIC_ID>-S1.4   seeder data migration
BR-<EPIC_ID>-S1.5   DbEmailContentProvider + registry rebind + flag
BR-<EPIC_ID>-S1.6   campus_id injection in 5 call sites
BR-<EPIC_ID>-S1.7   parity test + fixtures
```

Dependency edges to encode at bead-creation time:

```text
S1.1 -> S1.2
S1.2 -> S1.3
S1.2 -> S1.4
S1.4 -> S1.5
S1.3 -> S1.5
S1.5 -> S1.6
S1.6 -> S1.7
```

Swarm-width hint: parent can fan out S1.3 || S1.4 after S1.2 completes; the rest
is serial.

## Carried Constraints (From [validation-report.md](validation-report.md))

These are story-level acceptance criteria, not extra stories:

- **Q1 constraint** -> baked into S1.7 (parity test is the proof artifact).
- **Q3 constraint** -> N/A in P1; carried forward to P2 contract (no save surface,
  no sanitizer needed yet).
- **Q5 constraint** -> baked into S1.5 (memo by `(typeKey, campusId)`; test (c)+(d)
  prove single-query reuse and multi-campus split).

## Open Questions for `khuym:validating`

None. All story exits are testable; all file ownership is disjoint; all carried
constraints map to a specific story. Validating's next pass is purely a bead-
readiness review against this map.
