# InertiaUI Modal — @inertiaui/modal-vue v3

> Route-based modals for Inertia v3 + Vue 3. Swinx uses `@inertiaui/modal-vue ^3.1.0`.
> Backend package NOT installed — use `Inertia::render()` (not `Inertia::modal()`).

---

## Architecture in Swinx

### Setup (already done in app.ts)

```typescript
import { withInertiaModal } from '@inertiaui/modal-vue';
// Inside createInertiaApp setup:
withInertiaModal(app);
```

### Pattern: Route-based modal

1. **Backend**: Standard `Inertia::render()` returning props — same as any page.
2. **Modal page**: Vue page wrapping content in `<Modal>` component.
3. **Trigger**: `<ModalLink :href="...">` on parent page — fetches and renders as overlay.
4. **Close**: `modalRef.value?.close()` in `onSuccess`, or default close button/Esc/backdrop.

Flow: `<ModalLink>` click → fetch modal page → render in `<Modal>` overlay → `modalRef.close()` on success → back to parent.

---

## Components

### `Modal` — wraps modal page content

Import: `import { Modal } from '@inertiaui/modal-vue'`

```vue
<template>
    <Modal ref="modalRef">
        <div class="p-6">
            <!-- Modal content -->
        </div>
    </Modal>
</template>
```

**Events on `<Modal>`:**

| Event | When |
|-------|------|
| `@success` | Modal successfully fetched and opened |
| `@close` | Modal is closed |
| `@after-leave` | Modal removed from DOM (transition ended) |
| `@blur` | Another modal opened on top (stacked) |
| `@focus` | Child modal closed, this modal regains focus |

**Slot props** (`v-slot="{ close, emit, reload, getParentModal, getChildModal }"`):

| Prop | Use |
|------|-----|
| `close` | Close the modal programmatically |
| `emit(event, ...args)` | Emit event to parent `<ModalLink>` |
| `reload(options?)` | Reload modal props (like `router.reload`) |
| `getParentModal()` | Access parent modal in stacked modals |
| `getChildModal()` | Access child modal in stacked modals |

**Ref methods** (via `const modalRef = ref<InstanceType<typeof Modal>>(null)`):

| Method | Use |
|--------|-----|
| `modalRef.value?.close()` | Close modal (Swinx standard pattern) |
| `modalRef.value?.emit(event, ...args)` | Emit event to parent |
| `modalRef.value?.reload({ only: [...] })` | Partial reload of modal props |

### `ModalLink` — opens a route-based modal

Import: `import { ModalLink } from '@inertiaui/modal-vue'`

```vue
<ModalLink
    :href="route('campuses.create')"
    as="button"
    class="..."
>
    Add Campus
</ModalLink>
```

**Props:**

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `href` | `string` | required | URL of the modal page, or `#name` for local modals |
| `as` | `string \| Component` | `'a'` | Rendered HTML tag or Vue component |
| `method` | `string` | `'get'` | HTTP method for the request |
| `data` | `object` | `{}` | Additional data sent with the request |
| `headers` | `object` | `{}` | Custom request headers |
| `prefetch` | `boolean \| string \| string[]` | `false` | Prefetch strategy: `'hover'`, `'click'`, `'mount'` |
| `cache-for` | `number` | `30000` | Prefetch cache duration in ms |
| `navigate` | `boolean` | config default | Update browser URL (requires `Inertia::modal()` on backend) |
| `close-button` | `boolean` | `true` | Show/hide close button |
| `close-explicitly` | `boolean` | `false` | Only close via button/code (disable Esc + backdrop) |
| `close-on-click-outside` | `boolean` | `true` | Close on backdrop click (Esc still works) |
| `max-width` | `string` | `'2xl'` | Modal max-width: `sm\|md\|lg\|xl\|2xl\|3xl\|4xl\|5xl\|6xl\|7xl` |
| `padding-classes` | `string` | `'p-4 sm:p-6'` | Modal padding classes |
| `panel-classes` | `string` | `'bg-white rounded'` | Modal panel classes |
| `position` | `string` | `'center'` | Modal: `bottom\|center\|top`. Slideover: `left\|right` |
| `slideover` | `boolean` | `false` | Render as side panel instead of modal |

**Events on `<ModalLink>`:**

| Event | When |
|-------|------|
| `@start` | Request started (show loading) |
| `@success` | Modal loaded and opened |
| `@error` | Request failed |
| `@close` | Modal closed |
| `@after-leave` | Modal removed from DOM |
| `@prefetching` | Prefetch request started |
| `@prefetched` | Prefetch completed |

**Slot props** (`#default="{ loading }"`):

```vue
<ModalLink :href="route('campuses.create')" #default="{ loading }">
    {{ loading ? 'Loading...' : 'Add Campus' }}
</ModalLink>
```

### `visitModal` — programmatic modal opening

Import: `import { visitModal } from '@inertiaui/modal-vue'`

```typescript
visitModal('/users/create')
visitModal('#local-modal-name')  // local modals use # prefix

visitModal('/users/create', {
    method: 'post',
    data: { default_name: 'John Doe' },
    headers: { 'X-Header': 'Value' },
    config: { slideover: true, maxWidth: 'lg' },
    listeners: { saved: (data) => console.log(data) },
    onSuccess: () => console.log('Opened'),
    onClose: () => console.log('Closed'),
    onAfterLeave: () => console.log('Removed from DOM'),
})
```

### `useModal` — access modal context from child components

Import: `import { useModal } from '@inertiaui/modal-vue'`

```typescript
const { props } = useModal()
// Access modal props in nested child components without prop drilling
```

---

## Patterns

### Local Modals (inline, no route)

For simple modals that don't need their own route/page:

```vue
<template>
    <ModalLink href="#confirm-delete">Delete</ModalLink>

    <Modal name="confirm-delete" v-slot="{ close }">
        <p>Are you sure?</p>
        <button @click="deleteItem(); close()">Yes</button>
    </Modal>
</template>
```

With dynamic props via `visitModal`:

```typescript
import { visitModal } from '@inertiaui/modal-vue'

visitModal('#confirm-delete', {
    props: { itemId: 123, message: 'Delete this?' }
})
```

```vue
<Modal name="confirm-delete" v-slot="{ itemId, message }">
    <p>{{ message }}</p>
    <button @click="deleteItem(itemId)">Confirm</button>
</Modal>
```

### Nested / Stacked Modals

Use `<ModalLink>` inside `<Modal>` — child modal opens on top:

```vue
<Modal #default="{ reload }">
    <select name="role_id">
        <option v-for="role in roles" :key="role.id" :value="role.id">{{ role.name }}</option>
    </select>
    <!-- Reload parent modal props when child closes -->
    <ModalLink href="/roles/create" @close="reload">
        Add Role
    </ModalLink>
</Modal>
```

**Form submissions in nested modals**: Use Axios instead of `router.post()` — Inertia redirect closes ALL modals in stack.

```typescript
import axios from 'axios'
// Inside nested modal:
axios.post('/submit', formData).then(() => {
    modalRef.value?.close()  // Only closes current modal
})
```

### Slideover Panel

```vue
<ModalLink :href="route('settings.show')" slideover max-width="md">
    Settings
</ModalLink>
```

### Reload Modal Props

```vue
<Modal #default="{ reload }">
    <button @click="reload({ only: ['permissions'] })">
        Refresh permissions
    </button>
</Modal>
```

With lifecycle events:

```typescript
reload({
    only: ['permissions'],
    onStart: () => { loading.value = true },
    onSuccess: () => { console.log('Reloaded') },
    onFinish: () => { loading.value = false },
})
```

### Event Bus (modal → parent communication)

```vue
<!-- In modal page -->
<Modal #default="{ emit }">
    <button @click="emit('itemCreated', newItem)">Save</button>
</Modal>

<!-- In parent page -->
<ModalLink href="/items/create" @item-created="handleCreated">
    Add Item
</ModalLink>
```

---

## Default Configuration (putConfig)

```typescript
import { putConfig, getConfig } from '@inertiaui/modal-vue'

putConfig({
    type: 'modal',
    navigate: false,         // true requires Inertia::modal() backend
    useNativeDialog: true,   // <dialog> element for a11y
    appElement: '#app',      // aria-hidden when modal open
    modal:     { closeButton: true, closeExplicitly: false, closeOnClickOutside: true, maxWidth: '2xl', paddingClasses: 'p-4 sm:p-6', panelClasses: 'bg-white rounded', position: 'center' },
    slideover: { closeButton: true, closeExplicitly: false, closeOnClickOutside: true, maxWidth: 'md',  paddingClasses: 'p-4 sm:p-6', panelClasses: 'bg-white min-h-screen', position: 'right' },
})

putConfig('modal.closeButton', false)       // Override single key
const maxWidth = getConfig('modal.maxWidth') // Read config
```

---

## Swinx-Specific Conventions

1. **All CRUD modals use route-based pattern** — separate Vue page wrapped in `<Modal>`, opened via `<ModalLink>`.
2. **Backend uses `Inertia::render()`** — NOT `Inertia::modal()` (backend package not installed).
3. **Forms use `useForm`** from `@inertiajs/vue3` inside modals — not vee-validate.
4. **Close via ref**: `const modalRef = ref<InstanceType<typeof Modal>>(null)` → `modalRef.value?.close()` in `onSuccess`.
5. **Create forms**: `form.reset()` after close. Edit forms: no reset (or `form.defaults()`).
6. **Modal styling**: Content wrapped in `<div class="p-6">` inside `<Modal>` (Swinx convention for padding).
7. **Trigger buttons**: `<ModalLink ... as="button" class="...">` — use `as="button"` for non-link triggers.
8. **`navigate` is false** — Swinx does not update URL on modal open (no `Inertia::modal()` backend).
9. **`<ModalLink>` events**: Use `@close` on `<ModalLink>` to trigger parent page refresh when child modal closes.
10. **Confirmation dialogs**: Use `useConfirmDialogStore` (Pinia) for simple confirms — NOT `<Modal>` component.

---

## UI Component Gotchas Inside Modals

### Global config (app.ts)

Swinx disables the native `<dialog>` element globally to prevent z-index and teleport conflicts:

```typescript
// resources/js/app.ts
import { withInertiaModal, putConfig } from '@inertiaui/modal-vue';

putConfig({
    useNativeDialog: false,
    modal:     { closeOnClickOutside: false },
    slideover: { closeOnClickOutside: false },
});
withInertiaModal(app);
```

**Why `useNativeDialog: false`**: The browser's native `<dialog>` creates a top-layer stacking context. `SelectPortal` from reka-ui teleports to `document.body`, which is outside the top-layer — making Select dropdowns appear behind the modal.

**Why `closeOnClickOutside: false`**: Swinx modals require the explicit X button to close (prevents accidental close on mis-click).

---

### Select dropdowns inside modals

`useNativeDialog: false` fixes this automatically. No per-modal code needed.

**Root cause (for reference)**: Native `<dialog>` top-layer + `SelectPortal` teleporting to `document.body` = dropdown behind modal. Disabling native dialog eliminates the top-layer isolation.

---

### DatePicker (and any Popover) inside modals

`@inertiaui/modal-vue` with `useNativeDialog: false` creates a focus trap via `createFocusTrap` on the modal wrapper. When `PopoverPortal` teleports the calendar to `document.body`, focus leaving the modal triggers the trap to force focus back — dismissing the Popover immediately.

**Fix**: teleport the Popover content *inside* the modal DOM (inside the focus trap container).

`DatePicker` supports a `portalTo` prop for this:

```vue
<script setup lang="ts">
import { ref } from 'vue';
const modalContentRef = ref<HTMLElement | null>(null);
</script>

<template>
    <Modal ref="modalRef">
        <div ref="modalContentRef" class="p-6">
            <DatePicker
                v-model="form.date"
                :portal-to="modalContentRef ?? undefined"
            />
        </div>
    </Modal>
</template>
```

**Why it works**: calendar teleports inside `modalContentRef` (which is inside the focus trap) → focus stays in trap → Popover stays open.

**Note**: Reka-ui uses `position: fixed` (Floating UI default) so no overflow clipping occurs even when rendered inside a scrollable parent.

**Do NOT** omit `portalTo` when using `DatePicker` inside a modal — the calendar will open and immediately close due to the focus trap.

---

### TimePicker inside modals

`TimePicker` uses `TimeFieldRoot` + `TimeFieldInput` from reka-ui — no Popover, no portal. Works inside modals with no extra setup.

Usage:

```vue
<TimePicker v-model="form.start_time" />
<!-- v-model accepts/emits HH:mm string (24-hour, matches DB format) -->
```

Props: `modelValue?: string`, `disabled?: boolean`, `granularity?: 'hour'|'minute'|'second'` (default `minute`), `class?: string`.

---

### Summary table

| Component | Works inside modal? | Extra setup needed |
|-----------|--------------------|--------------------|
| `Select` / `SelectContent` | Yes (after `useNativeDialog: false`) | None |
| `DatePicker` | Yes | Pass `:portal-to="modalContentRef"` |
| `TimePicker` | Yes | None |
| Any `Popover`-based component | Needs fix | Pass `:to="modalContentRef"` to `PopoverContent` |
