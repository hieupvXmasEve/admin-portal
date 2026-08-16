---
title: Communications
description: 이메일 설정, 서식, 일괄 발송, 알림, 발송 결과 확인.
source:
  - resources/js/constants/menu-sidebar.ts
  - resources/js/pages/Admin/EmailConfiguration/Index.vue
  - resources/js/pages/Admin/EmailTemplate/Index.vue
  - resources/js/pages/Admin/BulkEmail/Index.vue
  - resources/js/pages/Admin/EmailLog/Index.vue
  - resources/js/pages/Admin/Notifications/Send.vue
  - resources/js/pages/Admin/NotificationTemplate/Index.vue
  - resources/js/pages/Admin/Notifications/Ops/Outbox.vue
  - resources/js/pages/Admin/Notifications/Ops/Messages.vue
  - resources/js/pages/Admin/Notifications/Ops/Deliveries.vue
---

<!-- docs-freshness: reviewed against menu-sidebar.ts change (sidebar IA restructure -- "Finance Office" renamed to "Finance", plans/260816-2125-sidebar-menu-ia-restructure/). -->

**Communications**는 메일과 알림을 외부로 보내고, 제대로 도착했는지 확인하는 도구를 제공합니다.

두 그룹으로 나뉩니다. **Email**과 **Notification Management**입니다.

이 영역은 정보를 **학교 밖으로** 보냅니다. 잘못 보낸 것은 회수할 수 없으므로 반드시 본인에게 먼저 시험 발송하십시오.

## Email

### Email Configuration — 발송 설정

**용도.** 캠퍼스별 메일 서버를 등록합니다. 화면 이름은 **SMTP Configuration**입니다.

**접근 권한.** 이메일 시스템 관리 권한이 있는 사용자.

**화면 내용.** **Total Configurations**(전체 설정), **Active Configurations**(사용 중), **Tested Configurations**(시험 완료), **Success Rate**(발송 성공률) 카드와 **Configuration by Campus**(캠퍼스별 설정) 표.

**유의 사항**

- **사용 전에 설정을 시험하십시오.** 설정이 잘못되면 누군가 불편을 호소할 때까지 모든 메일이 조용히 실패합니다.
- **Success Rate**가 갑자기 떨어지면 즉시 확인해야 합니다.

### Email Templates — 메일 서식

**용도.** 반복해서 쓰는 메일을 미리 작성해 둡니다. 합격 통지, 등록금 안내, 시험 일정 안내 등이 해당합니다.

**절차.** **Communications → Email → Email Templates**로 이동해 서식을 만들거나 수정합니다.

**유의 사항.** 서식에는 자동으로 채워지는 항목(학생 이름, 금액, 기한)이 있습니다. 집단에 사용하기 전에 본인에게 한 통 보내 확인하십시오.

### Bulk Email — 일괄 발송

**용도.** 여러 사람에게 같은 메일을 한 번에 보냅니다. 화면 이름은 **Bulk Email Composer**입니다.

**절차**

1. **Communications → Email → Bulk Email**로 이동합니다.
2. 수신 대상 집단을 선택합니다.
3. 서식을 고르거나 내용을 작성합니다.
4. 검토한 뒤 발송합니다.

**유의 사항**

- **본인에게 시험 발송을 먼저 하십시오.** 생략해서는 안 되는 단계입니다.
- 발송 전에 수신 인원 수를 확인하십시오. 예상과 다르면 대상 집단을 잘못 고른 것입니다.
- 발송된 메일은 회수할 수 없습니다.

### Email History — 발송 이력

**용도.** 발송된 메일과 그 상태를 조회합니다. 화면 이름은 **Email Logs**입니다.

**절차**

1. **Communications → Email → Email History**로 이동합니다.
2. 필터로 해당 메일을 찾습니다.
3. **Clear**를 누르면 필터가 해제됩니다.

**유의 사항.** 학생이 메일을 못 받았다고 하면 이곳을 먼저 확인하십시오. 발송은 성공했는데 보지 못했다면 대개 스팸함에 있습니다.

## Notification Management — 알림 관리

### Send Notification — 알림 발송

**용도.** 시스템 안에서 학생이나 교직원에게 알림을 보냅니다.

**절차**

1. **Communications → Notification Management → Send Notification**으로 이동합니다.
2. **Recipients** 영역에서 수신자를 선택합니다.
3. **Notification Details** 영역에 내용을 작성합니다.
4. 발송합니다.

### Email Templates(알림용)

**용도.** 알림과 함께 나가는 메일 서식입니다. 화면 이름은 **Notification Email Templates**이며 목록은 **Templates**에 있습니다.

**유의 사항.** Email 그룹의 **Email Templates**와 다릅니다. 이 그룹은 시스템 알림 전용입니다.

### Ops — 발송 상태 확인

알림이 실제로 나갔는지 확인하는 화면 세 개입니다.

| 화면 | 용도 |
| --- | --- |
| Outbox | 발송 대기 중인 알림 |
| Messages | 생성된 알림 내용 |
| Deliveries | 수신자별 발송 결과 |

**접근 권한.** 알림 운영 조회 권한이 있는 사용자.

**유의 사항**

- **Outbox**가 계속 쌓이면 발송이 막힌 것입니다. 기술 담당에게 알리십시오.
- 알림을 못 받았다는 문의가 오면 순서대로 확인하십시오. **Messages**(생성되었는가) → **Outbox**(발송되었는가) → **Deliveries**(도착했는가).

## 자주 있는 상황

| 상황 | 처리 순서 |
| --- | --- |
| 전체 학번에 시험 일정 안내 | Email Templates → Bulk Email(시험 발송 먼저) |
| 학생이 메일을 못 받음 | Email History → 학생 스팸함 확인 |
| 알림이 도착하지 않음 | Messages → Outbox → Deliveries |
| 전교 메일이 갑자기 안 나감 | Email Configuration(Success Rate 확인) |
| 등록금 납부 기한 안내 | Bulk Email, 또는 Finance 영역의 DNG Due Reminders |
