## Context / Investigation Notes

Trước khi viết spec này, đã kiểm tra toàn bộ 275 tuition_term charges trong DB:
- Tất cả đều có `source_type = NULL` (fallback đang chạy trong production)
- Fallback trong `buildTuitionPlanChecklist` dùng `intake_major` (không phải `intake_semester_id`) làm reference point → đã xử lý đúng pre-uni vs major enrollment
- Kết quả kiểm tra: **OK: 275 | MISMATCH: 0 | NO_TERM: 0** — fallback đang đúng 100%
- Source_type không cần thiết vì `semester_id` trên charge đã đủ để identify kỳ học; term_number chỉ là display label

**Quyết định**: Không thêm source_type/source_id vào tuition_term charges. Không cần backfill. Giữ nguyên fallback hiện tại.

---

## ADDED Requirements

### Requirement: Checklist term_number resolve đúng cho mọi student pattern
`buildTuitionPlanChecklist` SHALL resolve `term_number` chính xác cho student dùng fallback (source_type = NULL), bao gồm student có pre-university semester khác với major intake semester.

#### Scenario: Student bình thường (intake_major = intake_semester_id)
- **GIVEN** student có `intake_major = semester 1`, charge ở semester 1 và semester 3
- **AND** plan có term 1=45M, term 2=0M, term 3=45M
- **WHEN** checklist được build
- **THEN** charge ở sem1 → `term_number = 1`, charge ở sem3 → `term_number = 3`
- **AND** projected items chỉ hiển thị term 2 (waived), term 4, term 5 — không duplicate với generated items

#### Scenario: Student pre-university (intake_major khác intake_semester_id)
- **GIVEN** student có `intake_semester_id = 1` (pre-uni) nhưng `intake_major = 2` (bắt đầu major từ sem 2)
- **AND** charge duy nhất ở semester 2
- **WHEN** checklist được build
- **THEN** count từ `intake_major` (sem2) đến charge semester (sem2) = 1 → `term_number = 1` ✓
- **AND** KHÔNG dùng `intake_semester_id` để tính count (sẽ cho kết quả sai = 2)

#### Scenario: student với intake_major = NULL không có tuition charge
- **GIVEN** student có `intake_major = NULL`
- **WHEN** checklist được build
- **THEN** `linkedTerm = null` cho tất cả charges → `term_number = null` hiển thị "—"
- **AND** không crash, fallback gracefully
