# Canvas Grading System - Giải Thích Chi Tiết

## 🎯 TL;DR
**Điểm trong Canvas KHÔNG dựa vào Syllabus!**  
Điểm dựa vào **Assignment Groups** (weighted categories).

---

## 📚 Syllabus vs Assignment Groups

### 1. SYLLABUS (Document mô tả)
- **Chức năng**: Mô tả course policies, schedule, expectations
- **Không tính điểm**: Chỉ là text document
- **Auto-generated**: Có thể show list assignments từ Assignment Groups

### 2. ASSIGNMENT GROUPS (Grading Structure)
- **Chức năng**: ĐÂY mới là nơi tính điểm!
- **Structure**: Categories với weights (e.g., Exams 50%, Quizzes 20%)
- **Calculation**: Canvas tự động tính weighted final grade

---

## 📊 Student View - Xem Điểm Như Thế Nào?

Students khi vào **Grades** page sẽ thấy:

```
┌─────────────────────────────────────────────┐
│ Final Grade: 85.85%                         │
└─────────────────────────────────────────────┘

Assignment Groups:
┌─ Quizzes (20% of grade)
│  ├─ Quiz 1: 8/10 (80%)
│  ├─ Quiz 2: 9/10 (90%)  
│  └─ Quiz 3: 7/10 (70%)
│  → Group Total: 80% × 20% = 16%
│
├─ Assignments (30% of grade)
│  ├─ HW1: 18/20 (90%)
│  ├─ HW2: 19/20 (95%)
│  └─ Project: 50/60 (83.3%)
│  → Group Total: 87% × 30% = 26.1%
│
└─ Exams (50% of grade)
   ├─ Midterm: 85/100 (85%)
   └─ Final: 90/100 (90%)
   → Group Total: 87.5% × 50% = 43.75%

Final = 16% + 26.1% + 43.75% = 85.85%
```

---

## 🔍 Chi Tiết Từng Thành Phần

### Student Click vào "Quizzes" sẽ thấy:
```
Quizzes (20% of final grade)
─────────────────────────────
Quiz 1    [Week 1]    8/10   (80%)   ✓ Graded
Quiz 2    [Week 2]    9/10   (90%)   ✓ Graded
Quiz 3    [Week 3]    7/10   (70%)   ✓ Graded
─────────────────────────────
Group Total: 24/30 = 80%
Weighted Score: 80% × 20% = 16% of final grade
```

### "Assignments are weighted by group" nghĩa là gì?
- Canvas tính điểm theo **groups**, không phải từng assignment riêng lẻ
- Mỗi group có một **weight %**
- Final grade = tổng của (group score × group weight)

---

## 🔗 Mối Quan Hệ Với Syllabus

### Quy Trình Thực Tế:

1. **Instructor viết Syllabus (document)**
   ```
   Grading:
   - Quizzes: 20%
   - Assignments: 30%  
   - Exams: 50%
   ```

2. **Instructor setup Assignment Groups trong Canvas**
   ```
   Canvas Settings → Assignments → Create Groups:
   ✓ Quizzes (weight: 20%)
   ✓ Assignments (weight: 30%)
   ✓ Exams (weight: 50%)
   ```

3. **Instructor tạo assignments thuộc groups**
   ```
   Quiz 1 → thuộc "Quizzes" group
   HW1 → thuộc "Assignments" group
   Midterm → thuộc "Exams" group
   ```

4. **Canvas tự động tính điểm**
   - Không cần manual calculation
   - Students thấy real-time updates
   - Instructor chỉ cần nhập scores

---

## 🎯 Canvas API - Lấy Điểm Thành Phần

### API Endpoints:

#### 1. Lấy tất cả assignments với scores
```http
GET /api/v1/courses/:course_id/assignments
→ Returns: List of assignments with points_possible

GET /api/v1/courses/:course_id/assignments/:id/submissions
→ Returns: Student scores for specific assignment
```

#### 2. Lấy Assignment Groups với breakdown
```http
GET /api/v1/courses/:course_id/assignment_groups?include[]=assignments
→ Returns: Groups with weights + all assignments
```

#### 3. Lấy Student Grades với Group Breakdown
```http
GET /api/v1/courses/:course_id/enrollments/:user_id?include[]=current_grading_period_scores
→ Returns: 
{
  "grades": {
    "final_score": 85.85,
    "current_score": 85.85
  },
  "assignment_groups": [
    {
      "id": 1,
      "name": "Quizzes",
      "group_weight": 20,
      "current_score": 80.0,
      "final_score": 80.0
    },
    {
      "id": 2,
      "name": "Assignments", 
      "group_weight": 30,
      "current_score": 87.0,
      "final_score": 87.0
    }
  ]
}
```

---

## 💡 Implementation Cho Local System

### Approach Hiện Tại (ĐÚNG):

```
Local Syllabus Structure:
┌─ SyllabusTemplate
│  ├─ AssessmentComponent: "Attendance" (10%)
│  │  └─ AssessmentComponentDetail: "Class Attendance"
│  │     └─ Scores: Tính từ class_sessions
│  │
│  └─ AssessmentComponent: "Canvas Assignments" (90%)
│     ├─ AssessmentComponentDetail: "Quiz 1" (canvas_assignment_id: 123)
│     ├─ AssessmentComponentDetail: "HW1" (canvas_assignment_id: 124)
│     └─ AssessmentComponentDetail: "Midterm" (canvas_assignment_id: 125)
│        └─ Scores: Sync từ Canvas API
```

### Sync Strategy:

1. **Sync Assignments** (1 lần)
   ```php
   // Get all Canvas assignments
   $assignments = $canvasApi->getCourseAssignments($courseId);
   
   // Create local AssessmentComponentDetails
   foreach ($assignments as $assignment) {
       AssessmentComponentDetail::create([
           'name' => $assignment['name'],
           'canvas_assignment_id' => $assignment['id'],
           'max_points' => $assignment['points_possible']
       ]);
   }
   ```

2. **Sync Grades** (định kỳ hoặc on-demand)
   ```php
   // Get student scores for each assignment
   $submissions = $canvasApi->getSubmissions($courseId, $assignmentId);
   
   foreach ($submissions as $submission) {
       AssessmentComponentDetailScore::updateOrCreate([
           'assessment_component_detail_id' => $detailId,
           'student_id' => $studentId
       ], [
           'points_earned' => $submission['score'],
           'percentage_score' => ($submission['score'] / $maxPoints) * 100
       ]);
   }
   ```

3. **Calculate Final Grade**
   ```php
   // Attendance (10%)
   $attendanceScore = calculateAttendanceScore($student); // 0-10
   
   // Canvas Assignments (90%)
   $canvasTotal = 0;
   $canvasMaxPoints = 0;
   
   foreach ($canvasAssignments as $assignment) {
       $canvasTotal += $assignment->score;
       $canvasMaxPoints += $assignment->max_points;
   }
   
   $canvasScore = ($canvasTotal / $canvasMaxPoints) * 90; // 0-90
   
   // Final
   $finalGrade = $attendanceScore + $canvasScore; // 0-100
   ```

---

## ✅ Kết Luận

1. **Syllabus ≠ Grading Structure**
   - Syllabus: Document text
   - Assignment Groups: Actual grading mechanism

2. **Students xem điểm thành phần**
   - Mỗi assignment có score riêng
   - Grouped by categories (Assignment Groups)
   - Weighted tự động bởi Canvas

3. **Canvas API có đủ data**
   - Individual assignment scores ✓
   - Assignment group breakdowns ✓
   - Final calculated grades ✓

4. **Local implementation hợp lý**
   - Sync assignments → assessment_component_details ✓
   - Sync scores → assessment_component_detail_scores ✓
   - Show chi tiết từng assignment cho students ✓
   - Calculate final với Attendance + Canvas ✓
