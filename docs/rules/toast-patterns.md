---
paths: '**/*.{vue,ts,php}'
---

# Toast Notification Patterns

## Overview

This project uses **vue-sonner** with **Inertia v3 native flash** (`page.flash`) for toast notifications. The system provides automatic toast handling for backend success/error messages.

> **Two flash systems coexist** — see [Migration Guide](#migration-guide) for context.

## Architecture

```
NEW — Inertia v3 native flash (page.flash)
┌──────────────────┐     ┌──────────────────┐     ┌─────────────────┐
│ Controller       │────▶│ Inertia::flash() │────▶│ page.flash      │
│                  │     │ session key:     │     │ router.on('flash')
│ return back()    │     │ inertia.flash_data│    │ useFlashToast() │
└──────────────────┘     └──────────────────┘     └─────────────────┘
  ✅ Recommended for new code

LEGACY — session-based flash (page.props.flash)
┌──────────────────┐     ┌──────────────────┐     ┌─────────────────┐
│ Controller       │────▶│ ->with('key',...)│────▶│ page.props.flash│
│                  │     │ session('key')   │     │ consumed per-page│
│ return back()    │     │ HandleInertia... │     │ (no global toast)│
└──────────────────┘     └──────────────────┘     └─────────────────┘
  ⚠️ Legacy — 239 controllers, migrate gradually
```

Key differences:
- `page.flash` — NOT persisted in browser history, one-time only, global event
- `page.props.flash` — persisted in history state, read via `usePage().props.flash`

## Backend Pattern

### Controllers

Use `Inertia::flash()` for success messages:

```php
public function store(StoreRequest $request): RedirectResponse
{
    CreateAction::run($request->validated());
    
    Inertia::flash('message', 'Item created successfully.');
    
    return back();
}

public function update(UpdateRequest $request, Model $model): RedirectResponse
{
    UpdateAction::run($model, $request->validated());
    
    Inertia::flash('message', 'Item updated successfully.');
    
    return back();
}

public function destroy(Model $model): RedirectResponse
{
    DeleteAction::run($model);
    
    Inertia::flash('message', 'Item deleted successfully.');
    
    return back();
}
```

### Flash Message Types

Two patterns are supported by `useFlashToast`:

**Pattern 1 — `message` + `type` (recommended)**

```php
Inertia::flash('message', 'Created successfully.');         // defaults to success
Inertia::flash(['message' => 'Failed.', 'type' => 'error']); // explicit type
```

**Pattern 2 — keyed by type**

```php
Inertia::flash('success', 'Created successfully.');
Inertia::flash('error', 'Something went wrong.');
Inertia::flash('warning', 'Resource limited.');
```

| Type | Toast method |
|------|-------------|
| `success` (default) | `toast.success()` |
| `error` | `toast.error()` |
| `warning` | `toast.warning()` |
| `info` | `toast.info()` |

You can also flash non-toast data (e.g. IDs) alongside the message:

```php
Inertia::flash([
    'message' => 'Student enrolled!',
    'type' => 'success',
    'enrollmentId' => $enrollment->id, // accessible in onFlash callback
]);
```

## Frontend Pattern

### AppLayout Setup

```vue
<script setup lang="ts">
import { Toaster } from '@/components/ui/sonner';
import { useFlashToast } from '@/composables/useFlashToast';
import 'vue-sonner/style.css';

useFlashToast(); // Initialize toast listener
</script>

<template>
    <Toaster position="top-center" rich-colors closeButton />
    <AppLayout>
        <slot />
    </AppLayout>
</template>
```

### useFlashToast Composable

The composable automatically handles Inertia v3 `page.flash` events. It supports two flash data shapes:

1. `{ message: '...', type?: 'success' | 'error' | 'warning' | 'info' }`
2. `{ success: '...', error: '...' }` (keyed by toast type)

See source at `resources/js/composables/useFlashToast.ts`.

> **Important**: This composable handles `page.flash` only (native Inertia v3). Legacy `page.props.flash` from `->with()` is NOT handled here.

## Modal Form Integration

### Modal with useForm + Toast

```typescript
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Modal } from '@inertiaui/modal-vue';

const modalRef = ref<InstanceType<typeof Modal> | null>(null);

const form = useForm({
    name: '',
    code: '',
});

const submit = () => {
    form.post(route('items.store'), {
        onSuccess: () => {
            // Modal closes immediately
            modalRef.value?.close();
            
            // Toast shows automatically from backend flash
            // No manual toast.success() needed
            
            // Reset form for Create modal
            form.reset();
        },
        onError: () => {
            // Validation errors shown via form.errors
        },
    });
};
```

```vue
<template>
    <Modal ref="modalRef">
        <form @submit.prevent="submit">
            <!-- Form fields -->
            <button type="submit" :disabled="form.processing">
                {{ form.processing ? 'Saving...' : 'Save' }}
            </button>
        </form>
    </Modal>
</template>
```

## Full-page Form Integration

### Standard useForm with Toast

```typescript
const form = useForm({
    name: '',
    email: '',
});

const submit = () => {
    form.post(route('register'), {
        onSuccess: () => {
            // Page redirects automatically
            // Toast shows from backend flash
        },
        onError: () => {
            // Errors via form.errors
        },
    });
};
```

## Best Practices

### DO ✅
- Use `Inertia::flash()` in controllers for success messages
- Initialize `useFlashToast()` once in AppLayout
- Close modal manually in `onSuccess` callback
- Use `form.reset()` for Create forms after success
- Let backend handle toast messages automatically

### DON'T ❌
- Call `toast.success()` manually in form submissions
- Use `preserveScroll: true` with modal forms
- Mix `vee-validate` with Inertia `useForm`
- Create multiple toast listeners
- Handle toast logic in individual components

## Troubleshooting

### Toast not showing
1. Check `useFlashToast()` is called in AppLayout
2. Verify backend uses `Inertia::flash()` not `->with()`
3. Check browser console for JavaScript errors
4. Ensure vue-sonner CSS is imported

### Toast showing below modal
1. Toast uses portal rendering by default
2. If issues occur, check modal z-index
3. Vue-sonner typically handles this automatically

### Duplicate toast messages
1. Ensure only one `useFlashToast()` listener
2. Check for multiple AppLayout instances
3. Verify modal doesn't also call `useFlashToast()`

## Migration Guide

### Version context

The project upgraded `inertiajs/inertia-laravel` from v3.0.6 (no flash) to `3.x-dev` (with `Inertia::flash()` support). The `@inertiajs/core` client v3.0.3 already supported `page.flash`.

### Current state

- **239 controllers** use legacy `->with('success', ...)` → `page.props.flash`
- **BuildingController** uses `Inertia::flash()` → `page.flash` (reference implementation)
- Both systems coexist safely (different session keys, different page paths)

### From old `->with()` pattern

```php
// LEGACY — data goes to page.props.flash, requires per-page reading
return back()->with('success', 'Item created.');

// NEW — data goes to page.flash, auto-handled by useFlashToast
Inertia::flash('message', 'Item created.');
return back();

// Or chain flash with back():
return Inertia::flash('message', 'Item created.')->back();

// Or chain flash with render():
return Inertia::render('Items/Show', ['item' => $item])
    ->flash('message', 'Item loaded from cache.');
```

### From manual toast calls

```typescript
// OLD — manual toast in onSuccess
onSuccess: () => {
    toast.success('Item created!');
    router.visit(route('items.index'));
}

// NEW — toast handled automatically via page.flash
onSuccess: () => {
    router.visit(route('items.index'));
}
```

### Frontend: accessing non-toast flash data

```vue
<script setup>
import { router } from '@inertiajs/vue3';

// Via onFlash callback on a specific request
router.post('/enroll', data, {
    onFlash: ({ enrollmentId }) => {
        navigateToEnrollment(enrollmentId);
    },
});
</script>
```