# Curriculum Schema Implementation Summary

## Overview
A complete Laravel implementation of a curriculum management system as specified in the requirements. The implementation includes migrations, Eloquent models with relationships, seeders with sample data, and factories for testing.

## Database Tables Created

### 1. `programs` (Updated)
- **Fields**: `id`, `name`, `degree_level`, `timestamps`
- **Changes**: Added `degree_level` enum field, removed `description`
- **Enum Values**: `bachelor`, `master`, `phd`

### 2. `curriculum_versions` (Updated)
- **Fields**: `id`, `program_id`, `version_code`, `effective_from_semester_id`, `timestamps`
- **Changes**: Renamed `semester_id` to `effective_from_semester_id`
- **Foreign Keys**: References `programs` and `semesters`

### 3. `units` (New)
- **Fields**: `id`, `code` (unique), `name`, `credit_points`, `timestamps`
- **Purpose**: Represents academic units/courses

### 4. `curriculum_units` (New)
- **Fields**: `id`, `curriculum_version_id`, `unit_id`, `group_type`, `timestamps`
- **Purpose**: Links units to specific curriculum versions
- **Enum Values**: `core`, `major`, `elective`, `minor`, `second_major`
- **Constraints**: Unique combination of `curriculum_version_id` and `unit_id`

### 5. `unit_prerequisites` (New)
- **Fields**: `id`, `unit_id`, `required_unit_id`, `type`, `timestamps`
- **Purpose**: Defines prerequisite relationships between units
- **Enum Values**: `prerequisite`, `co_requisite`, `concurrent`, `anti_requisite`, `assumed_knowledge`
- **Constraints**: Unique combination of `unit_id`, `required_unit_id`, and `type`

### 6. `equivalent_units` (New)
- **Fields**: `id`, `unit_id`, `equivalent_unit_id`, `reason`, `valid_from_semester_id`, `timestamps`
- **Purpose**: Defines equivalence mapping between units
- **Constraints**: Unique combination of `unit_id` and `equivalent_unit_id`

## Models Created/Updated

### Program Model
```php
- Fillable: ['name', 'degree_level']
- Relationships: hasMany(CurriculumVersion::class)
- Casts: degree_level as string
```

### CurriculumVersion Model
```php
- Fillable: ['program_id', 'version_code', 'effective_from_semester_id']
- Relationships: 
  - belongsTo(Program::class)
  - belongsTo(Semester::class, 'effective_from_semester_id')
  - hasMany(CurriculumUnit::class)
```

### Unit Model
```php
- Fillable: ['code', 'name', 'credit_points']
- Relationships:
  - hasMany(CurriculumUnit::class)
  - hasMany(UnitPrerequisite::class) for prerequisites
  - hasMany(EquivalentUnit::class)
  - belongsToMany(Unit::class) for prerequisite relationships
- Casts: credit_points as decimal:2
```

### CurriculumUnit Model
```php
- Fillable: ['curriculum_version_id', 'unit_id', 'group_type']
- Relationships:
  - belongsTo(CurriculumVersion::class)
  - belongsTo(Unit::class)
```

### UnitPrerequisite Model
```php
- Fillable: ['unit_id', 'required_unit_id', 'type']
- Relationships:
  - belongsTo(Unit::class) - the unit with requirements
  - belongsTo(Unit::class, 'required_unit_id') - the required unit
```

### EquivalentUnit Model
```php
- Fillable: ['unit_id', 'equivalent_unit_id', 'reason', 'valid_from_semester_id']
- Relationships:
  - belongsTo(Unit::class)
  - belongsTo(Unit::class, 'equivalent_unit_id')
  - belongsTo(Semester::class, 'valid_from_semester_id')
```

## Sample Data Seeded

### Programs (5 total)
1. **IT** (Bachelor) - Information Technology program
2. **Business** (Bachelor) - Business program  
3. **Global Citizen** (Bachelor) - Global citizenship program
4. **Vovinam** (Master) - Martial arts program
5. **MC** (PhD) - Media/Communications program

### Units (19 total)
- **Computer Science**: CS101-CS401 (Introduction to Advanced topics)
- **Business**: BUS101-BUS401 (Introduction to Strategic Management)
- **Global Citizen**: GC101-GC301 (Foundations to International Relations)
- **Vovinam**: VV101-VV301 (Basics to Philosophy)
- **Media/Communications**: MC101-MC301 (Production to Advanced Technology)
- **Alternative**: CS102A (equivalent to CS102)

### Curriculum Mappings
Each program has a curriculum version with appropriately assigned units:
- **Core units**: Fundamental requirements for the program
- **Major units**: Program-specific advanced courses
- **Elective units**: Optional courses from other programs
- **Minor units**: Secondary focus area courses
- **Second Major units**: Additional major specialization

### Prerequisites (8 relationships)
Examples:
- CS102 requires CS101 (prerequisite)
- CS201 requires CS102 (prerequisite)
- BUS201 has assumed knowledge of BUS101
- VV201 requires VV101 (prerequisite)

### Equivalent Units (2 relationships)
- CS102 ↔ CS102A: Alternative programming fundamentals courses

## Factories Created

### UnitFactory
- Generates realistic unit codes (CS101, BUS201, etc.)
- Random credit points (2.0, 3.0, 4.0, 6.0)
- Descriptive unit names

### CurriculumVersionFactory
- Links to programs via factory relationships
- Generates version codes (V1.0, V2.5, etc.)
- Associates with existing semesters

### CurriculumUnitFactory
- Creates curriculum-unit relationships
- Random group types for testing
- Factory relationships to curriculum versions and units

## Database Verification

After seeding, the database contains:
- **Programs**: 10 (5 from seeder + 5 from original data)
- **Units**: 19
- **Curriculum Versions**: 10 (2 per program)
- **Curriculum Units**: 48 (unit-curriculum relationships)
- **Prerequisites**: 8 prerequisite relationships
- **Equivalent Units**: 2 equivalence mappings

## Key Features Implemented

### 1. Comprehensive Relationships
- One-to-many relationships (Program → CurriculumVersion → CurriculumUnit)
- Many-to-many relationships (Unit prerequisites via pivot table)
- Self-referencing relationships (Unit prerequisites and equivalences)

### 2. Data Integrity
- Foreign key constraints with cascade deletes
- Unique constraints to prevent duplicates
- Proper enum validations

### 3. Flexible Prerequisite System
- Multiple prerequisite types supported
- Self-referencing unit relationships
- Both direct and many-to-many access patterns

### 4. Equivalence Tracking
- Bidirectional unit equivalences
- Reason tracking for equivalences
- Semester-based validity periods

### 5. Testing Support
- Comprehensive factories for all models
- Realistic sample data generation
- Proper factory relationships

## Usage Examples

### Query a Program's Curriculum
```php
$program = Program::with('curriculumVersions.curriculumUnits.unit')->first();
foreach ($program->curriculumVersions as $version) {
    foreach ($version->curriculumUnits as $curriculumUnit) {
        echo $curriculumUnit->unit->code . ': ' . $curriculumUnit->group_type;
    }
}
```

### Find Unit Prerequisites
```php
$unit = Unit::with('requiresUnits')->where('code', 'CS201')->first();
foreach ($unit->requiresUnits as $prerequisite) {
    echo $prerequisite->code . ' (' . $prerequisite->pivot->type . ')';
}
```

### Check Unit Equivalences
```php
$unit = Unit::with('equivalentUnits.equivalentUnit')->where('code', 'CS102')->first();
foreach ($unit->equivalentUnits as $equiv) {
    echo 'Equivalent to: ' . $equiv->equivalentUnit->code;
}
```

This implementation provides a robust foundation for a curriculum management system with full Laravel best practices, comprehensive relationships, and realistic sample data for development and testing. 
