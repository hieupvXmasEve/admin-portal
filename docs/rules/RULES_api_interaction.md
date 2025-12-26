# API Interaction Rules (Laravel + useApi)

This guide standardizes how Frontend (Vue 3) communicates with Backend (Laravel) using the `useApi` composable and the unified `ApiResponse` class.

## 1. Backend Standard (Laravel)

All API responses MUST use the `App\Http\Responses\ApiResponse` class to ensure a consistent JSON envelope.

### 1.1. Success Response

```php
// Standard data return
return ApiResponse::success($data, $meta = [], $message = 'Success');

// Paginated data
return ApiResponse::paginated($paginator);
```

**Envelope Structure:**

```json
{
    "success": true,
    "timestamp": "2025-12-25T20:42:21Z",
    "data": [...],
    "message": "Optional message",
    "meta": {} // Optional
}
```

### 1.2. Error Response

```php
// Generic error
return ApiResponse::error('Something went wrong', $errors = [], $status = 400);

// Validation error (automatic in FormRequests, but manual if needed)
return ApiResponse::validationError($validator->errors()->toArray());
```

---

## 2. Frontend Standard (Vue 3 + useApi)

The `useApi` composable uses `@vueuse/core`'s `useFetch` under the hood. It returns **Refs**, not raw data.

### 2.1. Basic Usage Pattern

Always destructure `{ data: apiData }` from the api call. Remember that `apiData` is a **Ref**.

```ts
const api = useApi();

const fetchData = async () => {
    // 1. Destructure the 'data' ref from the response
    const { data: apiData, isFetching, error } = await api.get<DataType>(route('api.route'));

    // 2. Access actual content via .value
    // 3. Check .success from the BE envelope
    if (apiData.value?.success) {
        const result = apiData.value.data; // This is your T (DataType)
        // ... handle success
    } else {
        const msg = apiData.value?.message || 'Error occurred';
        // ... handle failure
    }
};
```

### 2.2. POST/PUT with useForm integration

```ts
const onSubmit = handleSubmit(async (values) => {
    const { data: apiData } = await api.post(route('api.store'), values);

    if (apiData.value?.success) {
        toast.success(apiData.value.message);
        // ... redirect or reset
    } else {
        // Display backend error message
        toast.error(apiData.value?.message || 'Failed to save');
    }
});
```

---

## 3. Core Rules Summary

1.  **NEVER** use `response()->json($data)` directly in controllers for API-style endpoints. Always use `ApiResponse::success($data)`.
2.  **ALWAYS** access data via `apiData.value.data` in the frontend (the first `.value` is for the Ref, the `.data` is for the Laravel envelope).
3.  **TYPE SAFETY**: Always provide a generic type to the api call: `api.get<Student[]>(...)`.
4.  **DESTRUCTURING**: Only destructure what you need. Common items are `{ data: apiData, isFetching, execute }`.
5.  **SILENT FAILURES**: The `useApi` implementation handles basic error logging. Frontend should focus on checking `apiData.value.success` to provide user feedback.

Following these rules ensures that our API communication is predictable, type-safe, and provides consistent feedback to the user.
