# Design

## Domain Model

The scheme catalog is a repo-tracked JSON fixture keyed by stable Metropolia
course identifiers such as `software_1.programming`. Each catalog entry stores
the `metropolia_v1` scheme that `S-001` can execute plus source labels from the
reference document.

Mappings connect local Swinx records to catalog entries:

- `unit_code`
- `syllabus_template_id` or `syllabus_title`
- `scheme_key`
- optional `campus_code`

## Application Flow

1. Operators create a school-specific mapping JSON file.
2. `academic:apply-grading-scheme-pack --dry-run --mapping=...` validates the
   catalog and mapping.
3. The command reports templates that would receive a scheme, templates already
   matching the scheme, and missing/ambiguous mappings.
4. Operators rerun with `--commit` to update `syllabus_templates.grading_scheme`
   for the current database only.

## Interface Contract

Console command:

```text
academic:apply-grading-scheme-pack
  --pack=metropolia
  --mapping=/absolute/path/to/mapping.json
  --dry-run
  --commit
```

The command exits `0` when all selected mappings validate. It exits `1` when
the catalog is invalid, a mapping cannot find exactly one template, or
`--commit` is used without a clean dry-run report.

## Data Model

No migration is required in this story. It writes the `grading_scheme` JSON
column created by S-001.

## UI / Platform Impact

CLI and docs only.

## Observability

The command logs one summary line with mapping count, updated count, skipped
count, and failed count. Write mode records normal model audit activity through
`SyllabusTemplate`.

## Alternatives Considered

1. Hardcode Metropolia rules in PHP.
2. Apply schemes by matching syllabus title text automatically.
3. Store a shared mapping table across schools.

The selected design keeps the pack versioned in the repo while making each
school database opt in through explicit local mappings.
