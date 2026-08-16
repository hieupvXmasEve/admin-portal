---
title: Administration
description: 사용자, 권한, 캠퍼스, 부서, 외부 연동, 시스템 설정.
source:
  - resources/js/constants/menu-sidebar.ts
  - resources/js/pages/Users/Index.vue
  - resources/js/pages/Roles/Index.vue
  - resources/js/pages/Campuses/Index.vue
  - resources/js/pages/Admin/Departments/Index.vue
  - resources/js/pages/SystemConfig/Index.vue
  - resources/js/pages/Systems/ActivityLogs.vue
  - resources/js/pages/Admin/EmailMonitoring/Index.vue
---

<!-- docs-freshness: reviewed against menu-sidebar.ts change (sidebar IA restructure -- Canvas Integrations moved out of Administration > Integrations to Academic Operations > Course Delivery (retitled Canvas Settings), plans/260816-2125-sidebar-menu-ia-restructure/). -->

**Administration**은 관리자를 위한 영역입니다. 이곳의 변경은 본인뿐 아니라 **모든 사용자**에게 영향을 줍니다.

기본 원칙은 다음과 같습니다. 한 번에 하나씩 바꾸고, 이유를 기록하고, 변경 후 실제 계정으로 확인하십시오.

## Identity & Access — 계정과 권한

### Users — 사용자

**용도.** 교직원 계정을 추가하고, 수정하고, 사용 중지합니다.

**접근 권한.** 사용자 조회 권한이 있는 사용자.

**절차**

1. **Administration → Identity & Access → Users**로 이동합니다.
2. 사용자를 추가하거나 기존 계정을 열어 수정합니다.
3. 역할과 접근 가능한 캠퍼스를 지정합니다.

**유의 사항**

- 사용자는 Google로 로그인합니다. 여기에 등록한 이메일이 실제 Google 계정과 **정확히 일치**해야 로그인할 수 있습니다.
- 퇴직자는 계정을 삭제하지 말고 **사용 중지**하십시오. 삭제하면 그동안의 작업 기록이 사라집니다.
- 지정한 캠퍼스에 따라 그 사람이 볼 수 있는 자료 범위가 정해집니다.

### Roles & Permissions — 역할과 권한

**용도.** 역할과 그에 딸린 권한을 등록합니다. 화면 이름은 **Roles**입니다.

**접근 권한.** 역할 조회 권한이 있는 사용자.

**기본 제공 역할**

| 역할 | 일반적인 범위 |
| --- | --- |
| Super Admin | 시스템 전체 권한 |
| Giám Đốc Đào Tạo(교육원장) | 전교 학사 관리 |
| Trưởng Phòng(처장) | 부서 단위 관리 |
| Cán Bộ(직원) | 일상 실무 |
| Phụ huynh(학부모) | 자녀 정보 열람 |

**유의 사항**

- 권한에 따라 사용자에게 보이는 메뉴가 달라집니다. 동료가 "메뉴가 없다"고 하면 이곳에서 그 사람의 역할을 확인하십시오.
- 역할을 수정하면 그 역할을 가진 **모든 사람**이 영향을 받습니다. 한 사람에게만 다른 권한이 필요하면 공용 역할을 고치지 말고 새 역할을 만드십시오.
- 업무에 필요한 만큼만 부여하십시오. 과도한 권한은 위험이며, 특히 재무 영역에서 그렇습니다.

## Organization — 조직

### Campuses — 캠퍼스

**용도.** 대학의 캠퍼스를 등록합니다.

**절차.** **Administration → Organization → Campuses**로 이동합니다. **Clear**를 누르면 필터가 해제됩니다.

**유의 사항.** 캠퍼스는 시스템 전체의 자료 경계입니다. 추가하거나 변경하는 일은 드물고 영향 범위가 넓으므로 확실할 때만 진행하십시오.

### Departments — 부서

**용도.** 부서를 등록합니다. 목록은 **Department List**에 표시됩니다.

**접근 권한.** 부서 관리 권한이 있는 사용자.

## Integrations — 외부 연동

| 화면 | 용도 |
| --- | --- |
| Staff Copilot | 교직원용 AI 도우미 현황 확인 |
| AI Provider Settings | AI 서비스 제공자 설정 |

**유의 사항.** AI 관련 화면은 별도 권한이 필요합니다. 이곳의 변경은 시스템 전체의 AI 기능에 영향을 줍니다.

Canvas 연결 설정(**Canvas Settings**)은 **Academic Operations → Course Delivery**로 이동했습니다. 이 설정이 지원하는 **Canvas Courses** 화면 바로 옆이며, 두 화면 모두 같은 권한을 쓰고 학사팀이 사용합니다.

## System Operations — 시스템 운영

### System Configuration — 시스템 설정

**용도.** 시스템 정보와 표시 요소를 변경합니다. 화면 이름은 **System configuration**입니다.

**접근 권한.** 시스템 설정 조회 권한이 있는 사용자.

**화면 내용.** **Application details**(시스템 이름과 일반 정보) 영역과 **Branding assets**(로고와 이미지) 영역.

**절차.** **Administration → System Operations → System Configuration**으로 이동해 수정하고 저장합니다. **Reset**을 누르면 이전 값으로 되돌립니다.

**유의 사항.** 시스템 이름과 로고는 모든 화면과 발송 메일에 나타납니다. 변경하면 모두에게 즉시 보입니다.

### Activity Logs — 활동 기록

**용도.** 시스템 전체의 작업 이력을 확인합니다.

**접근 권한.** 시스템 로그 조회 권한이 있는 사용자.

**유의 사항.** 학생 기록만 다루는 **Student Actions Audit**과 다릅니다. 이곳은 시스템 전체를 포괄합니다.

### Email Monitoring — 이메일 상태 감시

**용도.** 메일 시스템의 상태를 확인합니다.

**접근 권한.** 이메일 시스템 조회 권한이 있는 사용자.

**유의 사항.** 메일이 오지 않는다는 보고가 있으면 개별 설정을 손대기 전에 이곳을 먼저 확인하십시오.

## 자주 있는 상황

| 상황 | 처리 순서 |
| --- | --- |
| 신규 직원 입사 | Users(계정 추가, 역할과 캠퍼스 지정) |
| "X 메뉴가 보이지 않습니다" | Users(역할 확인) → Roles & Permissions |
| 직원 퇴직 | Users(사용 중지, 삭제 금지) |
| Canvas 연결 오류 | Canvas Settings(Academic Operations → Course Delivery) |
| 로고나 표시 이름 변경 | System Configuration |
| 예상치 못한 변경 조사 | Activity Logs |
| 전교 메일 장애 | Email Monitoring → Email Configuration |
