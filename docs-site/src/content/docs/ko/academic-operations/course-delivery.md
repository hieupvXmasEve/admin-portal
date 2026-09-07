---
title: Course Delivery
description: 강좌 개설, 시간표 편성, 수강신청, 재수강, 재시험, 교과목 통계, Canvas 연결.
source:
  - resources/js/constants/menu-sidebar.ts
  - resources/js/pages/CourseOfferings/Index.vue
  - resources/js/pages/ClassSchedule/Index.vue
  - resources/js/pages/CourseRegistrations/Index.vue
  - resources/js/pages/Academic/RetakeCourse/Index.vue
  - resources/js/pages/Academic/RetakeCourse/Create.vue
  - resources/js/pages/Academic/ExamResit/Index.vue
  - resources/js/pages/Academic/ExamResit/Create.vue
  - resources/js/pages/Academic/ExamResit/Schedule/Index.vue
  - resources/js/pages/CourseStatistics/Index.vue
  - resources/js/pages/Admin/Canvas/Courses/Index.vue
---

<!-- docs-freshness: reviewed against menu-sidebar.ts change (Placement Worklist entry added to Students group, plans/260817-1123-student-placement-worklist/). -->

**Course Delivery**는 학기 초 핵심 업무입니다. 강좌 개설, 시간표 편성, 수강신청, 재수강과 재시험 처리를 다룹니다.

## Course Offering List — 개설 강좌 목록

**용도.** 특정 학기에 강좌를 개설합니다. 어떤 교과목을, 어떤 학기에, 어떤 방식으로, 누가 담당하는지 정합니다.

**접근 권한.** 개설 강좌 조회 권한이 있는 사용자.

**절차**

1. **Academic Operations → Course Delivery → Course Offering List**로 이동합니다.
2. **Create Course Offering**(강좌 개설)을 누릅니다.
3. 교과목, 학기, 수업 방식과 나머지 정보를 선택하고 저장합니다.
4. 기존 강좌를 찾으려면 **Search courses...** 창에 입력하거나 **Filters** 영역에서 학습 단위(**All Modules**), 수준(**All Levels**), 유형(**All Types**), 상태(**All Statuses**), 수업 방식(**All Modes**)으로 좁힙니다.

**화면 내용.** 상단 카드 두 개: **Total Offerings**(전체 개설 강좌)와 **Active Offerings**(운영 중 강좌).

**유의 사항**

- 학생 수강신청을 열기 **전에** 강좌가 먼저 개설되어 있어야 합니다.
- 목록은 기본적으로 선택한 캠퍼스로 걸러집니다. 예상한 강좌가 없으면 화면 왼쪽 위 `Campus:` 줄을 확인하십시오.

**다음 화면.** Course Statistics, Class Schedule, Course Registration, Attendance Summary.

## Course Statistics — 교과목 통계

**용도.** 한 학기의 교과목별 현황을 봅니다. 수강 인원, 출석률, 전반적인 성적을 확인할 수 있습니다.

**접근 권한.** 출석 조회 권한이 있는 사용자.

**절차**

1. **Academic Operations → Course Delivery → Course Statistics**로 이동합니다.
2. **Select semester** 항목에서 학기를 선택합니다.
3. **Unit code or name...** 창으로 교과목을 찾습니다.

**유의 사항.** 학기를 먼저 선택해야 하며, 선택하지 않으면 화면에 자료가 표시되지 않습니다.

**다음 화면.** Failed Students(**Attendance** 그룹).

## Class Schedule — 수업 시간표

**용도.** 개설 강좌의 수업 일정을 편성합니다. 날짜, 시간, 강의실을 정합니다.

**접근 권한.** 개설 강좌 조회 권한이 있는 사용자.

**절차**

1. **Academic Operations → Course Delivery → Class Schedule**로 이동합니다.
2. 옆의 필터 영역에서 학기, 강좌, 기간을 선택합니다.
3. 달력 형식 또는 표 형식으로 확인합니다.
4. 수업을 누르면 오른쪽에 수정 패널이 열립니다. 수정 후 저장합니다.

**유의 사항**

- 저장 전에 강의실 중복과 교원 시간 중복을 확인하십시오.
- 이미 진행된 수업의 일정을 변경하면 해당 출석 자료가 어긋납니다.

**다음 화면.** Attendance Summary.

## Course Registration — 수강신청

**용도.** 어떤 학생이 어떤 강좌를 수강하는지 기록합니다.

**접근 권한.** 수강신청 조회 권한이 있는 사용자.

**절차**

1. **Academic Operations → Course Delivery → Course Registration**으로 이동합니다.
2. **Filters** 영역에서 학기, 강좌, 학생으로 좁힙니다.
3. **View**로 상세를 보고, **Edit**로 수정하고, **Delete**로 신청을 취소합니다.
4. 목록이 비어 있으면 **Register First Student**(첫 학생 등록)를 눌러 추가합니다.

**화면 내용.** 상단 카드 세 개: **Total Registrations**(전체 신청 건), **Active Registrations**(유효), **Pending Registrations**(대기).

**유의 사항**

- 신청을 취소하면 학생의 등록금에 영향이 갑니다. 금액 처리는 **Finance Office** 영역에서 진행하십시오.
- **Pending** 수치는 남은 처리 건입니다. 학기 시작 전에 정리하십시오.

**다음 화면.** 학생 목록, Attendance Summary.

## Retake Registration — 재수강 신청

**용도.** 미이수 교과목을 다시 수강하는 학생을 등록합니다.

**접근 권한.** 재수강 조회 권한이 있는 사용자.

**절차**

1. **Academic Operations → Course Delivery → Retake Registration**으로 이동합니다.
2. 처리할 학생이나 교과목을 필터로 찾습니다.
3. 재수강 신청을 기록합니다. 시스템이 현재 가격 카탈로그에 따라 재수강 수수료를 자동 계산하며, 가격이 아직 구성되지 않은 과목은 차단되고 구성을 먼저 요청합니다.
4. 목록이 예상과 다르면 **Xóa bộ lọc**(필터 해제)를 누릅니다.

**유의 사항**

- 재수강과 재시험 명단은 확정된 미이수 성적 기록을 함께 보여 줍니다. 재시험 신청이 아직 끝나지 않은 기록은 재수강 명단에서 숨기고, 재수강 신청이 있는 기록(반 배정 포함)은 재시험 명단에서 숨깁니다. 취소된 신청은 해당 기록이 두 명단에 다시 나타납니다.
- 재수강 수수료는 항상 시스템의 현재 가격 카탈로그를 따르며, 교과목에 저장된 옛 금액을 따르지 않습니다.
- 결제 완료 후 아직 **반에 배정되지 않은** 신청도 취소할 수 있습니다: 이미 낸 돈은 미적용 잔액이 되어 이후 발생하는 수수료에 사용됩니다(계좌로 환불되지 않음). 반에 배정된 신청은 취소할 수 없습니다.

**다음 화면.** Failed Students, Finance Office.

## Thi lại — 재시험

**용도.** 재시험 대상자 명단을 만들고 결과를 관리합니다.

**접근 권한.** 재시험 조회 권한이 있는 사용자.

**절차**

1. **Academic Operations → Course Delivery → Thi lại**로 이동합니다.
2. 학기, 교과목, 학생으로 좁힙니다.
3. 재시험 회차를 만들고 응시 학생을 선택합니다. 각 성적 기록마다 교과목이 정한 재시험 횟수만큼 응시할 수 있으며(기본 1회), syllabus에서 횟수를 올리면 별도 변경 없이 다시 열립니다.
4. 시험 후 결과를 입력해 회차를 마감합니다. 학생에게 시험 시간대가 있었는데 불참했다면 **Vắng thi**(불참)를 눌러 확인하십시오 — 응시 기회가 소진되며 원래 성적은 그대로 유지됩니다.

**유의 사항**

- **불참(No-show)**은 **취소(Cancel)**와 다릅니다: 불참은 시험 일정이 있었는데 응시하지 않은 것이며 수수료는 소멸됩니다; 취소는 시험 전에 처리하며 아래 선택에 따라 수수료가 결정됩니다.
- **결제 완료된** 재시험 신청을 취소할 때는 두 가지 결과 중 하나를 선택합니다: **수수료 몰수**(이미 걷은 수수료는 수익으로 유지) 또는 **추후 사용 보류**(수수료가 미적용 잔액이 되어 이후 수수료에 사용). 두 경우 모두 계좌로 환불되지 않습니다.

**다음 화면.** Lịch thi lại.

## Lịch thi lại — 재시험 일정

**용도.** 재시험 회차의 시험 시간대와 시험실을 정하고 감독 인원을 배정합니다.

**접근 권한.** 재시험 일정 관리 권한이 있는 사용자.

**절차**

1. **Academic Operations → Course Delivery → Lịch thi lại**로 이동합니다.
2. **Tạo ca phòng thi**(시험 시간대 생성)를 눌러 **Ca phòng thi mới** 패널을 엽니다.
3. 시간과 시험실을 입력하고 **Tạo ca**(시간대 생성)를 누릅니다.
4. 같은 회차에 시간대를 더 넣으려면 **Thêm ca thi**(시간대 추가)를 누릅니다.
5. **Phân công coi thi**(감독 배정)를 눌러 각 시간대의 감독을 지정합니다.

**유의 사항.** 다른 일정과 겹치지 않도록 **Campus**에서 시험실을 먼저 예약하십시오.

## Canvas Courses — Canvas 연결

**용도.** Portal의 개설 강좌를 온라인 학습 시스템 Canvas의 강의실과 연결합니다.

**접근 권한.** Canvas 연동 조회 권한이 있는 사용자.

**절차**

1. **Academic Operations → Course Delivery → Canvas Courses**로 이동합니다.
2. **Integration Status**(연동 상태) 영역에서 연결이 정상인지 확인합니다.
3. 연결할 강좌를 선택하고 **Map Course**(강의실 연결)를 누릅니다.
4. 여러 강좌를 한 번에 처리하려면 **Select All**(전체 선택)과 **Deselect All**(전체 해제)을 사용합니다.
5. 연결에 문제가 있으면 **Manage Integrations** 또는 **Go to Integrations**를 눌러 **Canvas Settings**를 엽니다.

**유의 사항.** 잘못 연결하면 학생이 다른 온라인 강의실로 들어갑니다. 확인 전에 강좌 코드와 학기를 점검하십시오.

**다음 화면.** Canvas Settings.

## Canvas Settings — Canvas 연결 설정

**용도.** Canvas 시스템과의 연결(API 키, 엔드포인트)을 설정합니다. 이전에는 Administration에 있었으나, 두 화면 모두 같은 권한(`view_canvas_integration`)을 쓰고 시스템 관리팀이 아니라 학사팀이 사용하는 화면이라 Canvas Courses 옆으로 옮겼습니다.

**접근 권한.** Canvas 연동 조회 권한이 있는 사용자.

**절차.** **Academic Operations → Course Delivery → Canvas Settings**로 이동합니다.

**다음 화면.** Canvas Courses.

## 권장 흐름

```text
Course Offering List
  -> Class Schedule
  -> Course Registration
  -> Canvas Courses
  -> Attendance Summary
```

미이수 학생이 있는 경우:

```text
Course Statistics
  -> Failed Students
  -> Retake Registration
  -> 재수강 등록금 처리가 필요하면 Finance Office
```
