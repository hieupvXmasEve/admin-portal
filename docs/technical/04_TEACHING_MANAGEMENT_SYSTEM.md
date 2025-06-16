# Giai Đoạn 4: Quản Lý Giảng Dạy

## 📋 Tổng Quan

Giai đoạn 4 xây dựng hệ thống quản lý giảng dạy toàn diện:
- **Mở Môn Học**: Tạo các lớp môn học cho từng kỳ
- **Phân Công Giảng Viên**: Gán giảng viên cho các môn học
- **Lập Lịch Dạy**: Tạo thời khóa biểu chi tiết
- **Quản Lý Lớp Học**: Theo dõi sĩ số và danh sách sinh viên
- **Đánh Giá & Chấm Điểm**: Hệ thống đánh giá học tập

## 🎯 Mục Tiêu Giai Đoạn 4

🔄 **Đang Phát Triển**:
- Course offering management cho từng semester
- Faculty assignment với workload balancing
- Intelligent scheduling với conflict detection
- Class roster management
- Grade management system

## 🏗️ Kiến Trúc Hệ Thống

### Backend Architecture
```
app/
├── Models/
│   ├── CourseOffering.php           # Môn học được mở
│   ├── ClassSchedule.php            # Lịch học
│   ├── TeachingAssignment.php       # Phân công giảng dạy
│   ├── ClassRoster.php              # Danh sách lớp
│   ├── Attendance.php               # Điểm danh
│   ├── Assessment.php               # Bài tập/kiểm tra
│   ├── Grade.php                    # Điểm số
│   └── CourseEvaluation.php         # Đánh giá môn học
├── Services/
│   ├── CourseOfferingService.php    # Logic mở môn
│   ├── SchedulingService.php        # Lập lịch thông minh
│   ├── TeachingAssignmentService.php # Phân công GV
│   ├── GradeCalculationService.php  # Tính điểm
│   └── WorkloadAnalysisService.php  # Phân tích khối lượng
└── Controllers/
    ├── CourseOfferingController.php
    ├── SchedulingController.php
    ├── TeachingController.php
    └── GradeController.php
```

## 📊 Cơ Sở Dữ Liệu

### Course Offerings Table
```sql
CREATE TABLE course_offerings (
    id BIGINT UNSIGNED PRIMARY KEY,
    semester_id BIGINT UNSIGNED NOT NULL,
    unit_id BIGINT UNSIGNED NOT NULL,
    campus_id BIGINT UNSIGNED NOT NULL,
    course_code VARCHAR(20) NOT NULL,
    section_code VARCHAR(10) DEFAULT 'A',
    max_enrollment INTEGER DEFAULT 30,
    current_enrollment INTEGER DEFAULT 0,
    waitlist_capacity INTEGER DEFAULT 10,
    current_waitlist INTEGER DEFAULT 0,
    delivery_mode ENUM('in_person', 'online', 'hybrid') DEFAULT 'in_person',
    status ENUM('planning', 'open', 'closed', 'cancelled', 'completed') DEFAULT 'planning',
    registration_start_date DATETIME,
    registration_end_date DATETIME,
    add_drop_deadline DATETIME,
    withdrawal_deadline DATETIME,
    tuition_fee DECIMAL(10,2),
    lab_fee DECIMAL(10,2) DEFAULT 0.00,
    other_fees DECIMAL(10,2) DEFAULT 0.00,
    prerequisites_met_auto_check BOOLEAN DEFAULT TRUE,
    requires_approval BOOLEAN DEFAULT FALSE,
    notes TEXT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    UNIQUE KEY unique_course_section (semester_id, unit_id, section_code),
    FOREIGN KEY (semester_id) REFERENCES semesters(id) ON DELETE CASCADE,
    FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE CASCADE,
    FOREIGN KEY (campus_id) REFERENCES campuses(id) ON DELETE CASCADE
);
```

### Teaching Assignments Table
```sql
CREATE TABLE teaching_assignments (
    id BIGINT UNSIGNED PRIMARY KEY,
    course_offering_id BIGINT UNSIGNED NOT NULL,
    lecturer_id BIGINT UNSIGNED,
    user_id BIGINT UNSIGNED,
    assignment_type ENUM('primary', 'co_instructor', 'teaching_assistant', 'grader') DEFAULT 'primary',
    teaching_percentage DECIMAL(5,2) DEFAULT 100.00,
    hourly_rate DECIMAL(8,2),
    total_compensation DECIMAL(10,2),
    assigned_date DATE NOT NULL,
    assignment_status ENUM('pending', 'accepted', 'declined', 'active', 'completed') DEFAULT 'pending',
    response_deadline DATE,
    accepted_date DATE,
    notes TEXT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (course_offering_id) REFERENCES course_offerings(id) ON DELETE CASCADE,
    FOREIGN KEY (lecturer_id) REFERENCES lecturers(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CHECK ((lecturer_id IS NOT NULL AND user_id IS NULL) OR (lecturer_id IS NULL AND user_id IS NOT NULL))
);
```

### Class Schedules Table
```sql
CREATE TABLE class_schedules (
    id BIGINT UNSIGNED PRIMARY KEY,
    course_offering_id BIGINT UNSIGNED NOT NULL,
    room_id BIGINT UNSIGNED,
    day_of_week ENUM('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday') NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    schedule_type ENUM('lecture', 'tutorial', 'lab', 'seminar', 'exam') DEFAULT 'lecture',
    effective_start_date DATE NOT NULL,
    effective_end_date DATE NOT NULL,
    is_recurring BOOLEAN DEFAULT TRUE,
    break_dates JSON, -- Các ngày nghỉ
    makeup_dates JSON, -- Các buổi học bù
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (course_offering_id) REFERENCES course_offerings(id) ON DELETE CASCADE,
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE SET NULL
);
```

## 🔧 Tính Năng Chính

### 1. Course Offering Management

#### Intelligent Course Planning
```php
class CourseOfferingService
{
    public function createOfferingFromCurriculum(
        Semester $semester, 
        CurriculumVersion $curriculum
    ): Collection {
        $offerings = collect();
        
        $curriculum->curriculumUnits->each(function ($curriculumUnit) use ($semester, &$offerings) {
            if ($this->shouldOfferInSemester($curriculumUnit, $semester)) {
                $offering = CourseOffering::create([
                    'semester_id' => $semester->id,
                    'unit_id' => $curriculumUnit->unit_id,
                    'campus_id' => $curriculum->program->campus_id,
                    'course_code' => $curriculumUnit->unit->code,
                    'max_enrollment' => $this->calculateExpectedEnrollment($curriculumUnit),
                ]);
                
                $offerings->push($offering);
            }
        });
        
        return $offerings;
    }
    
    public function calculateExpectedEnrollment(CurriculumUnit $curriculumUnit): int
    {
        // Tính toán dự kiến số sinh viên đăng ký dựa trên:
        // - Số sinh viên trong chương trình
        // - Tỷ lệ đăng ký lịch sử
        // - Môn bắt buộc hay tự chọn
        return $this->historicalEnrollmentAnalysis($curriculumUnit);
    }
}
```

### 2. Smart Scheduling System

#### Conflict Detection & Resolution
```php
class SchedulingService
{
    public function createOptimalSchedule(CourseOffering $offering): Collection
    {
        $availableSlots = $this->findAvailableTimeSlots($offering);
        $facultyConstraints = $this->getFacultyConstraints($offering);
        $roomRequirements = $this->getRoomRequirements($offering);
        
        $bestSchedule = $this->optimizeSchedule($availableSlots, $facultyConstraints, $roomRequirements);
        
        return $this->createScheduleRecords($offering, $bestSchedule);
    }
    
    public function detectScheduleConflicts(ClassSchedule $schedule): array
    {
        $conflicts = [];
        
        // Kiểm tra xung đột phòng học
        $roomConflicts = $this->checkRoomConflicts($schedule);
        if ($roomConflicts->isNotEmpty()) {
            $conflicts['room'] = $roomConflicts;
        }
        
        // Kiểm tra xung đột giảng viên
        $facultyConflicts = $this->checkFacultyConflicts($schedule);
        if ($facultyConflicts->isNotEmpty()) {
            $conflicts['faculty'] = $facultyConflicts;
        }
        
        // Kiểm tra xung đột sinh viên (môn học cùng thời gian)
        $studentConflicts = $this->checkStudentConflicts($schedule);
        if ($studentConflicts->isNotEmpty()) {
            $conflicts['students'] = $studentConflicts;
        }
        
        return $conflicts;
    }
}
```

### 3. Faculty Assignment System

#### Workload Balancing
```php
class TeachingAssignmentService
{
    public function assignLecturerToCourse(
        CourseOffering $offering, 
        Lecturer $lecturer,
        string $assignmentType = 'primary'
    ): TeachingAssignment {
        
        // Kiểm tra khối lượng công việc
        if (!$this->canTakeAssignment($lecturer, $offering)) {
            throw new WorkloadExceededException();
        }
        
        // Kiểm tra chuyên môn phù hợp
        if (!$this->hasRequiredExpertise($lecturer, $offering->unit)) {
            throw new ExpertiseMismatchException();
        }
        
        return TeachingAssignment::create([
            'course_offering_id' => $offering->id,
            'lecturer_id' => $lecturer->id,
            'assignment_type' => $assignmentType,
            'teaching_percentage' => $this->calculateTeachingPercentage($offering),
            'assigned_date' => now(),
        ]);
    }
    
    public function assignStaffToCourse(
        CourseOffering $offering, 
        User $staff,
        string $assignmentType = 'primary'
    ): TeachingAssignment {
        
        // Kiểm tra staff có thể dạy
        if (!$staff->can_teach) {
            throw new StaffCannotTeachException();
        }
        
        // Kiểm tra khối lượng công việc
        if (!$this->canTakeAssignment($staff, $offering)) {
            throw new WorkloadExceededException();
        }
        
        return TeachingAssignment::create([
            'course_offering_id' => $offering->id,
            'user_id' => $staff->id,
            'assignment_type' => $assignmentType,
            'teaching_percentage' => $this->calculateTeachingPercentage($offering),
            'assigned_date' => now(),
        ]);
    }
    
    public function analyzeWorkloadDistribution(Campus $campus, Semester $semester): array
    {
        $faculty = Faculty::where('campus_id', $campus->id)->get();
        
        return $faculty->map(function ($faculty) use ($semester) {
            return [
                'faculty_id' => $faculty->id,
                'name' => $faculty->user->full_name,
                'current_load' => $this->calculateCurrentLoad($faculty, $semester),
                'max_load' => $faculty->max_teaching_load_hours,
                'utilization' => $this->calculateUtilization($faculty, $semester),
                'available_capacity' => $this->getAvailableCapacity($faculty, $semester),
            ];
        })->toArray();
    }
}
```

### 4. Grade Management System

#### Comprehensive Assessment
```php
class GradeCalculationService
{
    public function calculateFinalGrade(
        Student $student, 
        CourseOffering $offering
    ): Grade {
        
        $assessments = Assessment::where('course_offering_id', $offering->id)->get();
        $totalWeight = $assessments->sum('weight_percentage');
        
        if ($totalWeight != 100) {
            throw new InvalidAssessmentWeightException();
        }
        
        $weightedScore = 0;
        foreach ($assessments as $assessment) {
            $studentGrade = $assessment->grades()
                ->where('student_id', $student->id)
                ->first();
                
            if ($studentGrade) {
                $weightedScore += ($studentGrade->score * $assessment->weight_percentage / 100);
            }
        }
        
        return Grade::create([
            'student_id' => $student->id,
            'course_offering_id' => $offering->id,
            'assessment_id' => null, // Final grade
            'score' => $weightedScore,
            'letter_grade' => $this->convertToLetterGrade($weightedScore),
            'grade_points' => $this->convertToGradePoints($weightedScore),
            'is_final_grade' => true,
        ]);
    }
    
    public function generateGradeReport(CourseOffering $offering): array
    {
        $students = $offering->enrolledStudents()->with('user')->get();
        
        return [
            'course_info' => [
                'code' => $offering->course_code,
                'name' => $offering->unit->name,
                'section' => $offering->section_code,
                'semester' => $offering->semester->name,
            ],
            'statistics' => [
                'total_students' => $students->count(),
                'average_grade' => $this->calculateAverageGrade($offering),
                'pass_rate' => $this->calculatePassRate($offering),
                'grade_distribution' => $this->getGradeDistribution($offering),
            ],
            'students' => $students->map(function ($student) use ($offering) {
                return [
                    'student_id' => $student->student_id,
                    'name' => $student->user->full_name,
                    'final_grade' => $this->getFinalGrade($student, $offering),
                    'assessments' => $this->getAssessmentGrades($student, $offering),
                ];
            }),
        ];
    }
}
```

## 🎨 Giao Diện Người Dùng

### Course Offering Dashboard
```vue
<template>
  <div class="course-offering-dashboard">
    <!-- Semester Selector -->
    <SemesterSelector v-model="selectedSemester" />
    
    <!-- Action Buttons -->
    <div class="mb-6 flex justify-between">
      <Button @click="createFromCurriculum">
        Create from Curriculum
      </Button>
      <Button @click="openOfferingModal">
        Add Individual Course
      </Button>
    </div>
    
    <!-- Course Offerings Table -->
    <DataTable
      :data="offerings"
      :columns="offeringColumns"
      @edit="editOffering"
      @assign-faculty="assignFaculty"
      @create-schedule="createSchedule"
    />
  </div>
</template>
```

### Smart Scheduling Interface
```vue
<template>
  <div class="scheduling-interface">
    <!-- Schedule Builder -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
      <!-- Left: Time Slots -->
      <div>
        <h3 class="text-lg font-semibold mb-4">Available Time Slots</h3>
        <TimeSlotGrid
          :available-slots="availableSlots"
          :selected-slot="selectedSlot"
          @slot-select="selectTimeSlot"
        />
      </div>
      
      <!-- Right: Room Selection -->
      <div>
        <h3 class="text-lg font-semibold mb-4">Available Rooms</h3>
        <RoomSelector
          :rooms="availableRooms"
          :selected-room="selectedRoom"
          :time-slot="selectedSlot"
          @room-select="selectRoom"
        />
      </div>
    </div>
    
    <!-- Conflict Detection -->
    <ConflictDetector
      v-if="schedule"
      :schedule="schedule"
      :conflicts="detectedConflicts"
    />
  </div>
</template>
```

## 📈 Analytics & Reporting

### Teaching Load Analytics
```php
public function getTeachingLoadAnalytics(Campus $campus, Semester $semester): array
{
    return [
        'faculty_utilization' => $this->getFacultyUtilization($campus, $semester),
        'course_distribution' => $this->getCourseDistribution($campus, $semester),
        'workload_balance' => $this->analyzeWorkloadBalance($campus, $semester),
        'overloaded_faculty' => $this->identifyOverloadedFaculty($campus, $semester),
        'underutilized_faculty' => $this->identifyUnderutilizedFaculty($campus, $semester),
    ];
}
```

## 🎯 Deliverables Giai Đoạn 4

### 🔄 Đang Phát Triển
1. **Course Offering System**: Mở môn học từ curriculum
2. **Smart Scheduling**: Lập lịch thông minh với conflict detection
3. **Faculty Assignment**: Phân công GV với workload balancing
4. **Grade Management**: Hệ thống chấm điểm toàn diện

## 🔜 Chuẩn Bị Cho Giai Đoạn 5

**Giai đoạn 5** sẽ tập trung vào "Đăng ký môn học" cho sinh viên. 
