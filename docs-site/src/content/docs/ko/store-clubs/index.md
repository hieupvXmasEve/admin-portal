---
title: Store & Clubs
description: 학생 동아리와 Gold 교환 스토어.
source:
  - resources/js/constants/menu-sidebar.ts
  - resources/js/pages/Clubs/Index.vue
  - resources/js/pages/Merchandise/Index.vue
  - resources/js/pages/Merchandise/Form.vue
  - resources/js/pages/Merchandise/Show.vue
  - resources/js/pages/Merchandise/Reports/Index.vue
  - resources/js/pages/RedemptionOrders/Index.vue
  - resources/js/pages/RedemptionOrders/Show.vue
  - app/Modules/Merchandise/routes/web.php
---

<!-- docs-freshness: reviewed against menu-sidebar.ts change (Placement Worklist entry added to Students group, plans/260817-1123-student-placement-worklist/). -->

**Store & Clubs**는 학생 동아리와 Gold 교환 스토어를 관리합니다 — 강의실/행사를 다루는 **Campus**와 분리된, HQ 전용 화면 두 곳입니다.

## Clubs — 동아리

**용도.** 학생 동아리와 회원을 관리합니다.

**접근 권한.** 동아리 조회 권한이 있는 사용자.

**절차.** **Store & Clubs → Clubs**로 이동합니다.

## Merchandise — Gold 교환 스토어

학생은 **Gold**(리워드 포인트)를 이 화면이 아니라 학생 포털에서 사용합니다.
여기 **Merchandise** 영역은 **staff**가 상품을 관리하고 교환 주문을
처리하는 곳입니다.

### Store — 상품 목록

**용도.** 교환 상품을 등록합니다. 이름, 설명, Gold 가격, 이미지, 캠퍼스별
옵션(색상/사이즈), 재고를 관리합니다.

**접근 권한.** 상품 조회/생성/수정 권한이 있는 사용자.

**절차**

1. **Store & Clubs → Merchandise → Store**로 이동합니다.
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

1. **Store & Clubs → Merchandise → Redemption Orders**로 이동합니다.
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
| 학생이 교환 주문이 아직 승인되지 않았다고 문의 | Redemption Orders → 주문 코드로 조회 |
| 스토어에 신상품 추가 필요 | Merchandise → Store → 상품 추가 + 캠퍼스별 옵션 등록 |
