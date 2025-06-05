You are a Laravel development assistant. Based on the following database schema, generate the complete Laravel code for:

1. Migration files
2. Eloquent models (with proper relationships and casts)
3. Database seeders (with sample data)
4. Model factories (for testing or seeding)

Please follow Laravel 12 conventions. Use soft deletes if `deleted_at` exists. Include timestamps if `created_at`/`updated_at` exist. Use foreign key constraints where applicable.

---

Schema:
### Table: semesters
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

### Table: programs
- id: bigint, primary key
- name: string
- degree_level: enum ['bachelor', 'master', 'phd'], default 'bachelor'

### Table: specializations
- id: bigint, primary key
- name: string
- program_id: foreign key → programs.id
- created_at, updated_at

### Table: curriculum_versions
- id: bigint, primary key
- program_id: foreign key → programs.id
- version_code: string, nullable
- semester_id: foreign key → semesters.id, nullable
- created_at, updated_at

### Table: curriculum_units
- id: bigint, primary key
- curriculum_version_id: foreign key → curriculum_versions.id
- unit_id: foreign key → units.id
- unit_type_id: foreign key → curriculum_unit_types.id, nullable
- semester_order: integer, nullable

### Table: curriculum_unit_types
- id: tinyint, primary key
- name: string, enum ['core', 'elective', 'major']
- created_at, updated_at


```sql
-- Chương trình đào tạo (Ngành lớn)
CREATE TABLE programs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    degree_level ENUM('bachelor', 'master', 'phd') NOT NULL DEFAULT 'bachelor',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);

-- Chuyên ngành (thuộc chương trình đào tạo)
CREATE TABLE specializations (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    program_id BIGINT NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (program_id) REFERENCES programs(id) ON DELETE CASCADE
);

-- Kỳ học
CREATE TABLE semesters (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(255) NOT NULL,
    name VARCHAR(255) NOT NULL,
    start_date DATETIME NULL,
    end_date DATETIME NULL,
    enrollment_start_date DATETIME NULL,
    enrollment_end_date DATETIME NULL,
    is_active TINYINT(1) DEFAULT 0 NOT NULL,
    is_archived TINYINT(1) DEFAULT 0 NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL
);

-- Khung chương trình đào tạo theo từng chuyên ngành
CREATE TABLE curriculum_versions (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    program_id BIGINT NOT NULL,
    specialization_id BIGINT NULL,
    version_code VARCHAR(20) NULL,
    semester_id BIGINT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (program_id) REFERENCES  programs(id),
    FOREIGN KEY (specialization_id) REFERENCES specializations(id),
    FOREIGN KEY (semester_id) REFERENCES semesters(id)
);

-- Loại môn học (core, elective, general,...)
CREATE TABLE curriculum_unit_types (
    id TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL
);

-- Môn học trong khung chương trình
CREATE TABLE curriculum_units (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    curriculum_version_id BIGINT NOT NULL,
    unit_id BIGINT NOT NULL,
    unit_type_id TINYINT UNSIGNED NULL, -- core, elective,...
    semester_order INT NULL, -- kỳ học đề xuất
    is_compulsory BOOLEAN DEFAULT TRUE,
    note TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (curriculum_version_id) REFERENCES curriculum_versions(id) ON DELETE CASCADE,
    FOREIGN KEY (unit_id) REFERENCES units(id),
    FOREIGN KEY (unit_type_id) REFERENCES curriculum_unit_types(id)
);

```
