---
paths: "**/*.{php,vue,js,ts}"
---

# API Interaction Rules

## 1. Backend Standard (Laravel)
- **Response Wrapper**: All API responses MUST use `App\Http\Responses\ApiResponse`.
- **Success**: `ApiResponse::success($data)`
- **Paginated**: `ApiResponse::paginated($paginator)`
- **Error**: `ApiResponse::error($message, $errors, $status)`
- **Prohibited**: Do NOT use `response()->json()` directly.

## 2. Frontend Standard (Vue 3 + useApi)
- **Composable**: Use `useApi()` (wrapper around `@vueuse/core`'s `useFetch`).
- **Return Type**: Returns **Refs**.
- **Pattern**:
    ```typescript
    const { data: apiData } = await api.get<DataType>(route('api.route'));
    if (apiData.value?.success) {
        // Access data
    }
    ```
- **Accessing Data**: `apiData.value.data` (First `.value` for Ref, second `.data` for envelope).

## 3. Type Safety
- **Generics**: ALWAYS provide the expected type: `api.get<Student[]>(...)`.

## 4. Error Handling
- **Frontend**: Check `apiData.value.success`.
- **Feedback**: Show toast messages based on success/failure.
