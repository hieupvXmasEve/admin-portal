---
title: Scholarship Adjustments — Điều chỉnh học bổng
description: Hướng dẫn từng bước xét, phỏng vấn, ra quyết định và áp dụng điều chỉnh học bổng cho sinh viên trượt môn.
source:
  - resources/js/pages/ScholarshipAdjustments/Index.vue
  - resources/js/pages/ScholarshipAdjustments/CandidatesPreview.vue
  - resources/js/pages/ScholarshipAdjustments/Show.vue
  - resources/js/pages/ScholarshipAdjustments/dossier-labels.ts
---

Tính năng dùng để xét những sinh viên bị trượt môn, ghi nhận buổi phỏng vấn và quyết định học bổng của họ có bị giảm ở học kỳ sau hay không. Không sửa hoặc xóa học bổng gốc — chỉ tạo một **hồ sơ điều chỉnh** riêng cho học kỳ bị ảnh hưởng.

**Ai vào được.** `view_scholarship_adjustment` để xem, `approve_scholarship_adjustment` để duyệt quyết định.

**Vào ở đâu.** **Discounts & Funding → Scholarship Adjustments**.

> 🎬 _Ảnh/video minh họa: (sẽ bổ sung)_

## Toàn cảnh quy trình

```text
1. Tìm sinh viên cần xét  →  2. Đặt lịch & ghi biên bản phỏng vấn  →
3. Sinh viên xác nhận  →  4. Ra quyết định & duyệt  →
5. Áp dụng vào học phí  →  6. Kỳ sau: khôi phục hoặc xét lại
```

## 1. Tìm sinh viên cần xét

**Màn hình.** Bấm nút **Tìm sinh viên cần xét** ở góc trên phải trang danh sách — mở màn hình **Tìm sinh viên cần xét**.

**Các bước**

1. Chọn **Học kỳ áp dụng điều chỉnh** (học kỳ đích, nơi học bổng sẽ bị ảnh hưởng).
2. Chọn **Học kỳ sinh viên bị trượt môn** (học kỳ nguồn — chỉ hiện các kỳ bắt đầu **trước** kỳ đích đã chọn).
3. Hệ thống tự hiển thị danh sách **sinh viên có thể đưa vào xét**, kèm các môn bị trượt của từng người.
4. Tick chọn những sinh viên muốn mở hồ sơ (không bắt buộc chọn hết danh sách).
5. Bấm **Mở đợt xét cho N sinh viên**.

**Đọc phần bị ẩn.** Ngay dưới tiêu đề danh sách có dòng ghi số sinh viên **không hiển thị**, ví dụ: đang được xét rồi, chưa có điểm chốt, hoặc đã phát sinh học phí kỳ này (kỳ đã thu tiền thì không đưa vào xét nữa).

**Cờ cần chú ý.** Dòng có nhãn đỏ **Cần kiểm tra điểm thủ công** nghĩa là điểm được chốt trước ngày hệ thống sửa lỗi tính đạt/không đạt (4/7/2026) — kiểm tra lại điểm gốc trước khi quyết định.

> 🎬 _Ảnh/video minh họa: (sẽ bổ sung)_

## 2. Mở hồ sơ & đặt lịch phỏng vấn

Sau khi mở đợt xét, mỗi sinh viên có một hồ sơ riêng, bấm **Mở** ở cột cuối bảng danh sách để vào.

**Khối "Lý do sinh viên này được đưa vào xét"** hiển thị: nguồn (hệ thống tự phát hiện / thêm thủ công), học bổng hiện tại, danh sách môn trượt kèm lần học thứ mấy và ngày chốt điểm.

**Đặt lịch phỏng vấn**

1. Ở khối **Phỏng vấn**, chọn **Ngày** và **Giờ**.
2. Bấm **Đặt lịch phỏng vấn**.

**Ghi biên bản sau khi phỏng vấn xong**

1. Sau khi lịch chuyển trạng thái **Đã hẹn lịch**, khối Phỏng vấn hiện ô **Nội dung đã thống nhất trong buổi phỏng vấn**.
2. Nhập nội dung biên bản.
3. Bấm **Lưu biên bản và gửi sinh viên xác nhận** — hệ thống tự gửi yêu cầu xác nhận cho sinh viên, không cần thao tác thêm.

**Lưu ý.** Biên bản có đánh số phiên bản (**bản chỉnh sửa thứ N**). Sửa biên bản sau khi sinh viên đã xác nhận sẽ tạo phiên bản mới và yêu cầu xác nhận lại từ đầu.

> 🎬 _Ảnh/video minh họa: (sẽ bổ sung)_

## 3. Xác nhận của sinh viên

Sinh viên xác nhận trên Student Portal. Trạng thái hiển thị ngay ở khối **Xác nhận của sinh viên**: *Chờ sinh viên phản hồi*, *Sinh viên đã đồng ý*, *Sinh viên không đồng ý*, *Quá hạn phản hồi*, hoặc *Người duyệt đã bác bỏ phản đối*.

**Nếu sinh viên chưa trả lời (Chờ phản hồi / Quá hạn):** staff có thể xác nhận thay.

1. Nhập vào ô **Bạn đã liên hệ sinh viên bằng cách nào và sinh viên đồng ý điều gì**.
2. Bấm **Xác nhận thay sinh viên**.

**Nếu sinh viên không đồng ý (đã phản hồi phản đối):** **không được** xác nhận thay. Chọn một trong hai hướng:

- **Sửa biên bản** — nhập bản đã chỉnh sửa vào ô **Biên bản phỏng vấn đã chỉnh sửa**, bấm **Lưu biên bản đã sửa và hỏi lại**. Sinh viên xác nhận lại trên bản mới.
- **Bác bỏ phản đối** — chỉ người có quyền duyệt, nhập lý do (tối thiểu 10 ký tự) vào ô **Hoặc bác bỏ phản đối**, bấm **Bác bỏ phản đối**. Ý kiến không đồng ý của sinh viên vẫn được giữ nguyên trên hồ sơ, không bị ghi đè thành "đã đồng ý".

> 🎬 _Ảnh/video minh họa: (sẽ bổ sung)_

## 4. Ra quyết định & duyệt

Chỉ thao tác được khi hồ sơ ở trạng thái **Đã phỏng vấn** hoặc **Chờ duyệt**.

**Bước đề xuất**

1. Chọn **Hình thức xử lý học bổng**: *Giữ nguyên*, *Giảm học bổng*, *Cắt toàn bộ*, *Tạm hoãn quyết định*, hoặc *Huỷ học bổng*.
2. Nếu chọn **Giảm học bổng**: nhập **Mức học bổng sinh viên còn được giữ** — đơn vị theo loại học bổng gốc (% học phí hoặc số tiền), và giá trị nhập là **phần còn lại sau khi giảm**, không phải phần bị cắt.
3. Khối **Ảnh hưởng tới học phí của học kỳ này** tự cập nhật ngay khi nhập số: học phí kỳ, học bổng bù trước/sau, số tiền sinh viên phải đóng trước/sau. Nếu kỳ đích chưa có hoá đơn, khối này báo điều chỉnh sẽ tự áp dụng khi hoá đơn phát hành.
4. Nhập **Lý do chọn hình thức xử lý này** (bắt buộc).
5. Bấm **Gửi đề xuất quyết định**.

**Chặn khi chưa xác nhận.** Nếu quyết định làm tăng học phí (*Giảm* hoặc *Cắt toàn bộ*) mà sinh viên chưa xác nhận biên bản, hệ thống báo lỗi và chặn gửi — quay lại bước 3.

**Bước duyệt.** Sau khi đề xuất được gửi, hồ sơ chuyển **Chờ duyệt**, form khoá lại (chỉ đọc):

- Người có quyền duyệt bấm **Duyệt quyết định này** để chốt nguyên như đề xuất, hoặc
- Bấm **Sửa lại quyết định** để mở lại form, chỉnh rồi gửi lại.

**Sau khi duyệt.** Form biến mất, thay bằng khối tóm tắt: hình thức xử lý, mức học bổng trước/sau, học kỳ áp dụng, lý do, người đề xuất/người duyệt kèm thời gian.

> 🎬 _Ảnh/video minh họa: (sẽ bổ sung)_

## 5. Áp dụng vào học phí

Sau khi duyệt, hồ sơ chuyển sang một trong các trạng thái sau, hiển thị ngay dưới khối tóm tắt quyết định:

| Trạng thái | Ý nghĩa |
| --- | --- |
| **Đã áp dụng vào học phí** | Học phí của học kỳ áp dụng đã được cập nhật theo quyết định. |
| **Giữ nguyên học bổng** | Không thay đổi học phí. |
| **Đã huỷ** | Học bổng đã bị huỷ. |
| **Cần phòng tài chính kiểm tra** | Hệ thống chưa tự cập nhật được (ví dụ: hoá đơn đã phát hành hoặc đã thanh toán) — Finance xử lý thủ công. |
| **Không áp dụng** | Kỳ áp dụng không thu học phí theo lộ trình đóng tiền. |
| **Đã duyệt** (chưa tới hạn xuất hoá đơn) | Sẽ tự cập nhật học phí khi hoá đơn của kỳ áp dụng được phát hành. |

**Lưu ý.** Hệ thống không tự sửa hồi tố invoice đã phát hành hoặc đã thanh toán.

> 🎬 _Ảnh/video minh họa: (sẽ bổ sung)_

## 6. Kỳ sau: khôi phục hoặc xét lại

Sau khi kết quả học kỳ bị điều chỉnh được chốt, hệ thống tự đánh giá:

- Sinh viên không trượt môn nào thuộc phạm vi xét → tự đề xuất **khôi phục** về mức học bổng gốc cho kỳ kế tiếp.
- Sinh viên tiếp tục trượt → mở **hồ sơ xét mới**, không tự kéo dài quyết định cũ.

Khôi phục không xoá hồ sơ giảm trừ trước đó và không tự sửa invoice đã phát hành của kỳ trước.

## Trạng thái hồ sơ (tham khảo nhanh)

`Mới ghi nhận → Đã hẹn phỏng vấn → Đã phỏng vấn → Chờ sinh viên xác nhận → Chờ duyệt → Đã duyệt → Đã áp dụng vào học phí → Đã đóng`

Nhánh phụ có thể gặp: **Sinh viên không đồng ý**, **Sinh viên không dự phỏng vấn**, **Quá hạn xác nhận**, **Giữ nguyên học bổng**, **Đã huỷ**, **Cần phòng tài chính kiểm tra**, **Không áp dụng**.

## Xem thêm

Nghiệp vụ đầy đủ (điều kiện vào diện xét, phân quyền chi tiết, tiêu chí nghiệm thu, các điểm khác với đề xuất ban đầu): [docs/features/academic/scholarship-adjustment-deduction.md](../../../../../docs/features/academic/scholarship-adjustment-deduction.md).
