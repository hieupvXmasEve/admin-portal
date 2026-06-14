# Syllabus Grading Scheme Admin UI Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add an admin UI for viewing, editing, validating, and previewing syllabus `grading_scheme` JSON without changing student or lecturer portal APIs.

**Architecture:** Reuse the backend validator and calculators from S-001/S-002 through web-authenticated preview endpoints. Add focused Vue components under the existing syllabus pages, keeping the saved value as `grading_scheme: null` for default weighted grading or a parsed JSON object for custom schemes.

**Tech Stack:** Laravel 13, Inertia v3, Vue 3 with `<script setup lang="ts">`, TypeScript, Tailwind CSS 4, lucide-vue-next, Pest feature tests, Docker wrapper scripts.

---

## Scope Guardrails

This story creates UI on existing admin syllabus pages. It does not create a visual drag-and-drop builder, does not touch portals, and does not run recalculation. Because these are Inertia pages, read `docs/inertiajs-vue-info.md` before implementation and keep props snake_case.

## File Structure

Create:

- `app/Http/Requests/SyllabusTemplate/PreviewGradingSchemeRequest.php` - validates preview payload.
- `app/Modules/Academic/Actions/PreviewSyllabusGradingSchemeAction.php` - runs calculator against sample component scores.
- `resources/js/types/grading-scheme.ts` - shared admin TypeScript types.
- `resources/js/pages/syllabus/components/GradingSchemeEditor.vue` - JSON editor, mode selector, validate button.
- `resources/js/pages/syllabus/components/GradingSchemePreview.vue` - sample score inputs and preview output.
- `tests/Feature/Academic/Grading/SyllabusGradingSchemeAdminUiTest.php`

Modify:

- `routes/web/syllabus-templates.php` - add options and preview routes.
- `app/Http/Controllers/Web/SyllabusTemplateController.php` - pass options and add preview handlers.
- `resources/js/pages/syllabus/TemplatesCreate.vue` - submit `grading_scheme`.
- `resources/js/pages/syllabus/TemplatesEdit.vue` - edit existing `grading_scheme`.
- `resources/js/pages/syllabus/TemplatesShow.vue` - display current scheme summary.
- `docs/features/academic/grading-rule-engine.md` - add admin UI operation notes.

---

### Task 1: Add Backend Preview Contract

**Files:**
- Create: `tests/Feature/Academic/Grading/SyllabusGradingSchemeAdminUiTest.php`
- Create: `app/Http/Requests/SyllabusTemplate/PreviewGradingSchemeRequest.php`
- Create: `app/Modules/Academic/Actions/PreviewSyllabusGradingSchemeAction.php`
- Modify: `routes/web/syllabus-templates.php`
- Modify: `app/Http/Controllers/Web/SyllabusTemplateController.php`

- [ ] **Step 1: Write failing preview tests**

```php
<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('previews a metropolia grading scheme without saving it', function () {
    $admin = User::factory()->create();

    $scheme = [
        'engine' => 'metropolia_v1',
        'version' => 1,
        'scale' => 'numeric_0_5',
        'rounding' => 'nearest_integer_after_components',
        'requirements' => [],
        'components' => [
            ['component' => 'EXAM', 'label' => 'Exam', 'weight' => 100, 'conversion' => ['type' => 'linear_percentage_to_grade', 'min_percentage' => 40, 'min_grade' => 1, 'max_percentage' => 88, 'max_grade' => 5]],
        ],
        'final' => ['type' => 'sum_converted_components', 'max_grade' => 5],
    ];

    $this->actingAs($admin)
        ->postJson('/api/syllabus-templates/grading-scheme/preview', [
            'grading_scheme' => $scheme,
            'component_scores' => [['component' => 'EXAM', 'percentage' => 88]],
        ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.final_label', '5')
        ->assertJsonPath('data.scale', 'numeric_0_5');
});

it('rejects malformed grading scheme preview payloads', function () {
    $admin = User::factory()->create();

    $this->actingAs($admin)
        ->postJson('/api/syllabus-templates/grading-scheme/preview', [
            'grading_scheme' => ['engine' => 'metropolia_v1'],
            'component_scores' => [],
        ])
        ->assertUnprocessable();
});
```

- [ ] **Step 2: Run the test and verify it fails**

Run:

```bash
./scripts/dev.sh test tests/Feature/Academic/Grading/SyllabusGradingSchemeAdminUiTest.php
```

Expected: FAIL because the preview route does not exist.

- [ ] **Step 3: Add request validation**

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\SyllabusTemplate;

use Illuminate\Foundation\Http\FormRequest;

final class PreviewGradingSchemeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('edit_syllabus') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'grading_scheme' => ['required', 'array'],
            'grading_scheme.engine' => ['required', 'string', 'in:metropolia_v1'],
            'grading_scheme.version' => ['required', 'integer', 'min:1'],
            'grading_scheme.scale' => ['required', 'string', 'in:numeric_0_5,pass_fail'],
            'grading_scheme.components' => ['required', 'array', 'min:1'],
            'grading_scheme.final' => ['required', 'array'],
            'component_scores' => ['required', 'array'],
            'component_scores.*.component' => ['required', 'string'],
            'component_scores.*.percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'component_scores.*.points' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
```

- [ ] **Step 4: Add preview action**

```php
<?php

declare(strict_types=1);

namespace App\Modules\Academic\Actions;

use App\Modules\Academic\Grading\Data\ComponentScore;
use App\Modules\Academic\Grading\GradingCalculatorResolver;
use App\Modules\Academic\Grading\Support\GradingSchemeValidator;

final class PreviewSyllabusGradingSchemeAction
{
    public function __construct(
        private readonly GradingCalculatorResolver $resolver,
        private readonly GradingSchemeValidator $validator,
    ) {}

    /** @return array<string, mixed> */
    public function execute(array $scheme, array $componentScores): array
    {
        $errors = $this->validator->validate($scheme);

        if ($errors !== []) {
            return ['valid' => false, 'errors' => $errors];
        }

        $scores = collect($componentScores)
            ->map(fn (array $score) => new ComponentScore(
                component: (string) $score['component'],
                percentage: isset($score['percentage']) ? (float) $score['percentage'] : null,
                points: isset($score['points']) ? (float) $score['points'] : null,
            ))
            ->all();

        $result = $this->resolver->forScheme($scheme)->calculate($scheme, $scores);

        return [
            'valid' => true,
            'scale' => $result->scale,
            'final_label' => $result->finalLabel,
            'final_numeric' => $result->finalNumeric,
            'is_passed' => $result->isPassed,
            'breakdown' => $result->breakdown,
        ];
    }
}
```

- [ ] **Step 5: Add routes and controller methods**

Add routes inside the authenticated `api` group in `routes/web/syllabus-templates.php`:

```php
Route::get('/syllabus-templates/grading-scheme/options', [SyllabusTemplateController::class, 'gradingSchemeOptions'])
    ->middleware('can:view_syllabus')
    ->name('syllabus_templates.grading-scheme.options');

Route::post('/syllabus-templates/grading-scheme/preview', [SyllabusTemplateController::class, 'previewGradingScheme'])
    ->middleware('can:edit_syllabus')
    ->name('syllabus_templates.grading-scheme.preview');

Route::post('/syllabus-templates/{syllabusTemplate}/grading-scheme/preview', [SyllabusTemplateController::class, 'previewExistingGradingScheme'])
    ->middleware('can:edit_syllabus')
    ->name('syllabus_templates.grading-scheme.preview-existing');
```

Add methods to `SyllabusTemplateController`:

```php
use App\Http\Requests\SyllabusTemplate\PreviewGradingSchemeRequest;
use App\Http\Responses\ApiResponse;
use App\Modules\Academic\Actions\PreviewSyllabusGradingSchemeAction;

public function gradingSchemeOptions(): JsonResponse
{
    return ApiResponse::success([
        'engines' => [
            ['value' => 'default', 'label' => 'Default weighted percentage'],
            ['value' => 'metropolia_v1', 'label' => 'Metropolia v1'],
        ],
    ]);
}

public function previewGradingScheme(
    PreviewGradingSchemeRequest $request,
    PreviewSyllabusGradingSchemeAction $action,
): JsonResponse {
    $result = $action->execute(
        $request->validated('grading_scheme'),
        $request->validated('component_scores'),
    );

    if (($result['valid'] ?? false) === false) {
        return ApiResponse::error('Invalid grading scheme.', $result['errors'] ?? [], 422);
    }

    return ApiResponse::success($result);
}

public function previewExistingGradingScheme(
    PreviewGradingSchemeRequest $request,
    SyllabusTemplate $syllabusTemplate,
    PreviewSyllabusGradingSchemeAction $action,
): JsonResponse {
    return $this->previewGradingScheme($request, $action);
}
```

- [ ] **Step 6: Run backend preview tests**

Run:

```bash
./scripts/dev.sh test tests/Feature/Academic/Grading/SyllabusGradingSchemeAdminUiTest.php
```

Expected: PASS.

### Task 2: Build Focused Vue Components

**Files:**
- Create: `resources/js/types/grading-scheme.ts`
- Create: `resources/js/pages/syllabus/components/GradingSchemeEditor.vue`
- Create: `resources/js/pages/syllabus/components/GradingSchemePreview.vue`

- [ ] **Step 1: Add TypeScript types**

```ts
export type GradingSchemeEngine = 'default' | 'metropolia_v1'
export type GradingSchemeScale = 'numeric_0_5' | 'pass_fail'

export interface GradingSchemeComponent {
    component: string
    label: string
    weight?: number
    conversion: Record<string, unknown>
}

export interface GradingScheme {
    engine: 'metropolia_v1'
    version: number
    source_reference?: string
    scale: GradingSchemeScale
    rounding?: string
    requirements?: Array<Record<string, unknown>>
    components: GradingSchemeComponent[]
    final: Record<string, unknown>
}

export interface PreviewComponentScore {
    component: string
    percentage: number | null
    points?: number | null
}

export interface GradingSchemePreviewResult {
    valid: boolean
    scale?: GradingSchemeScale
    final_label?: string
    final_numeric?: number | null
    is_passed?: boolean
    breakdown?: Record<string, unknown>
    errors?: string[]
}
```

- [ ] **Step 2: Add editor component**

Create `resources/js/pages/syllabus/components/GradingSchemeEditor.vue` with a `modelValue` prop, an engine select, JSON textarea, and a validation event. Use `<script setup lang="ts">`, `Textarea`, `Select`, `Button`, and lucide icons `CheckCircle2`, `Code2`, `XCircle`.

Core script:

```ts
const props = defineProps<{
    modelValue: GradingScheme | null
}>()

const emit = defineEmits<{
    'update:modelValue': [value: GradingScheme | null]
    validate: [value: GradingScheme | null]
}>()

const selected_engine = ref<GradingSchemeEngine>(props.modelValue?.engine ?? 'default')
const json_text = ref(props.modelValue ? JSON.stringify(props.modelValue, null, 2) : '')
const parse_error = ref<string | null>(null)

watch(() => props.modelValue, (value) => {
    selected_engine.value = value?.engine ?? 'default'
    json_text.value = value ? JSON.stringify(value, null, 2) : ''
})

function applyJson() {
    parse_error.value = null

    if (selected_engine.value === 'default') {
        emit('update:modelValue', null)
        return
    }

    try {
        const parsed = JSON.parse(json_text.value) as GradingScheme
        emit('update:modelValue', parsed)
    } catch (error) {
        parse_error.value = error instanceof Error ? error.message : 'Invalid JSON'
    }
}

function validateScheme() {
    applyJson()
    emit('validate', selected_engine.value === 'default' ? null : props.modelValue)
}
```

- [ ] **Step 3: Add preview component**

Create `resources/js/pages/syllabus/components/GradingSchemePreview.vue` with component score rows generated from `scheme.components`, numeric inputs for percentages, and a preview button that posts to `/api/syllabus-templates/grading-scheme/preview`.

Core request code:

```ts
async function runPreview() {
    if (!props.scheme) {
        result.value = null
        return
    }

    is_loading.value = true
    error_message.value = null

    try {
        const response = await fetch('/api/syllabus-templates/grading-scheme/preview', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
            },
            body: JSON.stringify({
                grading_scheme: props.scheme,
                component_scores: scores.value,
            }),
        })

        const payload = await response.json()
        if (!response.ok) {
            error_message.value = payload.message ?? 'Preview failed'
            return
        }

        result.value = payload.data as GradingSchemePreviewResult
    } finally {
        is_loading.value = false
    }
}
```

- [ ] **Step 4: Run frontend checks**

Run:

```bash
./scripts/dev.sh npm run type-check
./scripts/dev.sh npm run lint
```

Expected: PASS or only pre-existing unrelated diagnostics outside the new syllabus grading files. Record any unrelated diagnostics in the Harness trace.

### Task 3: Integrate Create, Edit, And Show Pages

**Files:**
- Modify: `resources/js/pages/syllabus/TemplatesCreate.vue`
- Modify: `resources/js/pages/syllabus/TemplatesEdit.vue`
- Modify: `resources/js/pages/syllabus/TemplatesShow.vue`
- Modify: `app/Http/Controllers/Web/SyllabusTemplateController.php`

- [ ] **Step 1: Pass scheme data to pages**

In `pageCreate`, `pageEdit`, and `pageShow`, add `grading_scheme_options` and include loaded `grading_scheme` on the template model.

```php
'grading_scheme_options' => [
    'engines' => [
        ['value' => 'default', 'label' => 'Default weighted percentage'],
        ['value' => 'metropolia_v1', 'label' => 'Metropolia v1'],
    ],
],
```

- [ ] **Step 2: Add `grading_scheme` to create submit data**

In `TemplatesCreate.vue`, add:

```ts
import type { GradingScheme } from '@/types/grading-scheme'
import GradingSchemeEditor from './components/GradingSchemeEditor.vue'
import GradingSchemePreview from './components/GradingSchemePreview.vue'

const grading_scheme = ref<GradingScheme | null>(null)
```

Add to `submitData`:

```ts
grading_scheme: grading_scheme.value,
```

Render the editor and preview inside the existing form after assessment components.

- [ ] **Step 3: Add `grading_scheme` to edit initial state and submit data**

In `TemplatesEdit.vue`, extend `SyllabusTemplate`:

```ts
grading_scheme: GradingScheme | null
```

Add:

```ts
const grading_scheme = ref<GradingScheme | null>(props.syllabusTemplate.grading_scheme ?? null)
```

Add to `submitData`:

```ts
grading_scheme: grading_scheme.value,
```

Render the editor and preview using the same components.

- [ ] **Step 4: Display scheme summary on show page**

In `TemplatesShow.vue`, extend `SyllabusTemplate`:

```ts
grading_scheme?: GradingScheme | null
```

Add a compact section:

```vue
<Card>
  <CardHeader>
    <CardTitle class="flex items-center gap-2">
      <FileText class="h-5 w-5" />
      Grading Scheme
    </CardTitle>
  </CardHeader>
  <CardContent>
    <Badge v-if="!template.grading_scheme" variant="secondary">Default weighted percentage</Badge>
    <div v-else class="space-y-2">
      <Badge>{{ template.grading_scheme.engine }}</Badge>
      <div class="text-sm text-muted-foreground">{{ template.grading_scheme.scale }}</div>
    </div>
  </CardContent>
</Card>
```

- [ ] **Step 5: Run full story verification**

Run:

```bash
./scripts/dev.sh test tests/Feature/Academic/Grading/SyllabusGradingSchemeAdminUiTest.php
./scripts/dev.sh npm run type-check
./scripts/dev.sh npm run lint
./scripts/dev.sh npm run format:check
./scripts/dev.sh artisan pint app/Http/Controllers/Web/SyllabusTemplateController.php app/Http/Requests/SyllabusTemplate app/Modules/Academic tests/Feature/Academic/Grading
git diff --check -- resources/js/pages/syllabus app/Http app/Modules/Academic docs/features/academic docs/stories/E-academic-grading-rule-engine docs/superpowers/plans
```

Expected: all targeted backend tests and formatting checks pass; frontend checks pass or document unrelated baseline diagnostics.

- [ ] **Step 6: Update Harness**

Run:

```bash
./scripts/harness story update --id S-003-syllabus-grading-scheme-admin-ui --status implemented
./scripts/harness trace --story S-003-syllabus-grading-scheme-admin-ui --summary "Syllabus grading scheme admin UI implemented" --actions "Added preview endpoints, editor components, create/edit/show integration, and validation" --changed "app/Http resources/js/pages/syllabus resources/js/types tests/Feature/Academic/Grading docs/features/academic" --outcome completed
```

## Self-Review

Spec coverage: S-003 covers admin create/edit/show UI, validation endpoint, preview endpoint, and no portal changes.

Self-audit scan: completed with zero matches for the forbidden terms pattern.

Type consistency: `GradingScheme`, `PreviewSyllabusGradingSchemeAction`, and the preview routes use the same snake_case payload keys throughout.
