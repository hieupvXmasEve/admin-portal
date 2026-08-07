---
title: Finance Office
description: 등록금 부과, 수납과 대사, 예외 처리, 그리고 장학금과 할인.
source:
  - resources/js/constants/menu-sidebar.ts
  - resources/js/pages/Finance/Cockpit/Index.vue
  - resources/js/pages/Finance/Reporting/Index.vue
  - resources/js/pages/Finance/Revenue/Index.vue
  - resources/js/pages/Finance/BatchStudio/Hub.vue
  - resources/js/pages/Finance/PricingOperations/Index.vue
  - resources/js/pages/Finance/Payments/Index.vue
  - resources/js/pages/Finance/Payments/DngPaymentRequests/Index.vue
  - resources/js/pages/Finance/Invoices/Index.vue
  - resources/js/pages/TuitionPlans/Index.vue
  - resources/js/pages/Scholarships/Index.vue
  - resources/js/pages/StudentScholarships/Index.vue
  - resources/js/pages/ScholarshipAdjustments/Index.vue
  - resources/js/pages/ScholarshipAdjustments/Show.vue
  - resources/js/pages/Vouchers/Index.vue
---

등록금 업무는 두 메뉴 그룹에 걸쳐 있습니다. **Finance Office**(부과금의 처리 흐름)와 **Discounts & Funding**(감면과 지원)입니다.

부과금은 다음 순서로 진행됩니다.

```text
부과  ->  수납 및 대사  ->  예외 처리(불일치 시)  ->  조회 및 감사
```

부과 단계에서 틀리면 이후 모든 단계가 함께 틀립니다. 실제 금전을 다루는 영역이므로 **일괄 작업 전에 반드시 확인하십시오.**

## Hôm nay — 오늘

**용도.** 매일 아침 처음 여는 화면으로, 오늘 처리할 일을 확인합니다.

**접근 권한.** 재무 현황판 조회 권한이 있는 사용자.

**화면 내용**

- **Tổng phải thu** — 총 미수 금액.
- **Đã thu** — 수납 완료 금액.
- **SV chưa sinh phí** — 부과금이 아직 생성되지 않은 학생. 보통 가장 급한 항목입니다.
- **Cần xử lý** — 처리 대기 목록.

**절차.** **Finance Office → Hôm nay**로 이동합니다. **Làm mới**(새로 고침)를 누르면 최신 수치를 가져옵니다.

**유의 사항.** 학기 중간에 **SV chưa sinh phí**가 0이 아니라면, 수업을 듣고 있으나 등록금이 부과되지 않은 학생이 있다는 뜻입니다. 시간이 지날수록 회수가 어려우니 조기에 처리하십시오.

## Finance Reporting — 재무 보고

**용도.** 상급 부서 제출용 수납 및 미수 현황 보고서입니다.

**접근 권한.** 재무 보고 조회 권한이 있는 사용자.

**절차.** **Finance Office → Finance Reporting**으로 이동해 학기나 기간을 선택하고 확인·내보내기합니다.

## Doanh thu — 매출 현황

**용도.** 여러 학기에 걸친 전교 매출을 캠퍼스와 항목별로 집계합니다. Collection Progress와 같은 원천 데이터로 계산하므로 두 보고서의 수치가 서로 어긋나지 않습니다.

**접근 권한.** 전교 매출 보고서 조회 권한이 있는 사용자(캠퍼스 단위 Finance Reporting 권한과 별개).

**절차.** **Finance Office → Doanh thu**로 이동해 학기별 표와 캠퍼스·항목별 세부 내역을 확인합니다. **미배분 금액** 행은 별도로 표시되며 합계에 포함되지 않습니다.

## Sinh phí — 부과금 생성

미수금을 만들어 내는 그룹으로, 이 영역에서 가장 위험이 큰 부분입니다.

### Batch Studio

**용도.** 학생 집단에 부과금을 일괄 생성합니다.

**접근 권한.** Batch Studio 사용 권한이 있는 사용자.

**절차**

1. **Finance Office → Sinh phí → Batch Studio**로 이동합니다.
2. 대상 학생 집단과 부과금 종류를 선택합니다.
3. 실제 실행 전에 **결과를 미리 확인합니다.**
4. 실행하고 완료될 때까지 기다립니다.

**유의 사항**

- 반드시 미리 확인하십시오. 일괄로 잘못 생성된 부과금은 하나씩 취소해야 합니다.
- 전체 학번에 적용하기 전에 소규모 집단으로 시험하십시오.
- 학생에게 이미 장학금이나 할인 쿠폰이 있는지 확인해, 잘못된 금액이 부과되지 않도록 하십시오.

### Pricing Operations — 요금 규칙

**용도.** 등록금 산정 규칙을 등록하고 조정합니다.

**절차**

1. **Finance Office → Sinh phí → Pricing Operations**로 이동합니다.
2. **Rule versions**(규칙 판본) 영역에서 기존 목록을 확인합니다.
3. **Create pricing rule version**을 눌러 생성 패널을 열고, 내용을 입력한 뒤 **Create version**을 누릅니다.

**유의 사항.** 요금 규칙은 판본 단위로 관리됩니다. 적용 중인 판본을 수정하지 말고 새 판본을 만드십시오. 이미 생성된 부과금이 영향을 받지 않습니다.

### EGC · Kết quả & học lại, EGC - Carry Forward

**용도.** 학업 결과에서 발생하는 등록금을 처리합니다. 재수강 비용과 다음 학기로 이월되는 금액이 해당합니다.

**유의 사항.** 성적이 확정된 뒤에만 실행하십시오. 일찍 실행하면 최종 성적이 없는 학생에게 잘못 부과됩니다.

## Thu & Đối soát — 수납 및 대사

들어온 금액을 기록하고 미수금과 맞추는 그룹입니다.

| 화면 | 용도 |
| --- | --- |
| DNG Due Reminders | 납부 기한 안내 일정 |
| DNG Campus Mapping | 캠퍼스별 수납 계정 지정 |
| Lập yêu cầu thanh toán DNG | DNG 결제 게이트웨이로 보낼 납부 요청 생성 |
| Settlement Worklist | 수동으로 맞춰야 하는 건 목록 |
| Payments | 수납된 금액 목록 |

### Payments — 수납 내역

**화면 내용.** 카드 세 개: **Tổng đã đóng**(총 납부액), **Đã thanh toán**(정산 완료), **Còn dư**(잔액).

**유의 사항.** **Còn dư**가 0이 아니면 학생이 과납했거나 금액이 아직 배분되지 않은 것입니다. **Settlement Worklist**에서 처리하십시오.

### Settlement Worklist — 정산 작업 목록

**용도.** 시스템이 자동으로 맞추지 못한 건에 대해, 받은 금액을 올바른 미수금에 배분합니다.

**접근 권한.** 납부금 배분 권한이 있는 사용자.

**유의 사항.** 학생의 금전을 직접 다루는 작업입니다. 배분 전에 증빙을 확인하고, 의심스러우면 배분하지 마십시오.

## Ngoại lệ — 예외

맞지 않는 건이 모이는 곳입니다.

| 화면 | 용도 |
| --- | --- |
| Exceptions Queue | 처리 대기 중인 예외 목록 |
| Lifecycle Exceptions | 학적 상태 변경으로 발생한 예외 |
| Lifecycle History | 이미 처리된 예외 이력 |

**유의 사항.** 예외가 쌓이면 학기 말 보고 수치가 어긋납니다. 몰아두지 말고 주 단위로 정리하십시오.

## Tra cứu & Audit — 조회 및 감사

찾고 대조하기 위한 읽기 전용 화면입니다.

| 화면 | 용도 |
| --- | --- |
| Audit Workspace | 한 건을 조사할 때 쓰는 통합 조회 영역 |
| Charge Ledger (Global) | 생성된 모든 부과금 원장 |
| Invoices | 청구서 목록으로, **Invoice List**에 표시됨 |
| DNG Payment Requests | DNG 게이트웨이로 보낸 납부 요청 |
| DNG · Cần kiểm tra | 이상이 있어 보이는 게이트웨이 영수증 |
| DNG Webhook Events | 게이트웨이에서 수신한 신호 기록 |

### DNG Payment Requests

**화면 내용.** 상태 카드: **Total**, **Pending**(대기), **Pushed to DNG**(전송됨), **Paid Uninvoiced**(납부됨, 청구서 미발행), **Paid Invoiced**(납부됨, 청구서 발행), **Failed / Bridged**(실패 또는 우회 처리). **학기** 필터, DNG가 반환한 참조번호를 보여주는 **Ref (Webhook)** 열, 현재 필터 기준으로 내보내는 **Export Excel** 버튼이 있습니다.

**유의 사항.** **Failed / Bridged**와 **DNG · Cần kiểm tra**는 매일 확인해야 합니다. 돈은 들어왔으나 제대로 기록되지 않은 건이 이곳에 있습니다.

### Invoices

**화면 내용.** **학기** 필터가 있습니다(기본값은 담당자가 현재 선택한 학기). Excel 내보내기는 **DNG Payment Requests** 화면으로 옮겨졌으며 여기에는 더 이상 없습니다.

## Discounts & Funding — 감면 및 지원

학생이 실제로 얼마를 내는지 결정하는 별도 메뉴 그룹입니다.

### Tuition Plans — 등록금 요금제

**절차**

1. **Discounts & Funding → Tuition Plans**로 이동합니다.
2. **Create Tuition Plan**을 눌러 새로 만듭니다.
3. **Filters** 영역으로 기존 요금제를 찾습니다.

### Scholarships — 장학금

**절차.** **Discounts & Funding → Scholarships**로 이동해 **Filter Scholarships** 영역으로 장학금 종류를 찾거나 만들거나 수정합니다.

### Student Scholarships — 학생 장학금 배정

**용도.** 개별 학생에게 장학금을 배정합니다. 화면 이름은 **Student Scholarship Assignments**입니다.

**접근 권한.** 장학금 배정 권한이 있는 사용자.

**절차.** **Discounts & Funding → Student Scholarships**로 이동해 **Filter Assignments** 영역으로 찾은 뒤 배정하거나 해제합니다.

**유의 사항.** 장학금은 부과금 생성 **전에** 배정하십시오. 나중에 배정하면 이미 생성된 부과금은 자동으로 줄지 않아 수동으로 조정해야 합니다.

### Scholarship Adjustments — 장학금 조정

**용도.** 낙제 과목이 있는 학생을 검토하고, 면담 내용을 기록하며, 다음 학기 장학금을 줄일지 결정합니다. 원래 장학금은 수정하거나 삭제하지 않고, 영향받는 학기에만 별도 조정을 생성합니다. 학생이 더 이상 낙제하지 않으면 다음 학기에 시스템이 자동으로 원래 비율 복원을 제안합니다.

**접근 권한.** 장학금 조정 건 조회/처리 권한 보유자(`view_scholarship_adjustment`, `approve_scholarship_adjustment`).

**단계별 가이드:** [Scholarship Adjustments — 장학금 조정](/ko/finance-office/scholarship-adjustments/) (대상 학생 찾기, 면담 일정, 학생 확인, 결정/승인, 학비 반영, 다음 학기 복원).

### Vouchers — 할인 쿠폰

**절차.** **Discounts & Funding → Vouchers**로 이동해 **Filter Vouchers** 영역에서 할인 코드를 관리합니다.

## 자주 있는 상황

| 상황 | 처리 순서 |
| --- | --- |
| 학기 초, 전체 학번 부과 | Student Scholarships → Pricing Operations → Batch Studio(미리 확인) → Hôm nay |
| 학생은 냈다는데 기록이 없음 | DNG · Cần kiểm tra → DNG Webhook Events → Settlement Worklist |
| 학생이 과납함 | Payments(Còn dư 열) → Settlement Worklist |
| 주간 정리 | Exceptions Queue → Lifecycle Exceptions |
| 학기 말 수치 확정 | 예외 전부 정리 → Charge Ledger → Finance Reporting |
| 재수강 등록금 | Retake Registration → EGC · Kết quả & học lại |
