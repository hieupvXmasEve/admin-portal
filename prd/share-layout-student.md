### 1. Route (Laravel)

```php
// routes/web.php
Route::prefix('students/{student}')->group(function () {
    Route::get('overview', [StudentController::class, 'overview'])->name('students.overview');
    Route::get('enrollments', [StudentController::class, 'enrollments'])->name('students.enrollments');
});
```

### 2. Controller

```php
// app/Http/Controllers/StudentController.php
class StudentController extends Controller
{
    public function overview(Student $student)
    {
        return Inertia::render('Students/Overview', [
            'student' => $student->only(['id','student_id','full_name','avatar_url']),
            'overviewData' => ['gpa' => 3.5], // demo
        ]);
    }

    public function enrollments(Student $student)
    {
        return Inertia::render('Students/Enrollments', [
            'student' => $student->only(['id','student_id','full_name','avatar_url']),
            'enrollments' => $student->enrollments()->with('semester')->get(),
        ]);
    }
}
```

### 3. Layout

```vue
<!-- resources/js/Layouts/StudentLayout.vue -->
<template>
  <div>
    <header>
      <img :src="student.avatar_url" class="w-10 h-10" />
      <span>{{ student.full_name }} ({{ student.student_id }})</span>
    </header>
    <nav>
      <Link :href="route('students.overview', student.id)">Overview</Link>
      <Link :href="route('students.enrollments', student.id)">Enrollments</Link>
    </nav>
    <main>
      <slot />
    </main>
  </div>
</template>

<script setup>
defineProps({ student: Object })
</script>
```

### 4. Page dùng layout

```vue
<!-- resources/js/Pages/Students/Overview.vue -->
<template>
  <StudentLayout :student="student">
    <h2>Overview</h2>
    <p>GPA: {{ overviewData.gpa }}</p>
  </StudentLayout>
</template>

<script setup>
import StudentLayout from '@/Layouts/StudentLayout.vue'
defineProps({ student: Object, overviewData: Object })
</script>
```

👉 Cách hoạt động:

* **Layout** chỉ nhận data nhẹ (`id, name, avatar`).
* **Mỗi tab** (`Overview`, `Enrollments`) load thêm data riêng qua route.
* Khi bạn click tab → Inertia chỉ fetch data của tab đó, không tải lại toàn bộ student.
