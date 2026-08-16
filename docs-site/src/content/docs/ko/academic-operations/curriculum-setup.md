---
title: Curriculum Setup
description: 학사 기본 틀 구성 — 학기, 학위과정, 교육과정 판본, 교과목, 학습 단위, 강의계획서 서식.
source:
  - resources/js/constants/menu-sidebar.ts
  - resources/js/pages/Semesters/Index.vue
  - resources/js/pages/Programs/Index.vue
  - resources/js/pages/CurriculumVersions/Index.vue
  - resources/js/pages/Units/Index.vue
  - resources/js/pages/Admin/Modules/Index.vue
  - resources/js/pages/Syllabus/TemplatesIndex.vue
---

<!-- docs-freshness: reviewed against menu-sidebar.ts change (sidebar IA restructure -- "Finance Office" renamed to "Finance", plans/260816-2125-sidebar-menu-ia-restructure/). -->

**Curriculum Setup**은 바탕이 되는 자료를 준비하는 그룹입니다. **한 번 만들어 여러 학기에 재사용**하며, 매일 하는 업무가 아닙니다.

이 그룹의 자료가 틀리거나 빠지면 강좌 개설, 수강신청, 평점 산출, 학사 보고가 모두 영향을 받습니다.

## Academic Terms — 학기

**용도.** 학기를 등록합니다. 학기 코드, 시작일과 종료일, 수강신청 기간을 정합니다. 다른 모든 활동이 학기에 연결됩니다.

**접근 권한.** 학기 조회 권한이 있는 사용자로, 보통 교무처입니다.

**새 학기 생성 절차**

1. **Academic Operations → Curriculum Setup → Academic Terms**로 이동합니다.
2. **Add New Semester**(학기 추가)를 누릅니다.
3. 학기 코드, 명칭, 시작일, 종료일, 수강신청 개시일과 마감일을 입력합니다.
4. 저장합니다.

**캠퍼스별 일정 등록 절차**

1. 방금 만든 학기를 엽니다.
2. **Campus schedules**(캠퍼스별 일정) 부분에서 **Add campus schedule**을 누릅니다.
3. 캠퍼스를 선택하고 해당 캠퍼스의 일정을 입력합니다.

**유의 사항**

- 학기는 전교 공통이지만 캠퍼스마다 일정이 다를 수 있으며, 이는 **Campus schedules**에 등록합니다.
- 수강신청 기간이 학생의 신청 가능 여부를 결정합니다. 이 기간을 잘못 넣는 것이 "학생이 수강신청을 못 한다"는 문제의 가장 흔한 원인입니다.
- 진행 중인 학기의 날짜를 바꾸면 수강신청과 등록금까지 연쇄로 영향을 받습니다. 신중히 판단하십시오.

**다음 화면.** Course Offering List, GPA Management.

## Programs — 학위과정

**용도.** 대학이 운영하는 전공과 학위과정을 등록합니다.

**접근 권한.** 학위과정 조회 권한이 있는 사용자.

**절차**

1. **Academic Operations → Curriculum Setup → Programs**로 이동합니다.
2. **Add Program**(학위과정 추가)을 눌러 새로 만듭니다.
3. 정보를 입력하고 저장합니다.
4. 기존 항목은 행 끝의 아이콘을 사용합니다. **View program**(보기), **Edit program**(수정), **Delete program**(삭제).

**유의 사항.** 재학생이 있는 학위과정은 삭제하지 마십시오. 신입생 모집을 중단한다면 삭제 대신 사용을 중지하십시오.

**다음 화면.** Curriculum Versions, Units.

## Curriculum Versions — 교육과정 판본

**용도.** 입학 연도마다 적용되는 교육과정이 다를 수 있습니다. 그 각각이 하나의 판본입니다.

**접근 권한.** 교육과정 판본 조회 권한이 있는 사용자.

**절차**

1. **Academic Operations → Curriculum Setup → Curriculum Versions**로 이동합니다.
2. **Add Curriculum Version**을 눌러 새 판본을 만듭니다.
3. 기존 판본을 조금만 수정할 경우 **Duplicate curriculum version**(복제) 아이콘을 사용해 사본에서 수정하십시오.

**화면 내용.** 상단 카드 세 개에 전체 판본 수, **적용 중**(Active) 판본 수, **중단됨**(Inactive) 판본 수가 표시됩니다.

**유의 사항.** 적용 중인 판본을 직접 수정하면 재학생에게 영향이 갑니다. 안전한 방법은 복제 후 사본을 수정하고 나중에 적용 대상을 바꾸는 것입니다.

**다음 화면.** Units, Course Registration.

## Units — 교과목

**용도.** 교과목 목록입니다. 교과목 코드, 명칭, 학점, 선수과목, 인정 대체과목을 관리합니다.

**접근 권한.** 교과목 조회 권한이 있는 사용자.

**절차**

1. **Academic Operations → Curriculum Setup → Units**로 이동합니다.
2. **Add Unit**을 눌러 교과목을 추가합니다.
3. 해당하는 경우 선수과목과 인정 대체과목을 등록합니다.
4. 행 끝의 아이콘으로 개별 교과목을 보거나 수정하거나 삭제합니다.

**화면 내용.** 상단 카드 세 개에 전체 교과목 수, **선수과목이 있는** 교과목 수, **대체과목이 있는** 교과목 수가 표시됩니다.

**유의 사항**

- 선수과목 등록은 학생의 수강신청 가능 여부에 직접 영향을 줍니다.
- 인정 대체과목은 학생이 학위과정을 바꾸거나, 인정된 다른 교과목으로 재수강할 때 사용합니다.
- 이 화면에는 **일괄 삭제** 기능이 있습니다. 확인 전에 선택 목록을 반드시 점검하십시오. 되돌릴 수 없습니다.

**다음 화면.** Syllabus Templates, Course Offering List.

## Modules — 학습 단위

**용도.** 학위과정 안에서 교과목을 학습 영역별로 묶습니다.

**접근 권한.** 학습 단위 조회 권한이 있는 사용자.

**절차.** **Academic Operations → Curriculum Setup → Modules**로 이동해 목록을 확인하고 관리합니다.

**다음 화면.** Programs, Units.

## Syllabus Templates — 강의계획서 서식

**용도.** 강의계획서 서식을 미리 만들어 두어 매 학기 처음부터 작성하지 않도록 합니다.

**접근 권한.** 강의계획서 조회 권한이 있는 사용자.

**절차**

1. **Academic Operations → Curriculum Setup → Syllabus Templates**로 이동합니다.
2. **New Template**(새 서식)을 누릅니다.
3. 내용을 작성하고 저장합니다.
4. 목록이 이상하면 **Clear filters**(필터 해제)를 누릅니다.

**다음 화면.** Course Offering List, Course Statistics.

## 강좌 개설 전 점검

- 개설하려는 학기가 존재하고 정확한지 확인합니다.
- 학위과정과 교육과정 판본이 해당 입학 연도와 맞는지 확인합니다.
- 교과목에 학점과 선수과목 정보가 갖추어졌는지 확인합니다.
- 개설할 교과목에 쓸 강의계획서 서식이 준비되었는지 확인합니다.
- 재수강 학생이 있다면 Finance 영역에서 재수강 등록금을 확인합니다.
