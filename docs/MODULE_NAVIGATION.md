# Module Navigation với Return Parameters

Hệ thống navigation cho phép giữ nguyên filter/sort/pagination khi di chuyển trong cùng một module mà không phụ thuộc vào localStorage hay session.

## Cách hoạt động

### 1. Return Parameter System
- Khi điều hướng từ trang danh sách (index) sang các trang khác trong module, URL hiện tại được truyền qua `return` parameter
- Backend nhận `return` parameter và redirect về URL đó sau khi hoàn thành action
- Frontend sử dụng composable `useModuleNavigation` để xử lý tự động

### 2. Composable useModuleNavigation

```typescript
import { useModuleNavigation } from '@/composables/useModuleNavigation'

const { navigateBack, getLinkUrlWithReturn, getReturnUrl } = useModuleNavigation({
    moduleIndexRoute: 'units.index' // Route name của trang index
})
```

#### Các function chính:
- `navigateBack()`: Điều hướng về return URL hoặc fallback về index
- `getLinkUrlWithReturn(routeName, params)`: Tạo URL có return parameter
- `getReturnUrl()`: Lấy return URL từ query params
- `buildUrlWithReturn(targetUrl, returnUrl?)`: Xây dựng URL với return param

### 3. Áp dụng trong Frontend

#### Trang Index (Danh sách)
```vue
<script setup>
const { getLinkUrlWithReturn } = useModuleNavigation({
    moduleIndexRoute: 'units.index'
});

const editUnit = (unit) => {
    const editUrl = getLinkUrlWithReturn('units.edit', { unit: unit.id });
    router.visit(editUrl, { preserveScroll: true, preserveState: true });
};
</script>
```

#### Trang Create/Edit
```vue
<script setup>
const { navigateBack, getReturnUrl } = useModuleNavigation({
    moduleIndexRoute: 'units.index'
});

const handleSubmit = () => {
    const formData = { ...form.data() };
    
    // Thêm return parameter vào form data
    const returnUrl = getReturnUrl();
    if (returnUrl) {
        formData.return = returnUrl;
    }
    
    router.post('/units', formData, {
        onSuccess: () => {
            toast.success('Created successfully');
            // Backend sẽ redirect về return URL
        }
    });
};

// Nút Cancel/Back
const handleCancel = () => {
    navigateBack(); // Về return URL hoặc index
};
</script>
```

#### Trang Show (Chi tiết)
```vue
<script setup>
const { navigateBack, getLinkUrlWithReturn, getReturnUrl } = useModuleNavigation({
    moduleIndexRoute: 'units.index'
});

// Nút Edit với return param
const navigateToEdit = () => {
    const editUrl = getLinkUrlWithReturn('units.edit', { unit: props.unit.id });
    router.visit(editUrl);
};

// Delete với return param
const deleteUnit = () => {
    const deleteData = {};
    const returnUrl = getReturnUrl();
    if (returnUrl) {
        deleteData.return = returnUrl;
    }
    
    router.delete(`/units/${unit.id}`, {
        data: deleteData,
        onSuccess: () => {
            toast.success('Deleted successfully');
            // Backend sẽ redirect về return URL
        }
    });
};
</script>
```

### 4. Xử lý Backend

#### Controller Methods
```php
public function store(StoreUnitRequest $request)
{
    try {
        $this->unitService->createUnit($request->validated());
        
        // Redirect về return URL nếu có
        $returnUrl = $request->input('return');
        if ($returnUrl && $this->isValidReturnUrl($returnUrl)) {
            return redirect($returnUrl)->with('success', 'Unit created successfully.');
        }

        return redirect()->route(UnitRoutes::INDEX)->with('success', 'Unit created successfully.');
    } catch (\Exception $e) {
        return redirect()->back()
            ->withErrors(['error' => 'Failed to create unit: '.$e->getMessage()])
            ->withInput();
    }
}

public function update(UpdateUnitRequest $request, Unit $unit)
{
    try {
        $this->unitService->updateUnit($unit, $request->validated());
        
        // Redirect về return URL nếu có
        $returnUrl = $request->input('return');
        if ($returnUrl && $this->isValidReturnUrl($returnUrl)) {
            return redirect($returnUrl)->with('success', 'Unit updated successfully.');
        }

        return redirect()->route(UnitRoutes::INDEX)->with('success', 'Unit updated successfully.');
    } catch (\Exception $e) {
        return redirect()->back()
            ->withErrors(['error' => 'Failed to update unit: '.$e->getMessage()])
            ->withInput();
    }
}

public function destroy(Request $request, Unit $unit)
{
    // ... validation logic ...
    
    try {
        $this->unitService->deleteUnit($unit);
        
        // Redirect về return URL nếu có
        $returnUrl = $request->input('return');
        if ($returnUrl && $this->isValidReturnUrl($returnUrl)) {
            return redirect($returnUrl)->with('success', 'Unit deleted successfully.');
        }

        return redirect()->route(UnitRoutes::INDEX)->with('success', 'Unit deleted successfully.');
    } catch (\Exception $e) {
        // Handle error với return URL
        $returnUrl = $request->input('return');
        if ($returnUrl && $this->isValidReturnUrl($returnUrl)) {
            return redirect($returnUrl)->with('error', 'Failed to delete unit: '.$e->getMessage());
        }
        
        return redirect()->route(UnitRoutes::INDEX)
            ->with('error', 'Failed to delete unit: '.$e->getMessage());
    }
}

/**
 * Validate return URL để tránh security issues
 */
private function isValidReturnUrl(?string $url): bool
{
    if (!$url) {
        return false;
    }
    
    $parsed = parse_url($url);
    
    // Chỉ cho phép relative URLs hoặc URLs từ cùng domain
    if (isset($parsed['host'])) {
        $currentHost = parse_url(config('app.url'), PHP_URL_HOST);
        return $parsed['host'] === $currentHost;
    }
    
    // Cho phép relative paths bắt đầu bằng /
    return str_starts_with($url, '/');
}
```

## Áp dụng cho Module khác

### 1. Sử dụng composable với route name tương ứng:
```typescript
// Cho module Programs
const { navigateBack, getLinkUrlWithReturn } = useModuleNavigation({
    moduleIndexRoute: 'programs.index'
});

// Cho module Curriculum Versions  
const { navigateBack, getLinkUrlWithReturn } = useModuleNavigation({
    moduleIndexRoute: 'curriculum-versions.index'
});
```

### 2. Cập nhật Controller tương tự như UnitController
- Thêm validation `isValidReturnUrl()`
- Xử lý return parameter trong store/update/destroy methods

### 3. Cập nhật Vue components
- Import và sử dụng `useModuleNavigation`
- Thêm return parameter vào form data
- Sử dụng `navigateBack()` cho Cancel/Back buttons
- Sử dụng `getLinkUrlWithReturn()` cho navigation links

## Lợi ích

1. **Consistency**: Mọi module xử lý navigation giống nhau
2. **Stateless**: Không phụ thuộc vào localStorage hay session
3. **SEO-friendly**: Filters được giữ trong URL
4. **Reliable**: Hoạt động kể cả khi reload trang hoặc mở tab mới
5. **Maintainable**: Dễ thêm vào module mới chỉ bằng cách thay đổi route name

## Ví dụ Flow hoàn chỉnh

1. User ở trang `/units?search=CS&page=2&sort=code`
2. Click "Edit" unit → điều hướng đến `/units/123/edit?return=/units?search=CS&page=2&sort=code`
3. Submit form → Backend nhận `return` parameter
4. Backend redirect về `/units?search=CS&page=2&sort=code` 
5. User quay lại danh sách với đúng filters/pagination ban đầu
