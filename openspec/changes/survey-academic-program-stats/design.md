## Context

Hệ thống survey hiện có:
- `form_targets` (1 survey run per course/semester)
- `student_form_assignments` (1 row per student, `response_id` nullable = chưa nộp)
- `students.program_id → programs.code` (ngành học)
- `answers.answer_number` + join `questions.type = 'rating'` → dữ liệu điểm rating

Không có cross-program aggregate view. Metrics cần tính liên quan đến nhiều `form_target` cùng lúc (không phải 1 target như `GetSurveyRunAggregateAction`), nên cần Query class riêng.

## Goals / Non-Goals

**Goals:**
- 3 metrics per program: submissions, KQ TBC ≥ 4, % ≥ 4
- Filter theo semester (all hoặc 1 semester)
- Hiển thị tổng cộng (row TOTAL ở cuối)
- Sidebar entry mới bên dưới "Survey Results"
- Query nằm trong `app/Queries/Form/` (folder mới, read-only pattern)

**Non-Goals:**
- Filter theo department, status, date range (chỉ semester)
- Export CSV (không yêu cầu)
- Drilldown per-program (chỉ aggregate table)
- Charts / visualization
- Pagination (số programs nhỏ, không cần)

## Decisions

### D1: Query class trong `app/Queries/Form/`

Theo AGENTS.md, read-only reports phù hợp với Query pattern. `Actions/` dành cho side-effect logic. Tạo folder `app/Queries/Form/` — không có Queries folder cho Form domain hiện tại.

### D2: Tính metric 3 (KQ TBC ≥ 4) bằng SQL subquery

Dùng `DB::select()` với raw SQL chia 2 bước:
1. Subquery: per-response AVG rating answers
2. Outer query: COUNT WHERE avg_score >= 4, GROUP BY program_code

Không dùng Eloquent collection để tránh load toàn bộ answers vào memory (có thể rất lớn).

```sql
-- Dạng tổng quát:
SELECT
    p.code AS program_code,
    p.name AS program_name,
    COUNT(DISTINCT sfa.response_id)                        AS submissions,
    SUM(CASE WHEN per_resp.avg_score >= 4 THEN 1 ELSE 0 END) AS high_rated_count
FROM student_form_assignments sfa
JOIN students s ON sfa.student_id = s.id
JOIN programs p ON s.program_id = p.id
JOIN form_targets ft ON sfa.form_target_id = ft.id
    AND ft.scope_type = 'course'           -- only course surveys (survey môn học)
JOIN forms f ON ft.form_id = f.id AND f.type = 'survey'
LEFT JOIN (
    SELECT a.response_id, AVG(a.answer_number) AS avg_score
    FROM answers a
    JOIN questions q ON a.question_id = q.id AND q.type = 'rating'
    WHERE a.answer_number IS NOT NULL
    GROUP BY a.response_id
) per_resp ON per_resp.response_id = sfa.response_id
WHERE sfa.response_id IS NOT NULL
  AND ft.campus_id = ?            -- campus scoping
  -- AND ft.semester_id = ?       -- optional semester filter
GROUP BY p.id, p.code, p.name
ORDER BY p.name ASC
```

### D3: Campus scoping

Giống `GetSurveyRunListAction` — lọc theo `session('current_campus_id')` nếu có.

### D4: Inertia props — eager load (không defer)

Query là 1 SQL aggregate duy nhất — không nặng như load từng answers per section. Không cần defer. Props: `stats[]`, `semesters[]`, `filters`.

### D5: Vue page — simple table, không dùng `useDataTable`

Không có pagination, không có nhiều filter → không cần `useDataTable`. Dùng `useForm` của Inertia cho filter (semester select + submit). Layout giống Index.vue — `AppLayout` + header.

### D6: Permission

Dùng cùng permission `view_survey_results_aggregate` — không cần tạo permission mới.

## Risks / Trade-offs

- **Query performance**: LEFT JOIN với subquery trên `answers` có thể chậm nếu answers table lớn. Tạm chấp nhận — có thể add index sau. Scope by campus làm nhỏ dataset đáng kể.
- **NULL program**: Sinh viên không có program_id sẽ bị loại khỏi stats (INNER JOIN). Acceptable — edge case hiếm gặp.
- **Rating-only avg**: Form có thể có câu non-rating — chỉ tính avg trên rating questions là đúng intent.
