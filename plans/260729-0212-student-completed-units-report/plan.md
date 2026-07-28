---
title: "Student Completed Units Report"
description: "Report liệt kê toàn bộ sinh viên với các môn đã hoàn thành (pass), tách cột GC / Major, kèm tổng số môn và tín chỉ đạt."
status: pending
priority: P2
effort: "1-2d"
tags: [academic, report, inertia]
created: 2026-07-29
---

# Student Completed Units Report

## Overview

Trang report mới liệt kê **toàn bộ sinh viên** (không cắt theo học kỳ) với các môn **đã hoàn thành và đạt**. Mỗi row = 1 sinh viên; môn hiển thị dạng chip mã môn, tách 2 cột theo `units.unit_type`: GC (`egc`) và Major (`general`). Kèm cột tổng số môn và tổng tín chỉ đạt.

Không phải mở rộng report cũ. Report hiện có tại `/academic/report` là ma trận điểm theo học kỳ, đọc `academic_records` × `curriculum_units` và hard-filter `students.status = 'intake_course'` — khác chiều, khác nguồn lọc. Nhồi vào đó sẽ vỡ cả hai.

## Bối cảnh dữ liệu (đã verify trên DB `asia`, 2026-07-29)

| Sự thật | Hệ quả thiết kế |
|---|---|
| `units.unit_type` đã populate: `egc` (7 unit: EGCF, EGC1–EGC6) / `general` (69) | Tách GC/Major = `GROUP BY units.unit_type`. **Không cần migration** |
| `course_registrations.registration_status` chỉ có `confirmed` / `completed` / `defer` — **không có `failed`** | Không dùng registration để xác định pass. Nguồn đúng là `academic_records.is_passed` |
| `academic_records`: 2940 row ↔ 2939 registration (gần 1-1). `is_passed`: 1853 true / 199 false / **888 NULL** | NULL = chưa finalize. Không hiển thị |
| `is_passed = 1`: EGC 264 row / 98 SV; General 1589 row / 142 SV | Quy mô nhỏ |
| 4 cặp `(student_id, unit_id)` trùng trong tập `is_passed = 1`, max 2 record | **Vẫn phải dedupe** dù không hiển thị attempt |
| 223/234 SV có ≥1 môn pass; min 1 / avg 8.3 / max 13 môn | Chip list nhét gọn 1 cell, không cần collapse. 11 SV không có môn pass nào |
| `students.specialization_id` = **0/234 rỗng** | Bỏ. Chỉ dùng `program` |
| `academic_records.is_transfer_credit` = **0 row** | Ngoài scope |
| **9 row** `is_passed = 0` nhưng `credit_points_earned > 0` | Không ảnh hưởng: report chỉ lấy `is_passed = 1`. Ghi nhận là nợ dữ liệu riêng |

## Quyết định đã chốt

- Chỉ hiển thị môn `is_passed = 1`. Môn trượt và môn chưa có kết quả **không xuất hiện ở bất kỳ đâu** — kể cả count và tổng tín chỉ.
- **Không hiển thị attempt.** Dedupe theo `(student_id, unit_id)`, giữ record `attempt_number` cao nhất (tie → `id` cao nhất). Chip hiện 1 lần, không badge `×n`.
- "Ngành" = `programs.name`. Bỏ specialization.
- Export **giữ nguyên hình dạng UI** (1 dòng/sinh viên, chip → chuỗi mã ngăn dấu phẩy). Không xuất flat student × unit.
- Không migration, không đổi schema, không dimension học kỳ, không grade/GPA, không tỉ lệ phần trăm.

## Goals

| # | Goal | Priority |
|---|------|----------|
| 1 | Query object + contract trả về sinh viên kèm môn đã đạt, tách GC/Major, đã dedupe | P1 |
| 2 | Trang Inertia với chip mã môn, filter Program/Campus/Keyword, sort | P1 |
| 3 | Export Excel giữ nguyên layout UI | P2 |

## Phases

| # | Phase | Status |
|---|-------|--------|
| 1 | [Phase 1: Query and contract](./phase-01-start.md) | Pending |
| 2 | [Phase 2: Web page and route](./phase-02-web-page-and-route.md) | Pending |
| 3 | [Phase 3: Excel export](./phase-03-excel-export.md) | Pending |

Phase 2 phụ thuộc Phase 1. Phase 3 phụ thuộc Phase 2 (tái dùng chính payload của UI).

## Success Criteria

- [ ] `/academic/reports/student-units` liệt kê sinh viên trong campus hiện tại, mỗi row có mã SV, tên, ngành, chip môn GC, chip môn Major, số môn, tín chỉ đạt
- [ ] Chỉ môn `is_passed = 1` xuất hiện; môn `is_passed = 0` và `is_passed IS NULL` bị loại khỏi chip, count và tổng tín chỉ
- [ ] Môn học nhiều lần chỉ hiện 1 chip, tín chỉ chỉ cộng 1 lần
- [ ] Sinh viên không có môn pass nào vẫn hiện row với 2 cell chip rỗng
- [ ] Filter Program / Campus / Keyword và sort hoạt động, giữ qua phân trang
- [ ] Export Excel ra đúng layout UI, tôn trọng filter đang áp
- [ ] Không rò dữ liệu ngoài campus của session

## Không nằm trong scope

- Migration / thay đổi schema
- Chiều học kỳ, điểm số, GPA, tỉ lệ tiến độ
- Transfer credit (0 row trong DB)
- Sửa 9 row `is_passed = 0` có `credit_points_earned > 0` — task riêng
- Điều tra 888 row `is_passed IS NULL`

<!-- slug: student-completed-units-report -->
