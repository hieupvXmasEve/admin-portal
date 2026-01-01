---
name: create-inertia-page
description: Scaffolds a new Vue 3 Inertia page. Use when creating frontend pages (e.g., "Create a student list page" or "Make a create form for courses").
allowed-tools: Write, Read
---

# Create Inertia Page

This skill generates a standardized Vue 3 + TypeScript + Inertia.js page component.

## Instructions

1.  **Analyze Request**:
    -   **Module**: (e.g., `Academic`, `Identity`)
    -   **Entity**: (e.g., `Student`, `Course`)
    -   **Type**: `Index` (List), `Create` (Form), `Edit` (Form), `Show` (Details).
    -   **Path**: `resources/js/Pages/{Module}/{Entity}/{Type}.vue`

2.  **Determine Structure**:
    -   **Index**: requires `DataTable`, filters prop, list prop.
    -   **Create/Edit**: requires `useForm`, validation errors handling.
    -   **Show**: requires entity detail prop.

3.  **Generate Code**:
    -   Use `<script setup lang="ts">`.
    -   Define `Props` interface.
    -   Use `<Head title="..." />`.
    -   Wrap content in `<AuthenticatedLayout>` (or similar Layout).
    -   **Strictly follow** `.claude/rules/frontend.md`.

## Template (Index Page)

```vue
<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Button } from '@/components/ui/button';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import DataTable from '@/Components/Common/DataTable.vue'; // Verify path
import { useInertiaFilters } from '@/Composables/useInertiaFilters';

// 1. Define Props
interface Props {
    items: {
        data: any[];
        links: any[];
        meta: any;
    };
    filters?: Record<string, any>;
}

const props = defineProps<Props>();

// 2. Setup Filters
const { filters, onFilterChange } = useInertiaFilters({
    baseUrl: route('module.entity.index'), // Replace with actual route
    initialFilters: props.filters,
});

const columns = [
    { key: 'id', label: 'ID' },
    { key: 'name', label: 'Name' },
    { key: 'created_at', label: 'Created' },
    { key: 'actions', label: 'Actions' },
];
</script>

<template>
    <Head title="Page Title" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    Page Title
                </h2>
                <Button as-child>
                    <Link :href="route('module.entity.create')">
                        Create New
                    </Link>
                </Button>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <Card>
                    <CardHeader>
                        <CardTitle>List of Items</CardTitle>
                        <CardDescription>Manage your items here.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <DataTable
                            :data="props.items.data"
                            :meta="props.items.meta"
                            :columns="columns"
                            @update:page="..."
                        />
                    </CardContent>
                </Card>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
```

## Checklist
- [ ] Did you define a `Props` interface?
- [ ] Are you using `route()` for links?
- [ ] Are you using shadcn components for UI?
- [ ] Is business logic (filtering/sorting) kept out of the component (server-side)?
