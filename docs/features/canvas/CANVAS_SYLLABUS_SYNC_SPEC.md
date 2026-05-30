# Canvas Syllabus & Assignments Sync - Specification

## 🎯 Objective - UPDATED
Sync syllabus từ Canvas với approach đơn giản:
1. **Attendance (10%)** - Local managed từ class sessions
2. **Canvas Assignments (90%)** - Aggregate score từ Canvas API
3. Final grade = Attendance (10%) + Canvas (90%) → lưu vào `AcademicRecord`

**Key Insight:** Không sync chi tiết từng assignment, chỉ lấy tổng điểm từ Canvas

## 📊 Current State Analysis

### Local Database Structure
```
canvas_course_mappings
├── id
├── canvas_course_id
├── course_offering_id (FK) ✓ Already mapped
└── sync_status

course_offerings
├── id
├── unit_id (FK)
├── syllabus_template_id (FK) ← Target for sync
├── semester_id
└── course_code

syllabus_templates ← Canvas syllabus làm chuẩn
├── id
├── unit_id
├── title, version, description
├── total_hours, total_sessions
├── learning_outcomes (JSON)
├── grading_criteria (JSON)
└── assessment_policy

assessment_components ← Canvas assignments sync vào đây
├── id
├── syllabus_template_id (FK)
├── name, type, weight
├── due_date, available_from
├── submission_type
├── is_published
└── category
```

**Key Insight:**
- ✅ `CourseOffering` đã có `syllabus_template_id` 
- ✅ Mỗi `CourseOffering` → 1 `SyllabusTemplate`
- ✅ `SyllabusTemplate` là chuẩn cho **tất cả** course offerings của cùng 1 Unit
- ⚠️ **Problem**: Nhiều CourseOfferings có thể share 1 SyllabusTemplate

### Canvas API Endpoints
```
GET /api/v1/courses/:id/assignments
GET /api/v1/courses/:id (includes syllabus_body)
GET /api/v1/courses/:id/modules
```

## 🤔 Design Decision: Sync Strategy

### Issue: SyllabusTemplate được share giữa nhiều CourseOfferings

**Scenario:**
```
Unit: "English for Global Citizen" (unit_id=71)
  └─ SyllabusTemplate (id=10, version=v1)
       ├─ CourseOffering #1 (EGCF-Fall2025-Section1) ← Canvas Course A
       ├─ CourseOffering #2 (EGCF-Fall2025-Section2) ← Canvas Course B
       └─ CourseOffering #3 (EGCF-Spring2026-Section1)
```

**Questions:**
1. Khi sync Canvas Course A → update SyllabusTemplate #10 → **ảnh hưởng tất cả CourseOfferings**?
2. Hay tạo **SyllabusTemplate mới** cho từng Canvas course?

### ✅ Recommended Strategy: **Per-CourseOffering Syllabus**

**Option A: Clone SyllabusTemplate cho mỗi mapped course** ⭐ RECOMMENDED
```php
// When mapping Canvas course to CourseOffering:
if ($courseOffering->syllabusTemplate) {
    // Clone existing template
    $newSyllabus = $courseOffering->syllabusTemplate->replicate();
    $newSyllabus->title .= ' (Canvas Sync)';
    $newSyllabus->version = 'canvas-' . now()->format('Y-m-d');
    $newSyllabus->save();
    
    // Update course offering
    $courseOffering->syllabus_template_id = $newSyllabus->id;
    $courseOffering->save();
}

// Then sync Canvas data to the new template
```

**Pros:**
- ✅ Không ảnh hưởng syllabus của sections khác
- ✅ Mỗi Canvas course có syllabus riêng
- ✅ Dễ rollback nếu sync sai

**Cons:**
- ❌ Tạo nhiều SyllabusTemplates (data duplication)
- ❌ Khó maintain nếu cần update chung

**Option B: Direct update (NOT recommended)**
```php
// Directly update shared SyllabusTemplate
$syllabusTemplate = $courseOffering->syllabusTemplate;
// Sync Canvas data → affects ALL course offerings using this template
```

**Cons:**
- ❌ Overwrite syllabus của tất cả sections
- ❌ Mất data local nếu Canvas khác biệt

### 🎯 Final Decision: **Clone on First Sync**

### Manual Course Offering Selection Guard

Once a course offering is mapped to Canvas, its assigned syllabus template is
reserved immediately, before assignment sync runs. Course offering create/edit
forms must not offer that template for another class.

The same guard also reserves:

- syllabus templates whose title contains `Canvas`;
- syllabus templates attached to a course offering with
  `is_canvas_synced = true`.

Backend validation enforces the same rule for direct requests. When editing an
existing offering, its currently assigned reserved template remains valid so
staff can update unrelated fields without changing the Canvas link.
Duplicating an offering with a reserved template is rejected because copying it
would bypass manual selection validation.

## 🔄 Sync Flow

### Phase 0: Prepare SyllabusTemplate (New!)

**Before syncing assignments:**
1. Check if CourseOffering has `syllabusTemplate`
2. Check if syllabus is already "Canvas-synced" (có flag `is_canvas_synced`?)
3. If NOT synced yet → **Clone template** → mark as Canvas-synced
4. Proceed to sync assignments

### Phase 1: Sync Assignments from Canvas → AssessmentComponents

#### Step 1: Fetch Canvas Assignments
```
GET /api/v1/courses/{canvas_course_id}/assignments
```

**Canvas Assignment Structure:**
```json
{
  "id": 123,
  "name": "Assignment 1",
  "description": "...",
  "points_possible": 100,
  "grading_type": "points",
  "submission_types": ["online_upload"],
  "due_at": "2025-12-01T23:59:00Z",
  "unlock_at": "2025-11-01T00:00:00Z",
  "lock_at": "2025-12-02T23:59:00Z",
  "published": true,
  "assignment_group_id": 456
}
```

#### Step 2: Map to Local AssessmentComponent

**Mapping Rules:**
```
Canvas → Local
─────────────────────────────────
name → name
points_possible → weight (convert to percentage)
submission_types[0] → submission_type
due_at → due_date
unlock_at → available_from
lock_at → late_submission_deadline
published → is_published
grading_type → type (quiz/assignment/exam)
```

**Type Mapping:**
```
Canvas grading_type → Local type
────────────────────────────────
"points" → "assignment"
"percent" → "assignment"
"letter_grade" → "exam"
"gpa_scale" → "exam"
"pass_fail" → "quiz"
"not_graded" → "other"

Canvas submission_types:
"online_quiz" → "quiz"
"discussion_topic" → "online_activity"
"online_text_entry" → "assignment"
"online_upload" → "assignment"
"external_tool" → "online_activity"
```

#### Step 3: Create/Update Local Records

**Logic:**
1. Check if `CanvasCourseMapping` is mapped → get `course_offering_id`
2. Get `CourseOffering` → get `syllabus_template_id`
3. Create/Update `AssessmentComponent` records:
   - Match by `canvas_assignment_id` (need new field)
   - If exists → update
   - If not → create

### Phase 2: Sync Course Syllabus Description

#### Canvas Course Syllabus
```json
{
  "id": 449,
  "name": "EGC-F.2-HN-Fall2025-M1",
  "syllabus_body": "<html>Course description...</html>",
  "public_syllabus": true
}
```

**Update Local:**
- `SyllabusTemplate.description` ← Strip HTML from `syllabus_body`

## 🗄️ Database Changes Needed

### 1. Add Canvas Assignment Tracking

**New Table: `canvas_assignment_mappings`**
```sql
CREATE TABLE canvas_assignment_mappings (
    id BIGINT PRIMARY KEY,
    canvas_course_mapping_id BIGINT FK,
    canvas_assignment_id VARCHAR(255),
    assessment_component_id BIGINT FK,
    canvas_data JSON,
    last_synced_at TIMESTAMP,
    UNIQUE(canvas_course_mapping_id, canvas_assignment_id)
)
```

**Or add to existing `assessment_components`:**
```sql
ALTER TABLE assessment_components
ADD COLUMN canvas_assignment_id VARCHAR(255) NULL,
ADD COLUMN canvas_data JSON NULL;
```

## 🛠️ Implementation

### Services

#### `CanvasSyllabusService`
```php
public function syncAssignments(CanvasCourseMapping $mapping): array
{
    // 1. Get Canvas assignments
    $assignments = $this->apiService->getCourseAssignments($integration, $courseId);
    
    // 2. Get local syllabus template
    $syllabusTemplate = $mapping->courseOffering->syllabusTemplate;
    
    // 3. Sync each assignment
    foreach ($assignments as $assignment) {
        $this->syncAssignment($syllabusTemplate, $assignment);
    }
}

private function syncAssignment($syllabusTemplate, $canvasAssignment)
{
    // Find or create assessment component
    $component = AssessmentComponent::firstOrNew([
        'syllabus_template_id' => $syllabusTemplate->id,
        'canvas_assignment_id' => $canvasAssignment['id'],
    ]);
    
    // Map data
    $component->fill([
        'name' => $canvasAssignment['name'],
        'description' => strip_tags($canvasAssignment['description']),
        'weight' => $this->calculateWeight($canvasAssignment['points_possible']),
        'type' => $this->mapAssignmentType($canvasAssignment),
        'due_date' => $canvasAssignment['due_at'],
        'available_from' => $canvasAssignment['unlock_at'],
        'late_submission_deadline' => $canvasAssignment['lock_at'],
        'submission_type' => $this->mapSubmissionType($canvasAssignment['submission_types']),
        'is_published' => $canvasAssignment['published'],
        'canvas_data' => $canvasAssignment,
    ]);
    
    $component->save();
}
```

### Controllers

#### `CanvasSyllabusController`
```php
POST /admin/canvas/courses/{mapping}/sync-syllabus
POST /admin/canvas/courses/{mapping}/sync-assignments
```

### UI Updates

**Add to Canvas Courses page:**
```vue
<Button @click="syncSyllabus(mapping)">
  <FileText class="mr-2 h-4 w-4" />
  Sync Syllabus
</Button>

<Button @click="syncAssignments(mapping)">
  <ClipboardList class="mr-2 h-4 w-4" />
  Sync Assignments
</Button>
```

## ⚠️ Important Considerations

### 1. Weight Calculation
Canvas uses `points_possible`, local uses `weight` (percentage):
```php
// Option A: Sum all points and calculate percentage
$totalPoints = array_sum(array_column($assignments, 'points_possible'));
$weight = ($points / $totalPoints) * 100;

// Option B: Use assignment groups
// Canvas has assignment_group with group_weight
```

### 2. Existing Assessments
- What if local syllabus already has assessment components?
- **Strategy**: 
  - Add Canvas assignments as NEW components
  - Or ask user to map/merge

### 3. Sync Frequency
- Manual sync button
- Or auto-sync when mapping?

### 4. Data Conflicts
- What if Canvas assignment updated after sync?
- Need `last_synced_at` + version tracking

## 📝 Next Steps

1. **Add canvas_assignment_id to assessment_components** (migration)
2. **Implement CanvasSyllabusService**
3. **Add sync buttons to UI**
4. **Test with real Canvas course**
5. **Handle edge cases**

## 🎯 Success Criteria

- ✅ Canvas assignments synced to local AssessmentComponents
- ✅ Correct type mapping (quiz/assignment/exam)
- ✅ Weights calculated properly
- ✅ Due dates preserved
- ✅ Can update existing synced assignments
- ✅ UI shows sync status

## 🚀 Future Enhancements

- Sync assignment submissions
- Sync grades
- Two-way sync (local → Canvas)
- Bulk sync for all mapped courses
- Assignment group support
