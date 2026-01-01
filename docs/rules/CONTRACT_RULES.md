# RULE: KHI NÀO BẮT BUỘC DÙNG CONTRACT

_(Shared/Contracts)_

## 0. Mục tiêu của rule này

- Ngăn module xâm phạm DB của nhau
- Giữ ranh giới nghiệp vụ rõ ràng
- Giảm rủi ro khi schema thay đổi
- Chuẩn bị cho việc tách module / refactor sau này

> **Contract không phải để cho đẹp.
> Contract chỉ dùng khi nó giải quyết rủi ro thật.**

## 1. NGUYÊN TẮC VÀNG

> ❗ **BẤT KỲ KHI NÀO module A cần dữ liệu hoặc hành vi của module B → PHẢI dùng Contract**

Không có ngoại lệ.

## 2. CÁC TRƯỜNG HỢP BẮT BUỘC DÙNG CONTRACT

### RULE C1 – ĐỌC DỮ LIỆU TỪ MODULE KHÁC

❌ Sai:

```php
use App\Modules\Academic\Models\AcademicRecord;

AcademicRecord::where('student_id', $id)->avg('grade_points');
```

✅ Đúng:

```php
$this->academicReader->getStudentGpa($studentId);
```

📌 **BẮT BUỘC** dùng Contract

### RULE C2 – LOGIC NGHIỆP VỤ QUAN TRỌNG, ỔN ĐỊNH

Áp dụng cho:

- GPA
- Graduation
- Tuition / Invoice
- Scholarship
- Wallet balance

👉 Các logic này **sẽ tồn tại rất lâu**

➡️ BẮT BUỘC Contract để:

- Không phụ thuộc schema
- Không phụ thuộc Model

### RULE C3 – MODULE KHÁC MUỐN BIẾT “TRẠNG THÁI”

Ví dụ:

- Student đã pass chưa?
- Đã đóng học phí chưa?
- Đã hoàn thành survey chưa?

❌ Sai:

```php
FormSubmission::where(...)->exists();
```

✅ Đúng:

```php
$formStatusChecker->hasCompletedSurvey($studentId, $formCode);
```

### RULE C4 – MODULE KHÁC CẦN DỮ LIỆU TỔNG HỢP

Ví dụ:

- Academic summary
- Financial summary
- Student profile overview

👉 Dữ liệu **không map 1–1 bảng**

➡️ **BẮT BUỘC Contract**

### RULE C5 – API / MOBILE / INTEGRATION ĐỌC DATA CORE

Nếu:

- API cần GPA
- Mobile cần wallet balance
- External system hỏi academic status

➡️ API Controller **KHÔNG ĐƯỢC** query DB trực tiếp
➡️ **BẮT BUỘC đi qua Contract**

### RULE C6 – CODE CÓ KHẢ NĂNG ĐƯỢC TÁCH SERVICE TRONG TƯƠNG LAI

Nếu bạn từng nghĩ:

> “Module này sau có thể tách ra”

➡️ **DÙ CHỈ MỚI NGHĨ → PHẢI DÙNG CONTRACT**

## 3. CÁC TRƯỜNG HỢP KHÔNG BẮT BUỘC (NHƯNG CÓ THỂ)

### ❌ C7 – CRUD nội bộ module

- Module tự đọc bảng của mình
- Không lộ ra ngoài

➡️ Không cần Contract

### ❌ C8 – Query phụ, không quan trọng

- Check tồn tại tạm thời
- Feature nhỏ

➡️ Có thể query trực tiếp (nhưng cân nhắc)

## 4. NHỮNG ĐIỀU CONTRACT KHÔNG ĐƯỢC LÀM

🚫 Contract KHÔNG:

- Trả về Eloquent Model
- Nhận Eloquent Model làm param
- Trả Collection Model

❌ Sai:

```php
public function getAcademicRecord(): AcademicRecord;
```

✅ Đúng:

```php
public function getStudentGpa(int $studentId): float;
```

## 5. NAMING RULE CHO CONTRACT

### Format:

```
{Noun}{Verb}{Purpose}
```

Ví dụ đúng:

- `StudentAcademicReader`
- `StudentFinancialStatusProvider`
- `WalletBalanceProvider`
- `SurveyCompletionChecker`

❌ Sai:

- `AcademicService`
- `HelperAcademic`
- `IAcademic`

## 6. VỊ TRÍ FILE

```
app/Shared/Contracts/
 ├─ Academic/
 │   └─ StudentAcademicReader.php
 ├─ Finance/
 │   └─ StudentFinancialStatusProvider.php
```

## 7. IMPLEMENTATION RULE

- Contract nằm trong `Shared`
- Implementation nằm trong **module owner**
- Bind trong Service Provider

❌ Module khác **KHÔNG được**:

- new implementation
- import implementation

## 8. DTO + CONTRACT (KHUYẾN NGHỊ)

Nếu trả về nhiều field:

❌ Không:

```php
array
```

✅ Đúng:

```php
StudentAcademicSummaryDTO
```

## 9. CHECKLIST REVIEW CODE (BẮT BUỘC TICK)

Trước khi merge, tự hỏi:

- [ ] Module này có đọc bảng module khác không?
- [ ] Có import Model module khác không?
- [ ] Có logic nghiệp vụ “lâu dài” không?
- [ ] Có khả năng dùng lại ở API / Mobile không?

👉 Nếu **YES ≥ 1 → PHẢI dùng Contract**

## 10. HÌNH PHẠT (THỰC TẾ 😄)

> ❗ Nếu module A import Model module B
> → PR bị reject
> → Phải viết lại bằng Contract

## 11. CÂU CHỐT QUAN TRỌNG NHẤT

> **Contract là “hàng rào pháp lý” của Modular Monolith.**
> **Không có nó, boundary chỉ là lời hứa.**
