# 🧭 Standard Filter Handling in Laravel + InertiaJS + Vue

## 1️⃣ Backend (Laravel Controller)

```php
public function index(Request $request)
{
    $filters = $request->only(['search', 'status', 'campus']);

    $students = Student::query()
        ->when($filters['search'] ?? null, fn($q, $v) => $q->where('name', 'like', "%$v%"))
        ->when($filters['status'] ?? null, fn($q, $v) => $q->where('status', $v))
        ->when($filters['campus'] ?? null, fn($q, $v) => $q->where('campus_id', $v))
        ->paginate(10)
        ->withQueryString();

    return inertia('Students/Index', [
        'students' => $students,
        'filters'  => $filters, // send filters back to FE
    ]);
}
````

## 2️⃣ Frontend (Vue + Inertia)

```vue
<script setup>
import { router } from '@inertiajs/vue3'
import { useUrlSearchParams } from '@vueuse/core'

const props = defineProps({
  students: Object,
  filters: Object,
})

// Create reactive URL params
const params = useUrlSearchParams('history')

// Sync initial filters from backend once
onMounted(() => {
  if (Object.keys(props.filters || {}).length > 0) {
    Object.assign(params, props.filters)
  }
})

// Watch for changes and re-fetch data
watch(params, (newVal) => {
  router.get(route('students.index'), newVal, {
    preserveState: true,
    replace: true,
  })
})
</script>

<template>
  <div class="filters">
    <input v-model="params.search" placeholder="Search student..." />
    <select v-model="params.status">
      <option value="">All</option>
      <option value="active">Active</option>
      <option value="inactive">Inactive</option>
    </select>
  </div>

  <Table :data="props.students.data" />
</template>
```

## 3️⃣ Best Practice Summary

| Step            | Description                                                         |
| --------------- | ------------------------------------------------------------------- |
| **1. Laravel**  | Extract filters from request and return via `props`                 |
| **2. FE init**  | On mounted, merge `props.filters` → `useUrlSearchParams()`          |
| **3. FE watch** | Watch `params` → trigger `router.get()` with `replace: true`        |
| **4. URL Sync** | `useUrlSearchParams()` keeps filters reactive and shareable via URL |

## ✅ Advantages

* Full 2-way sync between **URL ↔ Filters ↔ Data**
* Reload, Back/Forward, Share link all preserved
* Clean UX & simple integration with Laravel pagination (`withQueryString`)