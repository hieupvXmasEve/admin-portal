# Metropolia Scheme Pack And Mapping Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Convert the Metropolia grading reference into a validated, repo-tracked scheme pack that can be applied to one school database through explicit syllabus mappings.

**Architecture:** Keep the canonical Metropolia pack in docs as JSON, load it through a small Academic support class, validate it with the same rule vocabulary used by `metropolia_v1`, and apply it through a dry-run-first Artisan command. Schools share the repo but not the database, so the mapping file is local to the target database and no shared tenant table is introduced.

**Tech Stack:** Laravel 13, PHP 8.4, Pest feature tests, JSON fixtures, Artisan commands, existing Docker wrapper scripts.

---

## Scope Guardrails

This story depends on `S-001-syllabus-campus-grading-rule-engine`. It does not build UI, change portal APIs, or recalculate existing records. It only creates the scheme pack and the operator path for assigning schemes to syllabus templates.

## File Structure

Create:

- `docs/features/academic/metropolia-grading-schemes.json` - canonical scheme pack fixture.
- `docs/features/academic/metropolia-component-mapping.md` - operator guide for mapping local templates to scheme keys.
- `app/Modules/Academic/Grading/Support/MetropoliaSchemeCatalog.php` - loads and validates the JSON fixture.
- `app/Modules/Academic/Grading/Support/GradingSchemeValidator.php` - validates one scheme contract before storage.
- `app/Modules/Academic/Actions/ApplyGradingSchemePackAction.php` - applies a pack mapping to `SyllabusTemplate` records.
- `app/Console/Commands/Academic/ApplyGradingSchemePackCommand.php` - dry-run and commit CLI wrapper.
- `tests/Feature/Academic/Grading/MetropoliaSchemePackTest.php`
- `tests/Feature/Academic/Grading/ApplyMetropoliaSchemePackCommandTest.php`

Modify:

- `docs/features/academic/Grading_Schemes_Metropolia.md` - add link to the canonical JSON pack.
- `docs/features/academic/grading-rule-engine.md` - document pack application flow if this file exists from S-001.

---

### Task 1: Lock The Catalog Contract

**Files:**
- Create: `tests/Feature/Academic/Grading/MetropoliaSchemePackTest.php`
- Create: `docs/features/academic/metropolia-grading-schemes.json`
- Create: `app/Modules/Academic/Grading/Support/MetropoliaSchemeCatalog.php`

- [ ] **Step 1: Write the failing catalog test**

```php
<?php

declare(strict_types=1);

use App\Modules\Academic\Grading\Support\MetropoliaSchemeCatalog;

it('loads every metropolia scheme key from the canonical pack', function () {
    $catalog = app(MetropoliaSchemeCatalog::class);

    expect($catalog->keys())->toBe([
        'software_1.programming',
        'software_1.database',
        'software_1.maths_physics',
        'software_1.project',
        'software_2.programming',
        'software_2.web_development',
        'software_2.maths_physics',
        'software_2.project',
        'hardware_1.digital_systems',
        'hardware_1.networking',
        'hardware_1.linux',
        'hardware_1.health_technology',
        'hardware_1.maths_physics',
        'hardware_2.electronics',
        'hardware_2.maths_physics',
        'hardware_2.cloud_computing',
        'hardware_2.project',
    ]);
});

it('returns metropolia_v1 schemes with audit labels', function () {
    $scheme = app(MetropoliaSchemeCatalog::class)->get('software_1.programming');

    expect($scheme['engine'])->toBe('metropolia_v1')
        ->and($scheme['version'])->toBe(1)
        ->and($scheme['source_reference'])->toBe('Software 1 / Programming')
        ->and($scheme['scale'])->toBe('numeric_0_5')
        ->and($scheme['components'])->toHaveCount(2);
});
```

- [ ] **Step 2: Run the test and verify it fails**

Run:

```bash
./scripts/dev.sh test tests/Feature/Academic/Grading/MetropoliaSchemePackTest.php
```

Expected: FAIL because `MetropoliaSchemeCatalog` and the JSON fixture do not exist.

- [ ] **Step 3: Create the canonical JSON fixture**

Create `docs/features/academic/metropolia-grading-schemes.json` with this top-level shape and all keys listed in Step 1:

```json
{
  "pack": "metropolia",
  "version": 1,
  "source": "docs/features/academic/Grading_Schemes_Metropolia.md",
  "schemes": {
    "software_1.programming": {
      "engine": "metropolia_v1",
      "version": 1,
      "source_reference": "Software 1 / Programming",
      "scale": "numeric_0_5",
      "rounding": "nearest_integer_after_components",
      "requirements": [
        {"type": "component_min_percentage", "component": "ASSIGNMENTS", "threshold": 40},
        {"type": "component_min_percentage", "component": "EXAM", "threshold": 40}
      ],
      "components": [
        {"component": "ASSIGNMENTS", "label": "Assignments", "weight": 0, "conversion": {"type": "none"}},
        {"component": "EXAM", "label": "Exam", "weight": 100, "conversion": {"type": "linear_percentage_to_grade", "min_percentage": 40, "min_grade": 1, "max_percentage": 88, "max_grade": 5}}
      ],
      "final": {"type": "sum_converted_components", "max_grade": 5}
    },
    "software_1.database": {
      "engine": "metropolia_v1",
      "version": 1,
      "source_reference": "Software 1 / Database",
      "scale": "pass_fail",
      "rounding": "none",
      "requirements": [{"type": "component_min_percentage", "component": "ASSIGNMENTS", "threshold": 80}],
      "components": [{"component": "ASSIGNMENTS", "label": "Assignments", "weight": 100, "conversion": {"type": "pass_fail_percentage", "pass_percentage": 80}}],
      "final": {"type": "pass_fail"}
    }
  }
}
```

Add the other fifteen entries using the component names and formulas from `docs/features/academic/Grading_Schemes_Metropolia.md`. The final JSON must contain exactly the keys asserted in Step 1.

- [ ] **Step 4: Add the catalog loader**

```php
<?php

declare(strict_types=1);

namespace App\Modules\Academic\Grading\Support;

use Illuminate\Support\Arr;
use RuntimeException;

final class MetropoliaSchemeCatalog
{
    /** @return array<int, string> */
    public function keys(): array
    {
        return array_keys($this->schemes());
    }

    /** @return array<string, mixed> */
    public function get(string $key): array
    {
        $scheme = $this->schemes()[$key] ?? null;

        if (! is_array($scheme)) {
            throw new RuntimeException("Unknown Metropolia grading scheme key [{$key}].");
        }

        return $scheme;
    }

    /** @return array<string, array<string, mixed>> */
    public function schemes(): array
    {
        $path = base_path('docs/features/academic/metropolia-grading-schemes.json');
        $decoded = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        $schemes = Arr::get($decoded, 'schemes');

        if (! is_array($schemes)) {
            throw new RuntimeException('Metropolia grading scheme pack must contain a schemes object.');
        }

        return $schemes;
    }
}
```

- [ ] **Step 5: Run the catalog test**

Run:

```bash
./scripts/dev.sh test tests/Feature/Academic/Grading/MetropoliaSchemePackTest.php
```

Expected: PASS after all seventeen scheme keys exist.

### Task 2: Add Scheme Validation

**Files:**
- Modify: `tests/Feature/Academic/Grading/MetropoliaSchemePackTest.php`
- Create: `app/Modules/Academic/Grading/Support/GradingSchemeValidator.php`

- [ ] **Step 1: Add failing validator tests**

```php
use App\Modules\Academic\Grading\Support\GradingSchemeValidator;

it('validates every canonical metropolia scheme', function () {
    $catalog = app(MetropoliaSchemeCatalog::class);
    $validator = app(GradingSchemeValidator::class);

    foreach ($catalog->keys() as $key) {
        expect($validator->validate($catalog->get($key)))->toBe([]);
    }
});

it('reports concrete validation errors for malformed schemes', function () {
    $errors = app(GradingSchemeValidator::class)->validate([
        'engine' => 'metropolia_v1',
        'scale' => 'numeric_0_5',
        'components' => [],
        'final' => ['type' => 'sum_converted_components'],
    ]);

    expect($errors)->toContain('version is required')
        ->and($errors)->toContain('components must contain at least one component');
});
```

- [ ] **Step 2: Run the test and verify it fails**

Run:

```bash
./scripts/dev.sh test tests/Feature/Academic/Grading/MetropoliaSchemePackTest.php
```

Expected: FAIL because `GradingSchemeValidator` does not exist.

- [ ] **Step 3: Implement the validator**

```php
<?php

declare(strict_types=1);

namespace App\Modules\Academic\Grading\Support;

final class GradingSchemeValidator
{
    /** @return array<int, string> */
    public function validate(array $scheme): array
    {
        $errors = [];

        foreach (['engine', 'version', 'scale', 'components', 'final'] as $key) {
            if (! array_key_exists($key, $scheme)) {
                $errors[] = "{$key} is required";
            }
        }

        if (($scheme['engine'] ?? null) !== 'metropolia_v1') {
            $errors[] = 'engine must be metropolia_v1';
        }

        if (! in_array($scheme['scale'] ?? null, ['numeric_0_5', 'pass_fail'], true)) {
            $errors[] = 'scale must be numeric_0_5 or pass_fail';
        }

        if (! is_array($scheme['components'] ?? null) || count($scheme['components'] ?? []) === 0) {
            $errors[] = 'components must contain at least one component';
        }

        foreach (($scheme['components'] ?? []) as $index => $component) {
            if (! is_array($component)) {
                $errors[] = "components.{$index} must be an object";
                continue;
            }

            foreach (['component', 'label', 'conversion'] as $key) {
                if (! array_key_exists($key, $component)) {
                    $errors[] = "components.{$index}.{$key} is required";
                }
            }
        }

        return array_values(array_unique($errors));
    }
}
```

- [ ] **Step 4: Run validator tests**

Run:

```bash
./scripts/dev.sh test tests/Feature/Academic/Grading/MetropoliaSchemePackTest.php
```

Expected: PASS.

### Task 3: Add Mapping Action And Command

**Files:**
- Create: `tests/Feature/Academic/Grading/ApplyMetropoliaSchemePackCommandTest.php`
- Create: `app/Modules/Academic/Actions/ApplyGradingSchemePackAction.php`
- Create: `app/Console/Commands/Academic/ApplyGradingSchemePackCommand.php`
- Create: `docs/features/academic/metropolia-component-mapping.md`

- [ ] **Step 1: Write command tests**

```php
<?php

declare(strict_types=1);

use App\Models\SyllabusTemplate;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('dry runs a metropolia mapping without mutating templates', function () {
    Storage::fake('local');
    $unit = Unit::factory()->create(['code' => 'SW1-PROG']);
    $template = SyllabusTemplate::factory()->create(['unit_id' => $unit->id, 'title' => 'Programming']);
    $mappingPath = storage_path('app/testing-metropolia-map.json');
    file_put_contents($mappingPath, json_encode([
        ['unit_code' => 'SW1-PROG', 'syllabus_title' => 'Programming', 'scheme_key' => 'software_1.programming'],
    ], JSON_PRETTY_PRINT));

    $this->artisan('academic:apply-grading-scheme-pack', [
        '--mapping' => $mappingPath,
        '--dry-run' => true,
    ])->assertExitCode(0);

    expect($template->fresh()->grading_scheme)->toBeNull();
});

it('commits a metropolia mapping to the selected template only', function () {
    $unit = Unit::factory()->create(['code' => 'SW1-PROG']);
    $template = SyllabusTemplate::factory()->create(['unit_id' => $unit->id, 'title' => 'Programming']);
    $unmapped = SyllabusTemplate::factory()->create();
    $mappingPath = storage_path('app/testing-metropolia-map.json');
    file_put_contents($mappingPath, json_encode([
        ['unit_code' => 'SW1-PROG', 'syllabus_title' => 'Programming', 'scheme_key' => 'software_1.programming'],
    ], JSON_PRETTY_PRINT));

    $this->artisan('academic:apply-grading-scheme-pack', [
        '--mapping' => $mappingPath,
        '--commit' => true,
    ])->assertExitCode(0);

    expect($template->fresh()->grading_scheme['source_reference'])->toBe('Software 1 / Programming')
        ->and($unmapped->fresh()->grading_scheme)->toBeNull();
});
```

- [ ] **Step 2: Run the command tests and verify they fail**

Run:

```bash
./scripts/dev.sh test tests/Feature/Academic/Grading/ApplyMetropoliaSchemePackCommandTest.php
```

Expected: FAIL because the command and action do not exist.

- [ ] **Step 3: Implement the action**

```php
<?php

declare(strict_types=1);

namespace App\Modules\Academic\Actions;

use App\Models\SyllabusTemplate;
use App\Modules\Academic\Grading\Support\GradingSchemeValidator;
use App\Modules\Academic\Grading\Support\MetropoliaSchemeCatalog;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class ApplyGradingSchemePackAction
{
    public function __construct(
        private readonly MetropoliaSchemeCatalog $catalog,
        private readonly GradingSchemeValidator $validator,
    ) {}

    /** @return array{total:int,updated:int,skipped:int,errors:array<int,string>} */
    public function execute(array $mappings, bool $commit): array
    {
        $result = ['total' => count($mappings), 'updated' => 0, 'skipped' => 0, 'errors' => []];

        DB::transaction(function () use ($mappings, $commit, &$result): void {
            foreach ($mappings as $index => $mapping) {
                $template = $this->findTemplate($mapping);
                $scheme = $this->catalog->get((string) $mapping['scheme_key']);
                $errors = $this->validator->validate($scheme);

                if ($errors !== []) {
                    $result['errors'][] = "mapping {$index}: ".implode('; ', $errors);
                    continue;
                }

                if ($template->grading_scheme === $scheme) {
                    $result['skipped']++;
                    continue;
                }

                if ($commit) {
                    $template->forceFill(['grading_scheme' => $scheme])->save();
                }

                $result['updated']++;
            }

            if (! $commit) {
                DB::rollBack();
            }
        });

        return $result;
    }

    private function findTemplate(array $mapping): SyllabusTemplate
    {
        $query = SyllabusTemplate::query()
            ->whereHas('unit', fn ($query) => $query->where('code', $mapping['unit_code'] ?? ''));

        if (! empty($mapping['syllabus_template_id'])) {
            $query->whereKey($mapping['syllabus_template_id']);
        }

        if (! empty($mapping['syllabus_title'])) {
            $query->where('title', $mapping['syllabus_title']);
        }

        $matches = $query->get();

        if ($matches->count() !== 1) {
            throw new RuntimeException('Each mapping must resolve to exactly one syllabus template.');
        }

        return $matches->first();
    }
}
```

- [ ] **Step 4: Implement the command**

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands\Academic;

use App\Modules\Academic\Actions\ApplyGradingSchemePackAction;
use Illuminate\Console\Command;

final class ApplyGradingSchemePackCommand extends Command
{
    protected $signature = 'academic:apply-grading-scheme-pack
        {--mapping= : Absolute path to mapping JSON}
        {--dry-run : Validate and report without saving}
        {--commit : Save schemes to matched templates}';

    protected $description = 'Apply a grading scheme pack to local syllabus templates';

    public function handle(ApplyGradingSchemePackAction $action): int
    {
        $mappingPath = (string) $this->option('mapping');
        $commit = (bool) $this->option('commit');
        $dryRun = (bool) $this->option('dry-run');

        if ($mappingPath === '' || ! is_file($mappingPath)) {
            $this->error('A readable --mapping JSON path is required.');
            return self::FAILURE;
        }

        if ($commit === $dryRun) {
            $this->error('Pass exactly one of --dry-run or --commit.');
            return self::FAILURE;
        }

        $mappings = json_decode((string) file_get_contents($mappingPath), true, flags: JSON_THROW_ON_ERROR);
        $result = $action->execute($mappings, $commit);

        $this->table(['total', 'updated', 'skipped', 'errors'], [[
            $result['total'],
            $result['updated'],
            $result['skipped'],
            count($result['errors']),
        ]]);

        foreach ($result['errors'] as $error) {
            $this->error($error);
        }

        return $result['errors'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
```

- [ ] **Step 5: Add mapping docs**

Create `docs/features/academic/metropolia-component-mapping.md` with:

````markdown
# Metropolia Component Mapping

Use this file to create a school-database-specific mapping JSON for
`academic:apply-grading-scheme-pack`.

Example mapping:

```json
[
  {
    "unit_code": "SW1-PROG",
    "syllabus_title": "Programming",
    "scheme_key": "software_1.programming"
  }
]
```

Run dry-run first:

```bash
./scripts/dev.sh artisan academic:apply-grading-scheme-pack --mapping=/absolute/path/metropolia-map.json --dry-run
```

Commit only after the dry-run resolves every mapping to one syllabus template:

```bash
./scripts/dev.sh artisan academic:apply-grading-scheme-pack --mapping=/absolute/path/metropolia-map.json --commit
```
````

- [ ] **Step 6: Run command tests**

Run:

```bash
./scripts/dev.sh test tests/Feature/Academic/Grading/ApplyMetropoliaSchemePackCommandTest.php
```

Expected: PASS.

### Task 4: Final Verification And Harness Update

**Files:**
- Modify: `docs/features/academic/Grading_Schemes_Metropolia.md`
- Modify: `docs/features/academic/grading-rule-engine.md`
- Modify: `docs/stories/E-academic-grading-rule-engine/S-002-metropolia-scheme-pack-and-mapping/validation.md`

- [ ] **Step 1: Add doc links**

Append this note to `docs/features/academic/Grading_Schemes_Metropolia.md`:

```markdown
## Swinx Scheme Pack

The executable Swinx representation of these rules is maintained in
`docs/features/academic/metropolia-grading-schemes.json`. Local school database
mapping guidance is maintained in
`docs/features/academic/metropolia-component-mapping.md`.
```

- [ ] **Step 2: Run final commands**

Run:

```bash
./scripts/dev.sh test tests/Feature/Academic/Grading/MetropoliaSchemePackTest.php
./scripts/dev.sh test tests/Feature/Academic/Grading/ApplyMetropoliaSchemePackCommandTest.php
./scripts/dev.sh artisan pint app/Modules/Academic/Grading app/Modules/Academic/Actions app/Console/Commands/Academic tests/Feature/Academic/Grading
git diff --check -- docs/features/academic docs/stories/E-academic-grading-rule-engine docs/superpowers/plans app/Modules/Academic app/Console/Commands/Academic tests/Feature/Academic/Grading
```

Expected: all commands pass.

- [ ] **Step 3: Update Harness**

Run:

```bash
./scripts/harness story update --id S-002-metropolia-scheme-pack-and-mapping --status implemented
./scripts/harness trace --story S-002-metropolia-scheme-pack-and-mapping --summary "Metropolia scheme pack and mapping path implemented" --actions "Added catalog, validator, mapping command, docs, and tests" --changed "docs/features/academic app/Modules/Academic app/Console/Commands/Academic tests/Feature/Academic/Grading" --outcome completed
```

## Self-Review

Spec coverage: S-002 covers canonical fixture, validation, per-database mapping, dry-run, commit path, and operator docs.

Self-audit scan: completed with zero matches for the forbidden terms pattern.

Type consistency: `MetropoliaSchemeCatalog`, `GradingSchemeValidator`, `ApplyGradingSchemePackAction`, and `academic:apply-grading-scheme-pack` are named consistently across tests, implementation, and docs.
