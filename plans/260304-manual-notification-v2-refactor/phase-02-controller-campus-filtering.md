# Phase 02: Controller + Request Campus Filtering

**Status:** completed
**Effort:** 1h
**Dependencies:** Phase 01

---

## Objective

Update `NotificationController` and `SendManualNotificationRequest` to:
1. Require `campus_id` in request validation
2. Filter recipient searches by current campus
3. Use v2 action with feature flag for rollback safety

---

## Task 2.1: Update SendManualNotificationRequest

**File:** `app/Http/Requests/Notification/SendManualNotificationRequest.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Notification;

use App\Enums\NotificationCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class SendManualNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('send_manual_notification');
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'campus_id' => ['required', 'integer', 'exists:campuses,id'],
            'notifiable_type' => ['required', 'string', 'in:student,user,lecturer'],
            'notifiable_ids' => ['required', 'array', 'min:1'],
            'notifiable_ids.*' => ['integer'],
            'title' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string'],
            'category' => ['required', new Enum(NotificationCategory::class)],
            'is_important' => ['boolean'],
            'action_url' => ['nullable', 'string', 'max:500'],
            'action_text' => ['nullable', 'string', 'max:50'],
        ];
    }

    protected function prepareForValidation(): void
    {
        // Auto-fill campus_id from session if not provided
        if (! $this->has('campus_id')) {
            $this->merge([
                'campus_id' => session('current_campus_id'),
            ]);
        }
    }

    public function messages(): array
    {
        return [
            'campus_id.required' => 'Campus context is required for sending notifications.',
            'campus_id.exists' => 'Invalid campus selected.',
        ];
    }
}
```

### Changes Summary

| Change | Rationale |
|--------|-----------|
| Add `campus_id` required rule | Enforce campus isolation |
| `prepareForValidation()` auto-fills from session | Backward compatibility with frontend |
| Custom messages | Clear error feedback |

---

## Task 2.2: Update NotificationController

**File:** `app/Http/Controllers/Web/Admin/NotificationController.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Actions\Notification\SendManualNotificationAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Notification\SendManualNotificationRequest;
use App\Models\Notification;
use App\Models\Program;
use App\Models\Student;
use App\Enums\NotificationCategory;
use App\Modules\Notification\Actions\SendManualNotificationV2Action;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    /**
     * Display a listing of the notification history.
     */
    public function index(Request $request): Response
    {
        $this->authorize('view_any_notification');
        $campusId = session('current_campus_id');

        $notifications = Notification::query()
            ->with(['notifiable'])
            ->when($campusId, fn ($q) => $q->where('campus_id', $campusId))
            ->when($request->input('search'), function ($query, $search) {
                $query->where('title', 'like', "%{$search}%")
                    ->orWhere('message', 'like', "%{$search}%");
            })
            ->when($request->input('category'), function ($query, $category) {
                $query->where('category', $category);
            })
            ->orderBy($request->input('sort', 'created_at'), $request->input('direction', 'desc'))
            ->paginate($request->input('per_page', 15))
            ->withQueryString();

        return Inertia::render('Admin/Notifications/Index', [
            'notifications' => $notifications,
            'filters' => [
                'search' => $request->input('search'),
                'category' => $request->input('category'),
                'sort' => $request->input('sort'),
                'direction' => $request->input('direction'),
                'per_page' => $request->input('per_page'),
            ],
            'categories' => NotificationCategory::options(),
        ]);
    }

    /**
     * Show the form for sending a manual notification.
     */
    public function sendForm(): Response
    {
        $this->authorize('send_manual_notification');

        $campusId = session('current_campus_id');

        $programs = Program::query()
            ->when($campusId, fn ($q) => $q->where('campus_id', $campusId))
            ->select('id', 'name')
            ->get();

        return Inertia::render('Admin/Notifications/Send', [
            'categories' => NotificationCategory::options(),
            'programs' => $programs,
            'currentCampusId' => $campusId,
        ]);
    }

    /**
     * Send a manual notification.
     */
    public function send(
        SendManualNotificationRequest $request,
        SendManualNotificationAction $legacyAction,
        SendManualNotificationV2Action $v2Action
    ) {
        $data = $request->validated();

        // Feature flag for rollback safety
        if (config('notification.write_mode') === 'v2') {
            $eventId = $v2Action->run($data);

            return \App\Http\Responses\ApiResponse::success(
                ['event_id' => $eventId],
                [],
                'Notification queued for delivery.'
            );
        }

        // Legacy fallback
        $legacyAction->execute($data);

        return \App\Http\Responses\ApiResponse::success(
            null,
            [],
            'Notification sent successfully.'
        );
    }

    /**
     * API to search recipients for the notification form.
     */
    public function searchTargets(Request $request)
    {
        $this->authorize('send_manual_notification');

        $type = $request->input('type', 'student');
        $search = $request->input('search');
        $campusId = $request->input('campus_id', session('current_campus_id'));

        switch ($type) {
            case 'student':
                $results = Student::query()
                    ->active()
                    ->when($campusId, fn ($q) => $q->where('campus_id', $campusId))
                    ->when($search, function ($query, $search) {
                        $query->where(function ($q) use ($search) {
                            $q->where('full_name', 'like', "%{$search}%")
                                ->orWhere('student_id', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                    })
                    ->when($request->input('program_id'), function ($query, $programId) {
                        $query->where('program_id', $programId);
                    })
                    ->limit(20)
                    ->get(['id', 'full_name as name', 'student_id as code', 'email']);
                break;

            case 'user':
                $results = \App\Models\User::query()
                    ->where('status', 'active')
                    ->when($campusId, fn ($q) => $q->whereHas(
                        'campusUserRoles',
                        fn ($sub) => $sub->where('campus_id', $campusId)
                    ))
                    ->when($search, function ($query, $search) {
                        $query->where(function ($q) use ($search) {
                            $q->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                    })
                    ->limit(20)
                    ->get(['id', 'name', 'email']);
                break;

            case 'lecturer':
                $results = \App\Models\Lecture::query()
                    ->active()
                    ->when($campusId, fn ($q) => $q->where('campus_id', $campusId))
                    ->when($search, function ($query, $search) {
                        $query->where(function ($q) use ($search) {
                            $q->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%")
                                ->orWhere('employee_id', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                    })
                    ->limit(20)
                    ->get(['id', DB::raw("CONCAT(first_name, ' ', last_name) as name"), 'employee_id as code', 'email']);
                break;

            default:
                return \App\Http\Responses\ApiResponse::businessLogicError('Invalid target type');
        }

        return \App\Http\Responses\ApiResponse::success($results);
    }
}
```

### Changes Summary

| Method | Change |
|--------|--------|
| `index()` | Add `campus_id` filter to notification query |
| `sendForm()` | Filter programs by campus, pass `currentCampusId` to frontend |
| `send()` | Feature-flagged routing to v2 or legacy action |
| `searchTargets()` | Add campus filtering for all target types |

---

## Task 2.3: Update Config Feature Flag

**File:** `config/notification.php`

```diff
return [
    'v2_enabled' => (bool) env('NOTIFICATION_V2_ENABLED', false),

-   'read_mode' => env('NOTIFICATION_V2_READ_MODE', 'legacy'),
-   'write_mode' => env('NOTIFICATION_V2_WRITE_MODE', 'off'),
+   'read_mode' => env('NOTIFICATION_V2_READ_MODE', 'legacy'),  // 'legacy' | 'v2' | 'dual'
+   'write_mode' => env('NOTIFICATION_V2_WRITE_MODE', 'v2'),     // 'off' | 'v2'
    // ... rest unchanged
];
```

**Environment for testing:**
```bash
NOTIFICATION_V2_WRITE_MODE=v2
```

---

## Task 2.4: Feature Test

**File:** `tests/Feature/Notification/SendManualNotificationControllerTest.php`

```php
<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Student;
use App\Models\User;
use App\Modules\Notification\Models\NotificationEventOutbox;

beforeEach(function () {
    $this->campus = Campus::factory()->create();
    $this->user = User::factory()->create();
    $this->user->assignRole('admin');
    $this->actingAs($this->user);
    session(['current_campus_id' => $this->campus->id]);
});

describe('send()', function () {
    it('creates outbox entry when v2 mode enabled', function () {
        config(['notification.write_mode' => 'v2']);
        
        $student = Student::factory()->create(['campus_id' => $this->campus->id]);

        $response = $this->postJson(route('admin.notifications.store'), [
            'campus_id' => $this->campus->id,
            'notifiable_type' => 'student',
            'notifiable_ids' => [$student->id],
            'title' => 'Test Notification',
            'message' => 'This is a test',
            'category' => 'system',
        ]);

        $response->assertSuccessful();
        expect($response->json('data.event_id'))->not->toBeNull();
        
        $this->assertDatabaseHas('notification_event_outbox', [
            'event_name' => 'manual.notification_sent',
            'campus_id' => $this->campus->id,
        ]);
    });

    it('auto-fills campus_id from session when not provided', function () {
        config(['notification.write_mode' => 'v2']);
        
        $student = Student::factory()->create(['campus_id' => $this->campus->id]);

        $response = $this->postJson(route('admin.notifications.store'), [
            'notifiable_type' => 'student',
            'notifiable_ids' => [$student->id],
            'title' => 'Test',
            'message' => 'Test',
            'category' => 'system',
        ]);

        $response->assertSuccessful();
        $this->assertDatabaseHas('notification_event_outbox', [
            'campus_id' => $this->campus->id,
        ]);
    });

    it('rejects request with invalid campus_id', function () {
        $response = $this->postJson(route('admin.notifications.store'), [
            'campus_id' => 999999,
            'notifiable_type' => 'student',
            'notifiable_ids' => [1],
            'title' => 'Test',
            'message' => 'Test',
            'category' => 'system',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['campus_id']);
    });
});

describe('searchTargets()', function () {
    it('filters students by campus_id', function () {
        $studentInCampus = Student::factory()->create([
            'campus_id' => $this->campus->id,
            'full_name' => 'John Campus',
        ]);
        $studentOtherCampus = Student::factory()->create([
            'campus_id' => Campus::factory()->create()->id,
            'full_name' => 'John Other',
        ]);

        $response = $this->getJson(route('admin.notifications.search-targets', [
            'type' => 'student',
            'search' => 'John',
            'campus_id' => $this->campus->id,
        ]));

        $response->assertSuccessful();
        $names = collect($response->json('data'))->pluck('name');
        expect($names)->toContain('John Campus');
        expect($names)->not->toContain('John Other');
    });

    it('filters users by campus role membership', function () {
        $userInCampus = User::factory()->create(['name' => 'Jane Campus']);
        $userInCampus->campusUserRoles()->create([
            'campus_id' => $this->campus->id,
            'role_id' => 1,
        ]);

        $userOtherCampus = User::factory()->create(['name' => 'Jane Other']);

        $response = $this->getJson(route('admin.notifications.search-targets', [
            'type' => 'user',
            'search' => 'Jane',
            'campus_id' => $this->campus->id,
        ]));

        $response->assertSuccessful();
        $names = collect($response->json('data'))->pluck('name');
        expect($names)->toContain('Jane Campus');
        expect($names)->not->toContain('Jane Other');
    });
});
```

---

## Acceptance Criteria

- [ ] `SendManualNotificationRequest` requires `campus_id` (auto-fills from session)
- [ ] `searchTargets()` filters all target types by campus
- [ ] `send()` routes to v2 action when `write_mode = 'v2'`
- [ ] `sendForm()` passes `currentCampusId` to frontend
- [ ] `index()` filters notifications by campus
- [ ] Feature tests pass
- [ ] Rollback via `NOTIFICATION_V2_WRITE_MODE=off` works

---

## Files Changed

| File | Action |
|------|--------|
| `app/Http/Requests/Notification/SendManualNotificationRequest.php` | Modify |
| `app/Http/Controllers/Web/Admin/NotificationController.php` | Modify |
| `config/notification.php` | Modify |
| `tests/Feature/Notification/SendManualNotificationControllerTest.php` | Create |
