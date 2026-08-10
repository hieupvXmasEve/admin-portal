# Brainstorm — CRM NE → Student Application sync

Date: 2026-08-10 · Branch: dev · Status: superseded in part by the plan's red-team review

> **Read the plan first.** `plans/260810-0205-crm-ne-application-sync/plan.md` supersedes three claims
> below, corrected there after codebase verification: (a) D12 "FKs stay" — there is **no FK** on
> `campus_code`; (b) the sync design named `ApplicationIngestionService` — the live write path is the
> module's `UpsertCrmApplicationAction`; (c) the `crm_file_id` recipe used `student_code` — it is now
> keyed on the local application id. Field-level mapping below remains authoritative.

## Contract

**Outcome:** Scheduled/CLI pull from CRM (`POST /api/login` → `GET /api/ne`) upserts Student Applications in Swinx, incl. guardians + document links, idempotent per `student_code`.

**Constraints**
- Owner module = `Admissions`; docs = `Upload` (`ApplicationDocument`, link-only per ADR-0004).
- Reuse existing upsert path (`ApplicationIngestionService` / `UpsertCrmApplicationAction`), do not fork a second ingest architecture.
- Credentials via env/config only; never log password/token.
- Per-record failure isolation: one bad record must not abort the batch.

**Non-goals**
- No Finance charge/payment creation from `paid_amount`.
- No auto-approval / student creation. Sync only touches applications.
- No file download/mirroring — URLs stored as-is.
- No CRM push-back (one-way pull).

**Acceptance**
- 2 runs over same CRM data → no duplicate applications, guardians, or document rows.
- Application `status != pending` → record skipped, logged.
- All 3 transcript URLs + both achievement URLs persisted; primary transcript resolves `file_diploma ?? file_transcript ?? file_transcript_1`, null when all null.
- Login failure / 4xx / 5xx / timeout / non-array `data` → run fails cleanly with actionable error, no partial garbage.
- Application with any unmapped **required** value (campus, major, intake) cannot be approved/converted; the error
  names the missing mapping. Once mapped, the same application converts with no re-sync.

## Decisions (user-confirmed)

| # | Decision |
|---|---|
| D1 | Match/dedupe key = `student_code`. `crm_admission_id` left null for this path. |
| D2 | ~~`intake` supplied per run (CLI arg / config)~~ — **superseded by D16**. |
| D3 | Unmapped CRM data → new scalar columns + child table for subject scores (13 môn + 4 `thithpt_*`). |
| D4 | CRM overwrites mapped fields **only while** application `status = pending`; other statuses skipped + logged. |
| D5 | `address` = `new_address ?? address`. New cols: `permanent_address`, `new_street`, `new_ward`, `new_province`. |
| D6 | `paid_amount` → `crm_paid_amount` column on application. No Finance transaction. |
| D7 | `file_english_certificare` = IELTS certificate URL → own document row (`english_certificate`, page_index 1). `file_id_card_photo` ignored. |
| D8 | `scholarship`, `pathway_gateway`, `uu_dai_gc` stored as raw CRM text; value→domain mapping happens later, config-driven in UI, at student-conversion step. `registration_form` ignored. |
| D9 | `place_of_issue` → varchar `id_card_place_of_issue`, stored verbatim (CRM value may be a date or a place). No parsing. |
| D10 | `thithpt_option1/2` kept as opaque subject slots in the score table (`source = national_exam`). Relabel later if CRM exposes subject names. |
| D11 | Both `province` and `new_province` stored; UI displays `new_province ?? province`. |
| D12 | Campus/major NOT resolved at sync time. New raw columns `crm_campus`, `crm_major`; `campus_code` / `intended_program` left NULL until staff maps them in UI before conversion. FKs stay; `campus_code` relaxed to nullable. Mapping table shared with D8. |
| D13 | `ielts_certificate` = URL or null → document row `english_certificate`, page_index 2. |
| D14 | Non-IELTS certificates reuse the same 5 score columns; `english_test_type` distinguishes them. |
| D15 | Mapping UI lives in the **Admissions** module (not Platform settings). |
| D16 | `intake` is a **single fixed setting** in the Admissions mapping screen, not derived from any CRM value (`graduation_year` = year of high-school graduation, unrelated). Sync leaves `intake` NULL; staff sets the target `semesters.code` once in the mapping config, and it applies at conversion. Never a CLI arg. |

## Mapping

### Existing columns
`student_code`→`student_code` · `name`→`full_name` · `email`→`email` · `phone`→`phone` · `cccd`→`national_id` ·
`date_of_birth` (d/m/Y) → `birth_day/_month/_year` · `gender`→`gender` (lower) · `ethnicity`→`ethnicity` ·
`campus`→`crm_campus` (raw) · `major`→`crm_major` (raw) — `campus_code` / `intended_program` stay NULL at sync (D12) ·
`english_certificate_type`→`english_test_type` · `certificate_exam_date`→`exam_date` ·
`ielts_listening/reading/writing/speaking/overall`→`listening/reading/writing/speaking/overall` ·
`new_address ?? address`→`address`.

### New columns on `student_applications`
`permanent_address`, `new_street`, `new_ward`, `new_province`, `province`, `birth_place`, `nationality`,
`religion`, `school`, `graduation_year`, `gpa`, `gpa_type`, `scholarship`, `pathway_gateway`, `uu_dai_gc`,
`crm_paid_amount`, `id_card_place_of_issue`, `crm_campus`, `crm_major`, `last_synced_at`.
Schema relaxation: `campus_code` NOT NULL → nullable (D12). FKs unchanged.

### New child table `application_academic_scores`
Rows keyed `(student_application_id, subject_code)`; `subject_code` ∈ {toan, ly, hoa, sinh, tin_hoc, van, lich_su,
dia_ly, tieng_anh, giao_duc_cong_dan, giao_duc_quoc_phong, cong_nghe, kt_pl, thithpt_toan, thithpt_van,
thithpt_option1, thithpt_option2}; `score` decimal; `source` (school_report | national_exam). Null CRM value → no row.

### Guardians (`application_guardians`)
`father_name/father_phone` → row `relationship=father`; `mother_name/mother_phone` → `relationship=mother`.
Primary = father when present, else mother. Upsert by (application, relationship).

### Documents (`application_documents`, link-only)
| CRM field | file_type_code | page_index |
|---|---|---|
| `file_student_photo` | student_photo | 0 |
| `file_id_card_front` | id_card_front | 0 |
| `file_id_card_back` | id_card_back | 0 |
| `file_english_certificate` | english_certificate | 0 |
| `file_english_certificare` | english_certificate | 1 |
| `file_transcript` | transcript | 0 |
| `file_transcript_1` | transcript | 1 |
| `file_diploma` | diploma | 0 |
| `file_other_achievements` | other_achievements | 0 |
| `file_other_achievements_2` | other_achievements | 1 |
| `ielts_certificate` | english_certificate | 2 |
| `scholarship_cert_view_url` | scholarship_certificate (new catalog code) | 0 |

`file_id_card_photo`, `registration_form` → ignored.
`crm_file_id` absent from `/api/ne` → synthesize `ne:{student_code}:{file_type_code}:{page_index}` to keep the
UNIQUE idempotency key working. Null/blank URL → no row.

## Mapping & conversion-readiness gate (required step)

Sync never resolves CRM text to local codes. Resolution is a separate, explicit stage between sync and
approval/conversion.

**Stage 1 — sync (dumb).** Writes raw CRM values (`crm_campus`, `crm_major`, `scholarship`, `pathway_gateway`,
`uu_dai_gc`) + everything else mapped. Leaves `campus_code`, `intended_program`, `intended_specialization` NULL.
Never fails a record because a value is unmapped.

**Stage 2 — mapping config (UI, Admissions module).** Table
`crm_value_mappings (kind, crm_value, local_code, unique(kind, crm_value))`,
`kind` ∈ {campus, major, specialization, scholarship, pathway_gateway, uu_dai_gc}.
Same screen also holds the standalone **target intake** setting (a `semesters.code`, D16) — a config value, not a
keyed mapping row. Screen lists **unmapped
values discovered from synced data** (distinct raw value with no row for its kind, + count of affected
applications) and lets staff pick the local code. Rows are data, not code — new campus/major needs no deploy.

**Stage 3 — resolve.** Applying a mapping backfills `campus_code` / `intended_program` on all pending applications
carrying that raw value. Runs on mapping save and at the start of each sync run (so newly synced records inherit
existing mappings automatically). `intake` is filled from the target-intake setting at the same points.

**Gate.** An application is *conversion-ready* only when every required-for-approval field resolves:
`campus_code`, `intended_program`, `intake`, plus whatever `EloquentApplicationProgramMappingReader` needs to
return a unique curriculum version. Until then:
- Approve action rejects with a readiness error naming the exact missing mappings (not a generic FK failure).
- List/detail UI shows a "chưa map" state + the blocking values.
- A readiness query drives both the UI badge and the approve guard — one source of truth, no duplicated rules.

Non-required unmapped values (`scholarship`, `pathway_gateway`, `uu_dai_gc`) never block conversion; they surface
as warnings only.

## Sync design (to be detailed in plan)

- Config: `config/services.php` → `crm.base_url`, `crm.username`, `crm.password`, timeout. Env-only secrets.
- Client in `app/Modules/Admissions/Integrations/` — login → token cached in-memory for the run; 401 on `/ne` → one re-login retry, then fail.
- Command `admissions:sync-crm-ne --intake=<code> [--dry-run]` in Admissions module; route through existing
  upsert action inside a per-record DB transaction.
- Per-record: validate → skip+log on invalid; failures counted in a run summary; batch continues.
- `last_synced_at` written on every successful record.

## Open questions

All prior questions resolved by D9–D14. Remaining for the plan phase:

1. Whether `campus_code` nullable breaks any existing query/UI assuming NOT NULL (needs a grep sweep during planning).
2. `thithpt_option1/2` subject labels — pending CRM confirmation; stored as opaque slots meanwhile, no blocker.
3. Target intake is one global setting (D16). If a future admission cycle needs two intakes live at once, that
   setting must become per-application — out of scope now, noted so the plan does not hard-code the assumption
   deeper than one config read.
