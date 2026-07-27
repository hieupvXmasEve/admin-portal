---
title: Reports & Audits
description: 전교 단위 보고서와 학사 자료 대조.
source:
  - resources/js/constants/menu-sidebar.ts
  - resources/js/pages/Admin/Reports/StudentActionsAudit.vue
  - resources/js/pages/Admin/Reports/StudentDecisions/Index.vue
  - resources/js/pages/Admin/Reports/AcademicProgressionAudit/Index.vue
  - resources/js/pages/Admin/Reports/AcademicProgressionAudit/MissingDocuments.vue
  - resources/js/pages/Admin/Reports/AcademicProgressionAudit/MissingDecisions.vue
  - resources/js/pages/Admin/Academic/Performance/Dashboard.vue
  - resources/js/pages/Academic/CourseRanking/Index.vue
  - resources/js/pages/Academic/Report/Index.vue
---

**Reports & Audits**는 관리자용 영역입니다. 전교 단위 수치를 보고, 자료가 빠진 곳을 찾아냅니다.

학생 한 명씩 처리하는 **Student Services**와 다릅니다. 이곳에서는 보고서의 각 행이 해당 학생 기록으로 **한 방향으로만** 연결됩니다. 학생 기록에서 보고서로 되돌아오는 경로는 없습니다.

두 그룹으로 나뉩니다. **Lifecycle & Decisions**와 **Academic Performance**입니다.

## Lifecycle & Decisions

### Student Actions Audit — 학생 기록 변경 이력

**용도.** 학생 기록에 대해 수행된 모든 작업을 확인합니다. 누가, 무엇을, 언제 했는지 알 수 있습니다.

**접근 권한.** 학생 처리 이력 조회 권한이 있는 사용자.

**절차**

1. **Reports & Audits → Lifecycle & Decisions → Student Actions Audit**으로 이동합니다.
2. **Action Logs** 표를 확인합니다.
3. 기간, 작업 종류, 수행자로 좁힙니다.
4. **Reset all**을 누르면 모든 필터가 해제됩니다.

**유의 사항.** "이것을 누가 바꿨는가"에 답하는 화면입니다. 분쟁 처리와 내부 점검에 사용하십시오.

### Lifecycle Yearly Analysis — 연도별 학적 분석

**용도.** 입학 연도별로 학생이 해마다 어떻게 되었는지 봅니다. 계속 재학, 휴학, 중도 이탈 인원을 확인합니다.

**접근 권한.** 학생 처리 이력 조회 권한이 있는 사용자.

**절차**

1. **Reports & Audits → Lifecycle & Decisions → Lifecycle Yearly Analysis**로 이동합니다.
2. 분석할 연도나 기간을 선택합니다.
3. 보고서에 넣을 자료는 내보내기합니다.

### Academic Progression — 학업 진행 대조

**용도.** 학생이 올바른 학습 단계에 배치되어 있는지 확인합니다.

**접근 권한.** 학생 처리 이력 조회 권한이 있는 사용자.

**화면 내용.** 요약 카드: **Pre-Uni GC Placements**(예비 과정 배치), **Intake Course Placements**(입학 과정 배치), **Stage Changes**(단계 변경), **IELTS Recorded**(IELTS 점수 등록).

**유의 사항.** 수치가 실제와 다르다면 대개 기록이 갱신되지 않은 것이지 학생 배치가 잘못된 것은 아닙니다. 결론을 내리기 전에 아래 두 화면을 확인하십시오.

### Missing Documents — 서류 미제출

**용도.** IELTS 성적표가 아직 없는 학생을 가려냅니다. 화면 이름은 **Missing IELTS Documents**입니다.

**절차**

1. **Reports & Audits → Lifecycle & Decisions → Missing Documents**로 이동합니다.
2. **Students with Missing Documents** 표를 확인합니다.
3. 학생에게 제출을 요청하거나, 이미 제출되었으나 입력되지 않았다면 기록합니다.

**유의 사항.** 이 명단은 진급 심사 시점마다 0이 되어야 합니다. 쌓아두면 단계 변경에서 막힙니다.

### Missing Decisions — 처분 미등록

**용도.** 처분 문서가 붙어 있지 않은 단계 변경 건을 가려냅니다.

**절차**

1. **Reports & Audits → Lifecycle & Decisions → Missing Decisions**로 이동합니다.
2. **Transitions Missing a Decision** 표를 확인합니다.
3. 각 건에 해당 처분을 등록합니다.

**유의 사항.** 처분 누락은 단순한 자료 결손이 아니라 공식 기록의 공백입니다. 우선 처리하십시오.

### Student Decisions — 학생 처분

**용도.** 학생에게 내려진 모든 처분을 조회합니다.

**절차**

1. **Reports & Audits → Lifecycle & Decisions → Student Decisions**로 이동합니다.
2. **Filter** 영역으로 범위를 좁힙니다.
3. **Decision List** 표를 보고 개별 처분을 열어 상세를 확인합니다.

## Academic Performance

### Performance Dashboard — 성과 현황판

**용도.** 전교 학업 성과를 한눈에 봅니다. 화면 이름은 **Academic Performance**입니다.

**접근 권한.** 성적 조회 권한이 있는 사용자.

**화면 내용.** 카드 네 개: **Avg Semester GPA**(학기 평균 평점), **Avg Cumulative GPA**(누적 평균 평점), **At-Risk Students**(위험군 학생), **Completion Rate**(이수율).

**유의 사항.** 평점이 확정된 뒤에만 수치가 정확합니다. 그 전에 보면 미완성 상태의 값입니다.

### Course Ranking — 교과목 순위

**용도.** 교과목 간 성적을 비교하고, 유난히 높거나 낮은 교과목을 찾습니다.

**접근 권한.** 성적 조회 권한이 있는 사용자.

**유의 사항.** 학기 말 교육 품질 점검 시 Failed Students 화면의 **Top 10 Failed Units**와 함께 보십시오.

### Academic Report — 학사 보고서

**용도.** 내부 보고용 표와 그래프를 제공합니다.

**화면 내용.** **Grade Distribution**(성적 분포)과 **Grade Statistics**(성적 통계).

**절차**

1. **Reports & Audits → Academic Performance → Academic Report**로 이동합니다.
2. 학기, 학위과정, 교과목으로 좁힙니다.
3. **Clear Filters**를 누르면 전체가 다시 표시됩니다.

## 자주 있는 상황

| 상황 | 사용할 화면 |
| --- | --- |
| "이 학생 기록을 누가 수정했습니까?" | Student Actions Audit |
| 학기 결산 회의 준비 | Performance Dashboard → Academic Report → Course Ranking |
| 진급 심사 전 기록 정리 | Missing Documents → Missing Decisions → Academic Progression |
| 입학 연도별 이탈률 집계 | Lifecycle Yearly Analysis |
