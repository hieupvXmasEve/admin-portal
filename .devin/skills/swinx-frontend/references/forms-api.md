# Forms API Reference — Inertia v3

> Read this file when implementing forms: useForm, `<Form>`, file uploads, precognition, optimistic updates.

---

## useForm — Full API

### Constructor variants

```typescript
import { useForm } from '@inertiajs/vue3';

// Standard — with optional form key for history persistence
const form = useForm('edit-contact', {
    name: '',
    email: '',
    role: 'developer',
    subscribe: false,
});

// Without key
const form = useForm({ name: '', email: '' });

// Precognition constructor — method + url + data
const form = useForm('post', route('contacts.store'), {
    name: '',
    email: '',
});
```

### Submit methods

```typescript
import { updateContact } from '@/wayfinder/...';

// Wayfinder (preferred) — .submit() takes { url, method }
form.submit(updateContact({ contact: contact.id }), {
    preserveScroll: true,
    onSuccess: () => form.defaults(),
});

// Classic — explicit method
form.post(route('contacts.store'), options);
form.put(route('contacts.update', id), options);
form.patch(route('contacts.update', id), options);
form.delete(route('contacts.destroy', id), options);

// Transform before submit
form.transform((data) => ({
    ...data,
    name: data.name.toUpperCase(),
})).submit(action(), { preserveScroll: true });
```

### Submit options

```typescript
form.post(url, {
    preserveScroll: true,       // Don't reset scroll position
    preserveState: true,        // Keep component state
    errorBag: 'formName',       // Scope errors to a bag
    only: ['contacts'],         // Partial reload
    onBefore: () => confirm('Sure?'),
    onSuccess: (page) => { /* ... */ },
    onError: (errors) => { /* ... */ },
    onFinish: () => { /* ... */ },
    onCancel: () => { /* ... */ },
    onProgress: (progress) => { /* ... */ },
});
```

### Reactive state

| Property | Type | Description |
|----------|------|-------------|
| `form.processing` | `boolean` | Request in flight |
| `form.isDirty` | `boolean` | Data differs from defaults |
| `form.hasErrors` | `boolean` | Validation errors exist |
| `form.wasSuccessful` | `boolean` | Last submit succeeded |
| `form.recentlySuccessful` | `boolean` | Succeeded within 2s |
| `form.progress` | `{ percentage: number } \| null` | Upload progress |
| `form.errors` | `Record<string, string>` | Validation error messages |

### Methods

| Method | Description |
|--------|-------------|
| `form.reset()` | Reset all fields to defaults |
| `form.reset('name', 'email')` | Reset specific fields |
| `form.clearErrors()` | Clear all errors |
| `form.clearErrors('name')` | Clear specific field |
| `form.cancel()` | Cancel in-flight request |
| `form.defaults()` | Set current values as new defaults |
| `form.defaults('name', 'New')` | Set specific field default |
| `form.setError('field', 'msg')` | Set manual error |
| `form.setError({ a: '...', b: '...' })` | Set multiple errors |
| `form.data()` | Get plain data object |

### Template pattern

```vue
<form @submit.prevent="submit">
    <Input v-model="form.name" />
    <InputError :message="form.errors.name" />

    <div v-if="form.progress" class="w-full rounded-full bg-secondary">
        <div class="h-2 rounded-full bg-primary" :style="{ width: `${form.progress.percentage}%` }" />
    </div>

    <Button type="submit" :disabled="form.processing">
        {{ form.processing ? 'Saving...' : 'Save' }}
    </Button>
    <span v-if="form.isDirty">Unsaved changes</span>
    <span v-if="form.recentlySuccessful">Saved!</span>
</form>
```

---

## `<Form>` Component — Full API

Template-first alternative. Uses native HTML `name` attributes instead of `v-model`.

### Basic usage

```vue
<script setup>
import { Form } from '@inertiajs/vue3';
import { submitAction } from '@/wayfinder/...';
</script>

<template>
    <Form
        v-bind="submitAction.form()"
        :set-defaults-on-success="true"
        class="space-y-4"
        #default="{ errors, processing, isDirty, recentlySuccessful, progress, submit, reset, clearErrors, setError }"
    >
        <Input name="name" />
        <InputError :message="errors.name" />

        <Input name="email" type="text" />
        <textarea name="bio" rows="3" />

        <select name="role">
            <option value="developer">Developer</option>
            <option value="designer">Designer</option>
        </select>

        <input type="checkbox" name="subscribe" value="1" />

        <Button type="submit" :disabled="processing">Submit</Button>
    </Form>
</template>
```

### Edit forms — `:default-value`

```vue
<Form v-bind="updateContact.form({ contact: contact.id })" :set-defaults-on-success="true">
    <Input name="name" :default-value="contact.name" />
    <Input name="email" :default-value="contact.email" />
    <textarea name="notes" :default-value="contact.notes" />
</Form>
```

### Component props

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `action` | `string` | — | URL to submit to |
| `method` | `string` | `'post'` | HTTP method |
| `reset-on-success` | `boolean` | `false` | Clear form after success |
| `set-defaults-on-success` | `boolean` | `false` | Update defaults after success |
| `validation-timeout` | `number` | — | Precognition debounce (ms) |
| `optimistic` | `(page) => void` | — | Optimistic update function |

### Slot props

| Prop | Type | Description |
|------|------|-------------|
| `errors` | `Record<string, string>` | Validation errors |
| `processing` | `boolean` | Request in flight |
| `isDirty` | `boolean` | Unsaved changes |
| `recentlySuccessful` | `boolean` | Success within 2s |
| `progress` | `object \| null` | Upload progress |
| `submit` | `() => void` | Trigger submission |
| `reset` | `() => void` | Reset to defaults |
| `clearErrors` | `() => void` | Clear all errors |
| `setError` | `(field, msg) => void` | Set manual error |
| `validate` | `(field) => void` | Precognition: validate field |
| `valid` | `(field) => boolean` | Precognition: field passed |
| `invalid` | `(field) => boolean` | Precognition: field failed |
| `validating` | `boolean` | Precognition: in progress |

### Template ref — external control

```vue
<script setup>
const formRef = ref(null);
// From outside: formRef.value?.submit(), formRef.value?.reset(), formRef.value?.clearErrors()
</script>

<Form ref="formRef" ...><!-- content --></Form>
<Button @click="formRef?.submit()">Submit from outside</Button>
```

### Dotted keys — nested data

```vue
<!-- Submits { address: { city: '...', zip: '...' } } -->
<Input name="address.city" />
<Input name="address.zip" />
```

### useFormContext — child component access

```typescript
// In deeply nested child component
import { useFormContext } from '@inertiajs/vue3';
const form = useFormContext(); // Access parent <Form>
```

---

## File Uploads

### Single file

```typescript
const form = useForm({ name: '', photo: null as File | null });

function onFileChange(e: Event) {
    form.photo = (e.target as HTMLInputElement).files?.[0] ?? null;
}

form.post(route('contacts.store')); // Auto multipart/form-data
```

### Multiple files

```typescript
const form = useForm({ files: [] as File[] });

function onFilesChange(e: Event) {
    form.files = Array.from((e.target as HTMLInputElement).files ?? []);
}
```

### Progress tracking

```vue
<div v-if="form.progress" class="w-full rounded-full bg-secondary">
    <div class="h-2 rounded-full bg-primary transition-all"
         :style="{ width: `${form.progress.percentage}%` }" />
</div>
```

---

## Precognition (Real-Time Server Validation)

### Backend setup

```php
Route::post('/contacts', [ContactController::class, 'store'])
    ->middleware('precognitive');
```

### Frontend with useForm

```typescript
const form = useForm('post', route('contacts.store'), { name: '', email: '' });
form.setValidationTimeout(500);
```

```vue
<Input v-model="form.name" @change="form.validate('name')"
    :class="{ 'border-green-500': form.valid('name'), 'border-red-500': form.invalid('name') }" />
<span v-if="form.validating">Validating...</span>
```

### Precognition state

| API | Description |
|-----|-------------|
| `form.validate('field')` | Trigger server validation |
| `form.valid('field')` | Field passed validation |
| `form.invalid('field')` | Field failed validation |
| `form.touched('field')` | Field has been validated |
| `form.validating` | Validation in progress |
| `form.setValidationTimeout(ms)` | Debounce |

### Frontend with `<Form>` component

```vue
<Form action="/contacts" method="post" :validation-timeout="500"
    #default="{ errors, validate, valid, invalid, validating }">
    <Input name="name" @change="validate('name')"
        :class="{ 'border-green-500': valid('name') }" />
</Form>
```

---

## Optimistic Updates

Instant UI update before server responds. Auto-rollback on error.

### With router

```typescript
router.optimistic<PageProps>((page) => {
    page.contact.name = 'New Name';
}).post(route('contacts.update', id), { name: 'New Name' });
```

### With useForm

```typescript
form.optimistic<PageProps>((page) => {
    page.contact.name = form.name;
}).post(route('contacts.update', id), { preserveScroll: true });
```

### With `<Form>` component

```vue
<Form v-bind="updateAction.form()"
    :optimistic="(page) => { page.contact.name = 'Updated' }">
```

---

## Error Bags

Isolate errors when multiple forms share a page.

```typescript
form.post(url, { errorBag: 'secondaryForm' });
```

## Manual Errors

```typescript
form.setError('name', 'Already taken');
form.setError({ name: 'Error 1', email: 'Error 2' });
form.clearErrors('name');
form.clearErrors();
```
