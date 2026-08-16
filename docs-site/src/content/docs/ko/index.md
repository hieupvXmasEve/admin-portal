---
title: Portal 사용 안내서
description: 학사 운영 담당 교직원을 위한 Portal 사용 안내서로, 실제 메뉴 구성에 따라 작성되었습니다.
source:
  - resources/js/constants/menu-sidebar.ts
---

<!-- docs-freshness: reviewed against menu-sidebar.ts change (sidebar IA restructure -- Faculty & Teaching folded into Academic Operations; Campus Operations split into Campus and Store & Clubs; Student Services -> Students, Finance Office -> Finance, Forms & Quality -> Forms & Surveys, plans/260816-2125-sidebar-menu-ia-restructure/). -->

이 안내서는 브라우저에서 Portal 관리자 시스템을 사용하는 **교직원**을 위한 것입니다. 기술 지식은 필요하지 않습니다.

학생과 교원은 별도의 포털을 사용하며, 이 안내서에서는 다루지 않습니다.

## 어디서부터 시작할까

처음이라면 [처음 사용하기](/ko/bat-dau/)를 먼저 읽으십시오. 로그인, 캠퍼스 선택, 화면 구성을 다룹니다. 한 번만 읽으면 이후 모든 장을 이해할 수 있습니다.

그다음에는 담당 업무 영역으로 이동하십시오. 아래 표의 열 개 영역이 메뉴 그룹 아홉 개와 대응합니다.

## 수록 범위

| 영역 | 상태 |
| --- | --- |
| [처음 사용하기](/ko/bat-dau/) | 작성 완료 |
| [Academic Operations — 학사 운영(교원 포함)](/ko/academic-operations/) | 작성 완료 |
| [Students — 학생](/ko/student-services/) | 작성 완료 |
| [Reports & Audits — 보고서 및 감사](/ko/reports-audits/) | 작성 완료 |
| [Finance — 등록금](/ko/finance-office/) | 작성 완료 |
| [Store & Clubs — 상점 및 동아리](/ko/store-clubs/) | 작성 완료 |
| [Campus — 캠퍼스](/ko/campus/) | 작성 완료 |
| [Forms & Surveys — 양식 및 설문](/ko/forms-quality/) | 작성 완료 |
| [Communications — 이메일 및 알림](/ko/communications/) | 작성 완료 |
| [Administration — 사용자 및 권한](/ko/administration/) | 작성 완료 |

## 표기 규칙

- 메뉴 경로는 **메뉴 그룹 → 항목 → 하위 항목** 형식으로 표기합니다.
- 화면에 표시되는 이름은 현재 영어이므로 원문 그대로 적고 옆에 한국어 뜻을 덧붙였습니다. 예: **Programs(학위과정)**.
- 각 화면은 네 부분으로 나누어 설명합니다. *용도 · 접근 권한 · 절차 · 유의 사항*.
- 안내서에 있는 항목이 본인 메뉴에 없다면 해당 계정에 아직 권한이 부여되지 않은 것입니다. 관리자에게 문의하십시오.

## 문서 관리자를 위한 안내

각 문서는 머리말의 `source` 항목에 해당 문서가 설명하는 소스 파일 목록을 명시합니다. 그 파일이 변경되면 `scripts/check-docs-freshness.sh`가 같은 풀 리퀘스트에서 해당 문서를 갱신하도록 요구합니다.
