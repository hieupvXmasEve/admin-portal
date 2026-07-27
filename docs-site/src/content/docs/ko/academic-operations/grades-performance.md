---
title: Grades & Performance
description: 평점 확정, 과거 평점 조회, 학사경고 대상 관리.
source:
  - resources/js/constants/menu-sidebar.ts
  - resources/js/pages/Admin/Academic/Gpa/Index.vue
  - resources/js/pages/Admin/Academic/Gpa/History.vue
  - resources/js/pages/Academic/Warnings/Index.vue
---

**Grades & Performance**는 결과를 마감하는 단계입니다. 평점을 산출하고, 과거 결과를 조회하며, 학업 위험 학생을 찾아냅니다.

전교 단위 보고서인 Performance Dashboard, Academic Report, Course Ranking은 이곳이 아니라 별도 메뉴 그룹인 **Reports & Audits**에 있습니다.

## GPA Management — 평점 관리

**용도.** 한 학기의 학생 평균 평점을 확정합니다.

**접근 권한.** 성적 조회 권한이 있는 사용자.

**절차**

1. **Academic Operations → Grades & Performance → GPA Management**로 이동합니다.
2. **Semester Finalization**(학기 마감) 영역에서 확정할 학기를 선택합니다.
3. 수치를 확인한 뒤 확정합니다.

**유의 사항**

- 평점 확정은 중요한 기준점입니다. 장학금 심사, 학사경고, 졸업 심사가 모두 이 결과를 근거로 합니다.
- 성적 입력이 끝나기 전에 확정하면 평점이 잘못 산출됩니다. 확정 전에 성적 입력 부서에 완료 여부를 확인하십시오.

**다음 화면.** GPA History, Warning Center.

## GPA History — 평점 이력

**용도.** 이전 학기에 확정된 평점을 조회합니다.

**접근 권한.** 성적 조회 권한이 있는 사용자.

**절차**

1. **Academic Operations → Grades & Performance → GPA History**로 이동합니다.
2. **Search student...** 창에서 학생을 찾습니다.
3. **Semester**(학기), **Program**(학위과정), **Academic Standing**(학사 상태)으로 더 좁힙니다.

**유의 사항.** 성적 이의 신청에 답하거나 과거 학기 결과를 확인할 때 사용하십시오.

**다음 화면.** Warning Center, 학생 기록.

## Warning Center — 경고 관리

**용도.** 현재 경고 대상인 학생을 확인합니다. 두 종류가 있습니다.

- **Academic Standing Warnings** — 학업 성적에 따른 경고.
- **Attendance Warnings** — 결석에 따른 경고.

**접근 권한.** 출석 조회 권한과 성적 조회 권한을 모두 가진 사용자.

**절차**

1. **Academic Operations → Grades & Performance → Warning Center**로 이동합니다.
2. 두 영역을 확인하고 조치가 필요한 학생을 파악합니다.
3. 학생 기록으로 이동해 처리 내용을 기록합니다.

**유의 사항**

- 경고는 성적과 출석 자료에 따라 갱신되므로 평점 확정 후마다 다시 확인하십시오.
- 학기 말까지 미루지 말고 정기적으로 점검하십시오.

**다음 화면.** Attendance Summary, Student Services.

## 평점 확정 후 점검

```text
GPA Management
  -> GPA History
  -> Warning Center
```

수치가 이상한 경우:

```text
GPA History
  -> 해당 학생의 학사 기록
  -> Course Statistics
```
