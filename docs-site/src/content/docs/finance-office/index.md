---
title: Finance
description: Sinh phí, thu tiền, đối soát, xử lý ngoại lệ, cùng học bổng và ưu đãi học phí.
source:
  - resources/js/constants/menu-sidebar.ts
  - resources/js/pages/Finance/Cockpit/Index.vue
  - resources/js/pages/Finance/Reporting/Index.vue
  - resources/js/pages/Finance/Revenue/Index.vue
  - resources/js/pages/Finance/BatchStudio/Hub.vue
  - resources/js/pages/Finance/BatchStudio/ChargeGeneration.vue
  - resources/js/pages/Finance/BatchStudio/DngPush.vue
  - resources/js/pages/Finance/PricingOperations/Index.vue
  - resources/js/pages/Finance/Payments/Index.vue
  - resources/js/pages/Finance/Operations/DueCalendar.vue
  - resources/js/pages/Finance/Payments/DngPaymentRequests/Index.vue
  - resources/js/pages/Finance/Invoices/Index.vue
  - resources/js/pages/TuitionPlans/Index.vue
  - resources/js/pages/Scholarships/Index.vue
  - resources/js/pages/StudentScholarships/Index.vue
  - resources/js/pages/Vouchers/Index.vue
---

<!-- docs-freshness: reviewed against menu-sidebar.ts change (Placement Worklist entry added to Students group, plans/260817-1123-student-placement-worklist/). -->

**Finance** gồm vòng đời khoản phí và một nhóm con **Discounts & Funding** (gói học phí, học bổng, voucher).

Vòng đời một khoản phí đi theo thứ tự:

```text
Sinh phí  ->  Thu & Đối soát  ->  Ngoại lệ (khi lệch)  ->  Tra cứu & Audit
```

Sai ở bước sinh phí thì mọi bước sau đều sai theo. Đây là khu vực đụng tới tiền, nên **kiểm tra trước khi chạy hàng loạt**.

## Hôm nay

**Dùng để làm gì.** Màn hình mở đầu mỗi ngày: hôm nay còn việc gì.

**Ai vào được.** Người có quyền xem bảng điều hành tài chính.

**Nội dung màn hình**

- **Tổng phải thu** — tổng số tiền còn phải thu.
- **Đã thu** — số đã thu được.
- **SV chưa sinh phí** — sinh viên chưa được sinh khoản phí nào; đây thường là việc cần làm ngay.
- **Cần xử lý** — danh sách việc tồn đọng.

**Các bước.** Vào **Finance → Hôm nay**. Bấm **Làm mới** để lấy số liệu mới nhất.

**Lưu ý.** Con số **SV chưa sinh phí** khác 0 vào giữa kỳ nghĩa là có sinh viên đang học mà chưa bị tính tiền. Xử lý sớm, để lâu càng khó truy thu.

## Finance Reporting

**Dùng để làm gì.** Báo cáo tổng hợp tình hình thu chi để nộp lên cấp trên.

**Ai vào được.** Người có quyền xem báo cáo tài chính.

**Các bước.** Vào **Finance → Finance Reporting**, chọn kỳ hoặc khoảng thời gian, xem và xuất báo cáo.

## Doanh thu

**Dùng để làm gì.** Báo cáo doanh thu toàn trường, gộp nhiều kỳ, theo cơ sở và loại phí, tính từ cùng nguồn dữ liệu với Collection Progress nên hai báo cáo không lệch số.

**Ai vào được.** Người có quyền xem báo cáo doanh thu toàn trường (khác quyền xem Finance Reporting theo cơ sở).

**Các bước.** Vào **Finance → Doanh thu**, xem bảng theo kỳ và bảng chi tiết theo cơ sở/loại phí. Dòng **tiền chưa phân bổ** tách riêng, không gộp vào tổng.

## Sinh phí

Nhóm này tạo ra các khoản phải thu. Đây là bước rủi ro nhất trong toàn khu vực.

### Sinh phí & lệnh thu

**Dùng để làm gì.** Sinh phí hàng loạt cho một nhóm sinh viên, hoặc lập yêu cầu thanh toán DNG hàng loạt. Gửi nhắc nợ không nằm ở đây — dùng **DNG Due Reminders**.

**Ai vào được.** Người được phân công sinh phí hàng loạt hoặc lập lệnh thu.

**Các bước**

1. Vào **Finance → Sinh phí → Sinh phí & lệnh thu**.
2. Chọn **1. Sinh phí hàng loạt** hoặc **2. Lập yêu cầu thanh toán DNG**.
3. Chọn kỳ và loại phí — danh sách hiện ra.
4. Kiểm tra số lượng; chỉ điền hạn/mô tả khi bấm **Tạo lệnh thu**.
5. Sau sinh học phí HP/EGC: **Xem / lập lệnh thu kỳ này** mở trang lệnh thu (chọn lại kỳ và loại phí trên trang đó).

**Đọc dòng Số tiền khi xem trước.** Với phí HP (học phí), nếu sinh viên có học bổng, dòng Số tiền hiển thị:

- Học phí gốc (gạch ngang) và số tiền phải thu sau giảm (in đậm).
- 🎓 dòng học bổng — tên, tỉ lệ/mức gốc, số tiền được giảm theo mức gốc.
- 📉 dòng "Bị giảm học bổng" (chỉ hiện khi có quyết định điều chỉnh đang hiệu lực cho đúng học kỳ đã chọn) — mức còn lại sau quyết định và phần bị cắt bớt so với mức gốc.

Không thấy dòng học bổng dù sinh viên có học bổng → kiểm tra lại đã chọn đúng học kỳ đích của quyết định điều chỉnh chưa (bước 3).

**Lưu ý**

- Luôn đọc danh sách trước khi chạy. Sinh nhầm hàng loạt phải hủy từng khoản, rất tốn công.
- Chạy trên nhóm nhỏ để thử trước khi chạy toàn bộ khóa.
- Kiểm tra sinh viên đã có học bổng hoặc voucher chưa, tránh sinh phí sai mức. Nhắc hạn đóng tiền thì vào **Thu & Đối soát → DNG Due Reminders**.

### Pricing Operations

**Dùng để làm gì.** Khai báo và điều chỉnh quy tắc tính giá học phí.

**Các bước**

1. Vào **Finance → Sinh phí → Pricing Operations**.
2. Xem danh sách ở khung **Rule versions** (các phiên bản quy tắc).
3. Bấm **Create pricing rule version** để mở khung tạo mới, điền thông tin rồi bấm **Create version**.

**Lưu ý.** Quy tắc giá dùng theo phiên bản. Tạo phiên bản mới thay vì sửa phiên bản đang áp dụng, để không ảnh hưởng khoản phí đã sinh.

### EGC · Kết quả & học lại, EGC - Carry Forward

**Dùng để làm gì.** Xử lý phần học phí phát sinh từ kết quả học tập: học lại, và khoản chuyển tiếp sang kỳ sau.

**Lưu ý.** Chỉ chạy sau khi kết quả học tập đã chốt. Chạy sớm sẽ tính nhầm cho sinh viên chưa có điểm cuối cùng.

## Thu & Đối soát

Nhóm này ghi nhận tiền vào và khớp với khoản phải thu.

| Trang | Dùng để làm gì |
| --- | --- |
| DNG Due Reminders | Lịch nhắc hạn đóng tiền. Có nút **Phát thông báo học phí** gửi cho sinh viên và phụ huynh |
| DNG Campus Mapping | Khai báo cơ sở nào ứng với tài khoản thu nào |
| Lập yêu cầu thanh toán DNG | Tạo yêu cầu thanh toán gửi sang cổng DNG |
| Settlement Worklist | Danh sách khoản cần khớp tay |
| Payments | Danh sách khoản đã thu |

### Payments

**Nội dung màn hình.** Ba thẻ: **Tổng đã đóng**, **Đã thanh toán**, **Còn dư**.

**Lưu ý.** Cột **Còn dư** khác 0 nghĩa là sinh viên nộp thừa hoặc khoản chưa được phân bổ hết. Xử lý ở **Settlement Worklist**.

### Settlement Worklist

**Dùng để làm gì.** Khớp tiền đã nhận vào đúng khoản phải thu, cho các trường hợp hệ thống không tự khớp được.

**Ai vào được.** Người có quyền phân bổ thanh toán.

**Lưu ý.** Đây là thao tác đụng trực tiếp vào tiền của sinh viên. Đối chiếu chứng từ trước khi phân bổ, và không phân bổ khi còn nghi ngờ.

## Ngoại lệ

Nơi tập trung các trường hợp lệch, không tự xử lý được.

| Trang | Dùng để làm gì |
| --- | --- |
| Exceptions Queue | Hàng đợi ngoại lệ cần xử lý |
| Lifecycle Exceptions | Ngoại lệ phát sinh do thay đổi tình trạng sinh viên |
| Lifecycle History | Lịch sử các ngoại lệ đã xử lý |

**Lưu ý.** Ngoại lệ tồn đọng lâu sẽ làm sai báo cáo cuối kỳ. Nên dọn hàng đợi theo tuần, không để dồn.

## Tra cứu & Audit

Nhóm chỉ đọc, dùng để tìm và đối chiếu.

| Trang | Dùng để làm gì |
| --- | --- |
| Audit Workspace | Khu vực tra cứu tổng hợp khi cần điều tra một trường hợp |
| Charge Ledger (Global) | Sổ toàn bộ khoản phí đã sinh |
| Invoices | Danh sách hóa đơn, xem ở khung **Invoice List** |
| DNG Payment Requests | Yêu cầu thanh toán đã gửi sang cổng DNG |
| DNG · Cần kiểm tra | Biên lai từ cổng DNG có dấu hiệu bất thường |
| DNG Webhook Events | Nhật ký tín hiệu nhận từ cổng DNG |

### DNG Payment Requests

**Nội dung màn hình.** Các thẻ trạng thái: **Total**, **Pending** (chờ), **Pushed to DNG** (đã gửi), **Paid Uninvoiced** (đã trả, chưa xuất hóa đơn), **Paid Invoiced** (đã trả, đã xuất hóa đơn), **Failed / Bridged** (lỗi hoặc phải bắc cầu). Có bộ lọc theo **kỳ học**, cột **Ref (Webhook)** hiển thị mã DNG trả về, và nút **Export Excel** để xuất danh sách theo bộ lọc đang chọn.

**Lưu ý.** **Failed / Bridged** và **DNG · Cần kiểm tra** là hai chỗ cần xem hằng ngày. Đó là nơi tiền đã vào nhưng chưa ghi nhận đúng.

### Invoices

**Nội dung màn hình.** Có bộ lọc theo **kỳ học** (mặc định là kỳ đang chọn của thao tác viên). Xuất Excel đã chuyển sang màn hình **DNG Payment Requests**, không còn ở đây.

## Discounts & Funding — Giảm trừ và tài trợ

Nhóm con bên trong **Finance**, quyết định sinh viên phải trả bao nhiêu. Riêng **Scholarship Adjustments** (điều chỉnh học bổng do trượt môn) không nằm ở đây — đó là một Progression Action, xem ở nhóm **Academic Operations → Grades & Performance**.

### Tuition Plans — Gói học phí

**Các bước**

1. Vào **Finance → Discounts & Funding → Tuition Plans**.
2. Bấm **Create Tuition Plan** để tạo gói mới.
3. Dùng khung **Filters** để tìm gói đã có.

### Scholarships — Học bổng

**Các bước.** Vào **Finance → Discounts & Funding → Scholarships**, dùng khung **Filter Scholarships** để tìm, tạo hoặc sửa loại học bổng.

### Student Scholarships — Gán học bổng cho sinh viên

**Dùng để làm gì.** Gán học bổng cụ thể cho từng sinh viên. Màn hình tên **Student Scholarship Assignments**.

**Ai vào được.** Người có quyền gán học bổng.

**Các bước.** Vào **Finance → Discounts & Funding → Student Scholarships**, dùng khung **Filter Assignments** để tìm, rồi gán hoặc gỡ.

**Lưu ý.** Gán học bổng **trước khi** sinh phí. Gán sau thì khoản phí đã sinh không tự giảm, phải điều chỉnh tay.

### Vouchers

**Các bước.** Vào **Finance → Discounts & Funding → Vouchers**, dùng khung **Filter Vouchers** để tìm và quản lý mã ưu đãi.

## Việc thường gặp

| Tình huống | Thứ tự làm |
| --- | --- |
| Đầu kỳ, sinh phí cho toàn khóa | Student Scholarships → Pricing Operations → Sinh phí & lệnh thu → Hôm nay |
| Sinh viên báo đã đóng mà hệ thống chưa ghi | DNG · Cần kiểm tra → DNG Webhook Events → Settlement Worklist |
| Sinh viên nộp thừa | Payments (cột Còn dư) → Settlement Worklist |
| Dọn tồn đọng hằng tuần | Exceptions Queue → Lifecycle Exceptions |
| Cuối kỳ, chốt số báo cáo | Ngoại lệ (dọn hết) → Charge Ledger → Finance Reporting |
| Sinh viên học lại, phát sinh phí | Retake Registration → EGC · Kết quả & học lại |
