---
phase: 04
title: API & Web Controllers
status: completed
---

# Phase 04 — API & Web Controllers

## Overview

Update API controller to scope CRUD by campus. Web controller passes campuses list to frontend.

## API EmailConfigurationController

**File:** `app/Http/Controllers/Api/V1/Admin/EmailConfigurationController.php`

### index()
Filter by current campus (from session) + global configs:
```php
public function index(): JsonResponse
{
    $campusId = session('current_campus_id');
    $configurations = $this->smtpService->getAll($campusId ? (int) $campusId : null);
    $statistics = $this->smtpService->getStatistics();

    return response()->json([
        'success' => true,
        'data' => $configurations,
        'statistics' => $statistics,
    ]);
}
```

### store()
Include `campus_id` in validation and pass through:
```php
$rules = EmailConfiguration::validationRules();
// campus_id already added in validationRules() in Phase 02
$validated = $request->validate($rules);
// Optionally default campus_id to current session campus if not provided
if (!isset($validated['campus_id'])) {
    $validated['campus_id'] = session('current_campus_id') ?: null;
}
$configuration = $this->smtpService->create($validated);
```

### update()
Same — allow campus_id to be updated.

### setActive()
No change needed — `setAsActive()` on the model now handles campus-scoped deactivation (Phase 02).

## Web EmailConfigurationController

**File:** `app/Http/Controllers/Web/EmailConfigurationController.php`

Pass campuses list to the Index page so the frontend can render a campus selector:
```php
public function index(): Response
{
    $campuses = \App\Models\Campus::orderBy('name')->get(['id', 'name', 'code']);
    
    return Inertia::render('Admin/EmailConfiguration/Index', [
        'campuses' => $campuses,
        'currentCampusId' => session('current_campus_id'),
    ]);
}
```

## API Routes

**File:** `routes/api/admin.php`

Verify existing routes cover all endpoints (index, store, show, update, destroy, test, testData, setActive). No new routes needed — campus is resolved from session/request body.

## Todo

- [ ] Update `ApiEmailConfigurationController::index()` — filter by session campus
- [ ] Update `ApiEmailConfigurationController::store()` — default campus_id from session
- [ ] Update `ApiEmailConfigurationController::update()` — allow campus_id in validated data
- [ ] Update `WebEmailConfigurationController::index()` — pass campuses + currentCampusId
- [ ] Verify API routes are complete in `routes/api/admin.php`
