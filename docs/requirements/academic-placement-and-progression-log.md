# SPEC REQUIREMENT — Academic Placement & Progression Log (Phase 1)

## 0) Mục tiêu

1. Ghi nhận đầy đủ **xếp lớp ban đầu** và **tiến trình học thuật** liên quan đến:

   * `course_stage`: `intake_pre_uni_gc → intake_course` (một chiều, không rollback)
   * `english_level`: 0 → 5 (một chiều, không rollback)
2. Lưu & audit **IELTS** (điểm + file scan) để:

   * Vào `intake_course` **luôn cần IELTS ≥ 6.5**
   * Dù học đủ level 5 vẫn **phải nộp IELTS**
3. Thống kê theo **semester / year**:

   * bao nhiêu SV bắt đầu ở pre_intake / intake
   * bao nhiêu SV đổi level
   * bao nhiêu SV chuyển pre_intake → intake
   * danh sách SV **thiếu file IELTS** để rà soát

## 1) Phạm vi Phase 1

### In-scope

* `students.status` mặc định khi tạo account portal: **`pending`**
* Xếp lớp ban đầu (placement):

  * Có IELTS ≥ 6.5 → `intake_course`
  * Không có IELTS hoặc IELTS < 6.5 → placement test → `intake_pre_uni_gc` + `english_level` (0..5)
* Trong quá trình học:

  * SV ở `intake_pre_uni_gc` có thể tăng/nhảy level (0→5)
  * SV ở `intake_pre_uni_gc` nộp IELTS ≥ 6.5 → chuyển `intake_course`
* Lưu IELTS:
  * score
  * file scan (upload)
  * cho phép **missing_documents=true** ở placement ban đầu (ngoại lệ nghiệp vụ)

### Out-of-scope (Phase 1)

* Workflow verify/approve chứng chỉ
* Quản lý hết hạn IELTS/validity phức tạp
* Chuẩn hóa các chứng chỉ khác ngoài IELTS (Phase 1 tập trung IELTS; chứng chỉ khác chỉ ảnh hưởng “level change” theo quy trình hiện tại, chưa cần mô hình hóa sâu)

## 2) Định nghĩa & quy tắc nghiệp vụ (đóng băng)

### 2.1 Student status (snapshot)

* Tạo account portal ⇒ `students.status = pending`

> `pending` là trạng thái hệ thống/admin, **không đồng nghĩa** course_stage.

### 2.2 Course stage

* `intake_pre_uni_gc`
* `intake_course`
  **Rule một chiều:** chỉ được `intake_pre_uni_gc → intake_course`. Không có chiều ngược.

### 2.3 English level

* Level: **0,1,2,3,4,5**
  **Rule một chiều:** chỉ tăng hoặc nhảy lên, không giảm/reset.

### 2.4 IELTS rule (cốt lõi)

* Vào `intake_course` **luôn yêu cầu IELTS ≥ 6.5**
* Dù hoàn thành level 5 vẫn **bắt buộc phải nộp IELTS** (file scan cần lưu để rà soát/audit)

### 2.5 Ngoại lệ “thiếu file” ở Placement ban đầu

Khi placement ban đầu (bắt đầu học), **cho phép**:

* Nhập `IELTS score ≥ 6.5`
* Chuyển vào `intake_course`
* Nhưng đánh dấu `missing_documents = true` vì file scan chưa kịp nộp (tuyển sinh đã xác nhận)

> Ngoại lệ này chỉ để không cản trở vận hành nhập học, và phải có báo cáo/đầu việc để bổ sung file sau.


## 3) Data requirements (mô hình dữ liệu ở mức nghiệp vụ)

Phase 1 cần 2 nhóm dữ liệu: **IELTS Certificates** và **Progression Events**.

### 3.1 IELTS Certificates (lưu chứng chỉ IELTS)

**Mỗi record = 1 chứng chỉ IELTS được nhập/lưu.**

**Fields bắt buộc**

* `student_id`
* `overall_score` (decimal)
* `submitted_at` (datetime) — ngày nhập/nộp lên hệ thống
* `upload_record_id` (nullable) — file scan
* `missing_documents` (boolean)

**Fields khuyến nghị (optional Phase 1)**

* `issue_date` (date) — ngày cấp (nếu có)
* `notes` (text)

**Hard rules**

* Nếu `overall_score ≥ 6.5` ⇒ có thể đủ điều kiện vào `intake_course` (theo quy tắc ở mục 4)
* `missing_documents=true` nghĩa là **thiếu scan** và phải xuất hiện trong report rà soát


### 3.2 Academic Progression Events (log tiến trình học thuật)

**Mỗi record = 1 mốc thay đổi học thuật để audit & thống kê.**

**Fields chung (bắt buộc)**

* `student_id`
* `event_type` (enum – xem bên dưới)
* `semester_id` (bắt buộc để thống kê theo kỳ/năm)
* `effective_at` (datetime) — thời điểm thay đổi có hiệu lực
* `trigger_source` (enum): `ielts`, `placement_test`, `manual_admin`, `system`
* `created_by_user_id` (nullable nếu system)
* `notes` (optional)

**Before/After snapshot (để đọc audit dễ)**

* `from_course_stage` (nullable)
* `to_course_stage` (nullable)
* `from_english_level` (nullable)
* `to_english_level` (nullable)

**Danh sách `event_type` (Phase 1)**

1. `PLACEMENT_INITIALIZED`

* Ghi nhận xếp lớp ban đầu (stage + level nếu có)

2. `ENGLISH_LEVEL_CHANGED`

* Ghi nhận tăng/nhảy level (0→5) khi đang pre_intake

3. `COURSE_STAGE_CHANGED`

* Ghi nhận chuyển stage `intake_pre_uni_gc → intake_course`

4. `IELTS_RECORDED`

* Ghi nhận việc nhập/lưu IELTS (kể cả thiếu file)

**Link IELTS ↔ Event (để audit đúng)**

* Event liên quan IELTS (`IELTS_RECORDED`, `COURSE_STAGE_CHANGED`, hoặc `PLACEMENT_INITIALIZED` đi vào intake nhờ IELTS) **nên có tham chiếu** `ielts_certificate_id` (recommended) để biết “dựa trên IELTS nào”.


## 4) Luồng xử lý & quy tắc cập nhật snapshot (business behavior)

### 4.1 Khi tạo account portal

* Set `students.status = pending`

### 4.2 Placement ban đầu (bắt đầu học)

**Case A — Có IELTS (score ≥ 6.5) và có scan**

* Lưu IELTS certificate: `missing_documents=false`, `upload_record_id` có giá trị
* Set snapshot: `course_stage = intake_course`
* Tạo events:

  * `IELTS_RECORDED`
  * `PLACEMENT_INITIALIZED` (to_course_stage=intake_course)

**Case B — Có IELTS (score ≥ 6.5) nhưng CHƯA có scan (ngoại lệ)**

* Lưu IELTS certificate: `missing_documents=true`, `upload_record_id=null`
* Set snapshot: `course_stage = intake_course`
* Tạo events:

  * `IELTS_RECORDED` (missing_documents=true)
  * `PLACEMENT_INITIALIZED` (to_course_stage=intake_course)
* Bắt buộc hiển thị cảnh báo “Thiếu file IELTS – cần bổ sung” và đưa vào report.

**Case C — có IELTS < 6.5**

* Lưu IELTS certificate: `overall_score < 6.5`, `missing_documents=false`, `upload_record_id=` có giá trị
* IELTS overall_score → xác định `english_level` ∈ [0..5] theo rule placement test
* Set snapshot: 
  * `course_stage = intake_pre_uni_gc`
  * `english_level = assigned_level`
* Tạo events:

  * `IELTS_RECORDED` (overall_score < 6.5)
  * `PLACEMENT_INITIALIZED` (to_course_stage=intake_pre_uni_gc)

**Case D — Không có IELTS**

* Placement test → xác định `english_level` ∈ [0..5]
* Set snapshot:

  * `course_stage = intake_pre_uni_gc`
  * `english_level = assigned_level`
* Tạo event:

  * `PLACEMENT_INITIALIZED` (to_course_stage=intake_pre_uni_gc, to_english_level=assigned_level)


### 4.3 Trong quá trình học (đang `intake_pre_uni_gc`)

**A) Tăng/nhảy level**

* Điều kiện: student đang `intake_pre_uni_gc`
* Update snapshot: `english_level` tăng lên (không giảm)
* Tạo event `ENGLISH_LEVEL_CHANGED` (from_level → to_level)

**B) Nộp IELTS**

* Lưu IELTS certificate (score + file scan; có thể `missing_documents=true` nếu chưa có scan)
* Tạo event `IELTS_RECORDED`

**C) Chuyển sang intake_course**

* Chỉ cho phép nếu:

  * Có IELTS certificate liên quan với `overall_score ≥ 6.5`
  * Khuyến nghị: yêu cầu `missing_documents=false` (đủ scan) để đúng “bắt buộc phải nộp IELTS”

    * Nếu bạn muốn cho phép thiếu scan cả ở bước này, phải chốt rule riêng; hiện Phase 1 giữ chặt: **thiếu scan chỉ cho ngoại lệ ở placement ban đầu**
* Update snapshot: `course_stage = intake_course`
* Tạo event `COURSE_STAGE_CHANGED` (pre_intake → intake) + link `ielts_certificate_id`

## 5) Validation rules (one-way, không rollback)

* `english_level` chỉ được tăng/nhảy lên, không giảm
* `course_stage` chỉ được chuyển `intake_pre_uni_gc → intake_course`, không quay lại
* `course_stage=intake_course` ⇒ phải có IELTS score ≥ 6.5 (luôn đúng)
* Thiếu file IELTS:

  * Cho phép **ở placement ban đầu** (Case B), nhưng phải đánh dấu `missing_documents=true` và xuất hiện trong report
  * Chuyển stage trong quá trình học (Case 4.3C) mặc định yêu cầu `missing_documents=false`


## 6) UI Requirements (Admin Portal)

### 6.1 Student Profile — Tab “Academic Placement & Progression”

Hiển thị snapshot hiện tại:

* course_stage (pre_intake / intake)
* english_level (0..5, chỉ hiển thị khi pre_intake)
* IELTS status:

  * điểm mới nhất hoặc danh sách IELTS
  * trạng thái file scan (missing_documents)
  * file scan (nếu có)

Timeline events:

* Placement initialized
* IELTS recorded
* English level changed
* Course stage changed

Chức năng:

* Nhập placement ban đầu (nếu chưa có)
* Update level (chỉ tăng)
* Nhập IELTS (score + upload file; hoặc tick “thiếu file”)
* Chuyển intake (chỉ khi đủ điều kiện)

### 6.2 Trang Report — “Academic Progression Audit”

Filters:

* semester / year (bắt buộc)
* event_type
* stage changes (pre_intake → intake)
* level changes
* IELTS score range
* missing_documents (true/false)

Export:

* CSV/Excel theo filter

## 7) Reports cần có (Phase 1)

1. **Placement distribution theo kỳ/năm**

* # SV bắt đầu pre_intake vs intake (dựa trên `PLACEMENT_INITIALIZED`)

2. **Progression: pre_intake → intake theo kỳ/năm**

* # SV chuyển intake (dựa trên `COURSE_STAGE_CHANGED`)

3. **English level changes theo kỳ/năm**

* # event level changed, có thể thêm breakdown theo level (dựa trên `ENGLISH_LEVEL_CHANGED`)

4. **IELTS tracking theo kỳ/năm**

* # IELTS recorded + phân bố score (dựa trên `IELTS_RECORDED` + certificates)

5. **Rà soát thiếu hồ sơ IELTS**

* Danh sách SV `intake_course` nhưng IELTS `missing_documents=true`
  (Kỳ vọng chủ động đưa về 0 sau khi bổ sung)

## 8) Acceptance Criteria (nghiệm thu)

* Tạo account portal ⇒ `students.status=pending`
* Placement ban đầu:

  * Không IELTS ⇒ pre_intake + level 0..5 + event placement
  * IELTS ≥ 6.5 có scan ⇒ intake + events đầy đủ
  * IELTS ≥ 6.5 thiếu scan ⇒ intake + `missing_documents=true` + xuất hiện trong report thiếu hồ sơ
* Trong quá trình học:

  * Level chỉ tăng/nhảy lên, không giảm
  * Stage chỉ pre_intake → intake, không quay lại
  * Chuyển intake trong quá trình học chỉ khi IELTS ≥6.5 và (mặc định) không thiếu scan
* Report theo semester/year trả ra đúng số lượng.