# Grading Rule Engine

## Overview

The grading rule engine lets each syllabus template carry its own grading logic via a `grading_scheme` JSON column. When the column is null the system falls back to the existing weighted-average path, so all current syllabi are unaffected.

## Architecture

### Core classes

| Class | Location | Responsibility |
| --- | --- | --- |
| `GradingResult` | `Modules/Academic/Support/Grading/GradingResult.php` | Readonly value object: `finalPercentage`, `finalGrade`, `gradePoints`, `passed`, `gradeBreakdown` |
| `GradingCalculator` | `Modules/Academic/Support/Grading/Contracts/GradingCalculator.php` | Interface: `calculate(array $componentScores, ?array $scheme): GradingResult` |
| `GradingCalculatorResolver` | `Modules/Academic/Support/Grading/GradingCalculatorResolver.php` | Resolves engine key → concrete calculator |
| `DefaultWeightedPercentageCalculator` | `Modules/Academic/Support/Grading/DefaultWeightedPercentageCalculator.php` | Wraps existing weighted-average logic; sentinel key `__weighted_average__` |
| `MetropoliaV1Calculator` | `Modules/Academic/Support/Grading/MetropoliaV1Calculator.php` | Metropolia-style calculator (see below) |

### Integration points

- **`CourseCompletionService::aggregateManualGrades()`** — resolves calculator, builds component score map, calls `calculate()`, writes `final_percentage`, `final_letter_grade`, and `grade_breakdown` to `academic_records`.
- **`CourseCompletionService::finalizeAcademicRecords()`** — reads `grade_breakdown['grade_points']` and `grade_breakdown['final_grade']` directly when a custom engine is detected, bypassing the default letter-grade lookup.
- **`CanvasGradeSyncService`** — skips overwriting `final_percentage` from Canvas totals when a custom engine is active (engine is authoritative).

## Scheme JSON format

```json
{
  "engine": "metropolia_v1",
  "scale": "0-5",
  "components": [
    {
      "code": "ASSIGNMENT",
      "gate": { "min_pct": 40 },
      "conversion": null
    },
    {
      "code": "EXAM",
      "gate": { "min_pct": 40 },
      "conversion": {
        "type": "linear",
        "min_pct": 40,
        "max_pct": 88,
        "min_grade": 1,
        "max_grade": 5
      }
    }
  ]
}
```

Set `grading_scheme` to `null` (or omit) to use the default weighted-percentage path.

## MetropoliaV1Calculator

### Scales

| Scale | Final grade range | Passed when |
| --- | --- | --- |
| `0-5` | Integer 0–5 | All gates pass and final grade ≥ 1 |
| `pass_fail` | `P` / `F` | All gates pass and all pass_fail conversions pass |

### Conversion types

| Type | Logic |
| --- | --- |
| `linear` | `(score − min_pct) / (max_pct − min_pct) × (max_grade − min_grade) + min_grade`; clamped 0–max_grade |
| `threshold` | Highest `grade` whose `min_pct` the score meets; 0 if none |
| `direct` | `score / 100 × max_grade` |
| `pass_fail` | Pass if `score >= min_pct` |

### Gates

Each component may declare `gate.min_pct`. If the component score falls below this threshold the course fails regardless of the computed final grade. Gate failures are listed in `grade_breakdown.gate_failures`.

### grade_breakdown structure

```json
{
  "engine": "metropolia_v1",
  "scale": "0-5",
  "components": {
    "EXAM": { "score": 88.0, "conversion_result": 5 }
  },
  "fg_sum_raw": 5.0,
  "fg_rounded": 5,
  "gates_passed": true,
  "gate_failures": [],
  "final_grade": "5",
  "grade_points": 5.0
}
```

## Adding a new engine

1. Create a class implementing `GradingCalculator`.
2. Register it in `GradingCalculatorResolver::resolve()`.
3. Add unit tests in `tests/Feature/Academic/Grading/`.

## Database

Migration: `2026_06_21_165309_add_grading_scheme_to_syllabus_templates.php`

Column: `syllabus_templates.grading_scheme` — nullable JSON, cast to array on the model.

## Applying the Metropolia scheme pack

A canonical, repo-tracked Metropolia scheme pack lives at
`docs/features/academic/metropolia-grading-schemes.json`. Each entry is stored in
the executable `metropolia_v1` shape, so applying a pack entry writes a
ready-to-run scheme into `syllabus_templates.grading_scheme`.

- Catalog loader: `App\Modules\Academic\Support\Grading\MetropoliaSchemeCatalog`
- Scheme validator: `App\Modules\Academic\Support\Grading\GradingSchemeValidator`
- Apply action: `App\Modules\Academic\Actions\ApplyGradingSchemePackAction`
- Command: `academic:apply-grading-scheme-pack`

Schools share the repo but not the database, so each database opts in through a
local mapping JSON file. Operator guidance lives in
`docs/features/academic/metropolia-component-mapping.md`.

```bash
# Validate without writing
./scripts/dev.sh artisan academic:apply-grading-scheme-pack --mapping=/path/map.json --dry-run

# Write schemes to the matched templates in the current database
./scripts/dev.sh artisan academic:apply-grading-scheme-pack --mapping=/path/map.json --commit
```

The command exits `1` if any mapping cannot resolve to exactly one template; a
dirty plan writes nothing, so commit mode never leaves partial data behind.
