---
title: Academic Operations
description: 학사 운영 영역 개요 — 화면 그룹, 사용 대상, 시작 지점.
source:
  - resources/js/constants/menu-sidebar.ts
---

<!-- docs-freshness: reviewed against menu-sidebar.ts change (sidebar IA restructure -- Attendance & Completion renamed to Attendance; Course Statistics moved to Course Delivery; Faculty and Scholarship Adjustments folded in, plans/260816-2125-sidebar-menu-ia-restructure/). -->

**Academic Operations**는 학사 업무를 수행하는 영역입니다. 교육과정 구성, 강좌 개설, 수강신청 관리, 출석 확인, 평점 확정, 학업 위험 학생 파악을 다룹니다.

여섯 개 화면 그룹 중 앞의 네 개는 한 학기의 진행 순서를 그대로 따릅니다.

1. **Curriculum Setup** — 기본 틀: 학기, 학위과정, 교과목.
2. **Course Delivery** — 강좌 개설, 시간표, 수강신청, 재수강, 재시험, 교과목 통계, Canvas 연결.
3. **Attendance** — 출석 관리와 미이수 학생 확인.
4. **Grades & Performance** — 성적 확정, 평점, 학사경고, 미이수로 인한 장학금 조정.

나머지 한 그룹은 학기 진행 순서와 무관하게 독립적입니다.

- **Faculty** — 교원 기록, 강의시수, 담당 강좌 성적.

앞의 네 그룹 순서를 건너뛰면 진행이 막힙니다. 학기가 없으면 강좌를 개설할 수 없고, 강좌가 없으면 수강신청을 받을 수 없습니다.

## 사용 대상

| 역할 | 용도 |
| --- | --- |
| 교무처 | 학기, 학위과정, 교과목, 강의계획서 관리 |
| 학사 담당자 | 강좌 개설, 수강신청, 출석, 미이수 학생, 재수강 처리 |
| 처장 및 교육원장 | 평점 확정, 경고 검토, 학기 결과 대조 |
| 지원 부서 | 학생 문의 시 강좌나 성적 상태 조회 |

## 시작 지점

| 그룹 | 이럴 때 시작합니다 |
| --- | --- |
| [Curriculum Setup](/ko/academic-operations/curriculum-setup/) | 새 학기 전 기본 자료를 준비하거나 학위과정을 수정할 때 |
| [Course Delivery](/ko/academic-operations/course-delivery/) | 강좌 개설, 시간표 편성, 수강신청, 재수강·재시험 처리, 교과목 통계 확인 |
| [Attendance](/ko/academic-operations/attendance-completion/) | 출석, 미이수 학생 확인 |
| [Grades & Performance](/ko/academic-operations/grades-performance/) | 평점 확정, 과거 성적 조회, 경고 대상 확인, 장학금 조정 검토 |
| [Faculty](/ko/academic-operations/faculty/) | 교원 기록, 강의시수, 담당 강좌 성적 조회 |

시스템이 익숙하지 않다면 [화면 흐름도](/ko/academic-operations/flow-map/)에서 화면 간 연결을 먼저 확인하십시오.

## 영역 내 전체 화면

| 그룹 | 화면 | 용도 |
| --- | --- | --- |
| Curriculum Setup | Academic Terms | 학기와 수강신청 기간 등록 |
| Curriculum Setup | Programs | 학위과정 및 전공 등록 |
| Curriculum Setup | Curriculum Versions | 입학 연도별 교육과정 판본 |
| Curriculum Setup | Units | 교과목 목록, 선수과목, 인정 대체과목 |
| Curriculum Setup | Modules | 교과목을 학습 단위로 묶기 |
| Curriculum Setup | Syllabus Templates | 재사용 가능한 강의계획서 서식 |
| Course Delivery | Course Offering List | 학기별 강좌 개설 |
| Course Delivery | Class Schedule | 수업별 날짜, 시간, 강의실 |
| Course Delivery | Course Registration | 학생별 수강 강좌 등록 |
| Course Delivery | Retake Registration | 미이수 교과목 재수강 신청 |
| Course Delivery | Thi lại(재시험) | 재시험 대상자 명단 작성과 결과 관리 |
| Course Delivery | Lịch thi lại(재시험 일정) | 시험 시간대, 시험실, 감독 배정 |
| Course Delivery | Canvas Courses | 개설 강좌와 온라인 강의실 연결 |
| Course Delivery | Course Statistics | 학기 중 교과목별 현황 |
| Course Delivery | Canvas Settings | Canvas 시스템 연결 설정 |
| Attendance | Attendance Summary | 학생별·수업별 출석 기록 조회 |
| Attendance | Failed Students | 미이수 학생과 그 사유 |
| Grades & Performance | GPA Management | 학기 평점 확정 |
| Grades & Performance | GPA History | 확정된 과거 학기 평점 조회 |
| Grades & Performance | Warning Center | 학업 또는 출석 경고 대상 학생 |
| Grades & Performance | Scholarship Adjustments | 미이수로 인한 장학금 조정 검토 |
| Faculty | Lecturer List | 교원 명단과 기록 |
| Faculty | Lecturer Hours | 학기별 강의시수 |
| Faculty | Lecturer GPA | 담당 강좌의 평균 성적 |

전교 단위 보고서인 Performance Dashboard, Academic Report, Course Ranking은 별도 메뉴 그룹인 **Reports & Audits**에 있습니다.

## 자주 쓰는 순서

| 상황 | 화면 순서 |
| --- | --- |
| 새 학기 준비 | Academic Terms → Programs → Curriculum Versions → Units → Syllabus Templates |
| 강좌 개설과 운영 | Course Offering List → Class Schedule → Course Registration → Canvas Courses |
| 학업 위험 관리 | Attendance Summary → Failed Students → Warning Center → Retake Registration |
| 재시험 시행 | Thi lại → Lịch thi lại |
| 학기 마감 | Course Statistics → GPA Management → GPA History → Warning Center |
