# Task: Generate Migration, Model, and Seeder

## Framework
Assume this is a Laravel project using PHP 8+ and Laravel 11+. Use Eloquent for models and factory-based seeders. Use proper foreign key constraints and soft deletes where necessary.

## Objective
Create the following database structure with migrations, models, and seeders:

---

## 1. `programs`
Represents academic programs like IT, Business, etc.

### Fields
- id (bigint, auto-increment)
- name (string, required)
- degree_level (enum: 'bachelor', 'master', 'phd', required)
- timestamps

---

## 2. `curriculum_versions`
Represents different versions of a curriculum for each program.

### Fields
- id (bigint, auto-increment)
- program_id (foreign key to programs)
- version_code (string, optional)
- semester_id (foreign key to semesters — assume the table exists)
- timestamps

---

## 3. `units`
Represents academic units/courses.

### Fields
- id (bigint, auto-increment)
- code (string, unique, required)
- name (string)
- credit_points (decimal, required)
- timestamps

---

## 4. `curriculum_units`
Links units to a specific curriculum version.

### Fields
- id (bigint, auto-increment)
- curriculum_version_id (foreign key to curriculum_versions)
- unit_id (foreign key to units)
- group_type (enum: 'core', 'major', 'elective', 'minor', 'second_major')
- timestamps

---

## 5. `unit_prerequisites`
Defines different types of prerequisite relationships between units.

### Fields
- id (bigint, auto-increment)
- unit_id (foreign key to units) — the unit that requires the prerequisite
- required_unit_id (foreign key to units) — the required unit
- type (enum: 'prerequisite', 'co_requisite', 'concurrent', 'anti_requisite', 'assumed_knowledge')
- timestamps

### Requisite Types
- `prerequisite`: must complete before the target unit
- `co_requisite`: must take both units at the same time
- `concurrent`: can take before or at the same time
- `anti_requisite`: overlapping content, cannot take both
- `assumed_knowledge`: recommended prior knowledge

---

## 6. `equivalent_units`
Defines equivalence mapping between units.

### Fields
- id (bigint, auto-increment)
- unit_id (foreign key to units)
- equivalent_unit_id (foreign key to units)
- reason (string, optional)
- valid_from_semester_id (foreign key to semesters — assume the table exists)
- timestamps

---

## Seeder Requirements
- Create sample data for:
  - 5 programs: "IT", "Business", "Global Citizen", "Vovinam", "MC"
  - Each program has at least 1 curriculum version
  - Each curriculum has at least 3 units with various group types
  - At least one prerequisite relationship
  - At least one equivalent unit pair

## Output
Generate:
- Laravel migration files
- Laravel Eloquent models (with relationships)
- Laravel seeders using factories or manual insert
