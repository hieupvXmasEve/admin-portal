---
title: Attendance
description: 출석 관리와 미이수 학생 명단.
source:
  - resources/js/constants/menu-sidebar.ts
  - resources/js/pages/Attendance/Index.vue
  - resources/js/pages/FailedStudents/Index.vue
---

<!-- docs-freshness: reviewed against menu-sidebar.ts change (Placement Worklist entry added to Students group, plans/260817-1123-student-placement-worklist/). -->

**Attendance**에서는 학생의 수업 참여 상황과 후속 조치가 필요한 사례를 확인합니다. 교과목별 통계(**Course Statistics**)는 **Course Delivery** 그룹으로 이동했습니다.

## Attendance Summary — 출석 집계

**용도.** 학생별, 수업별 출석 기록을 조회합니다.

**접근 권한.** 출석 조회 권한이 있는 사용자.

**절차**

1. **Academic Operations → Attendance → Attendance Summary**로 이동합니다.
2. **Search students or sessions...** 창에 입력해 학생이나 수업을 찾습니다.
3. 상태(**All Statuses**)나 출석 처리 방식(**All Methods**)으로 더 좁힙니다.

**유의 사항**

- 이 화면은 조회와 대조를 위한 것입니다. 일상적인 출석 처리는 교원이 별도 포털에서 수행합니다.
- 학생이 결석 처리에 이의를 제기하면 판단 전에 이곳을 먼저 확인하십시오.

**다음 화면.** Warning Center, Failed Students.

## Failed Students — 미이수 학생

**용도.** 교과목을 이수하지 못한 학생과 그 사유를 확인합니다.

**접근 권한.** 출석 조회 권한이 있는 사용자.

**절차**

1. **Academic Operations → Attendance → Failed Students**로 이동합니다.
2. **Filters** 영역에서 학기, 학위과정, 교과목으로 좁힙니다.
3. 명단을 확인한 뒤 **Retake Registration** 또는 **Thi lại**로 이동합니다.
4. **Clear Filters**를 누르면 전체 명단이 다시 표시됩니다.

**화면 내용**

- 상단 카드 네 개: **Total Failed Students**(미이수 학생 수), **Total Failed Courses**(미이수 건수), **Avg Attendance**(평균 출석률), **Retake Eligible**(1·2회차 미이수 건수 — 재수강 신청 명단이 아님).
- **Fail Reason Distribution** — 사유별 비율.
- **Top 10 Failed Units** — 미이수자가 가장 많은 교과목 열 개.

**유의 사항**

- **Retake Eligible** 카드는 재수강을 여는 조건이 아닙니다. **Retake Registration**과 **Thi lại**에서 재수강 또는 재시험을 엽니다. 두 명단은 확정된 미이수 기록을 함께 보여 주며, 해당 기록에 다른 경로의 미완료 신청이 있으면 숨깁니다.
- **Top 10 Failed Units**는 교육 품질 점검의 출발점입니다. 매 학기 말에 확인하십시오.

**다음 화면.** Retake Registration, Thi lại, Finance Office.

## 자주 묻는 질문

| 질문 | 사용할 화면 |
| --- | --- |
| 이 강좌에서 몇 명이 이수했습니까? | Course Statistics(**Course Delivery** 그룹) |
| 결석이 많은 학생은 누구입니까? | Attendance Summary |
| 재수강이 필요한 학생은 누구입니까? | Failed Students |
| 경고를 발송해야 합니까? | Warning Center |

## 운영 참고

- **Attendance Summary**는 학기 진행 중 조기 확인에 적합합니다.
- **Failed Students**는 학기 말 성적이 나온 뒤에 적합합니다.
- 특정 교과목을 들여다볼 때는 **Course Delivery** 그룹의 **Course Statistics**를 사용하십시오.
