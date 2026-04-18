---
phase: 05
title: Frontend Vue Updates
status: completed
---

# Phase 05 — Frontend Vue Updates

## Overview

Add `campus_id` field to the email configuration form, show campus label in the config list, and allow filtering configs by campus.

## Files

- `resources/js/pages/Admin/EmailConfiguration/Index.vue`
- `resources/js/pages/Admin/EmailConfiguration/components/AddSmtpConfigurationModal.vue`
- `resources/js/pages/Admin/EmailConfiguration/components/EditSmtpConfigurationModal.vue`
- `resources/js/composables/useSmtpConfiguration.ts`

## 1. TypeScript Interface Updates

In both `Index.vue` and `useSmtpConfiguration.ts`, update the `EmailConfiguration` interface:
```ts
interface EmailConfiguration {
    // ... existing fields ...
    campus_id: number | null;
    campus?: { id: number; name: string; code: string };
}

interface ConfigurationForm {
    // ... existing fields ...
    campus_id: number | null;
}
```

## 2. Index.vue — Receive Props & Campus Filter

The Web controller now passes `campuses` and `currentCampusId` as Inertia props:
```ts
const props = defineProps<{
    campuses: { id: number; name: string; code: string }[];
    currentCampusId: number | null;
}>();
```

Add a campus badge next to each config name in the list. Configs with `campus_id = null` show a "Global" badge.

No client-side filter needed — the API already returns only configs relevant to the current campus session. Keep it simple.

## 3. AddSmtpConfigurationModal / EditSmtpConfigurationModal

Add a `campus_id` select field to the form:
```vue
<Select v-model="form.campus_id">
    <SelectTrigger>
        <SelectValue placeholder="Global (shared across campuses)" />
    </SelectTrigger>
    <SelectContent>
        <SelectItem :value="null">Global (shared)</SelectItem>
        <SelectItem v-for="campus in campuses" :key="campus.id" :value="campus.id">
            {{ campus.name }}
        </SelectItem>
    </SelectContent>
</Select>
```

Pass `campuses` prop down from Index.vue to both modals.

Default `campus_id` in the Add form to `currentCampusId` (pre-select current campus).

## 4. useSmtpConfiguration.ts

- Add `campus_id: number | null` to `ConfigurationForm`
- Include `campus_id` in `createConfiguration()` and `updateConfiguration()` payloads
- No changes needed to `loadConfigurations()` — campus filtering happens server-side

## Implementation Notes

- Use existing `Select` / `SelectItem` from `@/components/ui/select` (shadcn-vue)
- Keep the modal form under 200 lines — extract campus select into a small inline block, not a new component
- After save, `loadConfigurations()` already refreshes the list

## Todo

- [ ] Update `EmailConfiguration` TS interface (add `campus_id`, `campus?`)
- [ ] Update `ConfigurationForm` TS interface (add `campus_id`)
- [ ] Update `Index.vue` — add `defineProps` for `campuses` + `currentCampusId`
- [ ] Add campus badge to config list rows in `Index.vue`
- [ ] Update `AddSmtpConfigurationModal` — add campus_id select, accept campuses prop
- [ ] Update `EditSmtpConfigurationModal` — add campus_id select, accept campuses prop
- [ ] Update `useSmtpConfiguration.ts` — include campus_id in create/update payloads
- [ ] Run `./scripts/dev.sh npm run type-check` to verify no TS errors
