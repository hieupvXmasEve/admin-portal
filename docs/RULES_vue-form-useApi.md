# Vue Form Validation + useApi Rules

This document describes an exception path.

Default path in this codebase:

- Inertia page forms use Inertia `useForm` + server-side validation/redirect flow.

Use the rules below only when a Vue form validates on the client with `vee-validate` + Zod and persists data through `useApi`/`useApiRequest` instead of a full Inertia visit.

## 1. When to rely on this combo

- The UI submits data from the current page (modal, drawer, inline card, etc.) but should not navigate away.
- Laravel exposes JSON endpoints that follow the standard `{ success, message, data, errors }` contract.
- You need CSRF/session cookies, so `useApiRequest` must wrap the request to attach headers automatically.
- You intentionally accept the extra complexity vs standard Inertia `useForm`.

## 2. Frontend validation pattern

1. **Define the editing context**
    ```ts
    const props = defineProps<{ entity?: Entity | null }>();
    const isEditing = computed(() => !!props.entity);
    ```
    - Use computed flags for toggling create vs edit behaviour.
2. **Author a Zod schema + convert with `toTypedSchema`**
    - Mirror Laravel FormRequest rules (required, min/max, enums, refinements).
    - Store reusable schemas under `resources/js/schemas/`.
3. **Initialize `useForm` with the schema**
    ```ts
    const { handleSubmit, isSubmitting, setValues, errors, defineField } = useForm({
        validationSchema: formSchema,
        initialValues: { name: '', code: '' },
    });
    ```
    - Never leave `initialValues` undefined; keep types aligned with the schema and Laravel defaults.
4. **Expose fields via `defineField` or `<FormField>`**
    ```ts
    const [name, nameAttrs] = defineField('name');
    ```
    - Bind inputs with `v-model="name"` and `v-bind="nameAttrs"` so vee-validate handles input events correctly.
5. **Sync editing values**
    - When editing, call `setValues` inside `watch`/`onMounted` to hydrate the form from props.
    - On cancel, clear local form state to remove dirty values.
6. **Show errors consistently**
    - Apply error classes, e.g. `:class="{ 'border-destructive': errors.name }"`.
    - Render an inline message: `<p v-if="errors.name" class="text-destructive text-sm">{{ errors.name }}</p>`.

## 3. Calling Laravel APIs with `useApi`

1. **Initialize once per component**
    ```ts
    import { useApi } from '@/composables/useApiRequest';
    const api = useApi();
    ```
2. **Use centralized route helpers**
    - Generate URLs through `systemRoutes` or `route()` so frontend and backend stay in sync.
3. **Wrap submission with `handleSubmit`**
    ```ts
    const onSubmit = handleSubmit(async (values) => {
        if (isEditing.value && props.entity) {
            await api.put(route('entities.update', props.entity.id), values);
        } else {
            await api.post(route('entities.store'), values);
        }
        toast.success(isEditing.value ? 'Updated successfully' : 'Created successfully');
        // optionally reset local form state after success
        router.reload({ only: ['entities'] });
    });
    ```
    - Always `await` the `api` call; methods return `{ data }` from Laravel.
    - Disable submit buttons with `:disabled="isSubmitting"` or use loading indicators.
4. **Handle API errors**
    - Laravel validation errors come through `error.response?.data?.errors`. Feed them into vee-validate or show `toast.error('Please check the form')`.
    - For unexpected failures, emit a contextual toast (e.g., `'Failed to save entity'`).

## 4. After the request

- Decide whether to reset or keep the form. After create flows, clear local form state; for repeated edit flows, keep values as needed.
- Refresh Inertia props with `router.reload({ only: [...] })` or locally mutate arrays to avoid full reloads.
- Toasts should confirm success/failure; avoid silent updates.

## 5. Checklist

- Zod schema matches Laravel validation rules.
- `useForm` carries initial values for every field.
- Inputs rely on consistent form bindings (for example `defineField`) so errors and events are wired correctly.
- Submit button reflects `isSubmitting` to prevent duplicate calls.
- API URLs come from helpers, not literals.
- Both create and edit flows reuse the same handler with an `isEditing` guard.
- After success, either reload the necessary Inertia props or update local state to reflect changes.

Following these steps keeps form validation consistent, ensures frontend inputs mirror Laravel rules, and guarantees all API calls include the required CSRF/session context via `useApiRequest`.
