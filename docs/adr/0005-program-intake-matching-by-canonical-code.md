---
id: ADR-0005
title: "Program / Intake are matched by canonical code, not by name"
status: accepted
owner: Platform Team
last_verified: 2026-07-25
scope: architecture-decision
---

# Program / Intake are matched by canonical code, not by name

An Application's admission intent — campus, **Intended Program**, and **Intake** — must resolve unambiguously to the real `Program`, `Semester`, and `CurriculumVersion` a Student is created under on Approve, or admission produces the wrong record. We make the inbound values **canonical codes that Swinx owns** and validate them at the boundary, instead of accepting free-text labels and translating them later.

## Contract

- The admissions CRM (and the manual staff form) send `campus_code`, `intended_program`, and `intake` as **codes that already exist** in Swinx: `Campus.code`, `Program.code`, `Semester.code`. The manual form is a **dropdown that shows names but submits codes**, so both channels produce identical, valid values.
- **At the write boundary** (ingestion `FormRequest` and the manual request) the three codes are **required + `exists`**: `campus_code`, `intended_program`, `intake` must reference real rows. A missing or unknown code is rejected with `422` in the `ApiResponse` envelope (or a form error) so the CRM/staff can correct and retry — no orphan Application that can never be approved.
- **At Approve** (not at the boundary) the `CurriculumVersion` is resolved from `(program_id, semester_id [, specialization])` where `intake` is the **`Semester.code`**, and must match **exactly one** row; **zero or many blocks the Approve** with a clear staff message ("curriculum could not be uniquely determined"). This replaces a silent `->first()` that would pick an arbitrary version once a Program/Semester splits by specialization. Curriculum is checked here, not at ingestion, because admins may define Curriculum Versions for an intake *after* the CRM has already sent the Application — ingestion must not `422` on data the CRM got right.
- **Specialization is deferred.** `specializations` is empty today and no Curriculum Version is split by it, so `intended_specialization` is validated `exists` only when supplied, and the Student's `specialization_id` is taken **from the resolved Curriculum Version** (one source of truth). The exactly-one rule is the safety net: the day a split makes the match ambiguous, resolution fails closed rather than guessing.
- Approve also re-checks the Program still resolves (defense-in-depth), since a Program could be deactivated between ingestion and Approve.

## Why not name → code mapping

The prior approach stored free-text labels and translated them at Approve time through a hardcoded `PROGRAM_CODE_MAPPING` (Vietnamese name → code). It was already broken: of 241 real Applications, `"CS"` (62) and `"IT"` (1) had no map entry and `"IT"` mapped to a non-existent Program code — those would have failed Approve. Canonical codes delete that table and move the failure to the door, where it is cheap to fix.

## Migration of existing data

`intended_program` on existing rows is 100% labels, so a one-time backfill normalizes them to codes:

- **Converted rows** (a linked Student exists) take the Student's **actual `Program.code`** — ground truth. This is what cleanly resolves the ambiguous `"CS"`, which really split into `SEMI` (58) and `AI` (4), and `"IT"` → `AI`, per row, without guessing.
- **Unconverted rows** (the 12 `pending`) map the known labels → codes (`Công nghệ bán dẫn→SEMI`, `Trí tuệ nhân tạo→AI`, `Tài chính→FIN`, `Quản trị kinh doanh→BA`); all 12 resolve to valid codes.
- `campus_code` (HN/HCM) and `intake` (FALL2025) are already valid codes and need no change.

Trade-off accepted: overwriting a converted row's historical label (e.g. `"CS"`) with the actual placed Program loses the record of the original *intent* in favour of a uniformly code-valued column. The intent→outcome distinction is preserved in the glossary (**Intended Program** vs the Student's actual Program), not in the legacy text.
