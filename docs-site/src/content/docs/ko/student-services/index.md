---
title: Students
description: 학생 기록, 등록 및 학적 보류, 입학 지원서 처리.
source:
  - resources/js/constants/menu-sidebar.ts
  - resources/js/pages/Students/Index.vue
  - resources/js/pages/Students/enrollments/Index.vue
  - resources/js/pages/StudentApplications/Index.vue
---

<!-- docs-freshness: reviewed against menu-sidebar.ts change (sidebar IA restructure -- "Student Services" renamed to "Students", plans/260816-2125-sidebar-menu-ia-restructure/). -->

**Students**는 **학생 한 명 단위**로 일하는 영역입니다. 학생 기록, 등록 상태, 입학 지원서를 다룹니다.

**Reports & Audits**와 구분하십시오. 그쪽은 전교 단위 집계이고, 이곳은 개별 학생입니다.

## Students — 학생 목록

**용도.** 학생 기록을 조회하고, 추가하고, 수정합니다. 학생 상세 기록으로 들어가는 입구입니다.

**접근 권한.** 학생 조회 권한이 있는 사용자.

**절차**

1. **Students → Students**로 이동합니다. 화면 이름은 **Students Management**입니다.
2. 검색창이나 필터로 학생을 찾습니다.
3. 행을 눌러 상세 기록을 엽니다.
4. **Đặt lại**(초기화)를 누르면 모든 필터가 해제됩니다.

**목록 내보내기**

1. **Select format**에서 형식을 고릅니다.
2. **Select scope**에서 범위를 고릅니다. 현재 필터에 걸린 행만, 또는 전체.
3. 확인하여 파일을 내려받습니다.

**유의 사항**

- 학생 기록은 성적, 출석, 평점, 등록금, 처분을 모두 확인하는 기준점입니다.
- 목록은 선택한 캠퍼스로 걸러집니다. 학생이 보이지 않으면 `Campus:` 줄을 확인하십시오.
- 내보내기는 기본적으로 현재 필터를 따릅니다. 인원이 적게 나오면 대개 필터가 남아 있는 것입니다.

**다음 화면.** Course Registration, Warning Center, Finance Office.

## Enrollments & Holds — 등록 및 학적 보류

**용도.** 학기별 학생 등록 상태와 학적이 보류된 사례를 관리합니다.

**접근 권한.** 학생 조회 권한이 있는 사용자.

**절차**

1. **Students → Enrollments & Holds**로 이동합니다. 화면 이름은 **Student Enrollments & Holds Management**입니다.
2. **Student Enrollments** 표에서 누가 어느 학기에 등록되어 있는지 확인합니다.
3. 처리할 대상을 필터로 좁힙니다.
4. 행을 열어 상태를 확인하거나 변경합니다.

**유의 사항**

- 보류 상태인 학생은 보통 수강신청을 할 수 없습니다. "수강신청이 안 된다"는 문의가 오면 수강신청 기간보다 이 화면을 먼저 확인하십시오.
- 보류 사유는 등록금 미납인 경우가 많습니다. 해제 전에 **Finance Office** 영역과 대조하십시오.
- 보류 알림은 요약 화면(Dashboard)에도 표시됩니다.

**다음 화면.** Finance Office, Course Registration.

## Student Applications — 입학 지원서

**용도.** 입학 지원서를 처리합니다. 제출 서류를 확인하고 승인하거나 반려합니다.

**접근 권한.** 입학 지원서 조회 권한이 있는 사용자.

**절차**

1. **Students → Student Applications**로 이동합니다.
2. **Search name, email, code…** 창으로 찾거나, 상태(**Status**)와 모집 회차(**Intake**)로 좁힙니다.
3. 지원서 안의 파일 이름을 눌러 첨부 서류를 엽니다.
4. 지원서를 승인하거나 반려합니다.

**반려할 때**

1. **Explain the reason for rejection** 항목에 사유를 입력합니다.
2. **Confirm rejection**을 눌러 확정합니다.

**유의 사항**

- 반려 사유는 기록되며 지원자에게 전달될 수 있습니다. 무엇을 보완해야 하는지 알 수 있도록 명확히 작성하십시오.
- 승인 전에 첨부 서류를 꼼꼼히 확인하십시오. 승인하면 지원서는 다음 입학 절차로 넘어갑니다.
- 모집 회차가 섞이지 않도록 **Intake**로 걸러서 보십시오.

**다음 화면.** Students.

## 자주 있는 상황

| 상황 | 처리 순서 |
| --- | --- |
| 학생이 수강신청을 못 한다고 문의 | Enrollments & Holds → Finance Office → Academic Terms |
| 한 학생의 전체 현황 확인 | Students → 상세 기록 열기 |
| 새 모집 회차 지원서 심사 | Student Applications, Intake로 필터 |
| 보고용 학생 명단 내보내기 | Students → 형식과 범위 선택 |
