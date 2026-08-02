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
  - resources/js/pages/Merchandise/Index.vue
  - resources/js/pages/Merchandise/Form.vue
  - resources/js/pages/Merchandise/Show.vue
  - resources/js/pages/Merchandise/Reports/Index.vue
  - resources/js/pages/RedemptionOrders/Index.vue
  - resources/js/pages/RedemptionOrders/Show.vue
  - app/Modules/Merchandise/routes/web.php
---

<!-- docs-freshness: reviewed against menu-sidebar.ts change (new "Doanh thu" revenue nav item under Finance Office) -- no update needed here. -->

**Campus Operations**는 수업 외의 공간과 활동을 관리합니다. 강의실, 행사, 동아리, Gold 교환 스토어가 해당합니다.

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

## Merchandise — Gold 교환 스토어

학생은 **Gold**(리워드 포인트)를 이 화면이 아니라 학생 포털에서 사용합니다.
여기 **Merchandise** 영역은 **staff**가 상품을 관리하고 교환 주문을
처리하는 곳입니다.

### Store — 상품 목록

**용도.** 교환 상품을 등록합니다. 이름, 설명, Gold 가격, 이미지, 캠퍼스별
옵션(색상/사이즈), 재고를 관리합니다.

**접근 권한.** 상품 조회/생성/수정 권한이 있는 사용자.

**절차**

1. **Campus Operations → Merchandise → Store**로 이동합니다.
2. 새 상품을 추가하거나 기존 상품을 열어 수정합니다.
3. 상세 화면에서 캠퍼스별 옵션을 추가하고 재고를 조정합니다 — 반드시 조정
   폼을 통해서만 하고, 수치를 직접 고치지 않습니다.

**유의 사항**

- 상품을 숨기거나 보관 처리하면 학생 스토어에서 사라지지만, 기존 주문은
  주문 당시의 이름/가격을 그대로 유지합니다.
- 학생은 본인 캠퍼스에 속한 상품/옵션만 볼 수 있습니다.

### Redemption Orders — 교환 주문

**용도.** 학생의 교환 주문을 승인·거절하고 배송/수령까지 추적합니다.

**접근 권한.** 교환 주문 승인 권한이 있는 사용자. 본인이 부여받은 캠퍼스의
주문만 보입니다.

**절차**

1. **Campus Operations → Merchandise → Redemption Orders**로 이동합니다.
2. 처리할 주문을 엽니다.
3. 주문 상태에 맞는 버튼을 누릅니다: **Approve**, **Reject**(사유 입력
   필수), **Mark ready for collection**, **Mark as shipped**, **Confirm
   collected**, **Mark pickup overdue**, **Extend pickup deadline**, 또는
   취소 요청 처리(**Accept**/**Reject cancellation**).

**유의 사항**

- 주문을 거절하거나 취소하면 Gold와 재고가 학생에게 환불됩니다 — 여러 번
  눌러도 주문당 정확히 한 번만 환불됩니다.
- 배송(`shipping`) 주문은 staff가 외부 플랫폼으로 먼저 발송한 뒤 **Mark as
  shipped**를 누릅니다 — 별도 배송 모듈은 없습니다.
- 기한 내 수령하러 오지 않은 학생은 staff가 직접 **Mark pickup overdue**를
  누릅니다(아직 자동화 없음).

### Reports — Merchandise 보고

**용도.** 상태별 주문, 사용/환불된 Gold, 인기 상품, 캠퍼스별 재고를
확인합니다.

**접근 권한.** Merchandise 보고 조회 권한이 있는 사용자.

**유의 사항.** 엑셀 내보내기: 아직 없음, 확정 대기 중.

## 자주 있는 상황

| 상황 | 처리 순서 |
| --- | --- |
| 회의용 공간이 필요할 때 | Find Available Rooms → 예약 → 승인 대기 |
| 행사 개최 | Find Available Rooms → Events → List |
| 재시험 시간대 편성 | Room Usage Calendar → Find Available Rooms → Lịch thi lại |
| 중복 예약 신고 접수 | Room Usage Calendar → Pending Approvals |
| 강의실 사용 중단 | Rooms(점검 중으로 변경) |
| 학생이 교환 주문이 아직 승인되지 않았다고 문의 | Redemption Orders → 주문 코드로 조회 |
| 스토어에 신상품 추가 필요 | Merchandise → Store → 상품 추가 + 캠퍼스별 옵션 등록 |
