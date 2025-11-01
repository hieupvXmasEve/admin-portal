# Student Grades API Documentation

## Endpoint

```
GET /api/v1/student/grades
```

**Authentication:** Required (Bearer Token)

**Description:** Lấy thông tin điểm và roadmap học tập của sinh viên, bao gồm:
- Điểm các môn học theo từng kỳ (curriculum units)
- Module scores (cho Finland campus)
- EGC units (môn ngoại khóa)
- Thống kê tổng quan

---

## TypeScript Types

### Main Response

```typescript
interface GradesResponse {
  success: boolean;
  timestamp: string;
  data: GradesData;
  message: string;
}

interface GradesData {
  grades_by_semester: SemesterGrades[];
  egc_units: EGCUnit[];
  overall_summary: OverallSummary;
  generated_at: string;
}
```

### Semester Grades

```typescript
interface SemesterGrades {
  semester: SemesterInfo;
  curriculum_units: CurriculumUnit[];
  modules: ModuleScore[];
  semester_summary: SemesterSummary;
}

interface SemesterInfo {
  id: number;
  name: string;
  code: string;
  semester_number: number;
}

interface CurriculumUnit {
  curriculum_unit_id: number;
  unit_id: number;
  unit_code: string;
  unit_name: string;
  unit_scope: string;
  year_level: number;
  semester_number: number;
  credit_hours: string; // Decimal as string
  final_percentage: string | null;
  final_grade: string | null;
  numeric_grade: string | null;
  grade_points: string | null;
  completion_status: CompletionStatus;
  completion_date: string | null;
  is_passed: boolean;
  override_pass: boolean;
  override_reason: string | null;
  course_offering_id: number | null; // Only available if enrolled
  lecturer: string | null;
}

type CompletionStatus = 'not_enrolled' | 'in_progress' | 'completed' | 'failed';

interface SemesterSummary {
  total_units: number;
  completed_units: number;
  total_credits: number;
  earned_credits: number;
  semester_gpa: number;
}
```

### Module Scores

```typescript
interface ModuleScore {
  module_id: number;
  module_code: string;
  module_name: string;
  module_grade: number | null; // null if no graded units completed
  total_credits: string; // Decimal as string
  year_level: number;
  semester_number: number;
  is_required: boolean;
  group_name: string | null;
  sub_units: SubUnit[];
  completion: ModuleCompletion;
  status: ModuleStatus;
  grading_type: GradingType;
}

interface SubUnit {
  unit_id: number;
  code: string;
  name: string;
  credits: string; // Decimal as string
  final_percentage: string | null;
  final_grade: string | null;
  numeric_grade: string | null;
  status: CompletionStatus;
  completion_date: string | null;
  is_passed: boolean;
  override_pass: boolean;
  override_reason: string | null;
  course_offering_id: number | null; // Only available if enrolled
  grading_type: GradingType;
  included_in_module_average: boolean;
  weight: number | null;
  order: number;
  semester_taken: string | null;
}

interface ModuleCompletion {
  completed_units: number;
  total_units: number;
  percentage: number;
}

type ModuleStatus = 'not_started' | 'in_progress' | 'passed' | 'failed';
type GradingType = 'grade' | 'pass_fail';
```

### EGC Units

```typescript
interface EGCUnit {
  id: number;
  unit_id: number;
  unit_code: string;
  unit_name: string;
  credit_hours: string; // Decimal as string
  final_percentage: string | null;
  final_grade: string | null;
  numeric_grade: string | null;
  completion_status: CompletionStatus;
  completion_date: string | null;
  is_passed: boolean;
  override_pass: boolean;
  override_reason: string | null;
  course_offering_id: number | null; // Only available if enrolled
  semester: string | null;
  lecturer: string | null;
}
```

### Overall Summary

```typescript
interface OverallSummary {
  units: UnitsSummary;       // Curriculum units only (EGC excluded)
  modules: ModulesSummary;
  credits: CreditsSummary;   // Curriculum credits only (EGC excluded)
}

interface UnitsSummary {
  total: number;          // Only curriculum units (EGC not counted)
  not_enrolled: number;
  in_progress: number;
  passed: number;
  failed: number;
}

interface ModulesSummary {
  total: number;
  not_started: number;
  in_progress: number;
  passed: number;
  failed: number;
}

interface CreditsSummary {
  total_attempted: number;
  total_earned: number;
}
```

---

## Request Example

```typescript
const response = await fetch('/api/v1/student/grades', {
  method: 'GET',
  headers: {
    'Accept': 'application/json',
    'Authorization': `Bearer ${token}`,
  },
});

const data: GradesResponse = await response.json();
```

---

## Response Example

```json
{
  "success": true,
  "timestamp": "2025-11-01T20:23:09.559698Z",
  "data": {
    "grades_by_semester": [
      {
        "semester": {
          "id": 1,
          "name": "Fall 2025",
          "code": "FALL2025",
          "semester_number": 1
        },
        "curriculum_units": [
          {
            "curriculum_unit_id": 14,
            "unit_id": 5,
            "unit_code": "GCS2",
            "unit_name": "Global Citizen Skills 2",
            "unit_scope": "common",
            "year_level": 1,
            "semester_number": 1,
            "credit_hours": "5.00",
            "final_percentage": null,
            "final_grade": null,
            "numeric_grade": null,
            "grade_points": null,
            "completion_status": "not_enrolled",
            "completion_date": null,
            "is_passed": false,
            "override_pass": false,
            "override_reason": null,
            "lecturer": null
          }
        ],
        "modules": [
          {
            "module_id": 1,
            "module_code": "SW1",
            "module_name": "Software 1",
            "module_grade": null,
            "total_credits": "12.50",
            "year_level": 1,
            "semester_number": 1,
            "is_required": true,
            "group_name": "core",
            "sub_units": [
              {
                "unit_id": 14,
                "code": "MATH",
                "name": "Math",
                "credits": "2.50",
                "final_percentage": null,
                "final_grade": null,
                "numeric_grade": null,
                "status": "not_enrolled",
                "completion_date": null,
                "is_passed": false,
                "override_pass": false,
                "override_reason": null,
                "grading_type": "grade",
                "included_in_module_average": true,
                "weight": null,
                "order": 0,
                "semester_taken": null
              }
            ],
            "completion": {
              "completed_units": 0,
              "total_units": 5,
              "percentage": 0
            },
            "status": "not_started",
            "grading_type": "grade"
          }
        ],
        "semester_summary": {
          "total_units": 0,
          "completed_units": 0,
          "total_credits": 0,
          "earned_credits": 0,
          "semester_gpa": 0
        }
      }
    ],
    "egc_units": [
      {
        "id": 18,
        "unit_id": 6,
        "unit_code": "EGCF",
        "unit_name": "EGC - Foundation",
        "credit_hours": "0.00",
        "final_percentage": "76.15",
        "final_grade": "B+",
        "numeric_grade": null,
        "completion_status": "completed",
        "completion_date": null,
        "is_passed": false,
        "override_pass": false,
        "override_reason": null,
        "semester": "Fall 2025",
        "lecturer": null
      }
    ],
    "overall_summary": {
      "units": {
        "total": 5,
        "not_enrolled": 4,
        "in_progress": 0,
        "passed": 0,
        "failed": 0
      },
      "modules": {
        "total": 1,
        "not_started": 1,
        "in_progress": 0,
        "passed": 0,
        "failed": 0
      },
      "credits": {
        "total_attempted": 40,
        "total_earned": 0
      }
    },
    "generated_at": "2025-11-01T20:23:09.559831Z"
  },
  "message": "Grades retrieved successfully"
}
```

---

## Null Safety Guidelines

### ⚠️ IMPORTANT: Null Handling

Nhiều fields có thể là `null` khi sinh viên chưa học môn đó. **PHẢI** check null trước khi sử dụng:

#### ✅ Correct Usage

```typescript
// 1. Optional chaining cho nested objects
const grade = unit.final_percentage ?? 'N/A';
const lecturer = unit.lecturer ?? 'Not assigned';

// 2. Check trước khi display
if (unit.final_percentage !== null) {
  displayGrade(parseFloat(unit.final_percentage));
}

// 3. Type guard cho modules (có thể là empty array)
if (semester.modules.length > 0) {
  semester.modules.forEach(module => {
    // Process module
  });
}

// 4. Check module grade
const moduleGrade = module.module_grade !== null 
  ? module.module_grade.toFixed(2) 
  : 'Not graded yet';

// 5. Parse decimal strings safely
const credits = parseFloat(unit.credit_hours);
if (!isNaN(credits)) {
  totalCredits += credits;
}

// 6. Override reason check
if (unit.override_pass && unit.override_reason) {
  showOverrideReason(unit.override_reason);
}
```

#### ❌ Incorrect Usage

```typescript
// WRONG: Will crash if null
const grade = parseFloat(unit.final_percentage); // ❌

// WRONG: Assuming module exists
const firstModule = semester.modules[0]; // ❌ Might be undefined

// WRONG: Not handling null
const formatted = unit.completion_date.toLocaleDateString(); // ❌

// WRONG: Assuming always has value
if (unit.override_reason.length > 0) { } // ❌
```

---

## Common Use Cases

### 1. Display Student Roadmap

```typescript
function displayRoadmap(data: GradesData) {
  data.grades_by_semester.forEach(semester => {
    console.log(`Semester ${semester.semester.semester_number}: ${semester.semester.name}`);
    
    // Display curriculum units
    semester.curriculum_units.forEach(unit => {
      const grade = unit.final_percentage 
        ? `${parseFloat(unit.final_percentage).toFixed(1)}%`
        : 'Not enrolled';
      
      console.log(`  ${unit.unit_code}: ${grade}`);
      
      if (unit.override_pass && unit.override_reason) {
        console.log(`    Override: ${unit.override_reason}`);
      }
    });
    
    // Display modules (if any)
    if (semester.modules.length > 0) {
      semester.modules.forEach(module => {
        const grade = module.module_grade?.toFixed(2) ?? 'N/A';
        console.log(`  Module ${module.module_code}: ${grade}`);
        
        module.sub_units.forEach(subUnit => {
          const subGrade = subUnit.final_percentage ?? 'Not started';
          console.log(`    - ${subUnit.code}: ${subGrade}`);
        });
      });
    }
  });
}
```

### 2. Calculate Progress

```typescript
function calculateProgress(summary: OverallSummary): number {
  const { units } = summary;
  if (units.total === 0) return 0;
  
  return Math.round((units.passed / units.total) * 100);
}
```

### 3. Filter Units by Status

```typescript
function getUnitsByStatus(
  data: GradesData, 
  status: CompletionStatus
): CurriculumUnit[] {
  return data.grades_by_semester.flatMap(semester => 
    semester.curriculum_units.filter(unit => 
      unit.completion_status === status
    )
  );
}

// Usage
const notEnrolled = getUnitsByStatus(data, 'not_enrolled');
const inProgress = getUnitsByStatus(data, 'in_progress');
const completed = getUnitsByStatus(data, 'completed');
```

### 4. Get Module Progress

```typescript
function getModuleProgress(module: ModuleScore): {
  completed: number;
  total: number;
  percentage: number;
  status: string;
} {
  return {
    completed: module.completion.completed_units,
    total: module.completion.total_units,
    percentage: module.completion.percentage,
    status: module.status,
  };
}
```

### 5. Check if Student Has Modules (Finland Campus)

```typescript
function hasModularSystem(data: GradesData): boolean {
  return data.grades_by_semester.some(
    semester => semester.modules.length > 0
  );
}
```

### 6. Display EGC Units

```typescript
function displayEGCUnits(egcUnits: EGCUnit[]) {
  if (egcUnits.length === 0) {
    console.log('No EGC units');
    return;
  }
  
  egcUnits.forEach(unit => {
    const grade = unit.final_percentage 
      ? `${parseFloat(unit.final_percentage).toFixed(1)}%`
      : 'N/A';
    
    const passStatus = unit.is_passed ? '✓ Passed' : '✗ Not passed';
    
    console.log(`${unit.unit_code} - ${unit.unit_name}: ${grade} (${passStatus})`);
    
    if (unit.override_pass) {
      console.log(`  Override: ${unit.override_reason ?? 'No reason provided'}`);
    }
  });
}
```

---

## Validation Helpers

```typescript
// Type guard for checking if grade is available
function hasGrade(unit: CurriculumUnit | SubUnit): boolean {
  return unit.final_percentage !== null;
}

// Type guard for modules
function hasModules(semester: SemesterGrades): boolean {
  return semester.modules.length > 0;
}

// Safe grade parsing
function parseGrade(gradeStr: string | null): number | null {
  if (gradeStr === null) return null;
  const grade = parseFloat(gradeStr);
  return isNaN(grade) ? null : grade;
}

// Safe credits parsing
function parseCredits(creditsStr: string): number {
  const credits = parseFloat(creditsStr);
  return isNaN(credits) ? 0 : credits;
}
```

---

## Error Handling

```typescript
async function fetchGrades(token: string): Promise<GradesData | null> {
  try {
    const response = await fetch('/api/v1/student/grades', {
      headers: {
        'Authorization': `Bearer ${token}`,
        'Accept': 'application/json',
      },
    });
    
    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`);
    }
    
    const result: GradesResponse = await response.json();
    
    if (!result.success) {
      throw new Error(result.message || 'Failed to fetch grades');
    }
    
    return result.data;
  } catch (error) {
    console.error('Failed to fetch grades:', error);
    return null;
  }
}
```

---

## Notes

1. **Decimal as String**: `credit_hours`, `total_credits`, `final_percentage` trả về dạng string để tránh mất precision. Phải parse sang number trước khi tính toán.

2. **EGC Units Excluded from Statistics**: 
   - EGC units chỉ là điều kiện tiên quyết (prerequisite)
   - **KHÔNG** được đếm vào `overall_summary.units`
   - **KHÔNG** được đếm vào `overall_summary.credits`
   - Vẫn hiển thị trong `egc_units` array để tracking

3. **Empty Arrays vs Null**: 
   - `grades_by_semester` luôn là array (có thể empty nếu chưa có curriculum)
   - `egc_units` luôn là array (empty nếu không có EGC)
   - `modules` trong mỗi semester luôn là array (empty nếu không có modules)

4. **Campus Differences**:
   - **Vietnam**: `modules` = `[]` (empty array)
   - **Finland**: `modules` có data

5. **Override Pass**: Khi `override_pass = true`, sinh viên được pass dù có điểm thấp. Check `override_reason` để hiển thị lý do.

6. **Grading Type**: 
   - `grade`: Có điểm số (0-100)
   - `pass_fail`: Chỉ có pass/fail, không tính vào module average

7. **Module Grade Calculation**:
   - Chỉ tính từ sub-units có `grading_type = 'grade'`
   - Sub-units với `pass_fail` không tính vào average
   - `module_grade = null` nếu chưa có sub-unit nào completed
