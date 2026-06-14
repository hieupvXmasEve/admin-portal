# Grading Rollout Validation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a read-only validation command and runbook that prove a Metropolia pilot database is ready for grading-rule-engine rollout.

**Architecture:** Build a query/report action that checks scheme coverage, sample academic outcomes, Canvas boundary behavior, GPA source configuration, and portal readiness. The command writes a JSON report under storage and exits nonzero when required checks fail.

**Tech Stack:** Laravel 13, PHP 8.4, Artisan command, Laravel storage, Pest feature tests, portal validation commands, Docker wrapper scripts.

---

## Scope Guardrails

This story is read-only against academic data. It does not mutate `academic_records`, `syllabus_templates`, or portal code unless validation reveals a missed S-004 gap and the user approves that follow-up.

## File Structure

Create:

- `app/Modules/Academic/Queries/BuildGradingRolloutReportQuery.php` - read-only rollout report builder.
- `app/Console/Commands/Academic/ValidateGradingRolloutCommand.php` - CLI wrapper.
- `docs/runbooks/academic-grading-rollout-validation.md`
- `tests/Feature/Academic/Grading/GradingRolloutValidationCommandTest.php`

Modify:

- `docs/features/academic/grading-rule-engine.md`
- `docs/stories/E-academic-grading-rule-engine/S-006-grading-rollout-validation/validation.md`

---

### Task 1: Add Read-Only Report Builder

**Files:**
- Create: `tests/Feature/Academic/Grading/GradingRolloutValidationCommandTest.php`
- Create: `app/Modules/Academic/Queries/BuildGradingRolloutReportQuery.php`

- [ ] **Step 1: Write failing report query tests**

```php
<?php

declare(strict_types=1);

use App\Models\SyllabusTemplate;
use App\Models\Unit;
use App\Modules\Academic\Queries\BuildGradingRolloutReportQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('fails rollout coverage when an active template has no grading scheme', function () {
    $unit = Unit::factory()->create();
    SyllabusTemplate::factory()->create(['unit_id' => $unit->id, 'is_active' => true, 'grading_scheme' => null]);

    $report = app(BuildGradingRolloutReportQuery::class)->execute(['scheme' => 'metropolia_v1']);

    expect($report['passed'])->toBeFalse()
        ->and($report['checks']['scheme_coverage']['passed'])->toBeFalse()
        ->and($report['checks']['scheme_coverage']['missing_count'])->toBe(1);
});

it('passes rollout coverage when selected active templates have schemes', function () {
    $unit = Unit::factory()->create();
    SyllabusTemplate::factory()->create([
        'unit_id' => $unit->id,
        'is_active' => true,
        'grading_scheme' => ['engine' => 'metropolia_v1', 'version' => 1],
    ]);

    $report = app(BuildGradingRolloutReportQuery::class)->execute(['scheme' => 'metropolia_v1']);

    expect($report['checks']['scheme_coverage']['passed'])->toBeTrue();
});
```

- [ ] **Step 2: Run the test and verify it fails**

Run:

```bash
./scripts/dev.sh test tests/Feature/Academic/Grading/GradingRolloutValidationCommandTest.php
```

Expected: FAIL because the query does not exist.

- [ ] **Step 3: Implement report query**

```php
<?php

declare(strict_types=1);

namespace App\Modules\Academic\Queries;

use App\Models\CourseOffering;
use App\Models\SyllabusTemplate;

final class BuildGradingRolloutReportQuery
{
    /** @return array<string, mixed> */
    public function execute(array $filters): array
    {
        $scheme = (string) ($filters['scheme'] ?? 'metropolia_v1');
        $templates = SyllabusTemplate::query()
            ->where('is_active', true)
            ->when($filters['campus_id'] ?? null, fn ($query, $campusId) => $query->where('applicable_campus_id', $campusId))
            ->when($filters['program_id'] ?? null, fn ($query, $programId) => $query->where('applicable_program_id', $programId))
            ->get();

        $missing = $templates->filter(fn (SyllabusTemplate $template) => ($template->grading_scheme['engine'] ?? null) !== $scheme);

        $canvasCustomCount = CourseOffering::query()
            ->where('is_canvas_synced', true)
            ->whereHas('syllabusTemplate', fn ($query) => $query->where('grading_scheme->engine', $scheme))
            ->count();

        $checks = [
            'scheme_coverage' => [
                'passed' => $missing->isEmpty(),
                'active_template_count' => $templates->count(),
                'missing_count' => $missing->count(),
                'missing_template_ids' => $missing->pluck('id')->values()->all(),
            ],
            'canvas_boundary' => [
                'passed' => $canvasCustomCount === 0,
                'custom_canvas_course_count' => $canvasCustomCount,
            ],
            'gpa_source' => [
                'passed' => in_array(config('academic.gpa.source'), ['final_percentage', 'grade_points'], true),
                'source' => config('academic.gpa.source'),
            ],
        ];

        return [
            'report_id' => 'grading-rollout-'.now()->format('Ymd-His'),
            'filters' => $filters,
            'passed' => collect($checks)->every(fn (array $check) => $check['passed'] === true),
            'checks' => $checks,
        ];
    }
}
```

- [ ] **Step 4: Run report query tests**

Run:

```bash
./scripts/dev.sh test tests/Feature/Academic/Grading/GradingRolloutValidationCommandTest.php
```

Expected: PASS for query coverage cases.

### Task 2: Add Validation Command

**Files:**
- Modify: `tests/Feature/Academic/Grading/GradingRolloutValidationCommandTest.php`
- Create: `app/Console/Commands/Academic/ValidateGradingRolloutCommand.php`

- [ ] **Step 1: Add command tests**

```php
it('writes a rollout validation report and exits nonzero when checks fail', function () {
    $unit = Unit::factory()->create();
    SyllabusTemplate::factory()->create(['unit_id' => $unit->id, 'is_active' => true, 'grading_scheme' => null]);

    $this->artisan('academic:validate-grading-rollout', [
        '--scheme' => 'metropolia_v1',
        '--output' => 'json',
    ])->assertExitCode(1);
});

it('writes a rollout validation report and exits zero when checks pass', function () {
    $unit = Unit::factory()->create();
    SyllabusTemplate::factory()->create([
        'unit_id' => $unit->id,
        'is_active' => true,
        'grading_scheme' => ['engine' => 'metropolia_v1', 'version' => 1],
    ]);

    $this->artisan('academic:validate-grading-rollout', [
        '--scheme' => 'metropolia_v1',
        '--output' => 'json',
    ])->assertExitCode(0);
});
```

- [ ] **Step 2: Run command tests and verify they fail**

Run:

```bash
./scripts/dev.sh test tests/Feature/Academic/Grading/GradingRolloutValidationCommandTest.php
```

Expected: FAIL because the command does not exist.

- [ ] **Step 3: Implement command**

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands\Academic;

use App\Modules\Academic\Queries\BuildGradingRolloutReportQuery;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

final class ValidateGradingRolloutCommand extends Command
{
    protected $signature = 'academic:validate-grading-rollout
        {--campus-id= : Limit to a campus}
        {--program-id= : Limit to a program}
        {--semester-id= : Include semester in report filters}
        {--scheme=metropolia_v1 : Expected scheme engine}
        {--sample-course-offering-id= : Include sample course in report filters}
        {--output=json : Output format}';

    protected $description = 'Validate readiness for grading rule engine rollout';

    public function handle(BuildGradingRolloutReportQuery $query): int
    {
        $filters = array_filter([
            'campus_id' => $this->option('campus-id') ? (int) $this->option('campus-id') : null,
            'program_id' => $this->option('program-id') ? (int) $this->option('program-id') : null,
            'semester_id' => $this->option('semester-id') ? (int) $this->option('semester-id') : null,
            'scheme' => (string) $this->option('scheme'),
            'sample_course_offering_id' => $this->option('sample-course-offering-id') ? (int) $this->option('sample-course-offering-id') : null,
        ]);

        $report = $query->execute($filters);
        $path = "academic-grading-rollout/{$report['report_id']}.json";

        Storage::disk('local')->put($path, json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        $this->info("Rollout report: {$report['report_id']}");
        $this->info("Report path: storage/app/{$path}");

        foreach ($report['checks'] as $name => $check) {
            $this->line($name.': '.($check['passed'] ? 'PASS' : 'FAIL'));
        }

        return $report['passed'] ? self::SUCCESS : self::FAILURE;
    }
}
```

- [ ] **Step 4: Run command tests**

Run:

```bash
./scripts/dev.sh test tests/Feature/Academic/Grading/GradingRolloutValidationCommandTest.php
```

Expected: PASS.

### Task 3: Add Runbook And Portal Evidence Checklist

**Files:**
- Create: `docs/runbooks/academic-grading-rollout-validation.md`
- Modify: `docs/features/academic/grading-rule-engine.md`
- Modify: `docs/stories/E-academic-grading-rule-engine/S-006-grading-rollout-validation/validation.md`

- [ ] **Step 1: Add runbook**

````markdown
# Academic Grading Rollout Validation Runbook

Run the read-only validator in the target school database:

```bash
./scripts/dev.sh artisan academic:validate-grading-rollout --scheme=metropolia_v1 --output=json
```

For a scoped pilot:

```bash
./scripts/dev.sh artisan academic:validate-grading-rollout --campus-id=1 --program-id=2 --semester-id=3 --scheme=metropolia_v1 --output=json
```

Attach the generated `storage/app/academic-grading-rollout/<report-id>.json`
to the Harness trace.

Portal evidence:

```bash
./scripts/portal-status.sh
(cd FE/student-nuxt && pnpm lint && pnpm typecheck && pnpm build)
(cd FE/lecturer-nuxt && pnpm lint && pnpm typecheck && pnpm build)
```
````

- [ ] **Step 2: Run final verification**

Run:

```bash
./scripts/portal-status.sh
./scripts/dev.sh test tests/Feature/Academic/Grading/GradingRolloutValidationCommandTest.php
./scripts/dev.sh artisan academic:validate-grading-rollout --scheme=metropolia_v1 --output=json
(cd FE/student-nuxt && pnpm lint && pnpm typecheck && pnpm build)
(cd FE/lecturer-nuxt && pnpm lint && pnpm typecheck && pnpm build)
./scripts/dev.sh artisan pint app/Modules/Academic app/Console/Commands/Academic tests/Feature/Academic/Grading
git diff --check -- app docs/stories/E-academic-grading-rule-engine docs/superpowers/plans docs/runbooks
```

Expected: command exits according to local fixture readiness; tests and formatting pass. Record rollout command exit code and report path in Harness trace.

- [ ] **Step 3: Update Harness**

Run:

```bash
./scripts/harness story update --id S-006-grading-rollout-validation --status implemented
./scripts/harness trace --story S-006-grading-rollout-validation --summary "Grading rollout validation implemented" --actions "Added read-only rollout report command, runbook, and validation tests" --changed "app/Modules/Academic app/Console/Commands/Academic tests/Feature/Academic/Grading docs/runbooks" --outcome completed
```

## Self-Review

Spec coverage: S-006 covers read-only rollout report, scheme coverage, Canvas boundary, GPA source, portal evidence, and runbook.

Self-audit scan: completed with zero matches for the forbidden terms pattern.

Type consistency: `BuildGradingRolloutReportQuery`, `ValidateGradingRolloutCommand`, and `academic:validate-grading-rollout` are named consistently across tests and commands.
