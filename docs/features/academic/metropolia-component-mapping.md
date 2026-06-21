# Metropolia Component Mapping

This guide describes how to assign the canonical Metropolia grading schemes
(`docs/features/academic/metropolia-grading-schemes.json`) to local syllabus
templates in **one** school database. Schools share the Swinx repository but run
separate databases, so every database opts in through its own explicit mapping
file. No shared tenant table is involved.

## Scheme keys

The pack exposes these stable keys (also returned by
`MetropoliaSchemeCatalog::keys()`):

| Programme | Keys |
| --- | --- |
| Software 1 | `software_1.programming`, `software_1.database`, `software_1.maths_physics`, `software_1.project` |
| Software 2 | `software_2.programming`, `software_2.web_development`, `software_2.maths_physics`, `software_2.project` |
| Hardware 1 | `hardware_1.digital_systems`, `hardware_1.networking`, `hardware_1.linux`, `hardware_1.health_technology`, `hardware_1.maths_physics` |
| Hardware 2 | `hardware_2.electronics`, `hardware_2.maths_physics`, `hardware_2.cloud_computing`, `hardware_2.project` |

Each scheme is stored in the executable shape consumed by the `metropolia_v1`
calculator (`scale` plus `components[].{code,gate,conversion}`). Component scores
are percentages (0-100). The `source_reference`, `version`, and per-scheme
`notes` fields are audit metadata that the calculator ignores.

## Mapping file format

The mapping file is a JSON array. Each entry must resolve to **exactly one**
syllabus template. Use whichever selectors uniquely identify a template:

| Field | Required | Purpose |
| --- | --- | --- |
| `scheme_key` | yes | A key from the table above. |
| `unit_code` | recommended | Matches `units.code`. |
| `syllabus_title` | optional | Matches `syllabus_templates.title`. |
| `syllabus_template_id` | optional | Matches the template primary key directly. |
| `campus_code` | optional | Matches `campuses.code` for campus-specific templates. |

Example mapping:

```json
[
  {
    "unit_code": "SW1-PROG",
    "syllabus_title": "Programming",
    "scheme_key": "software_1.programming"
  },
  {
    "unit_code": "SW1-DB",
    "syllabus_title": "Database",
    "scheme_key": "software_1.database"
  }
]
```

## Apply workflow

Always dry-run first. The command exits `0` only when every mapping resolves to
exactly one template and each referenced scheme validates.

```bash
./scripts/dev.sh artisan academic:apply-grading-scheme-pack \
  --mapping=/absolute/path/metropolia-map.json --dry-run
```

The dry-run reports `total`, `updated` (templates that would receive a scheme),
`skipped` (templates already matching the scheme), and `failed`. It does not
write any rows.

Commit only after a clean dry-run:

```bash
./scripts/dev.sh artisan academic:apply-grading-scheme-pack \
  --mapping=/absolute/path/metropolia-map.json --commit
```

If any mapping is unresolved or ambiguous, the whole run aborts with exit code
`1` and writes nothing, so commit mode can never leave partial data behind.

## Engines used by the pack

Most schemes use `metropolia_v1` (sum of independently converted components).
Three formula-based courses use `metropolia_v2`, which evaluates the source
expression directly, so they reproduce the reference document exactly:

- `hardware_2.cloud_computing` — `(LAB + QUIZ + EXAM/2 - 40) / 10`
- `hardware_1.health_technology` — `(ASSIGNMENT - 40) / 10`
- `software_2.web_development` — `HTML_CSS * 2 / 100 + EXAM * 3 / 100`
  (with HTML+CSS and JavaScript assignment 40% gates)

`metropolia_v2` formulas are evaluated by a whitelisted arithmetic parser (no
`eval`), so operator-authored schemes cannot execute code.

## Remaining modeling note to confirm before a pilot

One scheme is still an approximation because the source document is incomplete,
not because the engine cannot express it:

- `hardware_1.networking` — the prose only fixes "50% total = grade 1"; grades
  2-5 are unspecified and modeled as a linear split. Confirm the intended 2-5
  boundaries with academic staff before relying on this course's grades.

See the per-scheme `notes` field in the JSON pack for the exact reasoning.
