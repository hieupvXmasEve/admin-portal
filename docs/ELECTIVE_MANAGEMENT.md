# Elective Management System

## Tổng quan

Hệ thống quản lý môn tự chọn cho phép sinh viên mỗi chuyên ngành chọn môn học từ tất cả các môn học khác trong trường đại học. Điều này tạo ra tính linh hoạt cao cho sinh viên trong việc tùy chỉnh chương trình học của mình.

## Kiến trúc

### Approach được sử dụng: "Sử dụng cấu trúc hiện tại"

- **Database schema**: Giữ nguyên cấu trúc hiện tại, không cần thêm trường phức tạp
- **Unit assignment**: Mỗi elective slot vẫn được assign một unit cụ thể
- **Flexibility**: Logic cho phép thay đổi unit được assign cho elective slots
- **Cross-specialization**: Sinh viên có thể chọn môn từ bất kỳ chuyên ngành/program nào khác

## Models được cập nhật

### 1. CurriculumUnit
**Các methods mới:**
- `getAvailableElectiveUnits()`: Lấy tất cả units có thể chọn làm elective
- `getUnitsFromOtherSpecializations()`: Units từ chuyên ngành khác trong cùng program
- `getUnitsFromOtherPrograms()`: Units từ programs khác
- `getAllAvailableElectives()`: Tổng hợp tất cả electives theo category
- `canBeSubstitutedWith(Unit $unit)`: Kiểm tra unit có thể thay thế không

### 2. CurriculumVersion
**Các methods mới:**
- `getAvailableElectiveUnits()`: Lấy tất cả electives cho curriculum version
- `getElectiveUnitsByCategory()`: Phân loại electives theo categories
- `getElectiveSlots()`: Lấy tất cả elective slots trong curriculum

### 3. Unit
**Các methods mới:**
- `canBeElectiveFor(int $specializationId, int $programId)`: Kiểm tra có thể làm elective không
- `getAvailableElectives()`: Static method lấy electives theo criteria
- `getFromOtherSpecializationsInProgram()`: Units từ chuyên ngành khác
- `getFromOtherPrograms()`: Units từ programs khác
- `getUnassignedUnits()`: Units chưa được assign

## API Endpoints

### 1. Lấy danh sách electives khả dụng
```
GET /api/curriculum-versions/{curriculumVersion}/available-electives
```

**Query Parameters:**
- `search`: Tìm kiếm theo tên hoặc code
- `category`: Filter theo category (same_program, other_programs, general)
- `per_page`: Số items per page (default: 15)

**Response:**
```json
{
  "data": {
    "curriculum_version": {
      "id": 1,
      "specialization": "Software Development",
      "program": "Computer Science"
    },
    "available_electives": {
      "same_program_other_specializations": {
        "label": "Units from other specializations in the same program",
        "count": 45
      },
      "cross_program_electives": {
        "label": "Units from other programs",
        "count": 120
      },
      "general_electives": {
        "label": "General elective units",
        "count": 8
      }
    },
    "units": [...],
    "pagination": {...}
  }
}
```

### 2. Lấy elective slots
```
GET /api/curriculum-versions/{curriculumVersion}/elective-slots
```

**Response:**
```json
{
  "data": {
    "curriculum_version": {...},
    "elective_slots": [
      {
        "id": 123,
        "year_level": 1,
        "semester_number": 1,
        "current_unit": {
          "id": 45,
          "code": "UN045",
          "name": "Foundation Computer Science",
          "credit_points": 6
        },
        "can_be_changed": true,
        "note": "Semester 1 (Year 1, Semester 1) - ELECTIVE SLOT: Student can choose any unit from other specializations or programs"
      }
    ],
    "total_elective_slots": 9
  }
}
```

### 3. Cập nhật elective slot
```
PUT /api/curriculum-units/{curriculumUnit}/update-elective
```

**Request Body:**
```json
{
  "unit_id": 67,
  "reason": "Student preference for advanced mathematics"
}
```

**Response:**
```json
{
  "message": "Elective unit updated successfully",
  "data": {
    "curriculum_unit_id": 123,
    "old_unit": {
      "id": 45,
      "code": "UN045",
      "name": "Foundation Computer Science"
    },
    "new_unit": {
      "id": 67,
      "code": "UN067",
      "name": "Advanced Mathematics",
      "credit_points": 12
    }
  }
}
```

### 4. Lấy chi tiết unit
```
GET /api/units/{unit}/details
```

**Response:**
```json
{
  "data": {
    "id": 67,
    "code": "UN067",
    "name": "Advanced Mathematics",
    "credit_points": 12,
    "prerequisites": [...],
    "syllabus": {...},
    "used_in_specializations": ["Data Science", "Engineering"]
  }
}
```

### 5. Lấy recommendations cho elective
```
GET /api/curriculum-units/{curriculumUnit}/recommendations
```

**Response:**
```json
{
  "data": {
    "curriculum_unit": {...},
    "recommendations": {
      "same_program_other_specializations": {
        "label": "From other specializations in your program",
        "units": [...],
        "total_count": 25
      },
      "other_programs": {
        "label": "From other programs",
        "units": [...],
        "total_count": 80
      },
      "unassigned_units": {
        "label": "General electives",
        "units": [...],
        "total_count": 5
      }
    }
  }
}
```

## Controllers

### 1. ElectiveController (API)
- `getAvailableElectives()`: Lấy electives với search/filter/pagination
- `getElectiveSlots()`: Lấy elective slots cho curriculum version
- `updateElectiveSlot()`: Cập nhật unit cho elective slot
- `getUnitDetails()`: Chi tiết unit với prerequisites và syllabus
- `getElectiveRecommendations()`: Recommendations theo categories

### 2. CurriculumVersionController
- `electiveManagement()`: Trang quản lý electives cho curriculum version
- Các CRUD operations cho curriculum versions

## Form Requests

### 1. StoreCurriculumVersionRequest
- Validation rules cho tạo curriculum version
- Auto-generate version code nếu không cung cấp

### 2. UpdateCurriculumVersionRequest
- Validation rules cho update curriculum version
- Kiểm tra specialization thuộc đúng program

## Seeder Updates

### ComprehensiveEducationSeeder
- Cập nhật note cho elective units để chỉ rõ đây là elective slots
- Note format: "ELECTIVE SLOT: Student can choose any unit from other specializations or programs"

## Business Logic

### Elective Selection Rules
1. **Same Program Rule**: Sinh viên có thể chọn units từ chuyên ngành khác trong cùng program
2. **Cross Program Rule**: Sinh viên có thể chọn units từ programs khác
3. **General Electives**: Units chưa được assign vào curriculum nào
4. **Exclusion Rule**: Không thể chọn units đã có trong curriculum của chuyên ngành hiện tại

### Validation Rules
1. **Elective Only**: Chỉ có thể thay đổi units có type "elective"
2. **Unit Availability**: Unit phải khả dụng cho specialization đó
3. **No Duplicates**: Không được có duplicate units trong cùng curriculum version

## Testing

### ElectiveManagementTest
- Test tất cả API endpoints
- Test validation rules
- Test business logic
- Test search và filter functionality

## Usage Examples

### Frontend Integration
```javascript
// Lấy available electives
const electives = await fetch('/api/curriculum-versions/1/available-electives?search=math&category=same_program')

// Update elective slot
const result = await fetch('/api/curriculum-units/123/update-elective', {
  method: 'PUT',
  body: JSON.stringify({
    unit_id: 67,
    reason: 'Student prefers advanced mathematics'
  })
})
```

### Backend Usage
```php
// Lấy electives cho curriculum version
$electives = $curriculumVersion->getElectiveUnitsByCategory();

// Kiểm tra unit có thể làm elective không
$canBeElective = $unit->canBeElectiveFor($specializationId, $programId);

// Lấy recommendations cho elective slot
$recommendations = $curriculumUnit->getAllAvailableElectives();
```

## Database Impact

- **Không có thay đổi schema**: Sử dụng cấu trúc hiện tại
- **Performance**: Queries được optimize với proper indexing
- **Data Integrity**: Foreign key constraints được maintain

## Kết luận

Hệ thống elective management này cung cấp:
- **Flexibility**: Sinh viên có thể chọn từ toàn bộ units trong trường
- **Maintainability**: Code clean và well-documented
- **Scalability**: API design cho phép mở rộng dễ dàng
- **User Experience**: Search, filter, và recommendation features 
