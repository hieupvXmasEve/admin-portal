---
title: 화면 흐름도
description: 학사 화면들이 실제 업무 순서에 따라 어떻게 이어지는지 정리한 내용.
source:
  - resources/js/constants/menu-sidebar.ts
---

이 문서는 담당자가 화면 사이를 이동하는 경로를 설명합니다. 할 일은 알지만 어디서 시작할지 모를 때 사용하십시오.

## 전체 흐름

```text
Curriculum Setup
  -> Course Delivery
  -> Attendance & Completion
  -> Grades & Performance
  -> 학적이나 등록금 처리가 필요하면 Student Services / Finance Office
```

## 학사 자료 준비

| 단계 | 화면 | 기대 결과 |
| --- | --- | --- |
| 1 | Academic Terms | 강좌 개설과 평점 확정에 쓸 학기가 생성됨 |
| 2 | Programs | 교육과정을 붙일 학위과정이 존재함 |
| 3 | Curriculum Versions | 적용 중인 교육과정 판본이 있음 |
| 4 | Units | 교과목에 학점과 선수과목 정보가 있음 |
| 5 | Syllabus Templates | 개설할 강좌에 쓸 강의계획서가 준비됨 |

여기까지 마치면 **Course Offering List**로 이동해 강좌를 개설합니다.

## 강좌 운영

| 단계 | 화면 | 기대 결과 |
| --- | --- | --- |
| 1 | Course Offering List | 학기별 강좌가 개설됨 |
| 2 | Class Schedule | 수업에 날짜, 시간, 강의실이 지정됨 |
| 3 | Course Registration | 학생이 강좌에 등록됨 |
| 4 | Canvas Courses | 필요한 경우 온라인 강의실과 연결됨 |
| 5 | Attendance Summary | 학기 중 출석을 확인할 수 있음 |

미이수 학생이 발생하면 **Failed Students**를 거쳐 **Retake Registration**으로 이동합니다.

## 위험 관리

| 신호 | 확인 화면 | 처리 화면 |
| --- | --- | --- |
| 결석이 잦음 | Attendance Summary | Warning Center |
| 강좌 성적이 저조함 | Course Statistics | Failed Students |
| 평점이 기준 미만 | GPA History | Warning Center |
| 재수강이 필요함 | Failed Students | Retake Registration |
| 재시험이 필요함 | Thi lại | Lịch thi lại |

## 학기 마감

| 단계 | 화면 | 기대 결과 |
| --- | --- | --- |
| 1 | Course Statistics | 강좌 자료와 성적을 확인함 |
| 2 | GPA Management | 학기 평점이 확정됨 |
| 3 | GPA History | 확정된 결과를 조회할 수 있음 |
| 4 | Warning Center | 조치가 필요한 학생을 가려냄 |

전교 단위 보고서는 **Reports & Audits** 메뉴 그룹에 있습니다.

## 학사 영역을 벗어나야 할 때

| 출발 화면 | 이동 대상 | 이유 |
| --- | --- | --- |
| Course Registration | Student Services | 등록 전 학생 기록 확인 |
| Failed Students | Student Services | 한 학생의 성적·출석·평점 상세 확인 |
| Retake Registration | Finance Office | 재수강 등록금 처리 |
| Lịch thi lại | Campus Operations | 시험실 예약 및 중복 방지 |
| Warning Center | Student Services | 학생 처리 결과 기록 |
| Canvas Courses | Faculty & Teaching | 담당 교원 대조 |

## 질문으로 찾는 시작 화면

| 질문 | 이동할 화면 |
| --- | --- |
| "새 학기 강좌를 개설해야 합니다" | Course Offering List |
| "이 학생이 수강신청을 했습니까?" | Course Registration |
| "결석이 많은 학생은 누구입니까?" | Attendance Summary |
| "미이수 학생은 누구입니까?" | Failed Students |
| "이번 학기 평점을 확정해야 합니다" | GPA Management |
| "학사경고 대상은 누구입니까?" | Warning Center |
| "재시험 시간대를 편성해야 합니다" | Lịch thi lại |
