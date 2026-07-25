---
title: Admissions CRM ingestion API
description: Server-to-server contract for creating and updating admissions applications from a CRM.
audience:
    - CRM integrators
    - Admissions maintainers
status: current
owner: Admissions Team
last_verified: 2026-07-25
scope: admissions-ingestion-api
source_of_truth:
    - app/Modules/Admissions/routes/api.php
    - app/Modules/Admissions/Http/Api/IngestionController.php
    - app/Modules/Admissions/Http/Requests/Admissions/IngestApplicationRequest.php
    - app/Modules/Admissions/Actions/UpsertCrmApplicationAction.php
    - app/Shared/Support/Admissions/AdmissionsIngestion.php
---

# Admissions CRM ingestion API

Base path: `/api/v1/admissions`

This surface is for trusted server-to-server ingestion. Every request requires
a Sanctum service token with the `admissions:ingest` ability and a source IP
allowed by `admissions.ingest.ip_allowlist`. Calls are rate-limited and written
to the admissions ingestion audit log.

## Endpoints

| Method | Path            | Purpose                                                          |
| ------ | --------------- | ---------------------------------------------------------------- |
| `GET`  | `/ping`         | Verify the token, ability, IP allowlist, and route availability. |
| `POST` | `/applications` | Create or update one CRM application.                            |

`GET /ping` returns the standard success envelope with
`data.service = "admissions-ingestion"` and
`data.ability = "admissions:ingest"`.

## Upsert identity and lifecycle

`crm_admission_id` is the idempotency identity:

- an unknown value creates an application and returns `201`;
- an existing pending application is updated and returns `200`;
- an existing non-pending application is frozen against ingestion and returns
  `409`;
- sending `guardians` replaces the application's guardian set; omitting it
  preserves the set;
- documents are upserted by `crm_file_id`; omitting `documents` preserves
  existing records.

## Application payload

The following fields are required on every request, including an update:

| Field              | Type   | Constraint                      |
| ------------------ | ------ | ------------------------------- |
| `crm_admission_id` | string | Maximum 255 characters.         |
| `full_name`        | string | Maximum 255 characters.         |
| `campus_code`      | string | Must exist in `campuses.code`.  |
| `intended_program` | string | Must exist in `programs.code`.  |
| `intake`           | string | Must exist in `semesters.code`. |

Optional application fields are:

`student_code`, `gender`, `ethnicity`, `birth_day`, `birth_month`,
`birth_year`, `national_id`, `phone`, `email`, `address`,
`health_information`, `intended_specialization`,
`is_international_applicant`, `exception_units`, `sut_id`,
`english_qualifications`, and `study_link_status`.

`intended_specialization`, when supplied, must exist in
`specializations.code`. The valid campus, program, specialization, intake, and
document-type values are database-owned catalog data and must not be cached as
a hand-maintained list in an integration.

### English test

`english_test` is optional. It accepts `test_type`, `exam_date`, `listening`,
`reading`, `writing`, `speaking`, and `overall`. Score fields are numeric from
`0` through `99.99`.

### Guardians

`guardians` is optional. Every item requires `full_name` and may include
`relationship`, `phone`, `email`, `occupation`, `address`, and `is_primary`.
Allowed relationship values are owned by
`App\Models\ApplicationGuardian::relationships()`.

### Documents

`documents` is optional. Every item requires:

- `crm_file_id`;
- `file_type_code`;
- `link`.

Optional document fields are `file_type_name`, `page_index`, `original_name`,
`mime_type`, `size`, and `status`.

### Minimal request

```json
{
    "crm_admission_id": "CRM-2026-0001",
    "full_name": "Example Applicant",
    "campus_code": "CAMPUS_CODE",
    "intended_program": "PROGRAM_CODE",
    "intake": "SEMESTER_CODE"
}
```

## Success data

The response `data` contains:

- `id`;
- `crm_admission_id`;
- `student_code`;
- `status`;
- `guardians[]` with `id`, `full_name`, and `is_primary`;
- `documents[]` with `id`, `crm_file_id`, and `file_type_code`.

## Errors

| Status | Meaning                                                            |
| ------ | ------------------------------------------------------------------ |
| `401`  | Missing or invalid Sanctum token.                                  |
| `403`  | Source IP is not allowed or the token lacks the ingestion ability. |
| `409`  | The existing application is no longer pending.                     |
| `422`  | Payload validation failed.                                         |
| `429`  | The named admissions ingestion rate limiter rejected the call.     |

Validation errors use the standard envelope described in
[`docs/api/README.md`](../README.md).
