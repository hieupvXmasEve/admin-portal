---
title: Forms & Surveys
description: 양식 보관함, 배포 회차, 설문 결과, 직원 문의함.
source:
  - resources/js/constants/menu-sidebar.ts
  - resources/js/pages/Forms/Admin/Index.vue
  - resources/js/pages/Forms/Runs/Index.vue
  - resources/js/pages/Forms/Admin/results/Index.vue
  - resources/js/pages/Forms/Queries/Inbox.vue
---

<!-- docs-freshness: reviewed against menu-sidebar.ts change (Placement Worklist entry added to Students group, plans/260817-1123-student-placement-worklist/). -->

**Forms & Surveys**는 학생에게서 정보를 수집합니다. 강의 평가 설문, 신청서, 문의가 이에 해당합니다.

다음 세 가지를 구분하십시오.

| 용어 | 의미 |
| --- | --- |
| **Form**(양식) | 문항 설계. 한 번 만들어 여러 번 사용합니다. |
| **Run**(배포 회차) | 그 양식을 특정 집단에게 일정 기간 배포하는 한 번의 시행. |
| **Result**(결과) | 한 회차에서 수집된 응답. |

학기마다 양식을 새로 만들지 마십시오. 기존 양식으로 **새 회차**를 만드십시오.

## Forms Library — 양식 보관함

**용도.** 양식을 만들고 관리합니다. 화면 이름은 **Forms Management**입니다.

**접근 권한.** 양식 조회 권한이 있는 사용자.

**절차**

1. **Forms & Surveys → Forms Library**로 이동합니다.
2. 양식을 새로 만들거나 기존 양식을 열어 수정합니다.
3. **Clear**를 누르면 필터가 해제됩니다.

**유의 사항**

- 회차가 진행 중인 양식을 수정하면 응답이 일관되지 않게 됩니다. 문항을 바꿔야 한다면 새 양식을 만드십시오.
- 동료가 용도를 알 수 있도록 양식 이름을 분명히 지으십시오.

## Runs — 배포 회차

### Runs List — 회차 목록

**용도.** 진행 중인 회차와 종료된 회차를 확인합니다. 화면 이름은 **Form Runs**입니다.

**절차**

1. **Forms & Surveys → Runs → Runs List**로 이동합니다.
2. **Filters** 영역으로 좁힙니다.
3. **Clear**를 누르면 전체가 다시 표시됩니다.
4. 회차를 열어 응답 진행 상황을 확인합니다.

### Create Run — 회차 생성

**용도.** 새 배포 회차를 엽니다.

**절차**

1. **Forms & Surveys → Runs → Create Run**으로 이동합니다.
2. 배포할 양식을 선택합니다.
3. 수신 대상 집단과 개방 기간을 선택합니다.
4. 저장하여 회차를 시작합니다.

**유의 사항**

- 저장 전에 수신 대상을 반드시 확인하십시오. 잘못 발송된 알림은 회수할 수 없습니다.
- 기간이 너무 짧으면 응답률이 낮아집니다. 학기 말 설문은 학생의 시험이 끝나기 전에 여십시오.

## Surveys — 설문 결과

### Survey Results

**용도.** 회차에서 수집된 응답을 확인합니다.

**접근 권한.** 설문 집계 결과 조회 권한이 있는 사용자.

**절차.** **Forms & Surveys → Surveys → Survey Results**로 이동해 회차를 선택합니다.

**유의 사항.** 강의 평가 결과는 민감한 자료입니다. 허용된 범위 안에서만 공유하십시오.

### Program Stats — 학위과정별 통계

**용도.** 회차별이 아니라 학위과정 단위로 집계한 수치입니다.

**다음 화면.** Lecturer GPA, Course Ranking.

## Staff Inbox — 직원 문의함

**용도.** 학생이 양식으로 제출한 요청과 문의를 접수하고 처리합니다.

**접근 권한.** 양식 검토 권한이 있는 사용자.

**절차**

1. **Forms & Surveys → Staff Inbox**로 이동합니다.
2. 각 문의를 열어 내용을 읽습니다.
3. 답변하거나 담당 부서로 전달합니다.

**유의 사항.** 개인 사서함이 아니라 공용 사서함입니다. 처리한 건은 표시해 동료가 중복 처리하지 않도록 하십시오.

## 자주 있는 상황

| 상황 | 처리 순서 |
| --- | --- |
| 학기 말 강의 평가 설문 | Forms Library(양식 선택) → Create Run → Survey Results |
| 진행 중 회차의 응답률 확인 | Runs List → 회차 열기 |
| 학위과정별 의견 정리 | Program Stats |
| 학생 문의 처리 | Staff Inbox |
