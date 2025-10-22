# Canvas Sync Approach - Final Design

## 🎯 Philosophy

**Canvas is the single source of truth for assessment structure and grades.**

Local system:
- Syncs exact structure from Canvas (Assignment Groups + Assignments)
- Stores grades from Canvas
- Provides custom final grade calculation logic (admin configurable)

---

## 📊 Data Flow

```
Canvas Assignment Groups (with weights)
    ↓ Sync via API
AssessmentComponents (1:1 mapping)
    ├─ name: from Canvas group name
    ├─ weight: from Canvas group_weight
    ├─ canvas_assignment_group_id: Canvas group ID
    └─ is_canvas_synced: true

Canvas Assignments (within groups)
    ↓ Sync via API
AssessmentComponentDetails (1:1 mapping)
    ├─ name: from Canvas assignment name
    ├─ max_points: from Canvas points_possible
    ├─ canvas_assignment_id: Canvas assignment ID
    └─ assessment_component_id: linked to parent component

Canvas Student Grades
    ↓ Sync via API
AssessmentComponentDetailScores
    ├─ points_earned: from Canvas score
    ├─ percentage_score: calculated
    └─ assessment_component_detail_id: linked to assignment
```

---

## 🔄 Sync Process

### 1. Sync Assignment Groups & Assignments
**Endpoint:** `/admin/canvas/courses/{mapping}/sync-assignments`

**What it does:**
1. Fetches Canvas Assignment Groups (via `GET /api/v1/courses/:id/assignment_groups?include[]=assignments`)
2. For each group:
   - Creates/updates AssessmentComponent
   - Maps Canvas group_weight to component weight
   - Stores canvas_assignment_group_id for tracking
3. For each assignment in group:
   - Creates/updates AssessmentComponentDetail
   - Links to parent component
   - Stores canvas_assignment_id for tracking

**Example Result:**
```
Canvas Course: "CS101 - Fall 2024"
├─ Assignment Group: "Quizzes" (20%)
│  ├─ Quiz 1 (10 points)
│  ├─ Quiz 2 (10 points)
│  └─ Quiz 3 (10 points)
├─ Assignment Group: "Assignments" (30%)
│  ├─ HW1 (20 points)
│  └─ Project (60 points)
└─ Assignment Group: "Exams" (50%)
   ├─ Midterm (100 points)
   └─ Final (100 points)

↓ Syncs to ↓

Local SyllabusTemplate
├─ AssessmentComponent: "Quizzes" (20%)
│  ├─ Detail: "Quiz 1" (10 pts)
│  ├─ Detail: "Quiz 2" (10 pts)
│  └─ Detail: "Quiz 3" (10 pts)
├─ AssessmentComponent: "Assignments" (30%)
│  ├─ Detail: "HW1" (20 pts)
│  └─ Detail: "Project" (60 pts)
└─ AssessmentComponent: "Exams" (50%)
   ├─ Detail: "Midterm" (100 pts)
   └─ Detail: "Final" (100 pts)
```

---

### 2. Sync Grades
**Endpoint:** `/admin/canvas/courses/{mapping}/sync-grades`

**What it does:**
1. Gets all students in course
2. For each assignment:
   - Fetches student submission
   - Gets score from Canvas
   - Creates/updates AssessmentComponentDetailScore

**Example:**
```
Student: John Doe
├─ Quiz 1: 8/10 (80%)
├─ Quiz 2: 9/10 (90%)
├─ HW1: 18/20 (90%)
└─ Midterm: 85/100 (85%)

↓ Stored in ↓

AssessmentComponentDetailScores
├─ {student_id: 123, detail_id: 1, points: 8, percentage: 80}
├─ {student_id: 123, detail_id: 2, points: 9, percentage: 90}
├─ {student_id: 123, detail_id: 5, points: 18, percentage: 90}
└─ {student_id: 123, detail_id: 7, points: 85, percentage: 85}
```

---

## 🎓 Final Grade Calculation

**Admin has full control over final grade formula.**

### Default Canvas-Only Approach:
```php
// Canvas automatically calculates weighted total
$canvasComponents = AssessmentComponent::where('is_canvas_synced', true)->get();

$finalGrade = 0;
foreach ($canvasComponents as $component) {
    $componentScore = $component->calculateStudentScore($studentId);
    $finalGrade += $componentScore * ($component->weight / 100);
}

// Result: 0-100 scale
```

### Custom Approach (Example with Attendance):
```php
// Admin can add local components (e.g., Attendance)
$attendance = AssessmentComponent::where('type', 'attendance')->first();
$attendanceScore = $attendance->calculateStudentScore($studentId); // e.g., 85%

// Canvas components scaled to 90%
$canvasComponents = AssessmentComponent::where('is_canvas_synced', true)->get();
$canvasTotal = 0;
foreach ($canvasComponents as $component) {
    $componentScore = $component->calculateStudentScore($studentId);
    // Scale weight to 90% total
    $scaledWeight = ($component->weight / 100) * 0.9;
    $canvasTotal += $componentScore * $scaledWeight;
}

// Final: Attendance (10%) + Canvas (90%)
$finalGrade = ($attendanceScore * 0.1) + $canvasTotal;
```

---

## ✅ Benefits

1. **Pure Canvas Structure**
   - No synthetic "Canvas Assignments" component
   - Exact match with Canvas gradebook
   - Students see familiar structure

2. **Simplicity**
   - One sync button (Sync Assignments)
   - No clone/overwrite complexity
   - Clear data lineage

3. **Flexibility**
   - Admin can add local components (Attendance, etc.)
   - Custom final grade formulas
   - Canvas weights can be adjusted locally if needed

4. **Transparency**
   - `canvas_assignment_group_id` tracks source
   - `canvas_synced_at` shows freshness
   - Easy to identify Canvas vs local components

---

## 🔧 Admin Workflow

### Initial Setup:
1. Map Canvas course to local CourseOffering
2. Click "Sync Assignments"
   - Pulls all Assignment Groups + Assignments
   - Creates matching AssessmentComponents structure
3. Click "Sync Grades"
   - Pulls all student scores
   - Populates grade records

### Ongoing:
- Sync Grades: Daily/weekly (manual or scheduled)
- Sync Assignments: When Canvas structure changes
- Final Grade: Calculated on-demand using current formula

### Optional:
- Add local components (e.g., Attendance)
- Adjust component weights if needed
- Configure custom grade calculation logic

---

## 📝 Notes

### Attendance Component
- **Not in Canvas**: Attendance is managed locally via class_sessions
- **Optional**: Admin can create Attendance component manually
- **Calculation**: Based on local attendance records, not Canvas

### Weight Adjustments
- Canvas weights are synced as-is
- Admin can manually adjust weights locally if needed
- Local adjustments don't sync back to Canvas

### Sync Frequency
- **Assignments**: Sync when structure changes (rare)
- **Grades**: Sync regularly (daily/weekly recommended)
- **Students**: Auto-sync when grades sync

---

## 🎯 Implementation Status

- ✅ Migration: canvas_assignment_group_id, canvas_group_weight added
- ✅ API: getAssignmentGroups() with assignments included
- ✅ Service: CanvasAssignmentSyncService syncs groups → components → assignments
- ✅ Service: CanvasGradeSyncService syncs student scores
- ✅ Controller: Endpoints for assignment and grade sync
- ✅ UI: Sync buttons with progress dialogs
- ✅ Docs: This document

---

## 📚 Related Docs

- [Canvas Grading Explained](./CANVAS_GRADING_EXPLAINED.md) - How Canvas calculates grades
- [Canvas Integration](./CANVAS_INTEGRATION.md) - OAuth setup and API access
- [Canvas Quick Start](./CANVAS_QUICK_START.md) - Getting started guide
