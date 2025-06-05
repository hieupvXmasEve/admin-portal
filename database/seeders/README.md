# Comprehensive Education Data Seeder

## Overview

The `ComprehensiveEducationSeeder` generates a complete educational data structure as specified in the requirements.

## Data Structure Generated

### Summary
- **50 sample units**
- **5 programs** with **3 specializations each** (15 total specializations)
- **5 curriculum versions** per specialization (75 total curriculum versions)
- **15 curriculum units** per curriculum version (1,125 total curriculum units)
- **3 curriculum unit types**: `core`, `elective`, `major`
- **9 semesters** distributed over 3 years
- **3 subjects per semester**: 2 core/major units + 1 elective unit

### Detailed Breakdown

#### Units (50 total)
- Generated with realistic academic subjects covering various disciplines
- Codes: `UN001` through `UN050`
- Credit points: Random distribution of 6, 12, 12.5, 15, or 18 points
- Subjects include: Computer Science, Mathematics, Business, Engineering, Arts, etc.

#### Programs (5 total)
1. **Bachelor of Computer Science (BCS)**
2. **Bachelor of Business Administration (BBA)**
3. **Bachelor of Engineering (BE)**
4. **Bachelor of Arts (BA)**
5. **Bachelor of Science (BS)**

#### Specializations (3 per program, 15 total)
- **BCS**: Software Development, Data Science, Cybersecurity
- **BBA**: Marketing, Finance, Human Resources
- **BE**: Mechanical Engineering, Electrical Engineering, Civil Engineering
- **BA**: Psychology, Communications, Philosophy
- **BS**: Mathematics, Physics, Biology

#### Curriculum Versions (5 per specialization, 75 total)
- Version codes follow pattern: `{SPECIALIZATION_CODE}-V{VERSION}-2025`
- Each version contains exactly 15 curriculum units
- Examples: `BCS-SD-V1-2025`, `BBA-MK-V3-2025`

#### Curriculum Units (15 per version, 1,125 total)
- **Distribution per semester**: 3 units (2 core/major + 1 elective)
- **Semester organization**: 5 semesters used (out of 9 available)
- **Unit type distribution**:
  - Core: ~30% (336 units)
  - Elective: ~33% (375 units)  
  - Major: ~37% (414 units)

#### Semesters (9 total over 3 years)
- **Year 1**: 2025S1, 2025S2, 2025S3
- **Year 2**: 2026S1, 2026S2, 2026S3
- **Year 3**: 2027S1, 2027S2, 2027S3
- **Schedule**: February, June, October start dates

#### Curriculum Unit Types (3 total)
- **Core**: Foundation subjects required for all students
- **Elective**: Optional subjects for student choice
- **Major**: Specialization-specific subjects

## Usage

### Run the Complete Seeder
```bash
php artisan db:seed
```

### Run Only This Seeder
```bash
php artisan db:seed --class=ComprehensiveEducationSeeder
```

### Verify Data
```bash
php artisan tinker --execute="
echo 'Units: ' . App\Models\Unit::count() . PHP_EOL;
echo 'Programs: ' . App\Models\Program::count() . PHP_EOL;
echo 'Specializations: ' . App\Models\Specialization::count() . PHP_EOL;
echo 'Curriculum Versions: ' . App\Models\CurriculumVersion::count() . PHP_EOL;
echo 'Curriculum Units: ' . App\Models\CurriculumUnit::count() . PHP_EOL;
"
```

## Database Relations

The seeder maintains proper foreign key relationships:

```
Programs (5)
├── Specializations (3 each) = 15 total
    ├── CurriculumVersions (5 each) = 75 total
        ├── CurriculumUnits (15 each) = 1,125 total
            ├── Unit (from 50 available)
            ├── Semester (from 9 available)
            └── CurriculumUnitType (core/elective/major)
```

## Key Features

1. **Realistic Data**: Uses diverse academic subjects and proper naming conventions
2. **Balanced Distribution**: Ensures even spread of unit types across curriculum
3. **Proper Relationships**: Maintains referential integrity between all tables
4. **Flexible Structure**: Supports the academic calendar with semester-based organization
5. **Scalable Design**: Easy to modify quantities or add new program areas

## Notes

- The seeder clears existing education data before generating new data
- Foreign key constraints are temporarily disabled during truncation
- Each curriculum version uses exactly 15 units as specified
- Units are distributed across first 5 semesters (3 units per semester)
- Unit types follow the pattern: 2 core/major + 1 elective per semester 
