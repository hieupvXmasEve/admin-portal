# Giai Đoạn 2: Quản Lý Cơ Sở và Lớp Học

## 📋 Tổng Quan

Giai đoạn 2 xây dựng hệ thống quản lý không gian vật lý và tổ chức học tập, bao gồm:
- **Quản Lý Campus**: Tạo và quản lý các cơ sở giáo dục
- **Quản Lý Tòa Nhà**: Tổ chức các tòa nhà trong campus
- **Quản Lý Phòng Học**: Tạo và phân loại phòng học, phòng thí nghiệm
- **Quản Lý Lớp Học**: Tạo lớp học và phân bổ không gian

## 🎯 Mục Tiêu Giai Đoạn 2

🔄 **Đang Phát Triển**:
- Cấu trúc campus với multiple locations
- Tòa nhà và phòng học có phân cấp rõ ràng
- Lớp học với capacity management
- Scheduling system cho phòng học
- Resource allocation và booking system

## 🏗️ Kiến Trúc Hệ Thống

### Backend Architecture (Laravel 12)
```
app/
├── Models/
│   ├── Campus.php                # Cơ sở giáo dục
│   ├── Building.php              # Tòa nhà
│   ├── Room.php                  # Phòng học/phòng thí nghiệm
│   ├── RoomType.php              # Loại phòng
│   ├── ClassGroup.php            # Lớp học
│   ├── RoomBooking.php           # Đặt phòng
│   └── RoomSchedule.php          # Lịch sử dụng phòng
├── Http/Controllers/
│   ├── CampusController.php      # CRUD campus
│   ├── BuildingController.php    # CRUD tòa nhà
│   ├── RoomController.php        # CRUD phòng học
│   ├── ClassGroupController.php  # CRUD lớp học
│   └── RoomBookingController.php # CRUD đặt phòng
├── Services/
│   ├── CampusManagementService.php # Logic quản lý campus
│   ├── RoomAllocationService.php   # Phân bổ phòng học
│   ├── SchedulingService.php       # Lập lịch sử dụng
│   └── CapacityManagementService.php # Quản lý sức chứa
└── Http/Requests/
    ├── StoreCampusRequest.php    # Validation tạo campus
    ├── StoreRoomRequest.php      # Validation tạo phòng
    └── StoreClassGroupRequest.php # Validation tạo lớp
```

### Frontend Architecture (Vue.js 3 + TypeScript)
```
resources/js/
├── pages/
│   ├── Campuses/
│   │   ├── Index.vue            # Danh sách campus
│   │   ├── Create.vue           # Tạo campus
│   │   ├── Edit.vue             # Chỉnh sửa campus
│   │   └── Show.vue             # Chi tiết campus
│   ├── Buildings/
│   │   ├── Index.vue            # Danh sách tòa nhà
│   │   ├── Create.vue           # Tạo tòa nhà
│   │   └── FloorPlan.vue        # Sơ đồ tầng
│   ├── Rooms/
│   │   ├── Index.vue            # Danh sách phòng
│   │   ├── Create.vue           # Tạo phòng
│   │   ├── Schedule.vue         # Lịch sử dụng phòng
│   │   └── Booking.vue          # Đặt phòng
│   ├── ClassGroups/
│   │   ├── Index.vue            # Danh sách lớp học
│   │   ├── Create.vue           # Tạo lớp học
│   │   └── Management.vue       # Quản lý lớp
│   └── Scheduling/
│       ├── Calendar.vue         # Lịch tổng thể
│       ├── RoomAvailability.vue # Tình trạng phòng
│       └── Conflicts.vue        # Xung đột lịch
├── components/
│   ├── CampusSelector.vue       # Chọn campus
│   ├── RoomSelector.vue         # Chọn phòng
│   ├── CapacityIndicator.vue    # Hiển thị sức chứa
│   ├── ScheduleCalendar.vue     # Lịch với drag-drop
│   └── FloorPlanViewer.vue      # Xem sơ đồ tầng
├── types/
│   ├── campus.ts                # Campus interfaces
│   ├── room.ts                  # Room interfaces
│   └── scheduling.ts            # Scheduling interfaces
└── composables/
    ├── useCampuses.ts           # Logic campus
    ├── useRooms.ts              # Logic phòng học
    ├── useScheduling.ts         # Logic lập lịch
    └── useCapacity.ts           # Logic sức chứa
```

## 📊 Cơ Sở Dữ Liệu

### Campuses Table
```sql
CREATE TABLE campuses (
    id BIGINT UNSIGNED PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    code VARCHAR(20) NOT NULL UNIQUE,
    description TEXT,
    address TEXT,
    city VARCHAR(100),
    state VARCHAR(100),
    postal_code VARCHAR(20),
    country VARCHAR(100) DEFAULT 'Vietnam',
    timezone VARCHAR(50) DEFAULT 'Asia/Ho_Chi_Minh',
    phone VARCHAR(20),
    email VARCHAR(255),
    website VARCHAR(255),
    is_main_campus BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    established_date DATE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    deleted_at TIMESTAMP NULL
);
```

### Buildings Table
```sql
CREATE TABLE buildings (
    id BIGINT UNSIGNED PRIMARY KEY,
    campus_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    code VARCHAR(20) NOT NULL,
    description TEXT,
    floors_count INTEGER DEFAULT 1,
    total_rooms INTEGER DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    building_type ENUM('academic', 'administrative', 'library', 'laboratory', 'dormitory', 'cafeteria', 'sports', 'other') DEFAULT 'academic',
    construction_year YEAR,
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    UNIQUE KEY unique_building_code_per_campus (campus_id, code),
    FOREIGN KEY (campus_id) REFERENCES campuses(id) ON DELETE CASCADE
);
```

### Room Types Table
```sql
CREATE TABLE room_types (
    id BIGINT UNSIGNED PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    code VARCHAR(20) NOT NULL UNIQUE,
    description TEXT,
    default_capacity INTEGER DEFAULT 30,
    has_projector BOOLEAN DEFAULT FALSE,
    has_whiteboard BOOLEAN DEFAULT TRUE,
    has_computer BOOLEAN DEFAULT FALSE,
    has_audio_system BOOLEAN DEFAULT FALSE,
    is_laboratory BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

### Rooms Table
```sql
CREATE TABLE rooms (
    id BIGINT UNSIGNED PRIMARY KEY,
    building_id BIGINT UNSIGNED NOT NULL,
    room_type_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    code VARCHAR(20) NOT NULL,
    floor INTEGER DEFAULT 1,
    capacity INTEGER NOT NULL,
    area_sqm DECIMAL(8,2),
    is_active BOOLEAN DEFAULT TRUE,
    is_bookable BOOLEAN DEFAULT TRUE,
    has_projector BOOLEAN DEFAULT FALSE,
    has_whiteboard BOOLEAN DEFAULT TRUE,
    has_computer BOOLEAN DEFAULT FALSE,
    has_audio_system BOOLEAN DEFAULT FALSE,
    has_air_conditioning BOOLEAN DEFAULT TRUE,
    accessibility_features JSON,
    equipment_list JSON,
    notes TEXT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    UNIQUE KEY unique_room_code_per_building (building_id, code),
    FOREIGN KEY (building_id) REFERENCES buildings(id) ON DELETE CASCADE,
    FOREIGN KEY (room_type_id) REFERENCES room_types(id) ON DELETE RESTRICT
);
```

### Class Groups Table
```sql
CREATE TABLE class_groups (
    id BIGINT UNSIGNED PRIMARY KEY,
    campus_id BIGINT UNSIGNED NOT NULL,
    program_id BIGINT UNSIGNED NOT NULL,
    specialization_id BIGINT UNSIGNED,
    name VARCHAR(255) NOT NULL,
    code VARCHAR(20) NOT NULL,
    year_level INTEGER NOT NULL,
    semester_number INTEGER DEFAULT 1,
    academic_year VARCHAR(9), -- e.g., "2024-2025"
    class_size INTEGER DEFAULT 30,
    current_enrollment INTEGER DEFAULT 0,
    max_enrollment INTEGER DEFAULT 35,
    homeroom_teacher_id BIGINT UNSIGNED,
    primary_room_id BIGINT UNSIGNED,
    is_active BOOLEAN DEFAULT TRUE,
    start_date DATE,
    end_date DATE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    UNIQUE KEY unique_class_code_per_campus (campus_id, code),
    FOREIGN KEY (campus_id) REFERENCES campuses(id) ON DELETE CASCADE,
    FOREIGN KEY (program_id) REFERENCES programs(id) ON DELETE CASCADE,
    FOREIGN KEY (specialization_id) REFERENCES specializations(id) ON DELETE SET NULL,
    FOREIGN KEY (homeroom_teacher_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (primary_room_id) REFERENCES rooms(id) ON DELETE SET NULL
);
```

### Room Bookings Table
```sql
CREATE TABLE room_bookings (
    id BIGINT UNSIGNED PRIMARY KEY,
    room_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    class_group_id BIGINT UNSIGNED,
    unit_id BIGINT UNSIGNED,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    booking_type ENUM('class', 'exam', 'meeting', 'event', 'maintenance', 'other') DEFAULT 'class',
    start_datetime DATETIME NOT NULL,
    end_datetime DATETIME NOT NULL,
    expected_attendees INTEGER,
    equipment_needed JSON,
    special_requirements TEXT,
    status ENUM('pending', 'confirmed', 'cancelled', 'completed') DEFAULT 'pending',
    booking_priority ENUM('low', 'normal', 'high', 'urgent') DEFAULT 'normal',
    recurring_pattern ENUM('none', 'daily', 'weekly', 'monthly') DEFAULT 'none',
    recurring_end_date DATE,
    approved_by_user_id BIGINT UNSIGNED,
    approved_at TIMESTAMP NULL,
    cancelled_at TIMESTAMP NULL,
    cancellation_reason TEXT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (class_group_id) REFERENCES class_groups(id) ON DELETE SET NULL,
    FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE SET NULL,
    FOREIGN KEY (approved_by_user_id) REFERENCES users(id) ON DELETE SET NULL
);
```

### Room Schedules Table
```sql
CREATE TABLE room_schedules (
    id BIGINT UNSIGNED PRIMARY KEY,
    room_booking_id BIGINT UNSIGNED NOT NULL,
    room_id BIGINT UNSIGNED NOT NULL,
    semester_id BIGINT UNSIGNED NOT NULL,
    day_of_week ENUM('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday') NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    is_recurring BOOLEAN DEFAULT FALSE,
    effective_start_date DATE NOT NULL,
    effective_end_date DATE NOT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (room_booking_id) REFERENCES room_bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
    FOREIGN KEY (semester_id) REFERENCES semesters(id) ON DELETE CASCADE
);
```

## 🔧 Tính Năng Chính

### 1. Quản Lý Campus

#### Đa Cơ Sở
- **Multiple Locations**: Hỗ trợ nhiều campus
- **Geographic Information**: GPS coordinates cho mapping
- **Timezone Support**: Quản lý múi giờ khác nhau
- **Contact Information**: Thông tin liên hệ đầy đủ

#### Tính Năng Nâng Cao
```php
// Campus Management
class Campus extends Model
{
    public function isMainCampus(): bool
    public function getTimezone(): string
    public function calculateDistance(Campus $other): float
    public function getActiveBuildings(): Collection
    public function getTotalCapacity(): int
    
    // Scopes
    public function scopeActive(Builder $query): void
    public function scopeMainCampus(Builder $query): void
    public function scopeInCity(Builder $query, string $city): void
}
```

### 2. Quản Lý Tòa Nhà

#### Phân Loại Tòa Nhà
- **Academic**: Tòa nhà học tập chính
- **Administrative**: Tòa nhà hành chính
- **Library**: Thư viện
- **Laboratory**: Tòa nhà thí nghiệm
- **Dormitory**: Ký túc xá
- **Sports**: Tòa nhà thể thao

#### Floor Plan Management
```php
class Building extends Model
{
    public function getTotalRooms(): int
    public function getAvailableRooms(Carbon $date, string $startTime, string $endTime): Collection
    public function getFloorRooms(int $floor): Collection
    public function calculateUtilizationRate(): float
    
    // Relationships
    public function campus(): BelongsTo
    public function rooms(): HasMany
}
```

### 3. Quản Lý Phòng Học

#### Phân Loại Phòng
- **Lecture Hall**: Giảng đường lớn
- **Classroom**: Phòng học thường
- **Laboratory**: Phòng thí nghiệm
- **Computer Lab**: Phòng máy tính
- **Meeting Room**: Phòng họp
- **Seminar Room**: Phòng seminar

#### Equipment Management
```php
class Room extends Model
{
    protected $casts = [
        'accessibility_features' => 'array',
        'equipment_list' => 'array',
    ];
    
    public function isAvailable(Carbon $start, Carbon $end): bool
    public function getCapacityUtilization(): float
    public function hasEquipment(string $equipment): bool
    public function getBookingsForPeriod(Carbon $start, Carbon $end): Collection
    
    // Equipment checking
    public function hasProjector(): bool
    public function hasComputer(): bool
    public function hasAudioSystem(): bool
    public function isAccessible(): bool
}
```

### 4. Quản Lý Lớp Học

#### Class Group Organization
- **Year Level**: Phân theo năm học (1, 2, 3, 4)
- **Program Integration**: Liên kết với chương trình đào tạo
- **Capacity Management**: Quản lý sĩ số lớp
- **Homeroom Teacher**: Giáo viên chủ nhiệm

#### Advanced Features
```php
class ClassGroup extends Model
{
    public function getCurrentEnrollment(): int
    public function hasAvailableSlots(): bool
    public function getAcademicYear(): string
    public function isActive(): bool
    public function getSchedule(): Collection
    
    // Student management
    public function addStudent(User $student): bool
    public function removeStudent(User $student): bool
    public function getStudentList(): Collection
}
```

### 5. Room Booking System

#### Đặt Phòng Linh Hoạt
- **Multiple Booking Types**: Class, Exam, Meeting, Event
- **Recurring Bookings**: Daily, Weekly, Monthly patterns
- **Conflict Detection**: Tự động phát hiện xung đột
- **Approval Workflow**: Quy trình phê duyệt

#### Booking Management
```php
class RoomBookingService
{
    public function checkAvailability(Room $room, Carbon $start, Carbon $end): bool
    public function createBooking(array $data): RoomBooking
    public function detectConflicts(RoomBooking $booking): Collection
    public function approveBooking(RoomBooking $booking, User $approver): bool
    public function cancelBooking(RoomBooking $booking, string $reason): bool
    
    // Recurring bookings
    public function createRecurringBookings(RoomBooking $template): Collection
    public function updateRecurringSeries(RoomBooking $booking, array $updates): bool
}
```

## 🎨 Giao Diện Người Dùng

### Campus Management Interface
```vue
<template>
  <div class="campus-management">
    <!-- Campus Selector -->
    <CampusSelector 
      v-model="selectedCampus"
      :campuses="campuses"
      @change="loadCampusData"
    />
    
    <!-- Building Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
      <BuildingCard
        v-for="building in buildings"
        :key="building.id"
        :building="building"
        @click="viewBuilding"
      />
    </div>
    
    <!-- Room Availability -->
    <RoomAvailabilityGrid
      :rooms="availableRooms"
      :date="selectedDate"
      @room-select="bookRoom"
    />
  </div>
</template>
```

### Scheduling Interface
```vue
<template>
  <div class="scheduling-interface">
    <!-- Calendar View -->
    <FullCalendar
      :events="roomBookings"
      :plugins="calendarPlugins"
      @event-drop="handleEventMove"
      @event-resize="handleEventResize"
      @date-select="createNewBooking"
    />
    
    <!-- Room Filter -->
    <RoomFilter
      v-model="roomFilters"
      :room-types="roomTypes"
      :buildings="buildings"
      @filter-change="filterRooms"
    />
    
    <!-- Booking Form -->
    <BookingForm
      v-if="showBookingForm"
      :room="selectedRoom"
      :initial-date="selectedDate"
      @submit="createBooking"
      @cancel="closeForm"
    />
  </div>
</template>
```

### Room Management Dashboard
```vue
<template>
  <div class="room-dashboard">
    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
      <StatCard
        title="Total Rooms"
        :value="stats.totalRooms"
        icon="building"
      />
      <StatCard
        title="Available Now"
        :value="stats.availableRooms"
        icon="check-circle"
        color="green"
      />
      <StatCard
        title="Utilization Rate"
        :value="`${stats.utilizationRate}%`"
        icon="activity"
        color="blue"
      />
      <StatCard
        title="Maintenance"
        :value="stats.maintenanceRooms"
        icon="tool"
        color="orange"
      />
    </div>
    
    <!-- Room List with Filters -->
    <DataTable
      :data="rooms"
      :columns="roomColumns"
      :filters="filters"
      @filter="handleFilter"
    />
  </div>
</template>
```

## 📈 Analytics & Reporting

### Campus Statistics
```php
public function getCampusStatistics(Campus $campus): array
{
    return [
        'total_buildings' => $campus->buildings()->count(),
        'total_rooms' => $campus->buildings()->withCount('rooms')->sum('rooms_count'),
        'total_capacity' => $campus->rooms()->sum('capacity'),
        'utilization_rate' => $this->calculateUtilizationRate($campus),
        'popular_buildings' => $this->getPopularBuildings($campus),
        'room_type_distribution' => $this->getRoomTypeDistribution($campus),
    ];
}
```

### Room Utilization Analytics
```php
public function getRoomUtilizationReport(Room $room, Carbon $startDate, Carbon $endDate): array
{
    $bookings = $room->bookings()
        ->whereBetween('start_datetime', [$startDate, $endDate])
        ->get();
        
    return [
        'total_hours_booked' => $this->calculateTotalHours($bookings),
        'utilization_percentage' => $this->calculateUtilization($room, $bookings, $startDate, $endDate),
        'peak_hours' => $this->findPeakHours($bookings),
        'booking_patterns' => $this->analyzeBookingPatterns($bookings),
        'average_occupancy' => $this->calculateAverageOccupancy($bookings),
    ];
}
```

### Capacity Planning
```php
public function generateCapacityPlanningReport(Campus $campus): array
{
    return [
        'current_capacity' => $campus->getTotalCapacity(),
        'projected_demand' => $this->calculateProjectedDemand($campus),
        'capacity_gaps' => $this->identifyCapacityGaps($campus),
        'expansion_recommendations' => $this->generateExpansionRecommendations($campus),
        'space_optimization_opportunities' => $this->findOptimizationOpportunities($campus),
    ];
}
```

## 🧪 Testing Strategy

### Backend Testing
```php
// Campus Tests
test('can create campus with full details', function () {
    $campus = Campus::factory()->create([
        'name' => 'Main Campus',
        'is_main_campus' => true,
    ]);
    
    expect($campus->isMainCampus())->toBeTrue();
    expect($campus->timezone)->toBe('Asia/Ho_Chi_Minh');
});

// Room Booking Tests
test('detects room booking conflicts', function () {
    $room = Room::factory()->create();
    
    // Create first booking
    $booking1 = RoomBooking::factory()->create([
        'room_id' => $room->id,
        'start_datetime' => '2024-01-15 09:00:00',
        'end_datetime' => '2024-01-15 11:00:00',
    ]);
    
    // Try to create conflicting booking
    expect(function () use ($room) {
        RoomBooking::factory()->create([
            'room_id' => $room->id,
            'start_datetime' => '2024-01-15 10:00:00',
            'end_datetime' => '2024-01-15 12:00:00',
        ]);
    })->toThrow(ValidationException::class);
});

// Capacity Tests
test('tracks room capacity correctly', function () {
    $room = Room::factory()->create(['capacity' => 30]);
    $classGroup = ClassGroup::factory()->create([
        'primary_room_id' => $room->id,
        'max_enrollment' => 25,
    ]);
    
    expect($classGroup->hasAvailableSlots())->toBeTrue();
    expect($room->capacity)->toBeGreaterThan($classGroup->max_enrollment);
});
```

### Frontend Testing
```typescript
// Room Booking Component Tests
describe('RoomBookingForm', () => {
  test('validates booking time conflicts', async () => {
    const wrapper = mount(RoomBookingForm, {
      props: {
        room: mockRoom,
        existingBookings: mockConflictingBookings
      }
    })
    
    await wrapper.find('[data-testid="start-time"]').setValue('09:00')
    await wrapper.find('[data-testid="end-time"]').setValue('11:00')
    
    expect(wrapper.find('[data-testid="conflict-warning"]').exists()).toBe(true)
  })
})

// Campus Selector Tests
describe('CampusSelector', () => {
  test('filters buildings by selected campus', async () => {
    const wrapper = mount(CampusSelector, {
      props: {
        campuses: mockCampuses
      }
    })
    
    await wrapper.find('select').setValue('campus-1')
    
    expect(wrapper.emitted('campus-change')).toBeTruthy()
  })
})
```

## 🚀 Performance Optimization

### Database Optimization
```php
// Efficient room availability query
public function getAvailableRooms(Campus $campus, Carbon $start, Carbon $end): Collection
{
    return Room::query()
        ->whereHas('building', fn($q) => $q->where('campus_id', $campus->id))
        ->where('is_active', true)
        ->where('is_bookable', true)
        ->whereDoesntHave('bookings', function ($query) use ($start, $end) {
            $query->where('status', 'confirmed')
                  ->where(function ($q) use ($start, $end) {
                      $q->whereBetween('start_datetime', [$start, $end])
                        ->orWhereBetween('end_datetime', [$start, $end])
                        ->orWhere(function ($inner) use ($start, $end) {
                            $inner->where('start_datetime', '<=', $start)
                                  ->where('end_datetime', '>=', $end);
                        });
                  });
        })
        ->with(['building', 'roomType'])
        ->get();
}
```

### Caching Strategy
```php
// Cache room availability
public function getCachedRoomAvailability(Campus $campus, Carbon $date): Collection
{
    $cacheKey = "room_availability_{$campus->id}_{$date->format('Y-m-d')}";
    
    return Cache::remember($cacheKey, 300, function () use ($campus, $date) {
        return $this->calculateRoomAvailability($campus, $date);
    });
}

// Cache campus statistics
public function getCachedCampusStats(Campus $campus): array
{
    return Cache::remember("campus_stats_{$campus->id}", 3600, function () use ($campus) {
        return $this->calculateCampusStatistics($campus);
    });
}
```

## 📝 API Documentation

### Campus Endpoints
```http
GET    /api/campuses                   # List all campuses
POST   /api/campuses                   # Create campus
GET    /api/campuses/{id}              # Show campus details
PUT    /api/campuses/{id}              # Update campus
DELETE /api/campuses/{id}              # Delete campus
GET    /api/campuses/{id}/buildings    # Get campus buildings
GET    /api/campuses/{id}/stats        # Get campus statistics
```

### Room Endpoints
```http
GET    /api/rooms                      # List rooms with filters
POST   /api/rooms                      # Create room
GET    /api/rooms/{id}                 # Show room details
PUT    /api/rooms/{id}                 # Update room
DELETE /api/rooms/{id}                 # Delete room
GET    /api/rooms/availability         # Check availability
POST   /api/rooms/{id}/book            # Book room
GET    /api/rooms/{id}/schedule        # Get room schedule
```

### Booking Endpoints
```http
GET    /api/bookings                   # List bookings
POST   /api/bookings                   # Create booking
GET    /api/bookings/{id}              # Show booking
PUT    /api/bookings/{id}              # Update booking
DELETE /api/bookings/{id}              # Cancel booking
POST   /api/bookings/{id}/approve      # Approve booking
GET    /api/bookings/conflicts         # Check conflicts
```

## 🎯 Deliverables Giai Đoạn 2

### 🔄 Đang Phát Triển
1. **Campus Management System**:
   - 🔄 Multi-campus support với geographic data
   - 🔄 Building và floor management
   - 🔄 Campus statistics và analytics

2. **Room Management System**:
   - 🔄 Comprehensive room classification
   - 🔄 Equipment và feature tracking
   - 🔄 Availability checking system

3. **Class Group Management**:
   - 🔄 Class organization với capacity limits
   - 🔄 Homeroom teacher assignment
   - 🔄 Academic year tracking

4. **Booking & Scheduling System**:
   - 🔄 Advanced booking system với conflict detection
   - 🔄 Recurring booking patterns
   - 🔄 Approval workflow

5. **User Interface**:
   - 🔄 Interactive campus maps
   - 🔄 Room booking calendar
   - 🔄 Real-time availability dashboard

## 🔜 Chuẩn Bị Cho Giai Đoạn 3

Sau khi hoàn thành giai đoạn 2, hệ thống sẽ có:
- ✅ Complete physical infrastructure management
- ✅ Advanced room booking system
- ✅ Class organization framework
- ✅ Scheduling foundation

**Giai đoạn 3** sẽ tập trung vào "Quản lý người dùng cơ bản" và sẽ tích hợp với cơ sở hạ tầng vật lý đã được xây dựng. 
