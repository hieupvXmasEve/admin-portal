# Syllabus Campus Grading Rule Engine Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a small syllabus/campus grading rule engine that supports Metropolia-style grading while preserving the current weighted-percentage default behavior.

**Architecture:** Store an optional `grading_scheme` JSON contract on `syllabus_templates`; when absent, use the existing weighted-percentage calculation. When present with `engine: metropolia_v1`, route manual and Canvas detail scores through focused Academic grading calculators that return an auditable `GradingResult` for `academic_records`. Schools share this repo but run separate databases, so no shared-database tenant isolation is introduced.

**Tech Stack:** Laravel 13, PHP 8.4, MySQL 8 JSON columns, Pest feature tests, existing Docker wrapper scripts.

---

## Scope Guardrails

This is the first production slice. It intentionally does not build a visual rule-builder UI. Operators configure schemes by seed/import/database update per school database. The code must keep existing default behavior unchanged for syllabi where `grading_scheme` is null.

Portal impact is `both` because grade semantics affect lecturer-entered scores and student-visible course outcomes. The first slice avoids response-shape changes. If implementation discovers a response-shape change is required, pause and add portal updates under `FE/student-nuxt` and `FE/lecturer-nuxt`.

## File Structure

Create:

- `config/academic.php` - academic calculation configuration, including GPA source and standing threshold.
- `database/migrations/2026_06_14_130000_add_grading_scheme_to_syllabus_templates_table.php` - nullable JSON scheme storage.
- `app/Modules/Academic/Grading/Contracts/GradingCalculator.php` - calculator interface.
- `app/Modules/Academic/Grading/Data/ComponentScore.php` - normalized per-component score input.
- `app/Modules/Academic/Grading/Data/GradingResult.php` - normalized final calculation output.
- `app/Modules/Academic/Grading/ComponentScoreBuilder.php` - converts assessment components/details/scores into component scores.
- `app/Modules/Academic/Grading/DefaultWeightedPercentageCalculator.php` - current behavior as an explicit calculator.
- `app/Modules/Academic/Grading/MetropoliaV1Calculator.php` - Metropolia rule interpreter.
- `app/Modules/Academic/Grading/GradingCalculatorResolver.php` - selects calculator by syllabus scheme.
- `app/Modules/Academic/Actions/AggregateCourseOfferingGradesAction.php` - course-level aggregation entry point.
- `tests/Feature/Academic/Grading/SyllabusGradingSchemePersistenceTest.php`
- `tests/Feature/Academic/Grading/DefaultWeightedPercentageCalculatorTest.php`
- `tests/Feature/Academic/Grading/MetropoliaV1CalculatorTest.php`
- `tests/Feature/Academic/Grading/AggregateCourseOfferingGradesActionTest.php`
- `tests/Feature/Academic/Grading/CourseCompletionGradingSchemeTest.php`
- `tests/Feature/Academic/Grading/GpaSourceConfigurationTest.php`
- `tests/Feature/Academic/Grading/CanvasCustomSchemeAggregationTest.php`
- `docs/features/academic/grading-rule-engine.md`

Modify:

- `app/Models/SyllabusTemplate.php` - add `grading_scheme` fillable/cast.
- `app/Http/Requests/SyllabusTemplate/StoreSyllabusTemplateRequest.php` - accept optional scheme.
- `app/Http/Requests/SyllabusTemplate/UpdateSyllabusTemplateRequest.php` - accept optional scheme.
- `app/Models/AcademicRecord.php` - use `is_passed` when present and keep helper scale-aware.
- `app/Models/CourseRegistration.php` - allow grade points up to 5.
- `app/Services/CourseCompletionService.php` - delegate aggregation and finalization result handling.
- `app/Services/Canvas/CanvasGradeSyncService.php` - keep Canvas total for default schemes, local aggregate for custom schemes.
- `app/Actions/Academic/CalculateStudentSemesterGpaAction.php` - configurable GPA source.
- `app/Actions/Academic/CalculateCumulativeGpaAction.php` - configurable GPA source.
- `app/Actions/Academic/DetermineAcademicStandingAction.php` - configurable threshold.
- `docs/features/academic/Grading_Schemes_Metropolia.md` - add a pointer to the engine docs; do not rewrite source reference rules.

---

### Task 1: Add Scheme Storage And Academic Config

**Files:**
- Create: `config/academic.php`
- Create: `database/migrations/2026_06_14_130000_add_grading_scheme_to_syllabus_templates_table.php`
- Modify: `app/Models/SyllabusTemplate.php`
- Modify: `app/Http/Requests/SyllabusTemplate/StoreSyllabusTemplateRequest.php`
- Modify: `app/Http/Requests/SyllabusTemplate/UpdateSyllabusTemplateRequest.php`
- Test: `tests/Feature/Academic/Grading/SyllabusGradingSchemePersistenceTest.php`

- [ ] **Step 1: Write the failing persistence test**

Create `tests/Feature/Academic/Grading/SyllabusGradingSchemePersistenceTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\SyllabusTemplate;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('persists a syllabus grading scheme as an array cast', function () {
    $unit = Unit::factory()->create();

    $scheme = [
        'engine' => 'metropolia_v1',
        'version' => 1,
        'scale' => 'numeric_0_5',
        'rounding' => 'nearest_integer_after_components',
        'requirements' => [
            ['type' => 'component_min_percentage', 'component' => 'ASSIGNMENTS', 'threshold' => 40],
        ],
        'components' => [
            ['component' => 'ASSIGNMENTS', 'conversion' => ['type' => 'none']],
            [
                'component' => 'EXAM',
                'conversion' => [
                    'type' => 'linear_percentage_to_grade',
                    'min_percentage' => 40,
                    'min_grade' => 1,
                    'max_percentage' => 88,
                    'max_grade' => 5,
                ],
            ],
        ],
        'final' => ['type' => 'sum_converted_components', 'max_grade' => 5],
    ];

    $template = SyllabusTemplate::create([
        'unit_id' => $unit->id,
        'title' => 'Metropolia Programming',
        'grading_scheme' => $scheme,
    ]);

    expect($template->fresh()->grading_scheme)
        ->toBeArray()
        ->and($template->fresh()->grading_scheme['engine'])->toBe('metropolia_v1')
        ->and($template->fresh()->grading_scheme['components'][1]['component'])->toBe('EXAM');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run:

```bash
./scripts/dev.sh test tests/Feature/Academic/Grading/SyllabusGradingSchemePersistenceTest.php
```

Expected: FAIL because `grading_scheme` column or model cast does not exist.

- [ ] **Step 3: Add academic config**

Create `config/academic.php`:

```php
<?php

declare(strict_types=1);

return [
    'gpa' => [
        'source' => env('ACADEMIC_GPA_SOURCE', 'final_percentage'),
        'normal_standing_threshold' => (float) env('ACADEMIC_NORMAL_STANDING_THRESHOLD', 50.0),
    ],
];
```

- [ ] **Step 4: Add migration**

Create `database/migrations/2026_06_14_130000_add_grading_scheme_to_syllabus_templates_table.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('syllabus_templates', function (Blueprint $table) {
            $table->json('grading_scheme')->nullable()->after('grading_criteria');
        });
    }

    public function down(): void
    {
        Schema::table('syllabus_templates', function (Blueprint $table) {
            $table->dropColumn('grading_scheme');
        });
    }
};
```

- [ ] **Step 5: Update model and requests**

Modify `app/Models/SyllabusTemplate.php`:

```php
protected $fillable = [
    'unit_id',
    'title',
    'version',
    'description',
    'total_hours',
    'total_sessions',
    'min_attendance_threshold',
    'min_grade_threshold',
    'learning_outcomes',
    'grading_criteria',
    'grading_scheme',
    'required_materials',
    'assessment_policy',
    'applicable_program_id',
    'applicable_campus_id',
    'delivery_mode',
    'is_default',
    'is_active',
    'source_template_id',
    'created_by',
];

protected $casts = [
    'total_hours' => 'integer',
    'total_sessions' => 'integer',
    'min_attendance_threshold' => 'decimal:2',
    'min_grade_threshold' => 'decimal:2',
    'learning_outcomes' => 'array',
    'grading_criteria' => 'array',
    'grading_scheme' => 'array',
    'required_materials' => 'array',
    'is_default' => 'boolean',
    'is_active' => 'boolean',
];
```

Modify both syllabus requests and add this rule next to `grading_criteria`:

```php
'grading_scheme' => ['nullable', 'array'],
'grading_scheme.engine' => ['required_with:grading_scheme', 'in:default_weighted_percentage,metropolia_v1'],
'grading_scheme.version' => ['nullable', 'integer', 'min:1'],
'grading_scheme.scale' => ['nullable', 'in:percentage_0_100,numeric_0_5,pass_fail'],
'grading_scheme.requirements' => ['nullable', 'array'],
'grading_scheme.components' => ['nullable', 'array'],
'grading_scheme.final' => ['nullable', 'array'],
```

In each request `prepareForValidation()` loop, include `grading_scheme`:

```php
foreach (['learning_outcomes', 'grading_criteria', 'grading_scheme', 'required_materials'] as $key) {
    if (is_string($this->input($key))) {
        $decoded = json_decode($this->input($key), true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $this->merge([$key => $decoded]);
        }
    }
}
```

- [ ] **Step 6: Run persistence test**

Run:

```bash
./scripts/dev.sh test tests/Feature/Academic/Grading/SyllabusGradingSchemePersistenceTest.php
```

Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add config/academic.php \
  database/migrations/2026_06_14_130000_add_grading_scheme_to_syllabus_templates_table.php \
  app/Models/SyllabusTemplate.php \
  app/Http/Requests/SyllabusTemplate/StoreSyllabusTemplateRequest.php \
  app/Http/Requests/SyllabusTemplate/UpdateSyllabusTemplateRequest.php \
  tests/Feature/Academic/Grading/SyllabusGradingSchemePersistenceTest.php
git commit -m "feat: add syllabus grading scheme storage"
```

---

### Task 2: Add Grading Data Objects, Score Builder, And Resolver

**Files:**
- Create: `app/Modules/Academic/Grading/Contracts/GradingCalculator.php`
- Create: `app/Modules/Academic/Grading/Data/ComponentScore.php`
- Create: `app/Modules/Academic/Grading/Data/GradingResult.php`
- Create: `app/Modules/Academic/Grading/ComponentScoreBuilder.php`
- Create: `app/Modules/Academic/Grading/GradingCalculatorResolver.php`
- Test: `tests/Feature/Academic/Grading/DefaultWeightedPercentageCalculatorTest.php`

- [ ] **Step 1: Write failing resolver test**

Create `tests/Feature/Academic/Grading/DefaultWeightedPercentageCalculatorTest.php` with the first resolver assertion:

```php
<?php

declare(strict_types=1);

use App\Modules\Academic\Grading\DefaultWeightedPercentageCalculator;
use App\Modules\Academic\Grading\GradingCalculatorResolver;

it('resolves default calculator when scheme is null', function () {
    $resolver = app(GradingCalculatorResolver::class);

    expect($resolver->resolve(null))->toBeInstanceOf(DefaultWeightedPercentageCalculator::class);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run:

```bash
./scripts/dev.sh test tests/Feature/Academic/Grading/DefaultWeightedPercentageCalculatorTest.php
```

Expected: FAIL because resolver and calculator classes do not exist.

- [ ] **Step 3: Add calculator contract**

Create `app/Modules/Academic/Grading/Contracts/GradingCalculator.php`:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Academic\Grading\Contracts;

use App\Modules\Academic\Grading\Data\GradingResult;
use Illuminate\Support\Collection;

interface GradingCalculator
{
    /**
     * @param  Collection<string, \App\Modules\Academic\Grading\Data\ComponentScore>  $componentScores
     * @param  array<string, mixed>  $scheme
     */
    public function calculate(Collection $componentScores, array $scheme = []): GradingResult;
}
```

- [ ] **Step 4: Add component score data object**

Create `app/Modules/Academic/Grading/Data/ComponentScore.php`:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Academic\Grading\Data;

final readonly class ComponentScore
{
    /**
     * @param  array<int, array{name: string, percentage: float|null, points: float|null, max_points: float|null, counted: bool}>  $details
     */
    public function __construct(
        public string $code,
        public string $name,
        public float $weight,
        public ?float $percentage,
        public float $pointsEarned,
        public float $maxPoints,
        public bool $hasScore,
        public array $details = [],
    ) {}

    public function percentageOrZero(): float
    {
        return $this->percentage ?? 0.0;
    }
}
```

- [ ] **Step 5: Add grading result data object**

Create `app/Modules/Academic/Grading/Data/GradingResult.php`:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Academic\Grading\Data;

final readonly class GradingResult
{
    /**
     * @param  array<string, mixed>  $breakdown
     */
    public function __construct(
        public ?float $finalPercentage,
        public string $finalGrade,
        public ?float $gradePoints,
        public ?bool $passed,
        public array $breakdown,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function academicRecordUpdates(): array
    {
        return [
            'final_percentage' => $this->finalPercentage,
            'raw_percentage' => $this->finalPercentage,
            'final_letter_grade' => $this->finalGrade,
            'grade_points' => $this->gradePoints,
            'grade_breakdown' => [
                'rule_engine' => [
                    'final_percentage' => $this->finalPercentage,
                    'final_grade' => $this->finalGrade,
                    'grade_points' => $this->gradePoints,
                    'passed' => $this->passed,
                    'breakdown' => $this->breakdown,
                ],
            ],
        ];
    }
}
```

- [ ] **Step 6: Add component score builder**

Create `app/Modules/Academic/Grading/ComponentScoreBuilder.php`:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Academic\Grading;

use App\Models\AssessmentComponent;
use App\Models\AssessmentComponentDetailScore;
use App\Modules\Academic\Grading\Data\ComponentScore;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class ComponentScoreBuilder
{
    /**
     * @param  Collection<int, AssessmentComponent>  $components
     * @param  Collection<int, AssessmentComponentDetailScore>  $studentScores
     * @param  array<int, int>  $gradedDetailIds
     * @return Collection<string, ComponentScore>
     */
    public function build(Collection $components, Collection $studentScores, array $gradedDetailIds): Collection
    {
        return $components->mapWithKeys(function (AssessmentComponent $component) use ($studentScores, $gradedDetailIds) {
            $weightedPercentage = 0.0;
            $detailWeightTotal = 0.0;
            $pointsEarned = 0.0;
            $maxPoints = 0.0;
            $details = [];

            foreach ($component->details as $detail) {
                $score = $studentScores
                    ->where('assessment_component_detail_id', $detail->id)
                    ->first();

                $counted = false;
                $percentage = null;
                $points = null;

                if ($score && $score->percentage_score !== null) {
                    $percentage = (float) $score->percentage_score;
                    $points = $score->points_earned !== null ? (float) $score->points_earned : null;
                    $counted = true;
                } elseif (in_array((int) $detail->id, $gradedDetailIds, true)) {
                    $percentage = 0.0;
                    $points = 0.0;
                    $counted = true;
                }

                if ($counted) {
                    $detailWeight = (float) ($detail->weight ?? 0);
                    $weightedPercentage += ($percentage ?? 0.0) * $detailWeight;
                    $detailWeightTotal += $detailWeight;
                    $pointsEarned += $points ?? 0.0;
                    $maxPoints += (float) ($detail->max_points ?? 0);
                }

                $details[] = [
                    'name' => (string) $detail->name,
                    'percentage' => $percentage,
                    'points' => $points,
                    'max_points' => $detail->max_points !== null ? (float) $detail->max_points : null,
                    'counted' => $counted,
                ];
            }

            $percentage = $detailWeightTotal > 0
                ? round($weightedPercentage / $detailWeightTotal, 2)
                : null;

            $code = $component->code ?: Str::of((string) $component->name)->upper()->snake()->toString();

            return [
                $code => new ComponentScore(
                    code: $code,
                    name: (string) $component->name,
                    weight: (float) ($component->weight ?? 0),
                    percentage: $percentage,
                    pointsEarned: $pointsEarned,
                    maxPoints: $maxPoints,
                    hasScore: $percentage !== null,
                    details: $details,
                ),
            ];
        });
    }
}
```

- [ ] **Step 7: Add resolver shell and default calculator shell**

Create `app/Modules/Academic/Grading/DefaultWeightedPercentageCalculator.php`:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Academic\Grading;

use App\Models\AcademicRecord;
use App\Modules\Academic\Grading\Contracts\GradingCalculator;
use App\Modules\Academic\Grading\Data\GradingResult;
use Illuminate\Support\Collection;

final class DefaultWeightedPercentageCalculator implements GradingCalculator
{
    public function calculate(Collection $componentScores, array $scheme = []): GradingResult
    {
        $finalPercentage = 0.0;

        return new GradingResult(
            finalPercentage: $finalPercentage,
            finalGrade: AcademicRecord::calculateLetterGrade($finalPercentage),
            gradePoints: AcademicRecord::calculateGradePoints($finalPercentage),
            passed: null,
            breakdown: ['engine' => 'default_weighted_percentage'],
        );
    }
}
```

Create `app/Modules/Academic/Grading/MetropoliaV1Calculator.php`:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Academic\Grading;

use App\Modules\Academic\Grading\Contracts\GradingCalculator;
use App\Modules\Academic\Grading\Data\GradingResult;
use Illuminate\Support\Collection;

final class MetropoliaV1Calculator implements GradingCalculator
{
    public function calculate(Collection $componentScores, array $scheme = []): GradingResult
    {
        return new GradingResult(
            finalPercentage: null,
            finalGrade: 'F',
            gradePoints: 0.0,
            passed: false,
            breakdown: ['engine' => 'metropolia_v1'],
        );
    }
}
```

Create `app/Modules/Academic/Grading/GradingCalculatorResolver.php`:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Academic\Grading;

use App\Modules\Academic\Grading\Contracts\GradingCalculator;
use InvalidArgumentException;

final class GradingCalculatorResolver
{
    public function __construct(
        private readonly DefaultWeightedPercentageCalculator $defaultCalculator,
        private readonly MetropoliaV1Calculator $metropoliaCalculator,
    ) {}

    /**
     * @param  array<string, mixed>|null  $scheme
     */
    public function resolve(?array $scheme): GradingCalculator
    {
        $engine = $scheme['engine'] ?? 'default_weighted_percentage';

        return match ($engine) {
            'default_weighted_percentage' => $this->defaultCalculator,
            'metropolia_v1' => $this->metropoliaCalculator,
            default => throw new InvalidArgumentException("Unsupported grading engine [{$engine}]."),
        };
    }
}
```

- [ ] **Step 8: Run resolver test**

Run:

```bash
./scripts/dev.sh test tests/Feature/Academic/Grading/DefaultWeightedPercentageCalculatorTest.php
```

Expected: PASS for resolver assertion.

- [ ] **Step 9: Commit**

```bash
git add app/Modules/Academic/Grading tests/Feature/Academic/Grading/DefaultWeightedPercentageCalculatorTest.php
git commit -m "feat: add grading calculator foundation"
```

---

### Task 3: Preserve Existing Weighted-Percentage Behavior

**Files:**
- Modify: `app/Modules/Academic/Grading/DefaultWeightedPercentageCalculator.php`
- Test: `tests/Feature/Academic/Grading/DefaultWeightedPercentageCalculatorTest.php`

- [ ] **Step 1: Extend failing default calculator test**

Append to `tests/Feature/Academic/Grading/DefaultWeightedPercentageCalculatorTest.php`:

```php
use App\Modules\Academic\Grading\Data\ComponentScore;
use Illuminate\Support\Collection;

it('calculates the existing weighted percentage result', function () {
    $calculator = app(DefaultWeightedPercentageCalculator::class);

    $scores = new Collection([
        'ASSIGNMENT' => new ComponentScore(
            code: 'ASSIGNMENT',
            name: 'Assignment',
            weight: 40.0,
            percentage: 80.0,
            pointsEarned: 80.0,
            maxPoints: 100.0,
            hasScore: true,
        ),
        'EXAM' => new ComponentScore(
            code: 'EXAM',
            name: 'Exam',
            weight: 60.0,
            percentage: 70.0,
            pointsEarned: 70.0,
            maxPoints: 100.0,
            hasScore: true,
        ),
    ]);

    $result = $calculator->calculate($scores);

    expect($result->finalPercentage)->toBe(74.0)
        ->and($result->finalGrade)->toBe('B')
        ->and($result->gradePoints)->toBe(2.96)
        ->and($result->passed)->toBeNull()
        ->and($result->breakdown['engine'])->toBe('default_weighted_percentage');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run:

```bash
./scripts/dev.sh test tests/Feature/Academic/Grading/DefaultWeightedPercentageCalculatorTest.php
```

Expected: FAIL because default calculator currently returns 0.

- [ ] **Step 3: Implement weighted calculator**

Replace `DefaultWeightedPercentageCalculator::calculate()` with:

```php
public function calculate(Collection $componentScores, array $scheme = []): GradingResult
{
    $totalWeightedScore = 0.0;
    $totalWeight = 0.0;
    $components = [];

    foreach ($componentScores as $componentScore) {
        if (! $componentScore->hasScore || $componentScore->weight <= 0) {
            $components[] = [
                'code' => $componentScore->code,
                'percentage' => $componentScore->percentage,
                'weight' => $componentScore->weight,
                'counted' => false,
            ];

            continue;
        }

        $totalWeightedScore += $componentScore->percentageOrZero() * $componentScore->weight;
        $totalWeight += $componentScore->weight;
        $components[] = [
            'code' => $componentScore->code,
            'percentage' => $componentScore->percentage,
            'weight' => $componentScore->weight,
            'counted' => true,
        ];
    }

    $finalPercentage = $totalWeight > 0
        ? round($totalWeightedScore / $totalWeight, 2)
        : 0.0;

    return new GradingResult(
        finalPercentage: $finalPercentage,
        finalGrade: AcademicRecord::calculateLetterGrade($finalPercentage),
        gradePoints: AcademicRecord::calculateGradePoints($finalPercentage),
        passed: null,
        breakdown: [
            'engine' => 'default_weighted_percentage',
            'total_weighted_score' => round($totalWeightedScore, 2),
            'total_weight' => round($totalWeight, 2),
            'components' => $components,
        ],
    );
}
```

- [ ] **Step 4: Run default calculator test**

Run:

```bash
./scripts/dev.sh test tests/Feature/Academic/Grading/DefaultWeightedPercentageCalculatorTest.php
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Modules/Academic/Grading/DefaultWeightedPercentageCalculator.php tests/Feature/Academic/Grading/DefaultWeightedPercentageCalculatorTest.php
git commit -m "feat: preserve default weighted grade calculation"
```

---

### Task 4: Implement Metropolia V1 Calculator

**Files:**
- Modify: `app/Modules/Academic/Grading/MetropoliaV1Calculator.php`
- Test: `tests/Feature/Academic/Grading/MetropoliaV1CalculatorTest.php`

- [ ] **Step 1: Write Metropolia calculator tests**

Create `tests/Feature/Academic/Grading/MetropoliaV1CalculatorTest.php`:

```php
<?php

declare(strict_types=1);

use App\Modules\Academic\Grading\Data\ComponentScore;
use App\Modules\Academic\Grading\MetropoliaV1Calculator;
use Illuminate\Support\Collection;

function metropoliaScores(array $items): Collection
{
    return collect($items)->mapWithKeys(fn(array $item) => [
        $item['code'] => new ComponentScore(
            code: $item['code'],
            name: $item['code'],
            weight: (float) ($item['weight'] ?? 0),
            percentage: array_key_exists('percentage', $item) ? (float) $item['percentage'] : null,
            pointsEarned: (float) ($item['points'] ?? 0),
            maxPoints: (float) ($item['max_points'] ?? 100),
            hasScore: array_key_exists('percentage', $item) || array_key_exists('points', $item),
        ),
    ]);
}

it('calculates programming exam linear grade after assignment gate passes', function () {
    $scheme = [
        'engine' => 'metropolia_v1',
        'version' => 1,
        'scale' => 'numeric_0_5',
        'rounding' => 'nearest_integer_after_components',
        'requirements' => [
            ['type' => 'component_min_percentage', 'component' => 'ASSIGNMENTS', 'threshold' => 40],
        ],
        'components' => [
            ['component' => 'ASSIGNMENTS', 'conversion' => ['type' => 'none']],
            [
                'component' => 'EXAM',
                'conversion' => [
                    'type' => 'linear_percentage_to_grade',
                    'min_percentage' => 40,
                    'min_grade' => 1,
                    'max_percentage' => 88,
                    'max_grade' => 5,
                ],
            ],
        ],
        'final' => ['type' => 'sum_converted_components', 'max_grade' => 5],
    ];

    $result = app(MetropoliaV1Calculator::class)->calculate(metropoliaScores([
        ['code' => 'ASSIGNMENTS', 'percentage' => 45],
        ['code' => 'EXAM', 'percentage' => 64],
    ]), $scheme);

    expect($result->gradePoints)->toBe(3.0)
        ->and($result->finalGrade)->toBe('3')
        ->and($result->passed)->toBeTrue();
});

it('fails programming when the zero weight assignment gate fails', function () {
    $scheme = [
        'engine' => 'metropolia_v1',
        'version' => 1,
        'scale' => 'numeric_0_5',
        'rounding' => 'nearest_integer_after_components',
        'requirements' => [
            ['type' => 'component_min_percentage', 'component' => 'ASSIGNMENTS', 'threshold' => 40],
        ],
        'components' => [
            ['component' => 'ASSIGNMENTS', 'conversion' => ['type' => 'none']],
            [
                'component' => 'EXAM',
                'conversion' => [
                    'type' => 'linear_percentage_to_grade',
                    'min_percentage' => 40,
                    'min_grade' => 1,
                    'max_percentage' => 88,
                    'max_grade' => 5,
                ],
            ],
        ],
        'final' => ['type' => 'sum_converted_components', 'max_grade' => 5],
    ];

    $result = app(MetropoliaV1Calculator::class)->calculate(metropoliaScores([
        ['code' => 'ASSIGNMENTS', 'percentage' => 39],
        ['code' => 'EXAM', 'percentage' => 88],
    ]), $scheme);

    expect($result->gradePoints)->toBe(0.0)
        ->and($result->finalGrade)->toBe('0')
        ->and($result->passed)->toBeFalse();
});

it('calculates pass fail database course', function () {
    $scheme = [
        'engine' => 'metropolia_v1',
        'version' => 1,
        'scale' => 'pass_fail',
        'requirements' => [
            ['type' => 'component_min_percentage', 'component' => 'ASSIGNMENTS', 'threshold' => 80],
        ],
        'components' => [
            ['component' => 'ASSIGNMENTS', 'conversion' => ['type' => 'none']],
        ],
        'final' => ['type' => 'pass_fail'],
    ];

    $pass = app(MetropoliaV1Calculator::class)->calculate(metropoliaScores([
        ['code' => 'ASSIGNMENTS', 'percentage' => 80],
    ]), $scheme);

    $fail = app(MetropoliaV1Calculator::class)->calculate(metropoliaScores([
        ['code' => 'ASSIGNMENTS', 'percentage' => 79],
    ]), $scheme);

    expect($pass->finalGrade)->toBe('P')
        ->and($pass->passed)->toBeTrue()
        ->and($fail->finalGrade)->toBe('F')
        ->and($fail->passed)->toBeFalse();
});

it('sums maths threshold component grades then rounds once', function () {
    $scheme = [
        'engine' => 'metropolia_v1',
        'version' => 1,
        'scale' => 'numeric_0_5',
        'rounding' => 'nearest_integer_after_components',
        'components' => [
            [
                'component' => 'ASSIGNMENTS',
                'conversion' => [
                    'type' => 'thresholds',
                    'thresholds' => [
                        ['percentage' => 55, 'grade' => 1],
                        ['percentage' => 70, 'grade' => 2],
                    ],
                ],
            ],
            [
                'component' => 'FINAL_EXAM',
                'conversion' => [
                    'type' => 'thresholds',
                    'thresholds' => [
                        ['percentage' => 50, 'grade' => 1],
                        ['percentage' => 70, 'grade' => 2],
                        ['percentage' => 85, 'grade' => 3],
                    ],
                ],
            ],
        ],
        'final' => ['type' => 'sum_converted_components', 'max_grade' => 5],
    ];

    $result = app(MetropoliaV1Calculator::class)->calculate(metropoliaScores([
        ['code' => 'ASSIGNMENTS', 'percentage' => 70],
        ['code' => 'FINAL_EXAM', 'percentage' => 85],
    ]), $scheme);

    expect($result->gradePoints)->toBe(5.0)
        ->and($result->finalGrade)->toBe('5')
        ->and($result->passed)->toBeTrue();
});

it('calculates cloud computing expression final formula', function () {
    $scheme = [
        'engine' => 'metropolia_v1',
        'version' => 1,
        'scale' => 'numeric_0_5',
        'rounding' => 'nearest_integer_after_components',
        'components' => [],
        'final' => [
            'type' => 'expression',
            'max_grade' => 5,
            'operations' => [
                ['op' => 'add_component_percentage', 'component' => 'LABS'],
                ['op' => 'add_component_percentage', 'component' => 'QUIZZES'],
                ['op' => 'add_component_percentage_divided_by', 'component' => 'FINAL_EXAM', 'divisor' => 2],
                ['op' => 'subtract', 'value' => 40],
                ['op' => 'divide', 'value' => 10],
            ],
        ],
    ];

    $result = app(MetropoliaV1Calculator::class)->calculate(metropoliaScores([
        ['code' => 'LABS', 'percentage' => 40],
        ['code' => 'QUIZZES', 'percentage' => 20],
        ['code' => 'FINAL_EXAM', 'percentage' => 60],
    ]), $scheme);

    expect($result->gradePoints)->toBe(5.0)
        ->and($result->finalGrade)->toBe('5')
        ->and($result->passed)->toBeTrue();
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run:

```bash
./scripts/dev.sh test tests/Feature/Academic/Grading/MetropoliaV1CalculatorTest.php
```

Expected: FAIL because calculator returns fixed failure result.

- [ ] **Step 3: Implement Metropolia calculator**

Replace `app/Modules/Academic/Grading/MetropoliaV1Calculator.php` with:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Academic\Grading;

use App\Modules\Academic\Grading\Contracts\GradingCalculator;
use App\Modules\Academic\Grading\Data\ComponentScore;
use App\Modules\Academic\Grading\Data\GradingResult;
use Illuminate\Support\Collection;
use InvalidArgumentException;

final class MetropoliaV1Calculator implements GradingCalculator
{
    public function calculate(Collection $componentScores, array $scheme = []): GradingResult
    {
        $requirementResults = $this->evaluateRequirements($componentScores, $scheme['requirements'] ?? []);
        $requirementsPassed = collect($requirementResults)->every(fn(array $result) => $result['passed'] === true);

        $convertedComponents = [];
        foreach (($scheme['components'] ?? []) as $componentRule) {
            $componentCode = (string) $componentRule['component'];
            $score = $componentScores->get($componentCode);
            $convertedComponents[] = [
                'component' => $componentCode,
                'grade' => $score instanceof ComponentScore
                    ? $this->convertComponent($score, $componentRule['conversion'] ?? ['type' => 'none'])
                    : 0.0,
                'percentage' => $score instanceof ComponentScore ? $score->percentage : null,
                'points' => $score instanceof ComponentScore ? $score->pointsEarned : 0.0,
            ];
        }

        $rawFinal = $this->calculateFinalValue($componentScores, $convertedComponents, $scheme['final'] ?? ['type' => 'sum_converted_components']);
        $maxGrade = (float) (($scheme['final']['max_grade'] ?? 5));
        $clamped = max(0.0, min($maxGrade, $rawFinal));
        $rounded = $this->roundFinal($clamped, (string) ($scheme['rounding'] ?? 'nearest_integer_after_components'));
        $scale = (string) ($scheme['scale'] ?? 'numeric_0_5');

        if (! $requirementsPassed) {
            return new GradingResult(
                finalPercentage: $this->averagePercentage($componentScores),
                finalGrade: $scale === 'pass_fail' ? 'F' : '0',
                gradePoints: $scale === 'pass_fail' ? null : 0.0,
                passed: false,
                breakdown: $this->breakdown($scheme, $requirementResults, $convertedComponents, $rawFinal, 0.0),
            );
        }

        if ($scale === 'pass_fail') {
            return new GradingResult(
                finalPercentage: $this->averagePercentage($componentScores),
                finalGrade: 'P',
                gradePoints: null,
                passed: true,
                breakdown: $this->breakdown($scheme, $requirementResults, $convertedComponents, $rawFinal, null),
            );
        }

        return new GradingResult(
            finalPercentage: $this->averagePercentage($componentScores),
            finalGrade: (string) (int) $rounded,
            gradePoints: (float) $rounded,
            passed: $rounded >= 1.0,
            breakdown: $this->breakdown($scheme, $requirementResults, $convertedComponents, $rawFinal, $rounded),
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $requirements
     * @return array<int, array<string, mixed>>
     */
    private function evaluateRequirements(Collection $componentScores, array $requirements): array
    {
        return collect($requirements)->map(function (array $requirement) use ($componentScores) {
            $componentCode = (string) $requirement['component'];
            $score = $componentScores->get($componentCode);
            $type = (string) $requirement['type'];

            $actual = match ($type) {
                'component_min_percentage' => $score instanceof ComponentScore ? $score->percentageOrZero() : 0.0,
                'component_min_points' => $score instanceof ComponentScore ? $score->pointsEarned : 0.0,
                default => throw new InvalidArgumentException("Unsupported Metropolia requirement [{$type}]."),
            };

            $threshold = (float) ($requirement['threshold'] ?? $requirement['min_points'] ?? 0);

            return [
                'type' => $type,
                'component' => $componentCode,
                'actual' => $actual,
                'threshold' => $threshold,
                'passed' => $actual >= $threshold,
            ];
        })->values()->all();
    }

    /**
     * @param  array<string, mixed>  $conversion
     */
    private function convertComponent(ComponentScore $score, array $conversion): float
    {
        $type = (string) ($conversion['type'] ?? 'none');

        return match ($type) {
            'none' => 0.0,
            'linear_percentage_to_grade' => $this->linearPercentageToGrade($score->percentageOrZero(), $conversion),
            'percentage_points' => $score->percentageOrZero() * (float) ($conversion['max_grade'] ?? 5) / 100,
            'points_divisor' => $score->pointsEarned / max(0.0001, (float) ($conversion['divisor'] ?? 1)),
            'thresholds' => $this->thresholdGrade($score->percentageOrZero(), $conversion['thresholds'] ?? []),
            'direct_points' => min((float) ($conversion['max_grade'] ?? 5), $score->pointsEarned),
            default => throw new InvalidArgumentException("Unsupported Metropolia conversion [{$type}]."),
        };
    }

    /**
     * @param  array<string, mixed>  $conversion
     */
    private function linearPercentageToGrade(float $percentage, array $conversion): float
    {
        $minPercentage = (float) $conversion['min_percentage'];
        $maxPercentage = (float) $conversion['max_percentage'];
        $minGrade = (float) $conversion['min_grade'];
        $maxGrade = (float) $conversion['max_grade'];

        if ($percentage < $minPercentage) {
            return 0.0;
        }

        if ($percentage >= $maxPercentage) {
            return $maxGrade;
        }

        $ratio = ($percentage - $minPercentage) / ($maxPercentage - $minPercentage);

        return $minGrade + ($ratio * ($maxGrade - $minGrade));
    }

    /**
     * @param  array<int, array{percentage: int|float, grade: int|float}>  $thresholds
     */
    private function thresholdGrade(float $percentage, array $thresholds): float
    {
        $grade = 0.0;
        foreach ($thresholds as $threshold) {
            if ($percentage >= (float) $threshold['percentage']) {
                $grade = max($grade, (float) $threshold['grade']);
            }
        }

        return $grade;
    }

    /**
     * @param  array<int, array<string, mixed>>  $convertedComponents
     * @param  array<string, mixed>  $finalRule
     */
    private function calculateFinalValue(Collection $componentScores, array $convertedComponents, array $finalRule): float
    {
        $type = (string) ($finalRule['type'] ?? 'sum_converted_components');

        if ($type === 'pass_fail') {
            return 0.0;
        }

        if ($type === 'sum_converted_components') {
            return (float) collect($convertedComponents)->sum('grade');
        }

        if ($type === 'expression') {
            $value = 0.0;
            foreach (($finalRule['operations'] ?? []) as $operation) {
                $op = (string) $operation['op'];
                $value = match ($op) {
                    'add_component_percentage' => $value + $this->componentPercentage($componentScores, (string) $operation['component']),
                    'add_component_percentage_divided_by' => $value + (
                        $this->componentPercentage($componentScores, (string) $operation['component'])
                        / max(0.0001, (float) $operation['divisor'])
                    ),
                    'subtract' => $value - (float) $operation['value'],
                    'divide' => $value / max(0.0001, (float) $operation['value']),
                    default => throw new InvalidArgumentException("Unsupported Metropolia final operation [{$op}]."),
                };
            }

            return $value;
        }

        throw new InvalidArgumentException("Unsupported Metropolia final type [{$type}].");
    }

    private function componentPercentage(Collection $componentScores, string $componentCode): float
    {
        $score = $componentScores->get($componentCode);

        return $score instanceof ComponentScore ? $score->percentageOrZero() : 0.0;
    }

    private function roundFinal(float $value, string $rounding): float
    {
        return match ($rounding) {
            'nearest_integer_after_components' => (float) round($value),
            'none' => round($value, 2),
            default => throw new InvalidArgumentException("Unsupported Metropolia rounding [{$rounding}]."),
        };
    }

    private function averagePercentage(Collection $componentScores): ?float
    {
        $available = $componentScores->filter(fn(ComponentScore $score) => $score->percentage !== null);

        if ($available->isEmpty()) {
            return null;
        }

        return round((float) $available->avg(fn(ComponentScore $score) => $score->percentageOrZero()), 2);
    }

    /**
     * @param  array<string, mixed>  $scheme
     * @param  array<int, array<string, mixed>>  $requirements
     * @param  array<int, array<string, mixed>>  $convertedComponents
     * @return array<string, mixed>
     */
    private function breakdown(
        array $scheme,
        array $requirements,
        array $convertedComponents,
        float $rawFinal,
        ?float $finalGrade
    ): array {
        return [
            'engine' => 'metropolia_v1',
            'version' => $scheme['version'] ?? 1,
            'scale' => $scheme['scale'] ?? 'numeric_0_5',
            'rounding' => $scheme['rounding'] ?? 'nearest_integer_after_components',
            'requirements' => $requirements,
            'components' => $convertedComponents,
            'raw_final' => round($rawFinal, 4),
            'final_grade' => $finalGrade,
        ];
    }
}
```

- [ ] **Step 4: Run Metropolia calculator tests**

Run:

```bash
./scripts/dev.sh test tests/Feature/Academic/Grading/MetropoliaV1CalculatorTest.php
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Modules/Academic/Grading/MetropoliaV1Calculator.php tests/Feature/Academic/Grading/MetropoliaV1CalculatorTest.php
git commit -m "feat: add metropolia grading calculator"
```

---

### Task 5: Integrate Manual Aggregation And Course Finalization

**Files:**
- Create: `app/Modules/Academic/Actions/AggregateCourseOfferingGradesAction.php`
- Modify: `app/Services/CourseCompletionService.php`
- Test: `tests/Feature/Academic/Grading/AggregateCourseOfferingGradesActionTest.php`
- Test: `tests/Feature/Academic/Grading/CourseCompletionGradingSchemeTest.php`

- [ ] **Step 1: Write aggregation tests**

Create `tests/Feature/Academic/Grading/AggregateCourseOfferingGradesActionTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\AcademicRecord;
use App\Models\AssessmentComponent;
use App\Models\AssessmentComponentDetail;
use App\Models\AssessmentComponentDetailScore;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\Student;
use App\Models\SyllabusTemplate;
use App\Models\Unit;
use App\Modules\Academic\Actions\AggregateCourseOfferingGradesAction;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function courseWithScheme(?array $scheme): array
{
    $unit = Unit::factory()->create(['credit_points' => 5]);
    $student = Student::factory()->create();
    $syllabus = SyllabusTemplate::factory()->create([
        'unit_id' => $unit->id,
        'grading_scheme' => $scheme,
        'min_grade_threshold' => 60,
    ]);
    $course = CourseOffering::factory()->create([
        'unit_id' => $unit->id,
        'syllabus_template_id' => $syllabus->id,
        'is_canvas_synced' => false,
    ]);
    CourseRegistration::factory()->create([
        'student_id' => $student->id,
        'course_offering_id' => $course->id,
        'semester_id' => $course->semester_id,
        'registration_status' => 'registered',
        'credit_points' => 5,
        'credit_hours' => 5,
    ]);
    AcademicRecord::factory()->create([
        'student_id' => $student->id,
        'course_offering_id' => $course->id,
        'semester_id' => $course->semester_id,
        'unit_id' => $unit->id,
        'program_id' => $student->program_id,
        'campus_id' => $student->campus_id,
        'credit_points' => 5,
        'credit_hours' => 5,
        'final_percentage' => null,
        'final_letter_grade' => null,
        'grade_points' => null,
    ]);

    return [$course, $student];
}

it('aggregates a default weighted syllabus into academic records', function () {
    [$course, $student] = courseWithScheme(null);

    $assignment = AssessmentComponent::factory()->create([
        'syllabus_template_id' => $course->syllabus_template_id,
        'code' => 'ASSIGNMENT',
        'name' => 'Assignment',
        'weight' => 40,
        'type' => 'assignment',
    ]);
    $exam = AssessmentComponent::factory()->create([
        'syllabus_template_id' => $course->syllabus_template_id,
        'code' => 'EXAM',
        'name' => 'Exam',
        'weight' => 60,
        'type' => 'exam',
    ]);
    $assignmentDetail = AssessmentComponentDetail::factory()->create([
        'assessment_component_id' => $assignment->id,
        'weight' => 100,
        'max_points' => 100,
    ]);
    $examDetail = AssessmentComponentDetail::factory()->create([
        'assessment_component_id' => $exam->id,
        'weight' => 100,
        'max_points' => 100,
    ]);

    AssessmentComponentDetailScore::factory()->create([
        'student_id' => $student->id,
        'course_offering_id' => $course->id,
        'assessment_component_detail_id' => $assignmentDetail->id,
        'percentage_score' => 80,
        'points_earned' => 80,
        'score_status' => 'final',
        'score_excluded' => false,
    ]);
    AssessmentComponentDetailScore::factory()->create([
        'student_id' => $student->id,
        'course_offering_id' => $course->id,
        'assessment_component_detail_id' => $examDetail->id,
        'percentage_score' => 70,
        'points_earned' => 70,
        'score_status' => 'final',
        'score_excluded' => false,
    ]);

    app(AggregateCourseOfferingGradesAction::class)->execute($course);

    $record = AcademicRecord::where('student_id', $student->id)->where('course_offering_id', $course->id)->firstOrFail();

    expect((float) $record->final_percentage)->toBe(74.0)
        ->and($record->final_letter_grade)->toBe('B')
        ->and((float) $record->grade_points)->toBe(2.96)
        ->and($record->grade_breakdown['rule_engine']['breakdown']['engine'])->toBe('default_weighted_percentage');
});
```

- [ ] **Step 2: Run aggregation test to verify it fails**

Run:

```bash
./scripts/dev.sh test tests/Feature/Academic/Grading/AggregateCourseOfferingGradesActionTest.php
```

Expected: FAIL because the action does not exist.

- [ ] **Step 3: Implement aggregation action**

Create `app/Modules/Academic/Actions/AggregateCourseOfferingGradesAction.php`:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Academic\Actions;

use App\Models\AcademicRecord;
use App\Models\AssessmentComponent;
use App\Models\AssessmentComponentDetailScore;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Modules\Academic\Grading\ComponentScoreBuilder;
use App\Modules\Academic\Grading\GradingCalculatorResolver;
use Illuminate\Support\Facades\Log;

final class AggregateCourseOfferingGradesAction
{
    public function __construct(
        private readonly ComponentScoreBuilder $componentScoreBuilder,
        private readonly GradingCalculatorResolver $calculatorResolver,
    ) {}

    public function execute(CourseOffering $courseOffering): void
    {
        $courseOffering->loadMissing('syllabusTemplate');

        $syllabus = $courseOffering->syllabusTemplate;
        if (! $syllabus) {
            return;
        }

        $components = AssessmentComponent::query()
            ->where('syllabus_template_id', $syllabus->id)
            ->with('details')
            ->orderBy('sort_order')
            ->orderBy('created_at')
            ->get();

        $registeredStudentIds = CourseRegistration::query()
            ->where('course_offering_id', $courseOffering->id)
            ->whereIn('registration_status', ['registered', 'confirmed', 'completed'])
            ->pluck('student_id')
            ->all();

        $allScores = AssessmentComponentDetailScore::query()
            ->where('course_offering_id', $courseOffering->id)
            ->whereIn('student_id', $registeredStudentIds)
            ->where('score_excluded', false)
            ->whereIn('score_status', ['final', 'provisional', 'draft'])
            ->get()
            ->groupBy('student_id');

        $gradedDetailIds = $allScores->flatten()->pluck('assessment_component_detail_id')->unique()->map(fn($id) => (int) $id)->all();
        $scheme = $syllabus->grading_scheme;
        $calculator = $this->calculatorResolver->resolve($scheme);

        foreach ($registeredStudentIds as $studentId) {
            $studentScores = $allScores->get($studentId) ?? collect();
            $componentScores = $this->componentScoreBuilder->build($components, $studentScores, $gradedDetailIds);
            $result = $calculator->calculate($componentScores, $scheme ?? []);

            AcademicRecord::query()
                ->where('course_offering_id', $courseOffering->id)
                ->where('student_id', $studentId)
                ->update($result->academicRecordUpdates());
        }

        Log::info('Aggregated course offering grades', [
            'course_offering_id' => $courseOffering->id,
            'students_count' => count($registeredStudentIds),
            'grading_engine' => $scheme['engine'] ?? 'default_weighted_percentage',
        ]);
    }
}
```

- [ ] **Step 4: Replace aggregation method body in CourseCompletionService**

Modify `app/Services/CourseCompletionService.php`.

Add import:

```php
use App\Modules\Academic\Actions\AggregateCourseOfferingGradesAction;
```

Add constructor dependency:

```php
public function __construct(
    protected EgcLevelProgressionService $egcService,
    protected CourseSurveyService $courseSurveyService,
    protected PublishDomainEventAction $publishDomainEventAction,
    protected AggregateCourseOfferingGradesAction $aggregateCourseOfferingGradesAction,
) {}
```

Replace `aggregateManualGrades()` body with:

```php
public function aggregateManualGrades(CourseOffering $courseOffering): void
{
    $this->aggregateCourseOfferingGradesAction->execute($courseOffering);
}
```

- [ ] **Step 5: Add finalization test**

Create `tests/Feature/Academic/Grading/CourseCompletionGradingSchemeTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\AcademicRecord;
use App\Models\AssessmentComponent;
use App\Models\AssessmentComponentDetail;
use App\Models\AssessmentComponentDetailScore;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\Student;
use App\Models\SyllabusTemplate;
use App\Models\Unit;
use App\Services\CourseCompletionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('finalizes metropolia pass status from rule engine result', function () {
    $unit = Unit::factory()->create(['credit_points' => 5, 'unit_type' => 'course']);
    $student = Student::factory()->create();
    $scheme = [
        'engine' => 'metropolia_v1',
        'version' => 1,
        'scale' => 'numeric_0_5',
        'rounding' => 'nearest_integer_after_components',
        'requirements' => [
            ['type' => 'component_min_percentage', 'component' => 'ASSIGNMENTS', 'threshold' => 40],
        ],
        'components' => [
            ['component' => 'ASSIGNMENTS', 'conversion' => ['type' => 'none']],
            [
                'component' => 'EXAM',
                'conversion' => [
                    'type' => 'linear_percentage_to_grade',
                    'min_percentage' => 40,
                    'min_grade' => 1,
                    'max_percentage' => 88,
                    'max_grade' => 5,
                ],
            ],
        ],
        'final' => ['type' => 'sum_converted_components', 'max_grade' => 5],
    ];
    $syllabus = SyllabusTemplate::factory()->create([
        'unit_id' => $unit->id,
        'grading_scheme' => $scheme,
        'min_grade_threshold' => 60,
    ]);
    $course = CourseOffering::factory()->create([
        'unit_id' => $unit->id,
        'syllabus_template_id' => $syllabus->id,
        'is_canvas_synced' => false,
    ]);
    CourseRegistration::factory()->create([
        'student_id' => $student->id,
        'course_offering_id' => $course->id,
        'semester_id' => $course->semester_id,
        'registration_status' => 'registered',
        'credit_points' => 5,
        'credit_hours' => 5,
    ]);
    AcademicRecord::factory()->create([
        'student_id' => $student->id,
        'course_offering_id' => $course->id,
        'semester_id' => $course->semester_id,
        'unit_id' => $unit->id,
        'program_id' => $student->program_id,
        'campus_id' => $student->campus_id,
        'credit_points' => 5,
        'credit_hours' => 5,
        'final_percentage' => null,
        'final_letter_grade' => null,
        'grade_points' => null,
        'grade_status' => 'in_progress',
    ]);

    $assignment = AssessmentComponent::factory()->create([
        'syllabus_template_id' => $syllabus->id,
        'code' => 'ASSIGNMENTS',
        'weight' => 0,
        'type' => 'assignment',
    ]);
    $exam = AssessmentComponent::factory()->create([
        'syllabus_template_id' => $syllabus->id,
        'code' => 'EXAM',
        'weight' => 100,
        'type' => 'exam',
    ]);
    $assignmentDetail = AssessmentComponentDetail::factory()->create([
        'assessment_component_id' => $assignment->id,
        'weight' => 100,
        'max_points' => 100,
    ]);
    $examDetail = AssessmentComponentDetail::factory()->create([
        'assessment_component_id' => $exam->id,
        'weight' => 100,
        'max_points' => 100,
    ]);
    AssessmentComponentDetailScore::factory()->create([
        'student_id' => $student->id,
        'course_offering_id' => $course->id,
        'assessment_component_detail_id' => $assignmentDetail->id,
        'percentage_score' => 45,
        'points_earned' => 45,
        'score_status' => 'final',
        'score_excluded' => false,
    ]);
    AssessmentComponentDetailScore::factory()->create([
        'student_id' => $student->id,
        'course_offering_id' => $course->id,
        'assessment_component_detail_id' => $examDetail->id,
        'percentage_score' => 64,
        'points_earned' => 64,
        'score_status' => 'final',
        'score_excluded' => false,
    ]);

    app(CourseCompletionService::class)->finalizeCourse($course);

    $record = AcademicRecord::where('student_id', $student->id)->where('course_offering_id', $course->id)->firstOrFail();

    expect($record->grade_status)->toBe('final')
        ->and($record->final_letter_grade)->toBe('3')
        ->and((float) $record->grade_points)->toBe(3.0)
        ->and($record->is_passed)->toBeTrue()
        ->and((float) $record->credit_points_earned)->toBe(5.0);
});
```

- [ ] **Step 6: Update finalization logic**

In `finalizeAcademicRecords()`, replace the grade decision block with this shape:

```php
$finalPercentage = (float) ($record->final_percentage ?? 0);
$breakdown = is_array($record->grade_breakdown) ? $record->grade_breakdown : [];
$enginePassed = data_get($breakdown, 'rule_engine.passed');

$isPassing = is_bool($enginePassed)
    ? $enginePassed
    : $finalPercentage >= $passingThreshold;

$gradePoints = $record->grade_points !== null
    ? (float) $record->grade_points
    : AcademicRecord::calculateGradePoints($finalPercentage);

$finalLetterGrade = $record->final_letter_grade
    ?: AcademicRecord::calculateLetterGrade($finalPercentage);
```

In the `$record->update([...])` array, keep the rule-engine grade and use a configurable quality-points base:

```php
$qualityBase = config('academic.gpa.source') === 'grade_points'
    ? $gradePoints
    : $finalPercentage;

$record->update([
    'grade_status' => 'final',
    'grade_finalized_date' => now(),
    'final_letter_grade' => $finalLetterGrade,
    'grade_points' => $gradePoints,
    'completion_status' => 'completed',
    'is_passed' => $finalPassed,
    'credit_points' => $creditPoints,
    'credit_points_earned' => $finalPassed ? $creditPoints : 0,
    'credit_hours_earned' => $finalPassed ? $record->credit_hours : 0,
    'affects_graduation_requirement' => true,
    'satisfies_prerequisite' => $finalPassed,
    'administrative_notes' => $cleanNotes ?: null,
    'quality_points' => $qualityBase * $creditPoints,
]);
```

- [ ] **Step 7: Run aggregation and finalization tests**

Run:

```bash
./scripts/dev.sh test tests/Feature/Academic/Grading/AggregateCourseOfferingGradesActionTest.php
./scripts/dev.sh test tests/Feature/Academic/Grading/CourseCompletionGradingSchemeTest.php
```

Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add app/Modules/Academic/Actions/AggregateCourseOfferingGradesAction.php \
  app/Services/CourseCompletionService.php \
  tests/Feature/Academic/Grading/AggregateCourseOfferingGradesActionTest.php \
  tests/Feature/Academic/Grading/CourseCompletionGradingSchemeTest.php
git commit -m "feat: aggregate course grades through grading schemes"
```

---

### Task 6: Make GPA And Pass Helpers Scale-Aware

**Files:**
- Modify: `app/Models/AcademicRecord.php`
- Modify: `app/Models/CourseRegistration.php`
- Modify: `app/Actions/Academic/CalculateStudentSemesterGpaAction.php`
- Modify: `app/Actions/Academic/CalculateCumulativeGpaAction.php`
- Modify: `app/Actions/Academic/DetermineAcademicStandingAction.php`
- Test: `tests/Feature/Academic/Grading/GpaSourceConfigurationTest.php`
- Existing tests: `tests/Feature/Academic/Gpa`

- [ ] **Step 1: Write GPA source tests**

Create `tests/Feature/Academic/Grading/GpaSourceConfigurationTest.php`:

```php
<?php

declare(strict_types=1);

use App\Actions\Academic\CalculateStudentSemesterGpaAction;
use App\Actions\Academic\DetermineAcademicStandingAction;
use App\Models\AcademicRecord;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('uses final percentage as default GPA source', function () {
    config()->set('academic.gpa.source', 'final_percentage');
    $student = Student::factory()->create();
    $semester = Semester::factory()->create();
    $unit = Unit::factory()->create(['credit_points' => 5]);

    AcademicRecord::factory()->create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'unit_id' => $unit->id,
        'credit_points' => 5,
        'final_percentage' => 80,
        'grade_points' => 4,
        'grade_status' => 'final',
        'is_passed' => true,
    ]);

    $result = app(CalculateStudentSemesterGpaAction::class)->execute($student, $semester->id);

    expect($result['gpa'])->toBe(80.0);
});

it('uses grade points when configured for Metropolia databases', function () {
    config()->set('academic.gpa.source', 'grade_points');
    $student = Student::factory()->create();
    $semester = Semester::factory()->create();
    $unit = Unit::factory()->create(['credit_points' => 5]);

    AcademicRecord::factory()->create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'unit_id' => $unit->id,
        'credit_points' => 5,
        'final_percentage' => 80,
        'grade_points' => 4,
        'grade_status' => 'final',
        'is_passed' => true,
    ]);

    $result = app(CalculateStudentSemesterGpaAction::class)->execute($student, $semester->id);

    expect($result['gpa'])->toBe(4.0);
});

it('uses configurable academic standing threshold', function () {
    config()->set('academic.gpa.normal_standing_threshold', 1.0);

    expect(app(DetermineAcademicStandingAction::class)->execute(1.0))->toBe('normal')
        ->and(app(DetermineAcademicStandingAction::class)->execute(0.99))->toBe('warning');
});
```

- [ ] **Step 2: Run GPA source tests to verify they fail**

Run:

```bash
./scripts/dev.sh test tests/Feature/Academic/Grading/GpaSourceConfigurationTest.php
```

Expected: FAIL because GPA actions always use `final_percentage` and standing threshold is hardcoded.

- [ ] **Step 3: Update AcademicRecord pass helper**

Modify `AcademicRecord::isPassed()`:

```php
public function isPassed(): bool
{
    if ($this->is_passed !== null) {
        return (bool) $this->is_passed;
    }

    return $this->override_pass || ($this->completion_status === 'completed' && (float) $this->grade_points > 0);
}
```

- [ ] **Step 4: Allow 0-5 grade points on course registrations**

Modify `app/Models/CourseRegistration.php` validation rule:

```php
'grade_points' => ['nullable', 'numeric', 'min:0', 'max:5'],
```

- [ ] **Step 5: Update semester GPA calculation**

In `app/Actions/Academic/CalculateStudentSemesterGpaAction.php`, replace the quality-point sum closure:

```php
$source = (string) config('academic.gpa.source', 'final_percentage');

$totalQualityPoints = $records->sum(function ($record) use ($source) {
    $base = $source === 'grade_points'
        ? (float) ($record->grade_points ?? 0)
        : (float) ($record->final_percentage ?? 0);

    return $base * (float) $record->credit_points;
});
```

- [ ] **Step 6: Update cumulative GPA calculation**

In `app/Actions/Academic/CalculateCumulativeGpaAction.php`, use the same source-aware closure:

```php
$source = (string) config('academic.gpa.source', 'final_percentage');

$totalQualityPoints = $records->sum(function ($record) use ($source) {
    $base = $source === 'grade_points'
        ? (float) ($record->grade_points ?? 0)
        : (float) ($record->final_percentage ?? 0);

    return $base * (float) $record->credit_points;
});
```

- [ ] **Step 7: Update academic standing threshold**

Modify `app/Actions/Academic/DetermineAcademicStandingAction.php`:

```php
public const NORMAL_STANDING_THRESHOLD = 50.0;

public function execute(float $gpa): string
{
    $threshold = (float) config('academic.gpa.normal_standing_threshold', self::NORMAL_STANDING_THRESHOLD);

    if ($gpa >= $threshold) {
        return 'normal';
    }

    return 'warning';
}
```

- [ ] **Step 8: Run GPA tests**

Run:

```bash
./scripts/dev.sh test tests/Feature/Academic/Grading/GpaSourceConfigurationTest.php
./scripts/dev.sh test tests/Feature/Academic/Gpa
```

Expected: PASS. Existing GPA tests prove default percentage behavior is preserved.

- [ ] **Step 9: Commit**

```bash
git add app/Models/AcademicRecord.php \
  app/Models/CourseRegistration.php \
  app/Actions/Academic/CalculateStudentSemesterGpaAction.php \
  app/Actions/Academic/CalculateCumulativeGpaAction.php \
  app/Actions/Academic/DetermineAcademicStandingAction.php \
  tests/Feature/Academic/Grading/GpaSourceConfigurationTest.php
git commit -m "feat: support configurable academic GPA source"
```

---

### Task 7: Protect Canvas Custom-Scheme Courses

**Files:**
- Modify: `app/Services/Canvas/CanvasGradeSyncService.php`
- Test: `tests/Feature/Academic/Grading/CanvasCustomSchemeAggregationTest.php`

- [ ] **Step 1: Write Canvas boundary test**

Create `tests/Feature/Academic/Grading/CanvasCustomSchemeAggregationTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\CourseOffering;
use App\Models\SyllabusTemplate;
use App\Models\Unit;
use App\Modules\Academic\Actions\AggregateCourseOfferingGradesAction;
use App\Services\Canvas\CanvasApiService;
use App\Services\Canvas\CanvasGradeSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('detects custom grading schemes as local aggregate source for canvas courses', function () {
    $unit = Unit::factory()->create();
    $syllabus = SyllabusTemplate::factory()->create([
        'unit_id' => $unit->id,
        'grading_scheme' => [
            'engine' => 'metropolia_v1',
            'version' => 1,
            'scale' => 'numeric_0_5',
            'components' => [],
            'final' => ['type' => 'sum_converted_components', 'max_grade' => 5],
        ],
    ]);
    $course = CourseOffering::factory()->create([
        'unit_id' => $unit->id,
        'syllabus_template_id' => $syllabus->id,
        'is_canvas_synced' => true,
    ]);

    $service = new CanvasGradeSyncService(
        app(CanvasApiService::class),
        app(AggregateCourseOfferingGradesAction::class),
    );

    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('shouldUseCanvasTotal');
    $method->setAccessible(true);

    expect($method->invoke($service, $course->fresh('syllabusTemplate')))->toBeFalse();
});
```

- [ ] **Step 2: Run Canvas boundary test to verify it fails**

Run:

```bash
./scripts/dev.sh test tests/Feature/Academic/Grading/CanvasCustomSchemeAggregationTest.php
```

Expected: FAIL because `CanvasGradeSyncService` constructor and method do not match.

- [ ] **Step 3: Inject aggregation action**

Modify constructor in `app/Services/Canvas/CanvasGradeSyncService.php`:

```php
use App\Modules\Academic\Actions\AggregateCourseOfferingGradesAction;
```

```php
public function __construct(
    private CanvasApiService $apiService,
    private AggregateCourseOfferingGradesAction $aggregateCourseOfferingGradesAction,
) {}
```

- [ ] **Step 4: Add Canvas total guard**

Add private method:

```php
private function shouldUseCanvasTotal(CourseOffering $courseOffering): bool
{
    $courseOffering->loadMissing('syllabusTemplate');

    return empty($courseOffering->syllabusTemplate?->grading_scheme);
}
```

In the block that syncs Canvas total to `AcademicRecord`, wrap the total-write section:

```php
if (! $this->shouldUseCanvasTotal($courseOffering)) {
    Log::info('Skipped Canvas total grade because course uses local grading scheme', [
        'course_offering_id' => $courseOffering->id,
        'grading_engine' => $courseOffering->syllabusTemplate?->grading_scheme['engine'] ?? null,
    ]);

    return $result;
}
```

At the end of `performSync()` after all student detail scores are processed, add:

```php
if (! $this->shouldUseCanvasTotal($courseOffering)) {
    $this->aggregateCourseOfferingGradesAction->execute($courseOffering);
}
```

- [ ] **Step 5: Run Canvas boundary test**

Run:

```bash
./scripts/dev.sh test tests/Feature/Academic/Grading/CanvasCustomSchemeAggregationTest.php
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Services/Canvas/CanvasGradeSyncService.php tests/Feature/Academic/Grading/CanvasCustomSchemeAggregationTest.php
git commit -m "feat: respect local grading schemes for canvas totals"
```

---

### Task 8: Document Scheme Contract And Run Final Verification

**Files:**
- Create: `docs/features/academic/grading-rule-engine.md`
- Modify: `docs/features/academic/Grading_Schemes_Metropolia.md`

- [ ] **Step 1: Add grading engine documentation**

Create `docs/features/academic/grading-rule-engine.md`:

```markdown
# Academic Grading Rule Engine

Last updated: 2026-06-14
Owner: Platform Team
Status: Planned implementation contract

## Purpose

Swinx supports multiple schools using the same repository but separate
databases. A school database may configure a syllabus-specific grading scheme
without changing the default behavior for other deployments.

## Storage

The scheme lives in `syllabus_templates.grading_scheme` as JSON.

When `grading_scheme` is null, Swinx uses the existing weighted-percentage
calculation.

## Supported Engines

### default_weighted_percentage

Uses assessment detail percentages, component detail weights, and component
weights to produce `academic_records.final_percentage`.

### metropolia_v1

Supports:

- requirement gates such as minimum assignment percentage.
- pass/fail final outcomes.
- linear percentage-to-grade conversion.
- threshold percentage-to-grade conversion.
- direct points conversion.
- arithmetic final formulas.
- final rounding after component conversion.

## Example: Metropolia Programming

```json
{
  "engine": "metropolia_v1",
  "version": 1,
  "scale": "numeric_0_5",
  "rounding": "nearest_integer_after_components",
  "requirements": [
    {
      "type": "component_min_percentage",
      "component": "ASSIGNMENTS",
      "threshold": 40
    }
  ],
  "components": [
    {
      "component": "ASSIGNMENTS",
      "conversion": { "type": "none" }
    },
    {
      "component": "EXAM",
      "conversion": {
        "type": "linear_percentage_to_grade",
        "min_percentage": 40,
        "min_grade": 1,
        "max_percentage": 88,
        "max_grade": 5
      }
    }
  ],
  "final": {
    "type": "sum_converted_components",
    "max_grade": 5
  }
}
```

## GPA Source

Default databases use:

```env
ACADEMIC_GPA_SOURCE=final_percentage
ACADEMIC_NORMAL_STANDING_THRESHOLD=50
```

Metropolia-style databases can use:

```env
ACADEMIC_GPA_SOURCE=grade_points
ACADEMIC_NORMAL_STANDING_THRESHOLD=1
```

## Audit

Each calculated record writes `academic_records.grade_breakdown.rule_engine`
with the engine name, requirement results, converted components, raw final
value, final grade, and pass outcome.

## Unresolved Questions

- Should a later story add a guarded syllabus UI editor for `grading_scheme`?
- Should Metropolia production rollout include a historical recalculation job?
```

- [ ] **Step 2: Add pointer to Metropolia reference doc**

Append this to `docs/features/academic/Grading_Schemes_Metropolia.md`:

```markdown

---

Implementation note: the Swinx rule-engine contract for representing these
rules lives in `docs/features/academic/grading-rule-engine.md`.
```

- [ ] **Step 3: Run targeted tests**

Run:

```bash
./scripts/dev.sh test tests/Feature/Academic/Grading
./scripts/dev.sh test tests/Feature/Academic/Gpa
```

Expected: PASS.

- [ ] **Step 4: Run formatting and static checks**

Run:

```bash
./scripts/dev.sh artisan pint
./scripts/dev.sh npm run type-check
./scripts/dev.sh npm run lint
git diff --check
```

Expected:

- Pint passes.
- Type-check and lint pass or report only pre-existing unrelated baseline errors.
- `git diff --check` passes.

- [ ] **Step 5: Record Harness trace**

Run:

```bash
./scripts/harness trace --summary "Implemented syllabus/campus grading rule engine first slice" --story S-001-syllabus-campus-grading-rule-engine --actions "added grading_scheme storage, default calculator, metropolia calculator, aggregation integration, GPA source config, Canvas boundary, docs" --changed "config/academic.php,syllabus_templates migration,app/Modules/Academic/Grading,CourseCompletionService,CanvasGradeSyncService,GPA actions,academic docs" --outcome completed --friction "Portal UI rule-builder and historical recalculation remain separate follow-up work"
```

Expected: trace recorded.

- [ ] **Step 6: Commit**

```bash
git add docs/features/academic/grading-rule-engine.md docs/features/academic/Grading_Schemes_Metropolia.md
git commit -m "docs: document academic grading rule engine"
```

---

## Self-Review

Spec coverage:

- Small rule engine by syllabus/campus: Tasks 1-5.
- Existing behavior preserved: Task 3 and Task 5 default aggregation test.
- Metropolia formulas: Task 4.
- Separate databases, no shared tenant isolation: Scope Guardrails and Task 8 docs.
- Course finalization and transcript fields: Task 5.
- GPA scale risk: Task 6.
- Canvas boundary: Task 7.
- Docs and verification: Task 8.

Placeholder scan:

- No empty task sections.
- No unspecified test commands.
- No undefined file paths.

Type consistency:

- `ComponentScore`, `GradingResult`, and `GradingCalculator::calculate()` signatures are reused consistently.
- `grading_scheme` is an array cast in model and request validation.
- `grade_breakdown.rule_engine.passed` is the finalization bridge.
