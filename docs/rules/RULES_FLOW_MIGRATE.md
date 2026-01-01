# 📘 MODULE MIGRATION RULE FLOW

_(Áp dụng cho chuyển từ Monolith → Modular Monolith, không phá code cũ)_

## 🎯 Mục tiêu

- Migrate **từng module độc lập**
- Không sửa hàng loạt code legacy
- Không move model sớm
- Không tạo vòng phụ thuộc
- Có thể rollback bất kỳ module nào

## 🧱 Nguyên tắc nền tảng (BẮT BUỘC)

1. **Model vẫn dùng chung (`App\Models`)**
2. **Không viết nghiệp vụ trong Model**
3. **Controller chỉ điều phối – không chứa logic**
4. **Module KHÔNG phụ thuộc module khác**
5. **Identity là module đặc biệt (core)**

## 🔁 FLOW MIGRATE 1 MODULE (CHUẨN)

> Mỗi module migrate **độc lập – tuần tự – không nóng vội**

## STEP 0 – ĐÁNH DẤU MODULE

Trước khi code, **phải trả lời được 3 câu hỏi**:

| Câu hỏi                                             | Bắt buộc |
| --------------------------------------------------- | -------- |
| Module này xử lý nghiệp vụ gì?                      | ✅       |
| Model nào thuộc ownership module?                   | ✅       |
| Module khác ĐƯỢC / KHÔNG ĐƯỢC làm gì với model này? | ✅       |

📌 Ghi vào file:

```
docs/modules/{ModuleName}/ownership.md
```

## STEP 1 – TẠO MODULE RỖNG

```text
app/Modules/{ModuleName}/
 ├─ Actions/
 ├─ Queries/
 ├─ Policies/
 ├─ Http/
 │   ├─ Web/
 │   └─ Api/
 ├─ routes/
 └─ {ModuleName}ServiceProvider.php
```

🚫 **KHÔNG move model**
🚫 **KHÔNG viết route ngay**

## STEP 2 – CẮT LOGIC RA KHỎI CONTROLLER

### ❌ Trước (legacy)

```php
public function store(Request $request)
{
    // 50 dòng xử lý
}
```

### ✅ Sau

```php
public function store(Request $request)
{
    CreateSomething::run($request->validated());
}
```

📌 **Rule**:

- Mỗi Action = 1 use-case
- Action **KHÔNG return View**

## STEP 3 – MODULE DÙNG MODEL CHUNG

```php
use App\Models\Something;
```

✔️ OK
✔️ Không Adapter
✔️ Không Alias

📌 **CẤM**:

- Viết logic nghiệp vụ trong Model
- Gọi module khác

## STEP 4 – CHUYỂN POLICY VÀO MODULE

```text
Modules/{Module}/Policies/*
```

```php
Gate::policy(Something::class, SomethingPolicy::class);
```

📌 **Rule**:

- Policy nằm trong module sở hữu model
- Module khác chỉ gọi `$user->can()`

## STEP 5 – ROUTE VÀO MODULE (KHI ĐÃ ỔN)

```text
Modules/{Module}/routes/web.php
```

```php
Route::middleware(['auth'])->group(...);
```

📌 **Rule**:

- Route module **không được override route khác**
- Route chỉ trỏ vào Controller của module

## STEP 6 – CONTROLLER = ADAPTER

Controller chỉ làm:

| Việc            | Cho phép |
| --------------- | -------- |
| Validate        | ✅       |
| Call Action     | ✅       |
| Return response | ✅       |

🚫 Không query DB
🚫 Không xử lý nghiệp vụ

## STEP 7 – BLOCK IMPORT CHÉO (CỰC QUAN TRỌNG)

### ❌ CẤM TUYỆT ĐỐI

```php
Modules/Academic → Modules/Finance
Modules/Event → Modules/Academic
```

### ✅ CHO PHÉP

```text
Any → Identity
Any → Shared
```

📌 Kiểm tra bằng:

- PHPStan rule
- Review PR

## STEP 8 – MIGRATE DẦN TỪ NGOÀI VÀO

Thứ tự **khuyến nghị**:

1. Identity
2. Academic
3. Events
4. Finance
5. Reporting

🚫 Không migrate nhiều module cùng lúc

## STEP 9 – ĐÁNH DẤU HOÀN THÀNH MODULE

Một module được coi là **migrate xong** khi:

- ✅ Controller legacy không còn logic
- ✅ Action nằm trong module
- ✅ Policy thuộc module
- ❌ Không move model
- ❌ Không import module khác

## STEP 10 – MOVE MODEL (OPTIONAL – SAU 6–12 THÁNG)

Chỉ làm khi:

- Module ổn định
- Không còn refactor lớn
- Có thời gian cleanup

📌 Luôn để alias ngược:

```php
app/Models/X.php → extends Modules/X/Models/X
```

## 🧠 MENTAL RULE CHO DEV (1 CÂU)

> **“Model là data, Action là nghiệp vụ, Module là ranh giới”**

## 🚦 NHỮNG LỖI PHỔ BIẾN (CẤM)

- “Cho nhanh, viết tạm trong controller”
- “Module khác gọi giúp cho tiện”
- “Move model trước cho đẹp”
- “Refactor cả hệ thống 1 lần”

## 📌 CHECKLIST DÙNG TRONG PR

- ⬜ Không sửa model chung
- ⬜ Không thêm logic vào model
- ⬜ Không import module khác
- ⬜ Có ownership rõ ràng
