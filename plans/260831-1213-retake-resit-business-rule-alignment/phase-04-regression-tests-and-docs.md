---
phase: 4
title: "Regression test, tài liệu, kiểm thử toàn luồng"
status: done
priority: P2
effort: "1.5d"
dependencies: [1, 2, 3]
---

# Phase 4: Regression test, tài liệu, kiểm thử toàn luồng

## Overview

Chốt thay đổi: test regression cho 5 quy tắc không sửa (xếp lớp đúng kỳ, không hoàn tiền, giảm giá EGC...), cập nhật tài liệu canonical + end-user guide, kiểm thử toàn luồng retake/resit.

## Requirements

- Functional: toàn bộ tình huống kiểm tra ở mục "Scenarios" dưới có test tự động hoặc verified bằng smoke.
- Non-functional: arch tests (boundary Academic/Finance) vẫn xanh; docs pass `./scripts/check-docs.sh` + freshness.

## Architecture

Không có kiến trúc mới — phase đóng gói.

## Related Code Files

- Modify: `docs/features/academic/` (runbook học lại/thi lại nếu có trang liên quan)
- Modify: `docs/adr/` (ADR mới ở Phase 2; kiểm tra ADR-0026 có cần annotation không)
- Create: `docs-site/src/content/docs/academic-operations/` — 4 trang mới (retake + resit guide) cho locale `vi`, `en`, `ko`, `zh` với `source:` frontmatter + freshness markers (vi trước, ba locale còn lại theo sau)
- Test: tests liên quan retake/resit.

## Implementation Steps

1. Regression test bảo vệ các quy tắc giữ nguyên: xếp lớp học lại kỳ khác không link (guard semester); không hoàn tiền khi hủy đã trả (thi lại); giảm giá EGC double-apply bị chặn.
2. Chạy scoped verification: `./scripts/dev.sh artisan test --compact --filter=Retake`, `--filter=Resit`, `--filter=Cancellation`; `composer exec pint -- --dirty --format agent`; `./scripts/dev.sh npm exec eslint -- <changed-files>`.
3. Arch tests (boundary ADR-0026) chạy và xanh.
4. Docs: ADR Phase 2 + cập nhật `docs/features/academic/` + CREATE 4 trang docs-site (vi → en → ko → zh).
5. `./scripts/check-docs.sh` + `./scripts/check-docs-freshness.sh`.

## Scenarios bắt buộc kiểm (từ báo cáo khảo sát, đối chiếu quy tắc owner)

1. Nếu môn không có `unit.retake_fee` nhưng catalog có rule, khi đăng ký học lại, thì hệ thống phải cho phép (Phase 1).
2. Nếu catalog không có rule, khi đăng ký học lại/thi lại, thì hệ thống phải chặn yêu cầu cấu hình Pricing (Phase 1 — parity đã có ở resit).
3. Nếu retake `paid` chưa-link bị hủy, khi completion outbox về, thì registration phải `cancelled` và tiền thành unapplied balance (Phase 2).
4. Nếu retake `paid` đã-link bị hủy, thì hệ thống phải chặn (Phase 2).
5. Nếu sinh viên no_show, khi nhân viên ghi nhận, thì resit → no_show, học lại mở, resit lần 2 chặn (Phase 3).
6. Nếu syllabus `max_attempts` hiện hành > 1, khi tạo resit lần 2, thì hệ thống phải cho phép (live-policy semantics: điều chỉnh syllabus = điều chỉnh giới hạn).
7. Nếu retake đã trả được xếp vào kỳ khác kỳ đăng ký, khi sync chạy, thì hệ thống phải không link (regression, Phase 4).
8. Nếu resit paid bị hủy, thì hệ thống phải không hoàn tiền + không mất lượt (regression, Phase 4).

## Success Criteria

- [ ] Tất cả scoped tests xanh; Pint/eslint sạch.
- [ ] `./scripts/check-docs.sh` + freshness xanh.
- [ ] Báo cáo cuối liệt kê changed files, validation results, scope decisions, unresolved questions (Done criteria AGENTS.md).

## Risk Assessment

- Thiếu coverage regression → quay lại lệch cũ. Mitigate: 8 scenarios trên là acceptance checklist bắt buộc của phase này.
