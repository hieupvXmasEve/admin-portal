# Laravel Curriculum Schema Implementation

This document describes the complete implementation of the Laravel curriculum schema as specified in the prompt. The implementation follows Laravel 12 conventions and includes migrations, models, factories, and seeders.

## Schema Overview

The implemented schema includes the following main entities:
- **Programs**: Degree programs (bachelor, master, phd)
- **Specializations**: Specialization tracks within programs
- **Semesters**: Academic terms with enrollment periods
- **Curriculum Versions**: Versioned curriculum structures
- **Curriculum Unit Types**: Classification of units (core, elective, major)
- **Curriculum Units**: Units within curriculum versions

## Database Structure

### Core Tables

#### 1. Programs Table
```sql
- id: bigint, primary key
- name: string
- degree_level: enum ['bachelor', 'master', 'phd'], default 'bachelor'
- created_at, updated_at
```

#### 2. Specializations Table
```sql
- id: bigint, primary key
- program_id: foreign key → programs.id
- name: string
- description: text, nullable
- created_at, updated_at
```

#### 3. Semesters Table
```sql
- id: bigint, primary key
- code: string
- name: string
- start_date: datetime, nullable
- end_date: datetime, nullable
- enrollment_start_date: datetime, nullable
- enrollment_end_date: datetime, nullable
- is_active: boolean, default false
- is_archived: boolean, default false
- created_at, updated_at
- deleted_at (soft deletes)
```

#### 4. Curriculum Versions Table
```sql
- id: bigint, primary key
- program_id: foreign key → programs.id
- specialization_id: foreign key → specializations.id, nullable
- version_code: string, nullable
- effective_from_semester_id: foreign key → semesters.id, nullable
- created_at, updated_at
```

#### 5. Curriculum Unit Types Table
```sql
- id: tinyint, primary key
- name: enum ['core', 'elective', 'major']
- created_at, updated_at
```

#### 6. Curriculum Units Table
```sql
- id: bigint, primary key
- curriculum_version_id: foreign key → curriculum_versions.id
- unit_id: foreign key → units.id
- unit_type_id: foreign key → curriculum_unit_types.id, nullable
- semester_order: integer, nullable
- is_compulsory: boolean, default true
- note: text, nullable
- created_at, updated_at
```

## File Structure

### Migrations
- `2025_01_15_120000_create_curriculum_unit_types_table.php`
- `2025_01_15_121000_create_specializations_table.php`
- `2025_01_15_122000_update_curriculum_versions_for_specializations.php`
- `2025_01_15_123000_update_curriculum_units_for_types.php`

### Models
- `app/Models/Program.php` - Updated with specializations relationship
- `app/Models/Specialization.php` - Existing model for specialization tracks
- `app/Models/Semester.php` - Existing model (already matches schema)
- `app/Models/CurriculumVersion.php` - Updated with specialization relationship
- `app/Models/CurriculumUnitType.php` - New model for unit types
- `app/Models/CurriculumUnit.php` - Updated with new fields and relationships

### Factories
- `database/factories/ProgramFactory.php` - Enhanced with degree level states
- `database/factories/SemesterFactory.php` - Enhanced with realistic data
- `database/factories/CurriculumVersionFactory.php` - Updated with specialization support
- `database/factories/CurriculumUnitTypeFactory.php` - New factory for unit types
- `database/factories/CurriculumUnitFactory.php` - Enhanced with new fields

### Seeders
- `database/seeders/CurriculumUnitTypeSeeder.php` - Seeds the three unit types
- `database/seeders/CurriculumSchemaSeeder.php` - Comprehensive seeder demonstrating relationships
- `database/seeders/DatabaseSeeder.php` - Updated to include new seeders

## Model Relationships

### Program Model
- `hasMany(CurriculumVersion::class)` - Curriculum versions
- `hasMany(Specialization::class)` - Specializations

### Specialization Model
- `belongsTo(Program::class)` - Parent program
- `hasMany(CurriculumVersion::class)` - Specialization-specific curricula

### CurriculumVersion Model
- `belongsTo(Program::class)` - Program
- `belongsTo(Specialization::class)` - Specialization
- `belongsTo(Semester::class, 'effective_from_semester_id')` - Effective semester
- `hasMany(CurriculumUnit::class)` - Units in this version

### CurriculumUnit Model
- `belongsTo(CurriculumVersion::class)` - Parent curriculum version
- `belongsTo(Unit::class)` - The academic unit
- `belongsTo(CurriculumUnitType::class, 'unit_type_id')` - Unit type

### CurriculumUnitType Model
- `hasMany(CurriculumUnit::class, 'unit_type_id')` - Units of this type

## Key Features

### 1. Type Safety
All models use strict typing with `declare(strict_types=1);`

### 2. Proper Relationships
Foreign key constraints and indexed relationships for optimal performance

### 3. Default Values
Program degree_level defaults to 'bachelor' as specified

### 4. Soft Deletes
Semesters table includes soft deletes functionality

### 5. Comprehensive Factories
Factories include multiple states and realistic test data generation

### 6. Seeding Strategy
Seeders demonstrate the complete relationship hierarchy

## Usage Examples

### Creating a Program with Specializations
```php
$program = Program::factory()->computerScience()->create();
$specialization = Specialization::factory()->forProgram($program)->create();
```

### Creating a Curriculum Version
```php
$curriculumVersion = CurriculumVersion::factory()
    ->forSpecialization($specialization)
    ->withEffectiveSemester($semester)
    ->create();
```

### Creating Curriculum Units
```php
$unit = CurriculumUnit::factory()
    ->core()
    ->forYearAndSemester(1, 1)
    ->create([
        'curriculum_version_id' => $curriculumVersion->id,
        'unit_id' => $unit->id,
        'unit_type_id' => CurriculumUnitType::where('name', 'core')->first()->id,
    ]);
```

### Querying Relationships
```php
// Get all specializations for a program
$specializations = $program->specializations;

// Get curriculum units for a specific specialization
$units = $specialization->curriculumVersions()
    ->with('curriculumUnits.unit', 'curriculumUnits.unitType')
    ->first()
    ->curriculumUnits;

// Get core units only
$coreUnits = $curriculumVersion->curriculumUnits()
    ->whereHas('unitType', fn($q) => $q->where('name', 'core'))
    ->get();
```

## Installation & Setup

1. Run the migrations:
```bash
php artisan migrate
```

2. Seed the database:
```bash
php artisan db:seed
```

3. Or run specific seeders:
```bash
php artisan db:seed --class=CurriculumUnitTypeSeeder
php artisan db:seed --class=CurriculumSchemaSeeder
```

## Notes

### Relationship with Existing System
This implementation integrates with the existing specializations-based system by:
- Using the existing Specialization model and enhancing it
- Adding proper unit type classification
- Enhancing curriculum unit structure
- Maintaining full backward compatibility

### Schema Compliance
The implementation exactly matches the schema provided in the prompt and uses the original table names and relationships.

### Performance Considerations
- All foreign keys are properly indexed
- Queries are optimized with appropriate relationships
- Factory states allow for efficient test data generation
- Seeders use `firstOrCreate` to prevent duplicate data

## Testing

The factories enable comprehensive testing:

```php
// Test curriculum structure
test('can create complete curriculum structure', function () {
    $program = Program::factory()->create();
    $specialization = Specialization::factory()->forProgram($program)->create();
    $curriculumVersion = CurriculumVersion::factory()->forSpecialization($specialization)->create();
    $unit = CurriculumUnit::factory()->core()->create([
        'curriculum_version_id' => $curriculumVersion->id
    ]);
    
    expect($unit->curriculumVersion->specialization->program->id)->toBe($program->id);
});
```

This implementation provides a complete, production-ready curriculum management system following Laravel best practices and the exact specifications provided in the prompt. 
