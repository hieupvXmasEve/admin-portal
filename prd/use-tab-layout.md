# Curriculum Version Detail Page

# 1) Mục tiêu & phạm vi

* Tách mỗi tab thành **route/page độc lập** để giảm bundle và payload, chỉ tải đúng dữ liệu cần thiết cho tab đó.
* Chuẩn hoá **response shape** giữa các tab để FE xử lý thống nhất.
* Giữ UX chuyển tab mượt (prefetch, preserve scroll, replace history).
* Tối ưu hiệu năng DB (select tối thiểu, phân trang, eager-load, index).

# 2) Routing & Controller

## 2.1 Cấu trúc URL & tên route

* Prefix: `/curriculum-versions/{curriculum_version}/*`
* Quy ước tên route: `curriculum-versions.summary.{tab}`

  * `curriculum-versions.summary.overview` → Tab **Overview** (Thông tin chung)
  * `curriculum-versions.summary.units` → Tab **Unit List** (Danh sách môn học)
  * `curriculum-versions.summary.students` → Tab **Student Stats** (Thống kê sinh viên)
  * `curriculum-versions.summary.deployments` → Tab **Deployment Tracking** (Liên kết & theo dõi triển khai)


> Lưu ý: Mỗi tab là 1 action riêng trong controller (cùng controller hoặc tách controller theo tab), tất cả dùng chung layout.

## 2.2 Nguyên tắc cho mỗi action

* **Chỉ load field tối thiểu** liên quan tab (tránh trả nguyên object curriculum version đầy đủ).
* **Phân trang** cho danh sách lớn (đặc biệt Units, Deployments).
* **Eager-load chọn lọc** để tránh N+1 (chỉ with quan hệ cần hiển thị).
* **Không nhúng dữ liệu của tab khác** vào response hiện tại.

# 3) Layout chung (UI & điều hướng)

## 3.1 Nội dung layout

* Header: tiêu đề “Curriculum Version”, **Program name + code**, **Specialization** (nếu có), **Version code**, **Status badge**.
* Subheader: **First applied semester**, **Notes**, **Created at/by** (nếu có).
* Hành động: **Back**, **Export** (gọi API export riêng; không gắn logic export vào tab).
* Tabs: 4–5 tab như mục 2.1.

## 3.2 Điều hướng tabs

* Mỗi tab là **Link Inertia** trỏ tới đúng route.
* Đặt **prefetch** cho Link để tải sớm response.
* Dùng **replace** để lịch sử gọn (nhấn Back quay về danh sách curriculum-version, không “đi qua” từng tab).
* Dùng **preserve-scroll** để không giật trang khi chuyển tab.
* Tab active: xác định theo **route name** hoặc **URL hiện tại**.

### Checklist Layout

* [ ] Header/Badge/Back/Export thống nhất mọi tab.
* [ ] Tabs dùng Link Inertia (không render lẫn nội dung tab khác).
* [ ] Responsive: lưới 3 cột (mobile) → 6 cột (desktop).
* [ ] `preserve-scroll` khi điều hướng.

# 4) Nội dung & dữ liệu từng tab

## 4.1 Tab Overview (Thông tin chung) (Đã làm)

## 4.2 Tab Unit List (Danh sách môn học) (Đã làm)

## 4.3 Tab Student Stats (Thống kê sinh viên)

**Nguồn**: `students` với `curriculum_version_id`.

**Số liệu chính:**

* Tổng SV đang học (`status/academic_status = active`)
* Đã tốt nghiệp
* Bảo lưu / withdrawn / suspended
* **Drill-down**: nhấn vào số → điều hướng sang route danh sách chi tiết **hoặc** mở modal (tuỳ chọn).

**Data contract:**

* `curriculum_version`: (như trên)
* `data`: { counts: { active, graduated, suspended, withdrawn, on\_leave, ... } }
* `meta`: { lastUpdatedAt, filters? }
* `links`: { drillDown: { active: url, graduated: url, ... } }

**Lưu ý:**

* Gom các `count()` trong một truy vấn tổng hợp hoặc cache ngắn (1–5 phút) để tránh lặp lại.
* Không trả danh sách sinh viên trong tab này (chỉ khi drill-down).

## 4.4 Tab Deployment Tracking (Liên kết & theo dõi triển khai)

**Mục tiêu**: theo dõi việc **mở môn** dựa trên version này.

**Nguồn**:

* `semesters` đã có triển khai từ version này (có offerings khớp unit của version).
* `course_offerings` (gộp theo `unit_id`) → số lớp, tổng số SV đăng ký.
* So sánh với `curriculum_units` để tìm **môn chưa bao giờ mở**.

**Hiển thị:**

* Danh sách kỳ học: tên kỳ, số môn đã mở, tổng lớp, tổng SV.
* Bảng môn **đã mở**: Unit code/name, số lớp, tổng SV.
* Bảng môn **chưa mở**: cảnh báo (đặc biệt những môn có `year_level/semester_number` gần kỳ hiện tại).

**Bộ lọc & phân trang:**

* Filter theo **semester**, **unit type**, **scope**.
* Phân trang cho bảng lớn.

**Data contract:**

* `curriculum_version`: (như trên)
* `data`:

  * `semesters`: \[ { id, name, opened\_units\_count, classes\_count, students\_count } ]
  * `opened_units`: { items:\[{ unit\_code, unit\_name, classes\_count, students\_count }], meta, links }
  * `never_opened_units`: { items:\[{ unit\_code, unit\_name, year\_level, semester\_number }], meta, links }
* `meta`: { currentSemester?, filters }
* `links`: { ...phân trang... }

**Lưu ý:**

* Index gợi ý cho `course_offerings`: `(curriculum_version_id)`, `(unit_id, semester_id)`.
* Nếu cần số lượng SV: tổng hợp qua bảng đăng ký (ví dụ `enrollments`), tránh JOIN nặng không cần thiết (sử dụng subquery/group-by một lần).

# 5) Trải nghiệm chuyển tab (UX)

## 5.1 Điều hướng mượt

* **prefetch** cho Link tabs (hover/chạm → tải sớm).
* **replace** lịch sử khi chuyển tab (gọn back stack).
* **preserve-scroll** để không nhảy trang.
* Nếu 1 tab chứa nhiều widget nặng: tách thành **lazy component** trong **chính page của tab** và hiển thị **skeleton/loader** khi mount.

### Checklist UX

* [ ] Prefetch cho tất cả Link tab.
* [ ] Replace + preserve-scroll khi điều hướng.
* [ ] Skeleton/loader cho biểu đồ/bảng lớn trong tab.

# 6) Tối ưu payload & hiệu năng

## 6.1 Giảm payload

* Mỗi tab **chỉ trả dữ liệu của chính tab**.
* Phân trang + filter/sort server-side; giới hạn **perPage** an toàn.
* Chuẩn hoá enum/ngày/thứ bậc **tại server** (FE không phải post-process nặng).

## 6.2 Tối ưu truy vấn

* Index theo cột lọc/sort phổ biến (xem gợi ý tại từng tab).
* Eager-load đúng quan hệ hiển thị (không with dây chuyền).
* Hợp nhất `count()/aggregate` trong 1 truy vấn; cân nhắc **cache ngắn** cho thống kê.

## 6.3 Static assets & code-splitting

* Mỗi page chỉ import component của tab → Vite tự chia chunk.
* Tránh import xuyên tab trong shared layout (layout chỉ chứa UI khung).

## 6.4 State & nhớ bộ lọc

* Dùng **`useRemember`** (Inertia) để lưu filter/pagination state theo **key mỗi tab**. Quay lại tab không mất state.

### Checklist Payload

* [ ] Response tab = `{ curriculum_version tối thiểu, data, meta, links }`.
* [ ] Đặt index phù hợp, eager-load chọn lọc.
* [ ] `useRemember` cho filter/page mỗi tab.

# 7) Quyền truy cập, audit & export

* **Authorization**: kiểm tra quyền theo tab (ví dụ xem thống kê/triển khai chỉ dành cho role quản trị).
* **Audit**: ghi log xem/chuyển tab nếu cần analytics nội bộ.
* **Export**: nút Export đặt ở layout; khi bấm gọi **API export riêng** theo **tab hiện tại** (xuất CSV/XLSX đúng dữ liệu đang filter).

# 8) Kiểm thử (QA) & giám sát

## 8.1 Test chức năng

* Điều hướng giữa tab: prefetch, replace, preserve-scroll hoạt động đúng.
* Phân trang + filter giữ trạng thái khi đổi tab/refresh (nhờ `useRemember`).
* Payload không chứa dữ liệu tab khác.

## 8.2 Test hiệu năng

* Thời gian phản hồi mỗi tab dưới ngưỡng mục tiêu (ví dụ P95 < 300ms trên dữ liệu thật).
* Số query/response size hợp lý (giám sát bằng debugbar/telescope/profiler).
* Kiểm tra index sử dụng (EXPLAIN các truy vấn chính).

## 8.3 Test biên

* Curriculum version **không có specialization**.
* **Không có units**, hoặc **nhiều units** (phân trang).
* **Chưa từng mở môn** (never\_opened\_units hiển thị đúng).
* **Không có sinh viên** gắn version.

# 9) Tóm tắt triển khai nhanh (checklist tổng)

* [ ] Routes theo chuẩn `curriculum-versions.summary.{tab}` với prefix cố định.
* [ ] Mỗi action controller: select tối thiểu, phân trang, eager-load chọn lọc, response chuẩn hoá.
* [ ] Layout chung: header + tabs (Link Inertia: prefetch, replace, preserve-scroll).
* [ ] Tab Overview/Units/Students/Deployments: dữ liệu đúng phạm vi, không kéo dữ liệu tab khác.
* [ ] Tối ưu DB: index, aggregate gộp, cache ngắn cho thống kê.
* [ ] FE: code-splitting theo page, lazy cho widget nặng, `useRemember` cho state.
* [ ] Export theo tab hiện tại (API riêng, tôn trọng filters).
* [ ] QA hiệu năng + trường hợp biên.
