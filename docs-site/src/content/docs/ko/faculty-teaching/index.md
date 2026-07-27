---
title: Faculty & Teaching
description: 교원 기록, 강의시수, 담당 강좌의 성적.
source:
  - resources/js/constants/menu-sidebar.ts
  - resources/js/pages/Lectures/Index.vue
  - resources/js/pages/Lectures/TeachingHours.vue
  - resources/js/pages/Lectures/LecturerGpa.vue
---

**Faculty & Teaching**은 강의 인력을 관리합니다. 누가 강의하는지, 시수는 얼마인지, 담당 강좌의 성적은 어떠한지를 다룹니다.

## Lecturer List — 교원 목록

**용도.** 교원 기록을 조회하고 관리합니다.

**접근 권한.** 교원 조회 권한이 있는 사용자.

**절차**

1. **Faculty & Teaching → Lecturer List**로 이동합니다. 화면 이름은 **Lecturers**입니다.
2. 학기(**All Semesters**), 학위과정(**All Programs**), 상태(**All Statuses**), 임용 형태(**All Types**)로 좁힙니다.
3. 행을 눌러 교원 기록을 엽니다.

**유의 사항**

- 임용 형태(전임, 시간강사)에 따라 다음 화면의 강의시수 산정 방식이 달라집니다.
- 강좌에 배정하려면 교원이 먼저 이 목록에 있어야 합니다.

**다음 화면.** Lecturer Hours, Course Offering List.

## Lecturer Hours — 강의시수

**용도.** 학기 또는 지정 기간의 교원별 강의시수를 집계합니다. 화면 이름은 **Lecturer Teaching Hours Report**입니다.

**접근 권한.** 교원 조회 권한이 있는 사용자.

**절차**

1. **Faculty & Teaching → Lecturer Hours**로 이동합니다.
2. **Select semester**에서 학기를 고르거나, **From date**와 **To date**에 기간을 입력합니다.
3. 표를 확인하고, 교원을 열어 강좌별 내역을 봅니다.

**유의 사항**

- 시수는 편성된 수업을 기준으로 계산됩니다. **Class Schedule**에 없는 수업은 집계되지 않습니다.
- 이 수치는 보통 강사료 산정에 쓰입니다. 확정 전에 실제 강의 기록과 대조하십시오.
- 시수 확정 후 수업 일정을 변경하면 해당 학기 수치가 어긋납니다.

**다음 화면.** Class Schedule.

## Lecturer GPA — 교원별 강좌 성적

**용도.** 각 교원이 담당한 강좌의 평균 성적을 봅니다.

**접근 권한.** 교원 조회 권한 **및** 설문 집계 결과 조회 권한을 모두 가진 사용자.

**절차**

1. **Faculty & Teaching → Lecturer GPA**로 이동합니다.
2. **Select semester**에서 학기를 선택합니다.
3. 교원별 결과를 확인합니다.

**유의 사항**

- 해당 학기 평점이 확정된 뒤에야 의미 있는 수치가 됩니다.
- 강좌 성적이 낮다고 해서 강의가 부실하다는 뜻은 아닙니다. 교과목 난도, 수강생 수준, 수강 인원이 모두 영향을 줍니다. **Course Ranking**과 **Course Statistics**를 함께 보고 판단하십시오.
- 사람에 관한 민감한 자료입니다. 허용된 범위 안에서만 사용하십시오.

**다음 화면.** Course Ranking, Survey Results.

## 자주 있는 상황

| 상황 | 처리 순서 |
| --- | --- |
| 학기 말 강사료 산정 | Class Schedule → Lecturer Hours |
| 새 학기 강의 배정 준비 | Lecturer List → Course Offering List |
| 교육 품질 검토 | Lecturer GPA → Course Ranking → Survey Results |
