# Quy Chuẩn Triển Khai: Cấu Trúc Service-Request-Resource Pattern

**Mục tiêu:** Thiết lập và duy trì một cấu trúc mã nguồn nhất quán, dễ bảo trì và mở rộng cho toàn bộ ứng dụng Laravel. Tất cả các tính năng mới hoặc các lần chỉnh sửa logic nghiệp vụ phải tuân thủ nghiêm ngặt theo pattern này.

**Đối tượng thực thi:** AI Agent / Developer.

**Phương pháp:** Quy trình này áp dụng cho việc tạo mới hoặc thay đổi bất kỳ module nào trong dự án. Module `Campus` được sử dụng như một ví dụ tham khảo xuyên suốt tài liệu.

---

## Bước 1: Chuẩn Bị Cấu Trúc Thư Mục

**Mục tiêu:** Đảm bảo các thư mục cần thiết cho pattern đã tồn tại.

*   **Bước 1.1: Thư mục cho Controllers**
    *   **Hành động:** Nếu chưa tồn tại, tạo thư mục cho Web (Inertia) và API controllers.
    *   **Lệnh:**
        ```bash
        mkdir -p app/Http/Controllers/Web
        mkdir -p app/Http/Controllers/Api/V1
        ```

*   **Bước 1.2: Thư mục cho Services**
    *   **Hành động:** Nếu chưa tồn tại, tạo thư mục chứa business logic.
    *   **Lệnh:**
        ```bash
        mkdir -p app/Services
        ```

*   **Bước 1.3: Thư mục con cho Form Requests & API Resources**
    *   **Hành động:** Đối với mỗi module (ví dụ `Campus`), tạo các thư mục con tương ứng.
    *   **Lệnh (ví dụ cho Campus):**
        ```bash
        mkdir -p app/Http/Requests/Campus
        mkdir -p app/Http/Resources/Campus
        ```

---

## Bước 2: Triển Khai Logic Module (Ví dụ: Module `Campus`)

**Mục tiêu:** Áp dụng pattern cho một module cụ thể.

*   **Bước 2.1: Tạo `Service`**
    *   **Hành động:** Tạo một file `Service` trong `app/Services`. File này sẽ chứa toàn bộ business logic cho việc quản lý module.
    *   **Ví dụ (`CampusService.php`):**
        ```php
        <?php

        namespace App\Services;

        use App\Models\Campus;
        use Illuminate\Support\Facades\Log;

        class CampusService
        {
            /**
             * Create a new campus.
             *
             * @param array $data Validated data.
             * @return Campus
             */
            public function createCampus(array $data): Campus
            {
                // Business logic for creating a campus can be added here.
                // For example, logging, firing events, etc.
                Log::info('Creating a new campus', $data);
                
                return Campus::create($data);
            }

            /**
             * Update an existing campus.
             *
             * @param Campus $campus The campus to update.
             * @param array $data Validated data.
             * @return Campus
             */
            public function updateCampus(Campus $campus, array $data): Campus
            {
                Log::info("Updating campus {$campus->id}", $data);
                $campus->update($data);
                return $campus;
            }

            /**
             * Delete a campus.
             *
             * @param Campus $campus The campus to delete.
             * @return void
             */
            public function deleteCampus(Campus $campus): void
            {
                Log::warning("Deleting campus {$campus->id}");
                // Add any cleanup logic here, e.g., deleting related models.
                $campus->delete();
            }
        }
        ```

*   **Bước 2.2: Tạo/Cập nhật `FormRequest`**
    *   **Hành động:** Tạo hoặc di chuyển các file FormRequest của module vào thư mục `app/Http/Requests/<ModuleName>`. Cập nhật namespace cho phù hợp.
    *   **Ví dụ (di chuyển cho Campus):**
        ```bash
        mv app/Http/Requests/StoreCampusRequest.php app/Http/Requests/Campus/StoreCampusRequest.php
        mv app/Http/Requests/UpdateCampusRequest.php app/Http/Requests/Campus/UpdateCampusRequest.php
        ```
    *   **Hành động:** Chỉnh sửa file, thay đổi namespace từ `namespace App\Http\Requests;` thành `namespace App\Http\Requests\Campus;`.

*   **Bước 2.3: Tạo `Resource`**
    *   **Hành động:** Tạo file `Resource` trong `app/Http/Resources/<ModuleName>` để định dạng output cho API.
    *   **Ví dụ (`CampusResource.php`):**
        ```php
        <?php

        namespace App\Http\Resources\Campus;

        use Illuminate\Http\Request;
        use Illuminate\Http\Resources\Json\JsonResource;

        class CampusResource extends JsonResource
        {
            /**
             * Transform the resource into an array.
             *
             * @return array<string, mixed>
             */
            public function toArray(Request $request): array
            {
                return [
                    'id' => $this->id,
                    'name' => $this->name,
                    'address' => $this->address,
                    'is_active' => $this->is_active,
                    'created_at' => $this->created_at->toIso8601String(),
                    'updated_at' => $this->updated_at->toIso8601String(),
                ];
            }
        }
        ```

*   **Bước 2.4: Cập nhật `Web Controller`**
    *   **Hành động:** Di chuyển Controller (nếu đã tồn tại) vào thư mục `app/Http/Controllers/Web`. Cập nhật lại để sử dụng `Service` và `FormRequest` mới.
    *   **Ví dụ (di chuyển):** `mv app/Http/Controllers/CampusController.php app/Http/Controllers/Web/CampusController.php`
    *   **Ví dụ (nội dung `Web/CampusController.php`):**
        ```php
        <?php

        namespace App\Http\Controllers\Web;

        use App\Http\Controllers\Controller;
        use App\Http\Requests\Campus\StoreCampusRequest;
        use App\Http\Requests\Campus\UpdateCampusRequest;
        use App\Models\Campus;
        use App\Services\CampusService;
        use Inertia\Inertia;
        use Inertia\Response;

        class CampusController extends Controller
        {
            public function __construct(protected CampusService $campusService)
            {
            }

            public function index(): Response
            {
                // Logic for displaying index page can also be moved to a service.
                return Inertia::render('campuses/Index', [
                    'campuses' => Campus::all(), // Example: $this->campusService->getAllForWeb()
                ]);
            }
            
            // ... (create, show, edit methods)

            public function store(StoreCampusRequest $request): \Illuminate\Http\RedirectResponse
            {
                $this->campusService->createCampus($request->validated());
                return redirect()->route('campuses.index')->with('success', 'Campus created successfully.');
            }

            public function update(UpdateCampusRequest $request, Campus $campus): \Illuminate\Http\RedirectResponse
            {
                $this->campusService->updateCampus($campus, $request->validated());
                return redirect()->route('campuses.index')->with('success', 'Campus updated successfully.');
            }

            public function destroy(Campus $campus): \Illuminate\Http\RedirectResponse
            {
                $this->campusService->deleteCampus($campus);
                return redirect()->route('campuses.index')->with('success', 'Campus deleted successfully.');
            }
        }
        ```

*   **Bước 2.5: Tạo/Cập nhật `API Controller`**
    *   **Hành động:** Tạo file `Controller` mới trong `app/Http/Controllers/Api/V1`.
    *   **Ví dụ (`Api/V1/CampusController.php`):**
        ```php
        <?php

        namespace App\Http\Controllers\Api\V1;

        use App\Http\Controllers\Controller;
        use App\Http\Requests\Campus\StoreCampusRequest;
        use App\Http\Requests\Campus\UpdateCampusRequest;
        use App\Http\Resources\Campus\CampusResource;
        use App\Models\Campus;
        use App\Services\CampusService;
        use Illuminate\Http\Request;
        use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

        class CampusController extends Controller
        {
            public function __construct(protected CampusService $campusService)
            {
            }

            public function index(): AnonymousResourceCollection
            {
                return CampusResource::collection(Campus::paginate()); // Example: $this->campusService->getAllForApi()
            }

            public function store(StoreCampusRequest $request): CampusResource
            {
                $campus = $this->campusService->createCampus($request->validated());
                return new CampusResource($campus);
            }

            public function show(Campus $campus): CampusResource
            {
                return new CampusResource($campus);
            }

            public function update(UpdateCampusRequest $request, Campus $campus): CampusResource
            {
                $updatedCampus = $this->campusService->updateCampus($campus, $request->validated());
                return new CampusResource($updatedCampus);
            }

            public function destroy(Campus $campus): \Illuminate\Http\Response
            {
                $this->campusService->deleteCampus($campus);
                return response()->noContent();
            }
        }
        ```

---

## Bước 3: Cấu Hình Routing

**Mục tiêu:** Trỏ các routes đến đúng vị trí controllers mới.

*   **Bước 3.1: Cập nhật Web Routes**
    *   **Hành động:** Mở file route tương ứng (ví dụ: `routes/campuses.php`).
    *   **Nội dung cần sửa:**
        *   Cập nhật `use` statement: `use App\Http\Controllers\Web\CampusController;`
        *   Đảm bảo `Route::resource('campuses', CampusController::class);` đang trỏ đến class mới.

*   **Bước 3.2: Cập nhật API Routes**
    *   **Hành động:** Mở file `routes/api.php`.
    *   **Nội dung cần thêm:**
        ```php
        use App\Http\Controllers\Api\V1\CampusController;

        Route::prefix('v1')->group(function () {
            Route::apiResource('campuses', CampusController::class);
        });
        ```

---

## Bước 4: Kiểm Tra và Hoàn Tất

*   **Bước 4.1: Chạy Tests**
    *   **Hành động:** Chạy bộ kiểm thử tự động của dự án để đảm bảo không có lỗi phát sinh.
    *   **Lệnh:** `php artisan test`
    *   **Kết quả mong muốn:** Tất cả các test liên quan đến module phải pass. Nếu có lỗi "Class not found", hãy kiểm tra lại các `use` statement và namespaces.
