---
title: Campus Operations
description: 강의실, 예약, 승인, 행사, 동아리.
source:
  - resources/js/constants/menu-sidebar.ts
  - resources/js/pages/Rooms/Index.vue
  - resources/js/pages/RoomBookings/Index.vue
  - resources/js/pages/RoomBookings/Availability.vue
  - resources/js/pages/RoomBookings/MyBookings.vue
  - resources/js/pages/RoomBookings/Pending.vue
  - resources/js/pages/RoomBookings/Calendar.vue
  - resources/js/pages/Events/Index.vue
  - resources/js/pages/Clubs/Index.vue
---

**Campus Operations**는 수업 외의 공간과 활동을 관리합니다. 강의실, 행사, 동아리가 해당합니다.

## Room Management — 강의실 관리

### Rooms — 강의실 목록

**용도.** 강의실을 등록합니다. 강의실 코드, 수용 인원, 비품, 상태를 관리합니다. 화면 이름은 **Room Management**입니다.

**접근 권한.** 강의실 조회 권한이 있는 사용자.

**절차.** **Campus Operations → Room Management → Rooms**로 이동해 강의실을 추가하거나 수정합니다.

**유의 사항**

- 점검 중으로 설정된 강의실은 시간표 편성이나 예약이 되지 않습니다. 점검이 끝나면 상태를 되돌리십시오.
- 수용 인원을 정확히 입력해야 빈 강의실 검색에서 제대로 걸러집니다.

### Find Available Rooms — 빈 강의실 찾기

**용도.** 필요한 시간대에 비어 있는 강의실을 찾습니다. **예약 전에 먼저 여는 화면입니다.**

**절차**

1. **Campus Operations → Room Management → Find Available Rooms**로 이동합니다.
2. **Filters** 영역에 날짜, 시간, 필요한 수용 인원을 입력합니다.
3. 결과에서 적합한 강의실을 골라 예약합니다.

### Bookings — 예약 현황

**용도.** 모든 강의실 예약을 확인합니다. 화면 이름은 **Room Bookings**입니다.

**접근 권한.** 예약 조회 권한이 있는 사용자.

### My Bookings — 내 예약

**용도.** 본인이 신청한 예약을 확인하고 관리합니다.

**접근 권한.** 예약 신청 권한이 있는 사용자.

### Pending Approvals — 승인 대기

**용도.** 다른 사람의 예약 신청을 승인하거나 반려합니다.

**접근 권한.** 예약 승인 권한이 있는 사용자.

**절차**

1. **Campus Operations → Room Management → Pending Approvals**로 이동합니다.
2. 각 신청을 검토해 승인하거나 반려합니다.
3. **All Bookings**를 누르면 대기 건뿐 아니라 전체가 표시됩니다.

**유의 사항.** 승인되지 않은 신청은 강의실을 **확보하지 못합니다.** 승인을 미루는 것이 중복 예약의 흔한 원인입니다.

### Room Usage Calendar — 강의실 사용 달력

**용도.** 수업과 행사를 포함한 전체 강의실 사용 현황을 달력으로 봅니다.

**절차.** **Campus Operations → Room Management → Room Usage Calendar**로 이동합니다. **Today**를 누르면 오늘로 돌아옵니다.

**유의 사항.** 중복을 가장 쉽게 발견할 수 있는 화면입니다. 큰 행사나 시험 시간대를 확정하기 전에 확인하십시오.

## Events — 행사

### List — 행사 목록

**용도.** 학교 행사를 만들고 관리합니다.

**접근 권한.** 행사 조회 권한이 있는 사용자.

**절차.** **Campus Operations → Events → List**로 이동해 행사를 만들거나 기존 행사를 엽니다.

**유의 사항.** **Find Available Rooms**에서 강의실을 먼저 예약한 뒤 행사 시간을 확정하십시오.

### Event Reports — 행사 보고

**용도.** 행사 건수와 참여 정도를 집계합니다.

## Clubs — 동아리

**용도.** 학생 동아리와 회원을 관리합니다.

**접근 권한.** 동아리 조회 권한이 있는 사용자.

**절차.** **Campus Operations → Clubs**로 이동합니다.

## 자주 있는 상황

| 상황 | 처리 순서 |
| --- | --- |
| 회의용 공간이 필요할 때 | Find Available Rooms → 예약 → 승인 대기 |
| 행사 개최 | Find Available Rooms → Events → List |
| 재시험 시간대 편성 | Room Usage Calendar → Find Available Rooms → Lịch thi lại |
| 중복 예약 신고 접수 | Room Usage Calendar → Pending Approvals |
| 강의실 사용 중단 | Rooms(점검 중으로 변경) |
